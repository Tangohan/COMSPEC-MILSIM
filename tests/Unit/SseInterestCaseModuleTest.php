<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Notifications\ActivityHubPresentationService;
use App\Services\Sse\SseInterestCasePdfService;
use App\Support\Migrations\SchemaInspector;
use App\Support\SseInterestCaseCatalog as Catalog;
use App\Support\SseInterestMarkup;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;
use Tests\Support\SseInterestFixture;

/**
 * Dossiers d'intérêt : règles pures (catalogue, mini-balisage), étape de migration, câblage des routes, du CSRF et des vues,
 * règles d'interface (pas d'eyebrow, textes de 13 px, cibles de 44 px), fiche PDF, notification.
 */
final class SseInterestCaseModuleTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private static function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    // ------------------------------------------------------------------ Mini-balisage

    public function testMarkupNeverLetsHtmlThrough(): void
    {
        $html = SseInterestMarkup::toHtml("<img src=x onerror=alert(1)> **gras** et *italique*\n\n- un\n- <b>deux</b>\n\n<script>x</script>");
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<b>', $html);
        self::assertStringContainsString('&lt;img', $html);
        self::assertStringContainsString('<strong>gras</strong>', $html);
        self::assertStringContainsString('<em>italique</em>', $html);
        self::assertStringContainsString('<ul><li>un</li><li>&lt;b&gt;deux&lt;/b&gt;</li></ul>', $html);
        self::assertSame('', SseInterestMarkup::toHtml('   '));
        self::assertStringNotContainsString('<a ', SseInterestMarkup::toHtml('[x](javascript:alert(1)) http://x.y'), 'aucun lien');
    }

    public function testMarkupCleanStripsControlCharactersAndCaps(): void
    {
        self::assertSame("a\n\nb", SseInterestMarkup::clean("a\r\n\r\n\r\n\r\nb\x00\x07", 100));
        self::assertSame(5, mb_strlen(SseInterestMarkup::clean('éééééééé', 5)));
        self::assertSame('gras · un', SseInterestMarkup::plain("**gras**\n- un") === "gras\n· un" ? 'gras · un' : 'x');
    }

    // ------------------------------------------------------------------ Catalogue

    public function testTagsAreNormalized(): void
    {
        self::assertSame(['Convoi', 'nord', 'véhicule 2'], Catalog::normalizeTags('Convoi, nord;;CONVOI,  véhicule   2 , '));
        self::assertCount(8, Catalog::normalizeTags('a,b,c,d,e,f,g,h,i,j'));
        self::assertSame(24, mb_strlen(Catalog::normalizeTags(str_repeat('x', 60))[0]));
        self::assertSame('a,b', Catalog::joinTags(['a', 'b']));
        self::assertNull(Catalog::joinTags([]));
        self::assertSame(['a', 'b'], Catalog::splitTags('a,b'));
    }

    public function testCommunityTypeCatalogOverridesDefaultsAndIgnoresGarbage(): void
    {
        self::assertSame(Catalog::DEFAULT_TYPES, Catalog::types(''));
        self::assertSame(Catalog::DEFAULT_TYPES, Catalog::types('pas du json'));
        self::assertSame(['milice' => 'Milice locale'], Catalog::types('{"milice":"Milice locale","Mauvaise Clé":"x","ok":"<b></b>"}'));
    }

    public function testPrioritiesMapTheExistingInterestLevelsWithoutRenamingKeys(): void
    {
        self::assertSame(['critique', 'prioritaire', 'a_surveiller', 'courant'], array_keys(Catalog::PRIORITIES));
        $legacy = array_keys(\App\Repositories\SseInterestCaseRepository::INTEREST);
        $mine = array_keys(Catalog::PRIORITIES);
        sort($legacy);
        sort($mine);
        self::assertSame($legacy, $mine);
        self::assertSame(['P1', 'P2', 'P3', 'P4'], array_column(Catalog::PRIORITIES, 'code'));
        self::assertTrue(isset(\App\Repositories\SseInterestCaseRepository::STATUSES['en_attente']));
        foreach (['brouillon', 'signalement_recu', 'identite_consolidee', 'archive'] as $legacy) {
            self::assertTrue(isset(\App\Repositories\SseInterestCaseRepository::STATUSES[$legacy]), $legacy);
        }
        foreach (['interne', 'encadrement', 'confidentiel', 'tres_restreint'] as $level) {
            self::assertTrue(isset(\App\Services\Sse\SseRedactionService::LEVELS[$level]), 'niveaux de classification existants réutilisés : ' . $level);
        }
    }

    // ------------------------------------------------------------------ Étape de migration

    public function testStepIsRegisteredIdempotentAndReversible(): void
    {
        $registry = self::read('database/steps/registry.php');
        self::assertStringContainsString("'file' => '4710_sse_interest_case_suite.php'", $registry);
        self::assertSame(1, substr_count($registry, "'file' => '4710_"), 'numéro libre');
        self::assertFileExists(self::ROOT . 'migrations/rollback/sse_interest_case_suite_down.sql');
        self::assertFileExists(self::ROOT . 'migrations/2026_11_18_100_sse_interest_case_suite.sql');
        self::assertStringNotContainsString('ADD UNIQUE KEY', self::read('database/steps/4710_sse_interest_case_suite.php'));

        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE tenants (id INT UNSIGNED NOT NULL, PRIMARY KEY (id))');
        $pdo->exec('CREATE TABLE sse_interest_cases (id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id INT UNSIGNED NOT NULL, status VARCHAR(40) NOT NULL, PRIMARY KEY (id))');
        $root = rtrim(self::ROOT, '/');
        $run = static function () use ($pdo, $root): string {
            ob_start();
            require $root . '/database/steps/4710_sse_interest_case_suite.php';

            return (string) ob_get_clean();
        };
        self::assertStringContainsString('Ajouté', $run());
        $changes = $pdo->changes;
        self::assertStringContainsString('déjà présentes', $run());
        self::assertSame($changes, $pdo->changes, 'une étape relancée ne change rien');
        $schema = new SchemaInspector($pdo);
        foreach (['version', 'classification', 'case_type', 'assignee_user_id', 'due_at', 'tags', 'archive_from_status', 'deleted_at', 'deleted_by', 'delete_reason'] as $column) {
            self::assertTrue($schema->columnExists('sse_interest_cases', $column), $column);
        }
        foreach (['sse_interest_case_history', 'sse_interest_case_links', 'sse_interest_case_watchers'] as $table) {
            self::assertTrue($schema->tableExists($table), $table);
        }
    }

    public function testDegradedModeIsAnnouncedOnScreen(): void
    {
        self::assertStringContainsString('Mise à niveau de la base en attente', self::read('views/atak/sse/interest_cases.php'));
        self::assertStringContainsString('Mise à niveau de la base en attente', self::read('views/atak/sse/interest_case_show.php'));
        self::assertStringContainsString('function capabilities', self::read('app/Repositories/SseInterestCaseRepository.php'));
    }

    // ------------------------------------------------------------------ Câblage des routes, CSRF, tenant

    public function testEveryRouteReachesAnExistingControllerMethodAndLiteralRoutesComeFirst(): void
    {
        $routes = self::read('routes/web.php');
        preg_match_all("#\\\$router->(get|post)\\('(/atak/sse/interet[^']*)', \\[\\\\App\\\\Controllers\\\\Web\\\\SseInterestCaseController::class, '(\w+)'\\]#", $routes, $m, PREG_SET_ORDER);
        self::assertGreaterThanOrEqual(22, count($m));
        $paths = array_map(static fn (array $r): string => $r[1] . ' ' . $r[2], $m);
        foreach ($m as [, $verb, $path, $method]) {
            self::assertTrue(method_exists(\App\Controllers\Web\SseInterestCaseController::class, $method), $method);
        }
        $posGet = array_search('get /atak/sse/interet/{id}', $paths, true);
        foreach (['get /atak/sse/interet/nouveau', 'get /atak/sse/interet/liens/recherche'] as $literal) {
            self::assertTrue(array_search($literal, $paths, true) < $posGet, $literal . ' avant {id}');
        }
        self::assertStringNotContainsString("SsePortalController::class, 'interest", $routes);
        foreach (['modifier', 'supprimer', 'restaurer', 'supprimer-definitivement', 'desarchiver', 'liens', 'abonnement', 'etat'] as $action) {
            self::assertContains('post /atak/sse/interet/{id}/' . $action, $paths, $action);
        }
    }

    public function testEveryWriteActionChecksCsrfAndNoActionReadsTheTenantFromTheRequest(): void
    {
        $src = self::read('app/Controllers/Web/SseInterestCaseController.php');
        $routes = self::read('routes/web.php');
        preg_match_all("#post\\('/atak/sse/interet[^']*', \\[[^,]+SseInterestCaseController::class, '(\w+)'\\]#", $routes, $m);
        self::assertGreaterThanOrEqual(15, count($m[1]));
        foreach ($m[1] as $method) {
            if (!preg_match('/public function ' . $method . '\(Request \$request[^{]*\{(.*?)\n    \}\n/s', $src, $body)) {
                self::fail('méthode introuvable : ' . $method);
            }
            $code = $body[1];
            self::assertTrue(str_contains($code, '$this->csrf($request)') || str_contains($code, '$this->simple('), $method . ' : jeton CSRF');
        }
        foreach (["input('tenant", "query('tenant", "all()['tenant", '$_GET', '$_POST'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $src, 'la communauté vient de la session : ' . $forbidden);
        }
        self::assertStringContainsString('FileRateLimiter', $src, 'débit sur les exports et la recherche');
        self::assertStringContainsString('recordExport', $src);
        self::assertStringContainsString('SseInterestActor::fromSession', $src);
    }

    public function testListSearchJnetAndWorkspaceUseTheClearanceAwareListing(): void
    {
        foreach (['app/Services/Jnet/JnetDashboardService.php', 'app/Services/Sse/SseWorkspaceService.php', 'app/Services/Sse/SseIntelFoundationService.php', 'app/Services/Sse/SseIntelligenceWorkspaceService.php'] as $file) {
            self::assertStringNotContainsString('interest->listForTenant', str_replace('interestCases->listForTenant', 'interest->listForTenant', self::read($file)), $file);
            self::assertStringContainsString('listForSession', self::read($file), $file);
        }
        self::assertStringContainsString('fromSession', self::read('app/Repositories/SseInterestCaseRepository.php'));
    }

    // ------------------------------------------------------------------ Vues et interface

    public function testViewsAreWiredToTheSharedAssetsWithVersionedDeferredScripts(): void
    {
        foreach (['views/atak/sse/interest_cases.php', 'views/atak/sse/interest_case_show.php'] as $view) {
            $src = self::read($view);
            self::assertStringContainsString("asset_url('assets/css/sse-dossiers-interet.css')", $src);
            self::assertStringContainsString('<script defer src="', $src);
            self::assertStringContainsString("asset_url('assets/js/sse-dossiers-interet.js')", $src);
            self::assertStringContainsString('Csrf::field()', $src . self::read('views/atak/sse/interest/_dialog_create.php'));
        }
        foreach (['_dialog_create', '_dialog_edit', '_dialog_status', '_dialog_delete', '_dialog_links'] as $partial) {
            $src = self::read('views/atak/sse/interest/' . $partial . '.php');
            self::assertStringContainsString('<dialog', $src, $partial);
            self::assertStringContainsString('\App\Core\Csrf::field()', $src, $partial);
            self::assertStringContainsString('data-di-close', $src, $partial);
        }
        $show = self::read('views/atak/sse/interest_case_show.php');
        foreach (['synthese', 'analyse', 'liens', 'historique', 'pieces', 'discussion', 'acces'] as $tab) {
            self::assertStringContainsString("id=\"di-panel-$tab\"", $show, $tab);
        }
        self::assertStringContainsString('role="tablist"', $show);
        self::assertStringContainsString('name="version"', self::read('views/atak/sse/interest/_dialog_edit.php'));
        self::assertStringContainsString('data-di-preview', self::read('views/atak/sse/interest_cases.php'));
        self::assertStringContainsString('di-empty', self::read('views/atak/sse/interest_cases.php'));
    }

    public function testNoEyebrowLabelsNoSimulationWordingAndEverySectionHasAOneLineDescription(): void
    {
        $files = ['views/atak/sse/interest_cases.php', 'views/atak/sse/interest_case_show.php', 'views/atak/sse/interest/_dialog_create.php', 'views/atak/sse/interest/_dialog_edit.php', 'views/atak/sse/interest/_dialog_status.php', 'views/atak/sse/interest/_dialog_delete.php', 'views/atak/sse/interest/_dialog_links.php', 'views/atak/sse/interest/_fields.php', 'public/assets/js/sse-dossiers-interet.js', 'public/assets/css/sse-dossiers-interet.css'];
        foreach ($files as $file) {
            $src = self::read($file);
            self::assertDoesNotMatchRegularExpression('/kicker|eyebrow|panel-index|overline/i', $src, $file . ' : aucun libellé au-dessus des titres');
            self::assertDoesNotMatchRegularExpression('/simulation|simulé/i', $src, $file);
        }
        $show = self::read('views/atak/sse/interest_case_show.php');
        preg_match_all('#<div class="di-panel__head">(.*?)</div>#s', $show, $heads);
        self::assertGreaterThanOrEqual(10, count($heads[1]));
        foreach ($heads[1] as $head) {
            self::assertMatchesRegularExpression('#<h2>[^<]+</h2>\s*<p[^>]*>[^<]+#', $head, 'titre sans ligne de description : ' . strip_tags($head));
        }
    }

    public function testStylesheetKeepsTextAt13PxAndTargetsAt44PxWithIsolatedTokens(): void
    {
        $css = self::read('public/assets/css/sse-dossiers-interet.css');
        self::assertStringContainsString('--di-bg: #0a0d0f', $css);
        self::assertStringContainsString('--di-accent: #12d18e', $css);
        self::assertStringContainsString('--di-radius: 6px', $css);
        self::assertStringContainsString('--di-target: 44px', $css);
        preg_match_all('/font-size:\s*([\d.]+)(px|rem)/', $css, $sizes, PREG_SET_ORDER);
        foreach ($sizes as [$all, $value, $unit]) {
            $px = $unit === 'rem' ? (float) $value * 16 : (float) $value;
            self::assertGreaterThanOrEqual(13.0, $px, $all);
        }
        self::assertStringNotContainsString('font-size: 12px', $css);
        self::assertStringContainsString('@media (max-width: 760px)', $css, 'tableau en cartes sur téléphone');
    }

    public function testScriptHasNoVisibleFrenchTextAndEscapesWhatItInserts(): void
    {
        $js = self::read('public/assets/js/sse-dossiers-interet.js');
        self::assertDoesNotMatchRegularExpression('/[àâçéèêëîïôûù]/iu', preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $js) ?? $js, 'libellés dans le bloc caché, pas dans le script');
        self::assertStringContainsString('textContent', $js);
        self::assertSame(1, substr_count($js, 'innerHTML'), 'un seul innerHTML : le HTML déjà assaini par le serveur');
        self::assertStringContainsString('showModal', $js);
    }

    public function testCerbereIsNamedInTheInterfaceOnly(): void
    {
        self::assertStringContainsString('Cerbère', self::read('views/atak/sse/interest_cases.php'));
        self::assertStringContainsString('Cerbère', self::read('views/atak/sse/interest_case_show.php'));
        self::assertStringContainsString("SseCrossMatchService", self::read('app/Controllers/Web/SseInterestCaseController.php'));
    }

    // ------------------------------------------------------------------ Notifications et PDF

    public function testNotificationCenterPresentsInterestCaseNotices(): void
    {
        $row = (new ActivityHubPresentationService())->formatNotificationRow([
            'type' => 'sse_interest_case', 'created_at' => '2026-11-15 12:00:00',
            'payload_json' => json_encode(['ref' => 'DI-2026-000001', 'path' => 'atak/sse/interet/12', 'detail' => 'Dossier modifié : titre.']),
        ]);
        self::assertStringContainsString('DI-2026-000001', $row['title']);
        self::assertStringContainsString('atak/sse/interet/12', $row['href']);
        $evil = (new ActivityHubPresentationService())->formatNotificationRow(['type' => 'sse_interest_case', 'payload_json' => json_encode(['path' => 'https://evil.example/x'])]);
        self::assertStringNotContainsString('evil', $evil['href']);
    }

    public function testPdfSheetCarriesOnlyWhatTheReaderMaySee(): void
    {
        $f = new SseInterestFixture();
        try {
            $id = $f->open(10, ['classification' => 'confidentiel']);
            $f->service->addLink($f->actor(10), $id, 'person', '5', '', '');
            $detail = $f->service->detail($f->actor(10), $id);
            $detail['links'][] = ['type_label' => 'Identité', 'label' => 'SECRET MASQUÉ', 'hint' => '', 'masked' => true, 'missing' => false];
            $html = (new SseInterestCasePdfService())->html($detail);
            self::assertStringContainsString('DUPONT Jean', $html);
            self::assertStringNotContainsString('SECRET MASQUÉ', $html);
            self::assertStringContainsString('Homme au manteau gris', $html);
            // L'export lui-même exige le droit d'exporter et un lecteur habilité.
            self::assertFalse($f->actor(11)->canExport);
            $this->expectException(\App\Services\Sse\SseInterestCaseException::class);
            $f->service->detail($f->actor(12), $id);
        } finally {
            $f->close();
        }
    }
}
