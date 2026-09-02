<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Audit\AuditAction;
use PHPUnit\Framework\TestCase;

final class PlatformSiteAdminAssetTest extends TestCase
{
    public function testSidebarAndHubExposeTheFullSiteAdminMap(): void
    {
        $root = dirname(__DIR__, 2);
        $sidebar = (string) file_get_contents($root . '/views/partials/platform_admin_sidebar.php');
        $directory = (string) file_get_contents($root . '/views/admin/partials/platform_site_directory.php');
        $quick = (string) file_get_contents($root . '/views/admin/partials/quick_actions_system.php');
        $nav = (string) file_get_contents($root . '/config/navigation.php');

        foreach ([
            'admin/system/demo-nda',
            'admin/system/subscription-plans',
            'admin/system/recruitment-portal-tools',
            'admin/system/cooperation/catalog',
            'admin/system/cooperation/announcements',
            'admin/system/military-referential',
            'admin/system/tenant-recovery',
            'admin/system/updates',
            'admin/newsletter',
        ] as $path) {
            self::assertStringContainsString($path, $sidebar, $path);
            self::assertStringContainsString($path, $directory, $path);
            self::assertStringContainsString($path, $nav, $path);
        }

        self::assertStringContainsString('Accès démo du site', $sidebar);
        self::assertStringContainsString('Formules d’accès', $sidebar);
        self::assertStringContainsString('Types de coopération', $sidebar);
        self::assertStringContainsString('Référentiel militaire', $sidebar);
        self::assertStringContainsString('Récupération communauté', $sidebar);
        self::assertStringContainsString('Administration complète du site', $directory);
        self::assertStringContainsString('Quatre postes, tout le site', $directory);
        self::assertStringContainsString('admin/system/demo-nda', $quick);
        self::assertStringContainsString('admin/system/updates', $quick);
        self::assertStringContainsString('admin/system/advanced-fiche-edit', $quick);
        self::assertStringNotContainsString('endpoint', $directory);
        self::assertStringNotContainsString('JSON', $directory);
        self::assertFileExists($root . '/public/assets/css/platform-admin.css');
        $dash = (string) file_get_contents($root . '/views/admin/system/dashboard.php');
        $dashCtrl = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemDashboardController.php');
        self::assertStringContainsString('class="pa"', $dash);
        self::assertStringContainsString('platform-admin.css', $dashCtrl);
        self::assertStringNotContainsString('quick_actions_system.php', $dash);
    }

    public function testEveryPlatformAccountRouteUsesThePlatformShell(): void
    {
        $helpers = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/helpers.php');

        self::assertMatchesRegularExpression(
            "/\\$prefixes\\s*=\\s*\\[[^;]*'users'[^;]*\\];/s",
            $helpers,
            'Les pages /admin/users et leurs sous-routes doivent conserver la navigation plateforme.'
        );
    }

    public function testCommunityFicheCoversIdentityTypeAndPlan(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemTenantsController.php');
        $view = (string) file_get_contents($root . '/views/admin/system/tenants_plan_form.php');
        $index = (string) file_get_contents($root . '/views/admin/system/tenants_index.php');
        $container = (string) file_get_contents($root . '/app/Core/Container.php');

        self::assertStringContainsString("post('/admin/tenants/{id}/identity'", $routes);
        self::assertStringContainsString("post('/admin/tenants/{id}/profil'", $routes);
        self::assertStringContainsString('updateIdentity', $controller);
        self::assertStringContainsString('updateType', $controller);
        self::assertStringContainsString('TenantTypeSwitchService', $controller);
        self::assertStringContainsString('TenantTypeSwitchService::class', $container);
        self::assertStringContainsString('platform-admin.css', $controller);
        self::assertStringContainsString('class="pa"', $view);
        self::assertStringContainsString('Fiche communauté', $view);
        self::assertStringContainsString('Nom affiché', $view);
        self::assertStringContainsString('Adresse courte de la page publique', $view);
        self::assertStringContainsString('Profil d’outils', $view);
        self::assertStringContainsString('confirm_type_change', $view);
        self::assertStringContainsString('Formule d’accès', $view);
        self::assertStringContainsString('Administrer', $index);
        self::assertStringNotContainsString('Changer la formule', $index);
        self::assertSame('platform.tenant_identity_updated', AuditAction::TENANT_IDENTITY_UPDATED);
        self::assertSame('platform.tenant_type_assigned', AuditAction::TENANT_TYPE_ASSIGNED);
    }
}
