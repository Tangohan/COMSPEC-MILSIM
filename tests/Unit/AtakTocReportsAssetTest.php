<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Panneau Rapports du poste TOC web : câblage de la console (onglet, tiroir, épingles, canal de synchro), règles d'interface
 * (aucune étiquette au-dessus d'un titre, aucune mention « simulation », texte de 13 px au moins, cibles de 44 px), textes
 * traduits, étape de migration complète (registre, SQL, retour arrière).
 */
final class AtakTocReportsAssetTest extends TestCase
{
    private static function read(string $rel): string
    {
        $p = dirname(__DIR__, 2) . '/' . $rel;
        self::assertFileExists($p);

        return (string) file_get_contents($p);
    }

    public function testConsoleLoadsTheReportsModuleBeforeTheConsoleScript(): void
    {
        $view = self::read('views/atak/toc/console.php');
        self::assertStringContainsString('assets/css/atak-toc-reports.css', $view);
        $module = strpos($view, 'assets/js/toc/toc-reports.js');
        self::assertNotFalse($module);
        self::assertLessThan((int) strpos($view, 'assets/js/toc/toc-console.js'), $module, 'le module est chargé avant la console (defer garde l’ordre)');
    }

    public function testConsoleWiresTabDrawerPinsAndSyncChannel(): void
    {
        $js = self::read('public/assets/js/toc/toc-console.js');
        self::assertStringContainsString("['rapports', 'Rapports']", $js);
        self::assertStringContainsString('rapports: function () { return S.reports ? S.reports.panel()', $js);
        self::assertStringContainsString("id === 'rapports'", $js, 'pastille de non lus sur l’onglet');
        self::assertStringContainsString('S.reports.onStamp(j.reports.stamp)', $js, 'canal de synchro de la salle');
        self::assertStringContainsString('it.data.type === \'report\'', $js, 'une épingle de rapport ouvre le tiroir');
        self::assertStringContainsString('reportsFeed:', $js);
        self::assertStringContainsString('p.rapport', $js, 'lien direct #rapport=tr-12');
        self::assertStringContainsString('S.reports.open(id)', $js, 'le flux ouvre aussi la fiche');
        $layers = self::read('public/assets/js/toc/toc-layers.js');
        self::assertStringContainsString('opts.reportsFeed', $layers);
        self::assertStringContainsString("data: { type: 'report', id: r.id, key: r.key }", $layers);
        self::assertStringContainsString('Rapports : BDA, comptes rendus, SITREP, 9-line', $layers, 'filtre de couche « Rapports »');
        // L'éditeur de plans garde l'ancienne lecture (pas de flux de rapports du poste).
        self::assertStringContainsString("/api/atak/reports?mapId=", $layers);
    }

    public function testModuleSpeaksToTheApiWithCsrfOnlyThroughTheConsoleHelper(): void
    {
        $js = self::read('public/assets/js/toc/toc-reports.js');
        foreach (['/atak/toc/api/reports', '/read', '/ack', '/attach', '/read-all', '/atak/toc/reports/'] as $path) {
            self::assertStringContainsString($path, $js, $path);
        }
        self::assertStringNotContainsString('fetch(', $js, 'tout passe par ctx.api (jeton CSRF sur les écritures)');
        self::assertStringNotContainsString('localStorage.setItem(\'athena:toc:reports\'', $js);
    }

    public function testEveryFieldOfTheGameIsEscapedBeforeItReachesInnerHtml(): void
    {
        $js = self::read('public/assets/js/toc/toc-reports.js');
        // Un champ du jeu (texte libre) n'entre jamais brut dans le HTML : esc() ou rich().
        foreach (['title', 'summary', 'who', 'unit', 'grid', 'type_label', 'status_label', 'priority_label', 'location', 'ref'] as $field) {
            self::assertDoesNotMatchRegularExpression('/\+\s*(?:it|r|x)\.' . $field . '\s*\+/', $js, 'champ ' . $field . ' concaténé sans échappement');
        }
        self::assertStringContainsString('function rich(s) { return esc(s)', $js);
        self::assertStringContainsString('rp-redact', $js, 'le caviardage devient un rectangle, pas le texte masqué');
    }

    public function testStyleSheetKeepsTextReadableAndTargetsBigEnough(): void
    {
        $css = self::read('public/assets/css/atak-toc-reports.css');
        preg_match_all('/font(?:-size)?\s*:[^;{}]*?(\d+(?:\.\d+)?)px/', $css, $m);
        foreach ($m[1] as $px) {
            self::assertGreaterThanOrEqual(13.0, (float) $px, 'texte sous 13 px');
        }
        self::assertGreaterThanOrEqual(8, substr_count($css, 'min-height: 44px'), 'cibles tactiles de 44 px');
        self::assertStringContainsString('@media (max-width: 640px)', $css);
        self::assertStringNotContainsString('var(--toc-radius', $css);
    }

    public function testNoEyebrowLabelNoSimulationWordingEveryTitleHasADescription(): void
    {
        $js = self::read('public/assets/js/toc/toc-reports.js');
        $css = self::read('public/assets/css/atak-toc-reports.css');
        self::assertDoesNotMatchRegularExpression('/eyebrow|kicker|overline/i', $js . $css);
        self::assertDoesNotMatchRegularExpression('/simulation|simulé|simulated/i', $js . $css . self::read('app/Services/AtakToc/TocReportCatalog.php'));
        // Le titre du panneau est suivi de sa ligne de description.
        self::assertMatchesRegularExpression('/<h2>Rapports<\/h2>.*?<p class="rp-desc">Les BDA, comptes rendus/s', $js);
    }

    public function testEnglishDictionaryCoversThePanelAndTheCatalogLabels(): void
    {
        $dict = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        self::assertIsArray($dict);
        foreach ([
            'Les BDA, comptes rendus, SITREP et 9-line reçus du terrain, à lire, accuser et rattacher au plan.', 'Exporter en PDF', 'Centrer la carte', 'Joindre au plan',
            'Munition / moyen', 'Dommages collatéraux', 'Matériel détruit', 'Nouvelle frappe', '9-LINE CAS (appui aérien)', 'MEDEVAC / CASEVAC (9 lignes)', 'Zone de poser',
            'Prioritaire', 'Reçu', 'Accusé', 'Sur zone', 'Rapports : BDA, comptes rendus, SITREP, 9-line',
        ] as $fr) {
            self::assertArrayHasKey($fr, $dict, $fr);
            self::assertNotSame('', $dict[$fr]);
        }
        $bundle = self::read('public/assets/js/i18n_en_dict.js');
        self::assertStringContainsString('Attach to plan', $bundle, 'bundle navigateur régénéré (site_i18n.py js-bundle)');
    }

    public function testMigrationStepIsRegisteredWithItsSqlAndRollback(): void
    {
        $registry = self::read('database/steps/registry.php');
        self::assertMatchesRegularExpression("/'id' => 'atak_toc_report_reads', 'label' => '[^']+(?:’[^']*)*', 'group' => 'schema', 'file' => '4740_atak_toc_report_reads\\.php', 'rerun' => true\\]/u", $registry);
        $step = self::read('database/steps/4740_atak_toc_report_reads.php');
        self::assertStringContainsString('migrations/2026_11_16_200_atak_toc_report_reads.sql', $step);
        self::assertStringContainsString('tableExists', $step);
        $sql = self::read('migrations/2026_11_16_200_atak_toc_report_reads.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS atak_toc_report_reads', $sql);
        self::assertStringContainsString('PRIMARY KEY (tenant_id, user_id, report_kind, report_id)', $sql);
        self::assertStringNotContainsString('ADD UNIQUE KEY', $sql, 'compatible sqlite');
        self::assertStringNotContainsString('ALTER TABLE', $sql);
        self::assertStringContainsString('DROP TABLE IF EXISTS atak_toc_report_reads', self::read('migrations/rollback/atak_toc_report_reads_down.sql'));
        // Le numéro de l'étape n'est pris que par elle.
        self::assertSame(1, preg_match_all("/'file' => '4740_/", $registry));
    }

    public function testStepCreatesTheTableOnSqliteAndIsIdempotent(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        // Même schéma que la migration MySQL, en sqlite : la lecture marque lu, relit, efface, cloisonne par communauté.
        $pdo = \Tests\Support\TocReportsSqlite::open(false);
        \Tests\Support\TocReportsSqlite::createReads($pdo);
        $svc = new \App\Services\AtakToc\TocReportService($pdo, static fn (int $t, array $rows): array => $rows);
        \Tests\Support\TocReportsSqlite::tactical($pdo, 1, 3, 'TIC', 'ALPHA 1-1', 'ALPHA 1', 'Contact (TIC) · 0100 0100', ['nature' => 'OBSERVÉ']);
        self::assertTrue($svc->readsReady());
        self::assertTrue($svc->markRead(1, 7, 'interne', 'tr-1', true));
        self::assertSame(0, $svc->list(1, 7, 'interne')['counts']['unread']);
        self::assertSame(1, $svc->list(1, 8, 'interne')['counts']['unread']);
    }
}
