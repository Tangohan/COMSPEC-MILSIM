<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseCaseRepository;
use App\Repositories\SseMeshRepository;
use App\Support\SseCreationBrief;
use App\Support\SseWizardUi;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseAttachSqlite;

/**
 * Choix des assistants de création SSE : clés autorisées, niveau plafonné à l'habilitation, synthèse composée sans
 * donnée libre dans l'en-tête, cartes accessibles, et enregistrement d'un classeur / d'un dossier dans sa communauté.
 */
final class SseCreationBriefTest extends TestCase
{
    public function testPickKeepsOnlyKnownKeys(): void
    {
        self::assertSame('chrono', SseCreationBrief::pick(' CHRONO ', SseCreationBrief::MESH_TYPES, ''));
        self::assertSame('', SseCreationBrief::pick('<script>', SseCreationBrief::MESH_TYPES, ''));
        self::assertSame('routine', SseCreationBrief::pick('urgent!!', SseCreationBrief::CASE_URGENCY, 'routine'));
    }

    public function testEveryChoiceHasALabelAndAnExplanation(): void
    {
        foreach ([SseCreationBrief::MESH_TYPES, SseCreationBrief::MESH_STARTS, SseCreationBrief::CASE_TYPES, SseCreationBrief::CASE_THEMES,
            SseCreationBrief::CASE_URGENCY, SseCreationBrief::OBJECT_KINDS, SseCreationBrief::OBJECT_STATES] as $group) {
            foreach ($group as $key => $o) {
                self::assertNotSame('', trim((string) $o['label']), (string) $key);
                self::assertNotSame('', trim((string) $o['desc']), (string) $key);
            }
        }
        foreach (array_keys(SseMeshRepository::KIND_LABELS) as $kind) {
            self::assertArrayHasKey($kind, SseCreationBrief::OBJECT_KINDS);
        }
        foreach (array_keys(SseCaseRepository::CLASSIFICATION_LABELS) as $level) {
            self::assertArrayHasKey($level, SseCreationBrief::CLASSIFICATION_HELP);
        }
    }

    public function testClassificationNeverExceedsTheSessionCeiling(): void
    {
        self::assertSame('encadrement', SseCreationBrief::capClassification('tres_restreint', 'confidentiel', 'encadrement'));
        self::assertSame('confidentiel', SseCreationBrief::capClassification('confidentiel', 'interne', 'tres_restreint'));
        self::assertSame('encadrement', SseCreationBrief::capClassification('n_importe_quoi', 'encadrement', 'tres_restreint'));
        self::assertSame('interne', SseCreationBrief::capClassification('', 'confidentiel', 'interne'));
    }

    public function testSummaryHeadHoldsMarksThenFreeText(): void
    {
        self::assertSame("Thème : Site · Urgence : Routine\n\nTexte", SseCreationBrief::composeSummary(['Thème' => 'Site', 'Urgence' => 'Routine'], '  Texte '));
        self::assertSame('Texte', SseCreationBrief::composeSummary(['Thème' => ''], 'Texte'));
        self::assertSame('Type de toile : Toile libre', SseCreationBrief::composeSummary(['Type de toile' => 'Toile libre'], ''));
        self::assertSame('', SseCreationBrief::composeSummary([], ''));
    }

    public function testCardsAreARadioGroupWithErrorMessageAndDisabledChoices(): void
    {
        $html = SseWizardUi::cards('start', 'Point de départ', [
            'vide' => ['label' => 'Vide', 'desc' => 'Rien'],
            'dossier' => ['label' => 'Dossier', 'desc' => 'Reprise', 'disabled' => true, 'note' => 'Aucun'],
        ], 'dossier', ['required' => true, 'when' => 'x=y']);
        self::assertStringContainsString('<fieldset class="wz-group" data-wz-field="start" data-wz-rules="required" data-wz-when="x=y">', $html);
        self::assertSame(2, substr_count($html, 'type="radio"'));
        self::assertStringContainsString('disabled', $html);
        self::assertStringNotContainsString('value="dossier" checked', $html, 'un choix grisé ne se coche pas');
        self::assertStringContainsString('data-wz-err="required" hidden', $html);
        self::assertStringContainsString('<legend', $html);
    }

    public function testProgressIsAnAccessibleBarWithOneButtonPerStep(): void
    {
        $html = SseWizardUi::progress(['A', 'B', 'C']);
        self::assertStringContainsString('role="progressbar"', $html);
        self::assertStringContainsString('aria-valuemax="3"', $html);
        self::assertSame(3, substr_count($html, 'data-wz-goto='));
        self::assertStringContainsString('aria-live="polite"', $html);
    }

    public function testBinderAndCaseAreStoredInTheirOwnCommunityOnly(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseAttachSqlite::open();
        try {
            $repo = new SseCaseRepository();
            $binder = $repo->create(['tenant_id' => 1, 'title' => 'Classeur Nord', 'classification' => 'encadrement', 'is_folder' => true, 'parent_id' => 0]);
            $summary = SseCreationBrief::composeSummary(['Thème' => 'Site ou installation', 'Urgence' => 'Prioritaire'], 'Contexte');
            $case = $repo->create(['tenant_id' => 1, 'title' => 'Site Alpha', 'summary' => $summary, 'classification' => 'confidentiel', 'parent_id' => $binder]);
            $other = $repo->create(['tenant_id' => 2, 'title' => 'Autre communauté', 'classification' => 'encadrement']);

            $row = $pdo->query('SELECT is_folder, parent_id, summary FROM sse_cases WHERE id = ' . $case)->fetch(PDO::FETCH_ASSOC);
            self::assertSame(0, (int) $row['is_folder']);
            self::assertSame($binder, (int) $row['parent_id']);
            self::assertStringStartsWith('Thème : Site ou installation · Urgence : Prioritaire', (string) $row['summary']);
            self::assertSame(1, (int) $pdo->query('SELECT is_folder FROM sse_cases WHERE id = ' . $binder)->fetchColumn());

            $mine = array_map(static fn (array $c): int => (int) $c['id'], $repo->listForTenant(1, null));
            self::assertContains($case, $mine);
            self::assertNotContains($other, $mine);
        } finally {
            SseAttachSqlite::close();
        }
    }
}
