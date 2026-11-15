<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\Links\SseLinksWidget;
use App\Support\SseCerbere;
use App\Support\SseLinkCatalog;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySseLinkViewer;
use Tests\Support\SseLinksFixture;
use Tests\Support\SseLinksSqlite;

/**
 * Widget « Liaisons » : une ligne dans chaque page d'élément, tiroir et modale accessibles, styles isolés dans des variables
 * --sl-*, aucun texte français dans les scripts, textes lisibles (12 px au moins), aucune étiquette au-dessus d'un titre,
 * migration, étape numérotée, retour arrière, traductions anglaises, documentation.
 */
final class SseLinksWidgetWiringTest extends TestCase
{
    protected function setUp(): void
    {
        // Le jeton CSRF des formulaires du widget : la session démarre avant toute sortie.
        $_SESSION = ['user_id' => 7, 'tenant_id' => 1];
        \App\Core\Csrf::token();
    }

    private static function read(string $relative): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
    }

    /** @return array<string, string> page => type de l'élément */
    private static function pages(): array
    {
        return [
            'views/atak/sse/person_show.php' => 'person',
            'views/atak/sse/papers/identity_show.php' => 'identity',
            'views/atak/sse/papers/document_show.php' => 'paper',
            'views/atak/sse/papers/check_show.php' => 'check',
            'views/atak/sse/transmission_show.php' => 'transmission',
            'views/atak/sse/case_show.php' => 'case',
            'views/atak/sse/interest_case_show.php' => 'interest',
            'views/atak/sse/document_show.php' => 'document',
            'views/atak/sse/field_note_show.php' => 'note',
            'views/atak/sse/site_show.php' => 'site',
            'views/atak/sse/campaign_show.php' => 'campaign',
        ];
    }

    public function testEveryElementPageIncludesTheWidgetInOneLine(): void
    {
        foreach (self::pages() as $file => $type) {
            $src = self::read($file);
            self::assertSame(1, substr_count($src, 'SseLinksWidget::render('), $file . ' : une seule inclusion');
            self::assertMatchesRegularExpression("#^\\s*<\\?php \\\\App\\\\Services\\\\Sse\\\\Links\\\\SseLinksWidget::render\\('" . $type . "', [^\\n]+\\); \\?>\r?\$#m", $src, $file . ' : une ligne, type ' . $type);
            self::assertArrayHasKey($type, SseLinkCatalog::TYPES);
            self::assertNotSame('jnet', $type);
        }
        self::assertGreaterThanOrEqual(11, count(self::pages()));
    }

    public function testThePagesOfTheOtherRefontesAreNotTouchedBeyondTheOneLine(): void
    {
        // Les pages d'éléments sans fiche propre (avis, SEEK II, véhicules, téléphones) n'ont pas de page à équiper : leurs éléments
        // se lisent comme maillons du chemin de la personne ou de l'identité.
        foreach (['notice', 'seek', 'photo', 'attribute', 'doc_issue', 'mention'] as $type) {
            self::assertArrayHasKey($type, SseLinkCatalog::TYPES);
            self::assertNotContains($type, self::pages());
        }
    }

    public function testThePartialIsAccessibleAndOffersEveryAction(): void
    {
        $v = self::read('views/atak/sse/_links_widget.php');
        foreach (['<dialog', 'aria-labelledby', 'role="tablist"', 'role="tab"', 'aria-selected', 'aria-controls', 'role="tabpanel"', 'aria-label="Fermer le panneau"', 'aria-label="Fermer la fenêtre"', 'aria-haspopup="dialog"', 'role="listbox"', 'role="note"'] as $needle) {
            self::assertStringContainsString($needle, $v, $needle);
        }
        foreach (['Chemin complet', 'Liens directs', 'Copier le chemin', 'Copier le lien', 'Insérer dans un compte rendu', 'Ajouter un lien', 'Retirer ce lien', 'Motif du lien (obligatoire)', 'badge', 'Cerbère', 'Traitement automatique du système', 'Manuel', 'Déduit', 'Vous êtes ici', 'Élément restreint'] as $text) {
            self::assertStringContainsString($text, $v, $text);
        }
        self::assertStringContainsString('name="_csrf_token"', $v);
        self::assertStringContainsString('required minlength="5"', $v, 'le motif est obligatoire dans le formulaire');
        self::assertStringContainsString('data-confirm=', $v);
        self::assertStringNotContainsString('<img', $v);
        self::assertStringNotContainsString('<script>', $v);
        self::assertMatchesRegularExpression('#<script defer src=#', $v);
        self::assertStringContainsString("asset_url('assets/css/sse-links.css')", $v);
        self::assertStringContainsString("asset_url('assets/js/sse-links.js')", $v);
        // Mode dégradé : message clair et ajout désactivé avec la raison.
        self::assertStringContainsString('Mode dégradé', $v);
        self::assertStringContainsString('La table des liens enregistrés n’est pas encore créée.', $v);
    }

    public function testNoEyebrowAndEachSectionHasItsDescriptionLine(): void
    {
        $v = self::read('views/atak/sse/_links_widget.php') . self::read('views/atak/sse/cerberes.php');
        self::assertDoesNotMatchRegularExpression('/class="[^"]*(eyebrow|kicker|overline|supertitle)/i', $v);
        $css = self::read('public/assets/css/sse-links.css');
        self::assertDoesNotMatchRegularExpression('/eyebrow|kicker|overline|supertitle/i', $css);
        $w = self::read('views/atak/sse/_links_widget.php');
        // Titre du tiroir, de la fenêtre et de chaque panneau : une ligne de description juste dessous.
        self::assertMatchesRegularExpression('#</h2>\s*<p class="sl-desc">#', $w);
        self::assertSame(2, preg_match_all('#</h2>\s*<p class="sl-desc">#', $w));
        foreach (['data-sl-panel="path"', 'data-sl-panel="direct"'] as $panel) {
            self::assertMatchesRegularExpression('#' . preg_quote($panel, '#') . '[^>]*>\s*<p class="sl-desc">#', $w);
        }
        $cb = self::read('views/atak/sse/cerberes.php');
        self::assertSame(4, preg_match_all('#class="panel-title" id="cb-[a-z]+">[^<]+</div></div>\s*<div class="panel-body">\s*<p class="muted">#', $cb));
    }

    public function testStylesAreIsolatedAndRemappableThroughSlVariables(): void
    {
        $css = self::read('public/assets/css/sse-links.css');
        preg_match('/^\.sl,\s*\n\.sl-drawer,\s*\n\.sl-modal \{(.*?)\n\}/ms', $css, $m);
        self::assertNotEmpty($m, 'un seul bloc de variables en tête');
        foreach (['--sl-surface', '--sl-line', '--sl-ink', '--sl-text', '--sl-dim', '--sl-accent', '--sl-warn', '--sl-danger', '--sl-radius', '--sl-fs', '--sl-fs-small', '--sl-on-accent'] as $var) {
            self::assertStringContainsString($var . ':', $m[1], $var);
        }
        self::assertStringContainsString('var(--ui-surface-2', $m[1], 'les jetons existants du portail SSE sont repris');
        self::assertStringContainsString('var(--ui-accent', $m[1]);
        $rest = str_replace($m[0], '', $css);
        self::assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $rest, 'aucune couleur en dur hors du bloc de variables');
        self::assertDoesNotMatchRegularExpression('/rgba?\(/', preg_replace('/var\(--sl-backdrop, rgba\([^)]*\)\)/', '', $rest) ?? '');
        // Chaque sélecteur reste dans le widget : aucune règle globale.
        preg_match_all('/^([^\s@\/}{][^{]*)\{/m', $rest, $sel);
        foreach ($sel[1] as $selector) {
            foreach (explode(',', $selector) as $one) {
                $one = trim($one);
                if ($one === '' || str_starts_with($one, 'from') || str_starts_with($one, 'to') || str_starts_with($one, '.sl') || str_starts_with($one, 'html.sl-noscroll')) {
                    continue;
                }
                self::fail('sélecteur hors du widget : ' . $one);
            }
        }
    }

    public function testTextIsNeverSmallerThan12px(): void
    {
        $css = self::read('public/assets/css/sse-links.css');
        preg_match_all('/font-size:\s*([0-9.]+)(px|rem|em)/', $css, $m, PREG_SET_ORDER);
        self::assertNotEmpty($m);
        foreach ($m as [$all, $n, $unit]) {
            $px = $unit === 'px' ? (float) $n : (float) $n * 16;
            self::assertGreaterThanOrEqual(12.0, $px, $all);
        }
        preg_match_all('/--sl-fs[a-z-]*:\s*([0-9.]+)(px|rem)/', $css, $v, PREG_SET_ORDER);
        foreach ($v as [$all, $n, $unit]) {
            self::assertGreaterThanOrEqual(12.0, $unit === 'px' ? (float) $n : (float) $n * 16, $all);
        }
        self::assertGreaterThanOrEqual(12.0, 0.8125 * 16);
    }

    public function testTheDrawerIsFullScreenOnPhonesAndNeverWiderThanTheViewport(): void
    {
        $css = self::read('public/assets/css/sse-links.css');
        self::assertMatchesRegularExpression('/@media \(max-width: 640px\)\s*\{[^@]*\.sl-drawer \{ width: 100%;/s', $css);
        self::assertStringContainsString('max-height: 100dvh', $css);
        self::assertStringNotContainsString('100vw', preg_replace('/max-width: calc\(100vw - 32px\)/', '', $css) ?? '', 'le pourcentage, pas la largeur de fenêtre (barre de défilement comprise)');
        self::assertStringContainsString('prefers-reduced-motion', $css);
        self::assertStringContainsString(':focus-visible', $css);
    }

    public function testTheScriptHasNoFrenchAndNoInnerHtml(): void
    {
        $js = self::read('public/assets/js/sse-links.js');
        $code = (string) preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $js);
        self::assertDoesNotMatchRegularExpression('/[éèàùçêôîï]/u', $code, 'les messages viennent de la page (éléments [data-sl-msg]), traduits côté serveur');
        self::assertStringNotContainsString('innerHTML', $js);
        self::assertStringNotContainsString('eval(', $js);
        foreach (['showModal', 'ArrowRight', "'Escape'", 'aria-selected', 'clipboard', 'data-sl-msg', 'preventScroll'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
    }

    public function testTheMigrationTheNumberedStepAndTheRollbackAreInPlace(): void
    {
        $sql = self::read('migrations/2026_11_16_110_sse_entity_links.sql');
        foreach (['source_type', 'source_id', 'target_type', 'target_id', 'relation', 'reason', 'rule_code', 'created_by', 'created_at', 'tenant_id'] as $col) {
            self::assertStringContainsString($col . ' ', $sql, $col);
        }
        self::assertStringContainsString('KEY idx_sse_entity_link_src (tenant_id, source_type, source_id)', $sql);
        self::assertStringContainsString('KEY idx_sse_entity_link_dst (tenant_id, target_type, target_id)', $sql);
        self::assertStringNotContainsString('INSERT', $sql, 'aucune donnée ajoutée');
        self::assertStringContainsString('DROP TABLE IF EXISTS sse_entity_links;', self::read('migrations/rollback/sse_entity_links_down.sql'));
        $registry = self::read('database/steps/registry.php');
        self::assertMatchesRegularExpression("/\\['id' => 'sse_entity_links', 'label' => '[^']+', 'group' => 'schema', 'file' => '4720_sse_entity_links\\.php', 'rerun' => true\\],/", $registry);
        self::assertFileExists(dirname(__DIR__, 2) . '/database/steps/4720_sse_entity_links.php');
        // Un seul fichier d'étape pour cette table (d'autres étapes peuvent porter le même numéro sur main : seul le nom compte).
        $files = array_map('basename', glob(dirname(__DIR__, 2) . '/database/steps/*_sse_entity_links.php') ?: []);
        self::assertSame(['4720_sse_entity_links.php'], $files);
        $step = self::read('database/steps/4720_sse_entity_links.php');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS', $sql);
        self::assertStringContainsString('runSqlFile', $step);
        self::assertStringNotContainsString('INSERT', $step);
    }

    public function testEveryEnglishEntryIsThereForTheCatalogsAndTheMessages(): void
    {
        $en = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        $texts = [];
        foreach (SseLinkCatalog::TYPES as $t) {
            $texts[] = $t['label'];
        }
        foreach (SseLinkCatalog::RELATIONS as $pair) {
            $texts[] = $pair[0];
            $texts[] = $pair[1];
        }
        foreach (SseCerbere::TREATMENTS as $t) {
            $texts[] = $t['label'];
            $texts[] = $t['does'];
        }
        foreach (SseCerbere::RULES as $r) {
            $texts[] = $r['label'];
            $texts[] = $r['reason'];
        }
        foreach (\App\Services\Sse\Links\SseLinkEdges::DEFS as $def) {
            $texts[] = $def['note'];
        }
        foreach (['Cerbère', 'Manuel', 'Déduit', 'Élément restreint', 'Éléments SSE', 'Élément SSE', 'Citer un élément SSE', 'Cité dans ce contenu JNET.', 'La file des propositions n’est pas encore créée (étape de base de données à passer).', 'Les avis de recherche ne sont pas encore activés (étape de base de données à passer).'] as $t) {
            $texts[] = $t;
        }
        foreach ([\App\Services\Sse\Links\SseLinkWriter::DENY_GUEST, \App\Services\Sse\Links\SseLinkWriter::DENY_RIGHT, \App\Services\Sse\Links\SseLinkWriter::NOT_FOUND, \App\Services\Sse\Links\SseLinkWriter::NOT_READY] as $m) {
            $texts[] = $m;
        }
        foreach (array_unique($texts) as $t) {
            self::assertArrayHasKey($t, $en, 'traduction anglaise manquante : ' . $t);
            self::assertNotSame('', trim((string) $en[$t]));
        }
        foreach (['Cerbère-Liaison', 'Cerbère-Validation', 'Cerbère-Corrélation', 'Cerbère-Détection', 'Cerbère-Création'] as $name) {
            self::assertArrayHasKey($name, $en);
            self::assertStringStartsWith('Cerbère-', (string) $en[$name], 'le nom propre se garde en anglais');
        }
    }

    public function testTheDocumentationDescribesThePathTheCerberesAndTheBackfill(): void
    {
        $doc = self::read('docs/sse-liaisons.md');
        foreach (['sse_entity_links', 'SseLinkGraph', 'Cerbère-Liaison', 'cerbere.link.check_biokey', 'php tools/sse-backfill-links.php', '--apply', 'Élément restreint', 'jnet_links', '[[sse:person-12|IDN-00012]]', 'mode dégradé'] as $needle) {
            self::assertStringContainsString($needle, $doc, $needle);
        }
        foreach (array_keys(SseCerbere::RULES) as $rule) {
            self::assertStringContainsString($rule, $doc, 'règle documentée : ' . $rule);
        }
    }

    public function testTheWidgetRendersOnRealisticDataForEachKindOfReader(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseLinksSqlite::open();
        $_SESSION = ['user_id' => 7, 'tenant_id' => 1];
        \App\Core\Csrf::token();
        try {
            $ids = SseLinksFixture::seed($pdo);
            $bridge = SseLinksFixture::jnetBridge('case:' . $ids['case']);
            $_SERVER['REQUEST_URI'] = '/atak/sse/identites/' . $ids['person'];
            $html = SseLinksWidget::render('person', $ids['person'], ['viewer' => ArraySseLinkViewer::full(), 'graph' => new \App\Services\Sse\Links\SseLinkGraph(ArraySseLinkViewer::full(), null, null, null, $bridge), 'return' => true]);
            self::assertStringContainsString('Liaisons (', (string) $html);
            self::assertStringContainsString('IDT-000001', (string) $html);
            self::assertStringContainsString('Cerbère-Liaison (règle cerbere.link.case_single_open)', (string) $html);
            self::assertStringContainsString('Fiche classée au seul dossier ouvert.', (string) $html);
            self::assertStringContainsString('data-sl-add-open', (string) $html, 'un analyste qui écrit voit « Ajouter un lien »');
            self::assertStringContainsString('[[sse:person-' . $ids['person'] . '|IDN-00001]]', (string) $html, 'la citation JNET ne porte que la référence');
            self::assertStringNotContainsString('Élodie|', (string) $html);
            // Un lecteur en lecture seule : mêmes liens, pas de bouton d'écriture, pas de retrait.
            $ro = SseLinksWidget::render('person', $ids['person'], ['viewer' => ArraySseLinkViewer::full()->with(['write' => []]), 'return' => true]);
            self::assertStringNotContainsString('data-sl-add-open', (string) $ro);
            self::assertStringNotContainsString('Retirer ce lien', (string) $ro);
            self::assertStringContainsString('Droit manquant', (string) $ro);
            // Un lecteur sans droit sur l'élément : pas de widget du tout (la page est déjà refusée).
            self::assertNull(SseLinksWidget::render('person', $ids['person'], ['viewer' => ArraySseLinkViewer::full()->with(['read' => ['papiers']]), 'return' => true]));
            // Aucun élément restreint ne laisse de nom dans le HTML.
            $limited = (string) SseLinksWidget::render('person', $ids['person'], ['viewer' => ArraySseLinkViewer::full()->with(['read' => ['fichiers', 'papiers', 'photos'], 'write' => [], 'truth' => false]), 'return' => true]);
            foreach (['Réseau des passeurs', 'SSE-2026-0001', 'Observation du pont', 'Faux-Nom', 'Dupuis'] as $leak) {
                self::assertStringNotContainsString($leak, $limited, $leak);
            }
            self::assertStringContainsString('Élément restreint', $limited);
        } finally {
            SseLinksSqlite::close();
        }
    }

    public function testTheDegradedModeSaysSoAndDisablesWrites(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseLinksSqlite::open(false);
        $_SESSION = ['user_id' => 7, 'tenant_id' => 1];
        \App\Core\Csrf::token();
        try {
            $ids = SseLinksFixture::seed($pdo, false);
            $_SERVER['REQUEST_URI'] = '/atak/sse/identites/' . $ids['person'];
            $html = (string) SseLinksWidget::render('person', $ids['person'], ['viewer' => ArraySseLinkViewer::full(), 'return' => true]);
            self::assertStringContainsString('Mode dégradé', $html);
            self::assertStringNotContainsString('data-sl-add-open', $html);
            self::assertStringContainsString('Déduit', $html);
        } finally {
            SseLinksSqlite::close();
        }
    }
}
