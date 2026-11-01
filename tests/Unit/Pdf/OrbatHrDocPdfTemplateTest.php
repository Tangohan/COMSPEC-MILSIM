<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Services\Organization\OrbatChartPdfService;
use App\Services\Training\TrainingFormationCustomPageExportPdfService;
use App\Support\PersonnelHrPdfService;
use App\Support\TrainingCertificatePdfEngine;
use PHPUnit\Framework\TestCase;

/**
 * Gabarit Athena (A4 portrait, en-tête / pied) appliqué à l'ORBAT, aux pièces RH du dossier
 * et à l'export PDF de la documentation de formation.
 */
final class OrbatHrDocPdfTemplateTest extends TestCase
{
    use AthenaPdfAssertions;

    /**
     * Le flux « file » de BypassFinals (tests/bootstrap.php) altère la lecture des polices TTF par
     * Dompdf (glyphes É, ’, — absents des sous-ensembles) : rendu Dompdf avec le flux natif.
     *
     * @param callable(): ?string $render
     */
    private static function withNativeFileStream(callable $render): ?string
    {
        $h = fopen(__FILE__, 'rb');
        $wrapped = $h !== false && (stream_get_meta_data($h)['wrapper_type'] ?? '') === 'user-space';
        if ($h !== false) {
            fclose($h);
        }
        if (!$wrapped || !class_exists(\DG\BypassFinals::class)) {
            return $render();
        }
        // Lecture complète des polices (cmap) : plus gourmande que la lecture tronquée par le flux de test.
        $limit = (string) ini_get('memory_limit');
        if ($limit !== '-1') {
            ini_set('memory_limit', '512M');
        }
        stream_wrapper_restore('file');
        try {
            return $render();
        } finally {
            \DG\BypassFinals::enable(bypassReadOnly: false);
        }
    }

    /** @param list<array<string, mixed>> $children */
    private static function unit(string $label, string $role, string $type, int $id, string $leader, int $members, array $children = []): array
    {
        return [
            'label' => $label,
            'role' => $role,
            'type' => $type,
            'unitId' => $id,
            'leader' => $leader,
            'mission' => '',
            'strength' => $members + 2,
            'visibleStrength' => $members,
            'members' => array_fill(0, $members, ['user_id' => $id, 'label' => 'X']),
            'children' => $children,
        ];
    }

    /** Arbre de 4 niveaux sous la racine, 16 unités. */
    private static function roster(): array
    {
        $u = [self::class, 'unit'];

        return [
            'label' => 'Command',
            'role' => 'TF',
            'type' => 'command',
            'unitId' => 0,
            'leader' => 'COL Durand',
            'mission' => 'Sécuriser le secteur nord et appuyer les opérations alliées.',
            'members' => [],
            'children' => [
                $u('ÉTAT-MAJOR', 'HQ', 'hq', 1, 'LCL Moreau', 4, [
                    $u('Cellule renseignement', 'S2', 'isr', 2, 'CNE Petit', 2),
                    $u('Cellule logistique', 'S4', 'log', 3, 'LTN Roux', 0),
                ]),
                $u('CIE ALPHA', 'A', 'alpha', 10, 'CPT Martin', 3, [
                    $u('Section Alpha 1', 'A1', 'alpha', 11, 'LTN Bernard', 1, [
                        $u('Groupe Alpha 1-1', 'A11', 'alpha', 12, 'SGT Leroy', 4, [
                            $u('Équipe feu A11-a', 'A11A', 'alpha', 13, 'CPL Faure', 3),
                            $u('Équipe feu A11-b <Bravo & Co>', 'A11B', 'alpha', 14, '—', 2),
                        ]),
                        $u('Groupe Alpha 1-2', 'A12', 'alpha', 15, 'SGT Garnier', 4),
                    ]),
                    $u('Section Alpha 2', 'A2', 'alpha', 16, 'LTN Girard', 6),
                    $u('Section appui feu', 'MRT', 'mortier', 17, 'ADJ Blanc', 5),
                ]),
                $u('APPUIS', 'SUP', 'support', 20, 'MAJ Chevalier', 2, [
                    $u('Détachement génie', 'GEN', 'genie', 21, 'SGT Lopez', 3),
                    $u('Détachement aviation', 'AV', 'aviation', 22, 'CNE Mercier', 2),
                    $u('Poste de secours', 'MED', 'soutien', 23, 'MED Fontaine', 0),
                ]),
                $u('RÉSERVE', 'RES', 'alpha', 30, '', 0),
            ],
        ];
    }

    public function testOrbatPdfIsAthenaA4PortraitWithAllUnits(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() && !class_exists(\Dompdf\Dompdf::class)) {
            self::markTestSkipped('Aucun moteur PDF.');
        }
        $service = new OrbatChartPdfService();
        $doc = $service->buildDocument(self::roster(), [
            'unit_label' => 'Task Force Phoenix',
            'theater' => 'Altis',
            'reference' => 'ORBAT-20261101',
            'version' => '2.1',
            'classification' => 'Diffusion restreinte · usage interne',
            'include_mission' => true,
            'include_notes' => true,
            'include_legend' => true,
            'country' => 'FR',
        ]);
        self::assertSame(16, $doc['totals']['unit_count']);

        $html = $service->tcpdfFriendlyHtml($doc);
        foreach (['ÉTAT-MAJOR', 'Équipe feu A11-a', 'Groupe Alpha 1-2', 'Poste de secours', 'RÉSERVE'] as $label) {
            self::assertStringContainsString($label, $html);
        }
        self::assertStringContainsString('Équipe feu A11-b &lt;Bravo &amp; Co&gt;', $html);
        self::assertStringNotContainsString('<Bravo', $html);

        $pdf = TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(static fn (): ?string => $service->buildPdf($doc));
        self::assertIsString($pdf);
        if (TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
            self::assertAthenaTcpdf($pdf, 'orbat');
        } else {
            self::assertAthenaDompdf($pdf, 'orbat');
        }
    }

    public function testHrPieceIsAthenaDompdf(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            self::markTestSkipped('Dompdf absent.');
        }
        $pdf = self::withNativeFileStream(static fn (): ?string => (new PersonnelHrPdfService())->buildPdf(
            12,
            ['id' => 34, 'first_name' => 'Jean', 'last_name' => 'Dupont', 'callsign' => 'Falcon', 'grade_id' => 0],
            'affectation',
            [
                'community' => 'Unité Alpha',
                'detail' => 'Affecté(e) à la section Alpha 2 à compter du 1er novembre.',
                'grade_label' => 'SGT — Sergent',
                'authority' => 'CNE Mercier, officier RH',
                'place' => 'Camp Maxwell',
            ]
        ));
        self::assertIsString($pdf);
        self::assertAthenaDompdf($pdf, 'rh-piece-dossier');
    }

    public function testFormationDocumentationIsAthenaA4Portrait(): void
    {
        if (!TrainingCertificatePdfEngine::isAvailable()) {
            self::markTestSkipped('Aucun moteur PDF.');
        }
        $chapters = [];
        foreach (['Sécurité au tir', 'Procédures radio', 'Évacuation sanitaire'] as $i => $title) {
            $chapters[] = [
                'title' => $title,
                'html' => '<p>Paragraphe ' . ($i + 1) . ' : consignes à respecter en toutes circonstances, '
                    . str_repeat('règle détaillée et commentée, ', 25) . '</p>'
                    . '<ul><li>Point de contrôle A</li><li>Point de contrôle B</li></ul>'
                    . '<table><tr><th>Étape</th><th>Action</th></tr><tr><td>1</td><td>Vérifier l’arme</td></tr><tr><td>2</td><td>Rendre compte</td></tr></table>',
            ];
        }
        $service = new TrainingFormationCustomPageExportPdfService();
        $pdf = self::withNativeFileStream(static fn (): ?string => $service->generateBinary([
            'id' => 7,
            'slug' => 'manuel-tir',
            'title' => 'Manuel du tireur',
            'subtitle' => 'Fondamentaux et sécurité',
            'summary' => 'Document de référence remis à chaque stagiaire du module tir.',
            'intro_html' => '<p>Ce manuel rassemble les règles essentielles du module.</p>',
            'sections_json' => json_encode($chapters),
            'community' => 'Unité Alpha',
        ]));
        self::assertIsString($pdf, (string) $service->getLastFailureReason());
        if (class_exists(\Dompdf\Dompdf::class)) {
            self::assertAthenaDompdf($pdf, 'formation-documentation');
        } else {
            self::assertAthenaTcpdf($pdf, 'formation-documentation');
        }
    }
}
