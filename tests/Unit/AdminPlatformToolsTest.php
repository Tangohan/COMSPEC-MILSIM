<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\System\SystemAdminSearchController;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\SystemAdminMiddleware;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Admin\PlatformHealthDigest;
use App\Services\Audit\AuditActionLabel;
use PHPUnit\Framework\TestCase;

/**
 * Outils de l'administration du site : droits, tableau de bord (état du site), recherche globale, journal.
 */
final class AdminPlatformToolsTest extends TestCase
{
    /** Routes ajoutées ou modifiées par la refonte des outils d'administration. */
    private const TOUCHED_ROUTES = [
        'get /admin',
        'get /admin/recherche',
        'get /admin/analytics',
        'get /admin/audit',
        'get /admin/maintenance',
        'get /admin/system/alerts',
        'post /admin/system/cron/run',
        'post /admin/system/cron/install-vps',
        'post /admin/system/cron/uninstall-vps',
        'get /admin/system/updates',
        'post /admin/system/updates/upload',
        'post /admin/system/updates/{id}/deploy',
        'post /admin/system/updates/{id}/rollback',
        'post /admin/system/integrations/discord',
        'post /admin/system/integrations/roles-lies',
        'post /admin/system/integrations/emails',
        'post /admin/system/demo-nda/regenerate-code',
        'post /admin/system/demo-nda/add-bypass',
        'post /admin/system/demo-nda/remove-bypass',
        'post /admin/system/demo-nda/add-my-ip',
        'post /admin/system/demo-nda/reset-visit',
        'post /admin/system/contributeurs/role',
        'post /admin/system/contributeurs/langue',
    ];

    protected function tearDown(): void
    {
        Gate::getInstance()->setPermissions([]);
        $_SESSION = [];
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testEveryTouchedRouteIsReservedToSiteAdministrators(): void
    {
        $routes = (string) file_get_contents(self::root() . '/routes/web.php');
        foreach (self::TOUCHED_ROUTES as $route) {
            [$method, $path] = explode(' ', $route, 2);
            self::assertMatchesRegularExpression(
                '#router->' . $method . "\\('" . preg_quote($path, '#') . "', \\[[^\\]]+\\], \\[AuthMiddleware::class, SystemAdminMiddleware::class\\]\\);#",
                $routes,
                $route . ' doit passer par SystemAdminMiddleware'
            );
        }
    }

    public function testCommunityManagerAndSupportNeverReachAnAdminHandler(): void
    {
        $_SESSION = ['user_id' => 42];
        $request = new Request();
        // Un slug réservé porté par un rôle de communauté ne vaut rien sans le drapeau d’administrateur du site.
        foreach ([[], ['admin.organization', 'admin.access'], ['site.support'], ['admin.system', '*']] as $perms) {
            Gate::getInstance()->setPermissions($perms);
            $reached = false;
            $response = (new SystemAdminMiddleware())($request, function () use (&$reached): Response {
                $reached = true;

                return new Response();
            });
            self::assertFalse($reached, 'Droits ' . implode(',', $perms) . ' : le contrôleur ne doit pas être exécuté');
            self::assertSame(302, $response->statusCode());
            self::assertStringNotContainsString('/admin', (string) $response->headerValue('Location'));
        }
    }

    public function testSiteAdministratorReachesTheHandler(): void
    {
        $_SESSION = ['user_id' => 1];
        Gate::getInstance()->setPermissions([]);
        Gate::getInstance()->setPlatformAdmin(true);
        $reached = false;
        (new SystemAdminMiddleware())(new Request(), function () use (&$reached): Response {
            $reached = true;

            return new Response();
        });
        self::assertTrue($reached);
    }

    public function testAnonymousVisitorIsSentToLogin(): void
    {
        $_SESSION = [];
        $response = (new SystemAdminMiddleware())(new Request(), fn (): Response => new Response());
        self::assertSame(302, $response->statusCode());
        self::assertStringContainsString('login', (string) $response->headerValue('Location'));
    }

    public function testSearchFindsAccountsAndCommunitiesAndAddsTheAccountCarryingTheNumber(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('searchAccountsForPlatformOperator')->with('42', 25)->willReturn([
            ['id' => 7, 'email' => 'bravo@example.test', 'display_name' => 'Bravo', 'callsign' => 'B-42', 'steam_id' => '', 'tenant_name' => 'Unité A', 'status' => 'active'],
        ]);
        $users->expects(self::once())->method('findById')->with(42)->willReturn(['id' => 42, 'email' => 'x@example.test', 'status' => 'pending_verification']);
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('searchForPlatformAdmin')->with('42', 20)->willReturn([
            ['id' => 42, 'name' => 'Task Force 42', 'slug' => 'tf42', 'community_code' => 'TF-42', 'archived' => false],
        ]);

        $r = (new SystemAdminSearchController($users, $tenants))->results('42');

        self::assertNull($r['error']);
        self::assertSame([42, 7], array_column($r['accounts'], 'id'));
        self::assertSame('E-mail à vérifier', $r['accounts'][0]['state']);
        self::assertSame('Compte actif', $r['accounts'][1]['state']);
        self::assertSame('Task Force 42', $r['communities'][0]['name']);
    }

    public function testSearchRefusesOneLetterAndSurvivesADatabaseFailure(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::never())->method('searchAccountsForPlatformOperator');
        $tenants = $this->createMock(TenantRepository::class);
        self::assertTrue((new SystemAdminSearchController($users, $tenants))->results('a')['too_short']);
        self::assertSame([], (new SystemAdminSearchController($users, $tenants))->results('')['accounts']);

        $broken = $this->createMock(UserRepository::class);
        $broken->method('searchAccountsForPlatformOperator')->willThrowException(new \PDOException('SQLSTATE[HY000] boom'));
        $r = (new SystemAdminSearchController($broken, $tenants))->results('bravo');
        self::assertNotNull($r['error']);
        self::assertStringNotContainsString('SQLSTATE', (string) $r['error']);
    }

    public function testSearchPageRendersResultsWithQuickActions(): void
    {
        Gate::getInstance()->setPlatformAdmin(true); // l’administrateur du site voit les e-mails en clair
        $html = Response::view('admin.system.search', [
            'searchQuery' => 'bravo',
            'searchAccounts' => [['id' => 7, 'email' => 'bravo@example.test', 'display_name' => 'Bravo', 'callsign' => 'B-1', 'steam_id' => '76561198000000000', 'community' => 'Unité A', 'state' => 'Compte actif']],
            'searchCommunities' => [['id' => 3, 'name' => 'Bravo Company', 'slug' => 'bravo', 'community_code' => 'BRAVO', 'archived' => true]],
            'searchError' => null,
            'searchTooShort' => false,
        ])->body();

        foreach (['admin/users/7/edit', 'admin/users/person?email=bravo%40example.test', 'admin/parc-atak?q=76561198000000000', 'admin/audit?user_id=7',
            'admin/tenants/3/edit', 'admin/system/tenants/3/intervention', 'admin/audit?tenant_id=3', 'Archivée', 'Bravo Company'] as $needle) {
            self::assertStringContainsString($needle, $html);
        }
        self::assertStringNotContainsString('pending_verification', $html);
    }

    public function testHealthSummaryAndModVersionSignals(): void
    {
        self::assertSame('ok', PlatformHealthDigest::summarize([['tone' => 'ok'], ['tone' => 'info']])['tone']);
        self::assertSame(['ok' => 1, 'warn' => 1, 'bad' => 1, 'tone' => 'bad'], PlatformHealthDigest::summarize([['tone' => 'ok'], ['tone' => 'warn'], ['tone' => 'bad']]));

        [$value, $tone, $hint] = PlatformHealthDigest::modVersionSignal('3.4.1', ['3.4.1' => 10, '3.3.0' => 2]);
        self::assertSame('warn', $tone);
        self::assertStringContainsString('3.4.1 (10), 3.3.0 (2)', $value);
        self::assertStringContainsString('2 joueur(s)', $hint);

        self::assertSame('ok', PlatformHealthDigest::modVersionSignal('3.4.1', ['3.4.1' => 4, '3.5.0' => 1])[1]);
        self::assertSame('info', PlatformHealthDigest::modVersionSignal('3.4.1', ['' => 3])[1]);
        // Comparaison à la version d'Overwatch connect, de même nature que ce que le mod remonte.
        self::assertMatchesRegularExpression('/^[0-9]+\.[0-9]+\.[0-9]+$/', (string) \App\Support\AtakReleaseInfo::connectVersion(dirname(__DIR__, 2)));
        self::assertSame('bad', PlatformHealthDigest::queueTone('overloaded'));
        self::assertSame('info', PlatformHealthDigest::queueTone('none'));
    }

    public function testHealthCardRendersOneLinkPerSignal(): void
    {
        $html = Response::view('admin.partials.platform_health', [
            'adminHealthRows' => [
                ['key' => 'cron', 'label' => 'Tâches automatiques', 'value' => 'à l’arrêt', 'tone' => 'bad', 'hint' => 'Vérifiez la crontab.', 'href' => 'admin/system/cron'],
                ['key' => 'disk', 'label' => 'Espace disque', 'value' => '81 % occupé', 'tone' => 'warn', 'hint' => '', 'href' => 'admin/system/storage'],
            ],
            'adminHealthSummary' => ['ok' => 0, 'warn' => 1, 'bad' => 1, 'tone' => 'bad'],
        ])->body();

        self::assertStringContainsString('1 point(s) à traiter, 1 à surveiller', $html);
        self::assertStringContainsString('admin/system/cron', $html);
        self::assertStringContainsString('pa-health__row--bad', $html);
        self::assertStringContainsString('action_domain=platform', $html);
    }

    public function testSiteSupportHasNoAccessLeftUnderAdmin(): void
    {
        $routes = (string) file_get_contents(self::root() . '/routes/web.php');
        self::assertStringNotContainsString('PlatformHubMiddleware', $routes);
        self::assertFileDoesNotExist(self::root() . '/app/Middleware/PlatformHubMiddleware.php');

        // L'assistance (même avec site.support dans ses droits) est renvoyée hors de /admin.
        $_SESSION = ['user_id' => 5];
        Gate::getInstance()->setPermissions(['site.support', 'admin.organization']);
        $reached = false;
        $response = (new SystemAdminMiddleware())(new Request(), function () use (&$reached): Response {
            $reached = true;

            return new Response();
        });
        self::assertFalse($reached);
        self::assertSame(302, $response->statusCode());

        $sidebar = (string) file_get_contents(self::root() . '/views/partials/platform_admin_sidebar.php');
        self::assertStringNotContainsString("'access' => 'hub'", $sidebar);
        self::assertStringNotContainsString('ASSISTANCE PLATEFORME', $sidebar);
        self::assertStringNotContainsString('isSupportHub', (string) file_get_contents(self::root() . '/views/admin/system/dashboard.php'));
    }

    public function testDashboardSynthesisAndConsoleAreOnePilotPage(): void
    {
        $routes = (string) file_get_contents(self::root() . '/routes/web.php');
        // Anciennes adresses : renvoi vers la page de pilotage, toujours réservé à l'administrateur du site.
        self::assertMatchesRegularExpression("#router->get\\('/admin/ops-center', fn \\([^)]*\\) => \\\\App\\\\Core\\\\Response::redirect\\(url\\('admin'\\)\\), \\[AuthMiddleware::class, SystemAdminMiddleware::class\\]\\);#", $routes);
        self::assertMatchesRegularExpression("#router->get\\('/admin/system/exploitation', fn \\([^)]*\\) => \\\\App\\\\Core\\\\Response::redirect\\(url\\('admin'\\) \\. '\\?' \\. http_build_query\\(\\['onglet' => [^\\n]*\\[AuthMiddleware::class, SystemAdminMiddleware::class\\]\\);#", $routes);
        self::assertFileDoesNotExist(self::root() . '/app/Controllers/Admin/System/SystemOpsCenterController.php');
        self::assertFileDoesNotExist(self::root() . '/views/admin/system/ops_center.php');

        // Les onglets d'exploitation passent par la console, qui garde sa double vérification.
        $dashController = (string) file_get_contents(self::root() . '/app/Controllers/Admin/System/SystemDashboardController.php');
        self::assertStringContainsString("in_array((string) \$request->query('onglet', ''), SystemOpsConsoleController::TABS, true)", $dashController);
        self::assertStringContainsString('(new SystemOpsConsoleController())->index($request, $params)', $dashController);
        $console = (string) file_get_contents(self::root() . '/app/Controllers/Admin/System/SystemOpsConsoleController.php');
        self::assertStringContainsString("if (!\$this->stepUp()->isVerified((int) \$me['id'])) {", $console);
        self::assertStringContainsString("\$url = url('admin');", $console);
        self::assertStringNotContainsString("Response::redirect(url('admin/system/exploitation'))", $console);

        // Une seule barre d'onglets, sur la vue d'ensemble comme dans la console.
        $dash = (string) file_get_contents(self::root() . '/views/admin/system/dashboard.php');
        $consoleView = (string) file_get_contents(self::root() . '/views/admin/system/ops_console.php');
        foreach ([$dash, $consoleView] as $view) {
            self::assertStringContainsString('views/admin/partials/pilotage_tabs.php', $view);
            self::assertStringContainsString('Pilotage du site', $view);
        }
        self::assertStringContainsString("url('admin/recherche')", $dash);
        self::assertStringContainsString('platform_communication.php', $dash);
        $sidebar = (string) file_get_contents(self::root() . '/views/partials/platform_admin_sidebar.php');
        self::assertStringNotContainsString("'admin/ops-center'", $sidebar);
        self::assertStringNotContainsString("'admin/system/exploitation'", $sidebar);
        self::assertStringContainsString("['path' => 'admin/recherche', 'label' => 'Recherche', 'icon' => 'search', 'access' => 'system'", $sidebar);
    }

    public function testPilotTabsLinkEveryConsoleTabToTheAdminPage(): void
    {
        $html = Response::view('admin.partials.pilotage_tabs', ['pilotTab' => 'migrations', 'opsSummary' => ['bad' => 1, 'warn' => 0]])->body();
        foreach (\App\Controllers\Admin\System\SystemOpsConsoleController::TABS as $tab) {
            self::assertStringContainsString('/admin?onglet=' . $tab, $html);
        }
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringNotContainsString('exploitation', $html);
    }

    public function testNewAuditActionsHaveReadableLabels(): void
    {
        foreach (['platform.cron_run', 'platform.cron_installed', 'platform.update_deployed', 'platform.update_rolled_back',
            'platform.integrations_updated', 'platform.demo_access_updated', 'platform.contributor_updated'] as $slug) {
            $label = AuditActionLabel::toFrench($slug);
            self::assertNotSame($slug, $label);
            self::assertStringNotContainsString('_', $label);
        }
    }

    public function testOrphanAdminScreensAreGone(): void
    {
        foreach (['app/Controllers/Admin/System/SystemCommandCenterController.php', 'views/admin/system/command_center.php', 'views/admin/hub.php', 'views/admin/system/placeholder.php'] as $rel) {
            self::assertFileDoesNotExist(self::root() . '/' . $rel);
        }
        $updates = (string) file_get_contents(self::root() . '/app/Controllers/Admin/System/SystemUpdatesController.php');
        self::assertStringNotContainsString("\$request->query['release']", $updates);
    }
}
