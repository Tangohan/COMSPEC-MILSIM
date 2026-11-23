<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseCaseRepository;
use App\Repositories\SseDocumentRepository;
use App\Services\Sse\SseDocumentPdfService;
use App\Support\SseDocumentTemplates;
use App\Support\SseRichText;
use App\Support\TrainingCertificatePdfEngine;
use PHPUnit\Framework\TestCase;
use Tests\Support\PdfText;

/**
 * Modèles de documents SSE (catalogue PHP versionné) : chaque modèle est du HTML déjà assaini par la liste blanche de
 * SseRichText, porte son en-tête officiel (service, classification, référence, dossier, date, rédacteur), des sections guidées
 * par un texte d'aide grisé qui ne s'imprime jamais, des tableaux, des zones de signature et un pied de page ; le PDF garde
 * cette mise en page avec le bandeau de classification en haut et en bas de chaque page.
 */
final class SseDocumentTemplatesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private static function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . '/' . $path);
    }

    /** @return list<string> */
    private static function codes(): array
    {
        return array_keys(SseDocumentTemplates::CATALOGUE);
    }

    public function testCatalogueIsCompleteAndCoherent(): void
    {
        foreach (['note_renseignement', 'compte_rendu_entretien', 'fiche_recherche', 'rapport_incident', 'fiche_controle_identite', 'sitrep', 'proces_verbal', 'courrier_officiel', 'page_blanche'] as $wanted) {
            self::assertTrue(SseDocumentTemplates::exists($wanted), $wanted);
        }
        $labels = [];
        foreach (SseDocumentTemplates::CATALOGUE as $code => $meta) {
            self::assertMatchesRegularExpression('/^[a-z_]+$/', $code);
            self::assertArrayHasKey($meta['document_type'], SseDocumentRepository::TYPE_LABELS, $code . ' : type de document connu');
            self::assertArrayHasKey($meta['classification'], SseCaseRepository::CLASSIFICATION_LABELS, $code . ' : classification connue');
            self::assertArrayHasKey($meta['group'], SseDocumentTemplates::GROUPS, $code . ' : famille connue');
            self::assertNotSame('', trim($meta['description']), $code);
            self::assertMatchesRegularExpression('/^[MLHVZACQSTmlhvzacqst0-9 .,\-]+$/', $meta['icon'], $code . ' : tracé d’icône simple');
            $labels[$meta['label']] = true;
        }
        self::assertCount(count(self::codes()), $labels, 'les libellés sont uniques');
        self::assertFalse(SseDocumentTemplates::exists('../../etc/passwd'));
    }

    public function testEveryTemplateIsAlreadySanitisedAndStable(): void
    {
        foreach (self::codes() as $code) {
            $html = SseDocumentTemplates::body($code, ['nom' => 'Dupont', 'grade' => 'Lieutenant']);
            self::assertNotSame('', $html, $code);
            self::assertSame($html, SseRichText::sanitize($html), $code . ' : la liste blanche ne change rien au modèle');
            self::assertSame($html, SseRichText::htmlOf(SseRichText::MARK . $html), $code);
            self::assertStringNotContainsString('<script', $html);
            self::assertStringNotContainsString('onclick', $html);
            self::assertStringNotContainsString('<img', $html);
            self::assertStringNotContainsString('href=', $html);
        }
    }

    public function testEveryTemplateHasTheOfficialHeaderAndGuidedSections(): void
    {
        foreach (self::codes() as $code) {
            $html = SseDocumentTemplates::body($code);
            self::assertStringStartsWith('<table data-lock="1">', $html, $code);
            self::assertStringContainsString('ATHENA · Bureau de renseignement (SSE)', $html, $code);
            self::assertStringContainsString('CLASSIFICATION :', $html, $code);
            foreach (['classification', 'reference', 'dossier', 'date', 'nom', 'grade'] as $field) {
                self::assertStringContainsString('<span class="sse-field" data-field="' . $field . '">', $html, $code . ' : champ ' . $field);
            }
            self::assertStringContainsString('<span class="sse-hint">', $html, $code . ' : texte d’aide à remplacer');
            self::assertStringContainsString('Document à usage restreint', $html, $code . ' : pied de page');
            self::assertTrue(SseRichText::hasTemplateHeader(SseRichText::MARK . $html), $code);
        }
        foreach (['note_renseignement', 'compte_rendu_entretien', 'rapport_incident', 'fiche_controle_identite', 'proces_verbal', 'fiche_recherche', 'sitrep'] as $code) {
            $html = SseDocumentTemplates::body($code);
            self::assertGreaterThanOrEqual(2, substr_count($html, '<h3>'), $code . ' : sections');
            self::assertStringContainsString('<th', $html, $code . ' : tableau à en-tête');
            self::assertStringContainsString('Signatures', $html, $code . ' : zones de signature');
        }
    }

    public function testFieldsCarryTheDossierValuesAndUserTextIsEscaped(): void
    {
        $html = SseDocumentTemplates::body('note_renseignement', [
            'nom' => '<b>Dupont</b> & fils', 'grade' => 'Lieutenant', 'dossier' => 'SSE-2026-0042', 'classification' => 'Secret (S)', 'date' => '23/11/2026',
        ]);
        self::assertStringContainsString('data-field="dossier">SSE-2026-0042<', $html);
        self::assertStringContainsString('data-field="classification">Secret (S)<', $html);
        self::assertStringContainsString('data-field="date">23/11/2026<', $html);
        self::assertStringContainsString('data-field="grade">Lieutenant<', $html);
        self::assertStringContainsString('&lt;b&gt;Dupont&lt;/b&gt; &amp; fils', $html);
        self::assertStringNotContainsString('<b>Dupont', $html);
    }

    public function testHintsAreVisibleInPreviewButNeverOnPaperOrInThePdf(): void
    {
        $body = SseRichText::MARK . SseDocumentTemplates::body('compte_rendu_entretien');
        self::assertGreaterThan(5, SseRichText::hintCount($body));
        self::assertStringContainsString('sse-hint', SseRichText::toPreviewHtml($body));
        self::assertStringNotContainsString('sse-hint', SseRichText::toPaperHtml($body));
        self::assertStringNotContainsString('Nom, prénom, qualité', SseRichText::toPaperHtml($body));
        self::assertStringNotContainsString('sse-hint', SseRichText::toPdfHtml($body));
        // Le texte écrit à la place d'une aide reste.
        $filled = str_replace('<span class="sse-hint">Nom, prénom, qualité</span>', 'Jean Martin', $body);
        self::assertStringContainsString('Jean Martin', SseRichText::toPaperHtml($filled));
        self::assertSame(SseRichText::hintCount($body) - 1, SseRichText::hintCount($filled));
    }

    public function testTheHintClassCannotBeUsedToSmuggleAnything(): void
    {
        $evil = SseRichText::sanitize('<div class="sse-hint" onclick="x()">a</div><p class="sse-hint">b</p><span class="sse-hint sse-field" data-field="nom">c</span>'
            . '<span class="sse-hint" style="position:fixed;color:red" onmouseover="y()">d</span><span class="sse-field" data-field="inconnu">e</span>');
        self::assertStringNotContainsString('onclick', $evil);
        self::assertStringNotContainsString('onmouseover', $evil);
        self::assertStringNotContainsString('position', $evil);
        self::assertStringNotContainsString('<div class="sse-hint"', $evil);
        self::assertStringNotContainsString('<p class="sse-hint"', $evil);
        self::assertStringNotContainsString('sse-hint sse-field', $evil);
        self::assertStringContainsString('<span class="sse-hint">d</span>', $evil);
        self::assertSame($evil, SseRichText::sanitize($evil), 'idempotent');
    }

    public function testNewFieldsAreRefreshedWithTheDocumentValues(): void
    {
        $html = SseDocumentTemplates::body('page_blanche');
        $out = SseRichText::refreshFields($html, ['reference' => 'ATH-SSE-0042', 'titre' => 'Mon titre', 'dossier' => 'SSE-2026-0001']);
        self::assertStringContainsString('data-field="reference">ATH-SSE-0042<', $out);
        self::assertStringContainsString('data-field="dossier">SSE-2026-0001<', $out);
        self::assertContains('reference', SseRichText::FIELDS);
        self::assertContains('titre', SseRichText::FIELDS);
    }

    public function testUnknownCodeFallsBackToTheBlankPage(): void
    {
        self::assertSame(SseDocumentTemplates::body(SseDocumentTemplates::BLANK), SseDocumentTemplates::body('nimportequoi'));
    }

    public function testPdfKeepsTheTemplateLayoutWithBandsOnTopAndBottom(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
            self::markTestSkipped('TCPDF requis');
        }
        $doc = [
            'reference_code' => 'ATH-SSE-0007', 'title' => 'Entretien du 23', 'classification' => 'confidentiel', 'classification_label' => 'Secret (S)',
            'document_type_label' => 'Compte rendu d’exploitation', 'status_label' => 'Brouillon', 'case_reference' => 'SSE-2026-0042',
            'author_label' => 'Lieutenant Dupont', 'updated_at' => '2026-11-23 10:00:00',
            'body' => SseRichText::MARK . SseDocumentTemplates::body('compte_rendu_entretien', ['dossier' => 'SSE-2026-0042', 'classification' => 'Secret (S)']),
        ];
        $pdf = (new SseDocumentPdfService())->build($doc, 0);
        self::assertStringStartsWith('%PDF', $pdf);
        $text = PdfText::of($pdf);
        self::assertStringContainsString('ATHENA', $text);
        self::assertStringContainsString('Cadre de l', $text);
        self::assertStringContainsString('Signatures', $text);
        self::assertStringContainsString('ATH-SSE-0007', $text, 'référence du document dans le champ dynamique');
        self::assertStringContainsString('SSE-2026-0042', $text, 'référence du dossier');
        self::assertStringNotContainsString('Nom, prénom, qualité', $text, 'le texte d’aide ne s’imprime pas');
        self::assertGreaterThanOrEqual(2, substr_count(mb_strtoupper($text, 'UTF-8'), 'SECRET (S)'), 'bandeau de classification en haut ET en bas');
    }

    public function testWiringRoutesStepViewsAndAssets(): void
    {
        $routes = self::read('routes/web.php');
        $store = strpos($routes, "'/atak/sse/documents/modeles'");
        $del = strpos($routes, "'/atak/sse/documents/modeles/{id}/supprimer'");
        $byId = strpos($routes, "'/atak/sse/documents/{id}'");
        self::assertNotFalse($store);
        self::assertNotFalse($del);
        self::assertNotFalse($byId);
        self::assertLessThan($byId, $store, 'la route des modèles précède la route {id}');

        $registry = self::read('database/steps/registry.php');
        self::assertSame(1, substr_count($registry, "'file' => '5060_sse_document_templates.php'"));
        self::assertSame(1, substr_count($registry, "'id' => 'sse_document_templates'"));
        self::assertSame(1, preg_match("~'id' => 'sse_document_templates'.*'rerun' => true~", $registry));
        self::assertFalse(stripos('5060_sse_document_templates.php sse_document_templates', 'require'), 'ni le fichier d’étape ni son identifiant');
        self::assertFileExists(self::ROOT . '/database/steps/5060_sse_document_templates.php');
        self::assertFileExists(self::ROOT . '/migrations/rollback/sse_document_templates_down.sql');

        $sql = self::read('migrations/2026_11_25_100_sse_document_templates.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS sse_document_templates', $sql);
        self::assertStringContainsString('DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $sql);
        self::assertStringContainsString('tenant_id INT UNSIGNED NOT NULL', $sql, 'cloisonnement par communauté');
        self::assertStringNotContainsString('COMMENT', $sql);
        foreach (explode("\n", $sql) as $line) {
            if (str_starts_with(trim($line), '--')) {
                self::assertStringNotContainsString(';', $line, 'pas de point-virgule dans un commentaire SQL');
            }
        }

        $gallery = self::read('views/atak/sse/document_templates.php');
        self::assertSame(1, preg_match_all('/<h1\b/', $gallery), 'un seul h1');
        foreach (['kicker', 'eyebrow', 'overline', 'surtitre'] as $bad) {
            self::assertStringNotContainsString($bad, $gallery);
            self::assertStringNotContainsString($bad, self::read('views/atak/sse/document_form.php'));
            self::assertStringNotContainsString($bad, self::read('public/assets/css/sse-doc-templates.css'));
        }
        self::assertStringContainsString('sse-doc-templates.css', $gallery);
        $ctrl = self::read('app/Controllers/Web/SsePortalController.php');
        self::assertStringContainsString("'modele'", $ctrl);
        self::assertStringContainsString('SseDocumentTemplates', $ctrl);
    }
}
