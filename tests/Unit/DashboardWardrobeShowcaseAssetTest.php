<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardWardrobeShowcaseAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testRoutesViewsAndMigrationExist(): void
    {
        $root = $this->root();
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $dash = (string) file_get_contents($root . '/views/partials/dashboard_command_center.php');
        $css = (string) file_get_contents($root . '/public/assets/css/dashboard-impact.css');
        $storage = (string) file_get_contents($root . '/app/Support/EquipmentCoverStorage.php');
        $form = (string) file_get_contents($root . '/views/admin/organization/dashboard_wardrobe_pins_form.php');
        $home = (string) file_get_contents($root . '/app/Controllers/Web/HomeController.php');

        self::assertStringContainsString('/back-office/dashboard-tenues', $routes);
        self::assertStringContainsString('DashboardWardrobePinsAdminController', $routes);
        self::assertFileExists($root . '/views/admin/organization/dashboard_wardrobe_pins.php');
        self::assertFileExists($root . '/views/admin/organization/dashboard_wardrobe_pins_form.php');
        self::assertFileExists($root . '/migrations/20260902000002_dashboard_wardrobe_pins.sql');
        self::assertFileExists($root . '/bootstrap/dashboard_wardrobe_pins_migration.php');
        $sql = (string) file_get_contents($root . '/migrations/20260902000002_dashboard_wardrobe_pins.sql');
        self::assertStringContainsString('tenant_dashboard_wardrobe_pins', $sql);
        $run = (string) file_get_contents($root . '/run-migrations.php');
        self::assertStringContainsString('dashboard_wardrobe_pins_migration', $run);
        self::assertStringContainsString('Nos tenues', $dash);
        self::assertStringContainsString('dash-showcase__card--kit', $dash);
        self::assertStringContainsString('dash-showcase__card-figure', $css);
        self::assertStringContainsString('object-fit: contain', $css);
        self::assertStringContainsString('storeFigureFromUpload', $storage);
        self::assertStringContainsString('savePreservingAlpha', $storage);
        self::assertStringContainsString('PNG du personnage', $form);
        self::assertStringContainsString('showcase_kit_items', $home);
        self::assertStringNotContainsString('endpoint', $form);
        self::assertStringNotContainsString('mkdir', $form);
    }

    public function testConfigurationUpdateIsDeclared(): void
    {
        $root = $this->root();
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $probe = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateProbes.php');
        $seed = (string) file_get_contents($root . '/bootstrap/configuration_updates_migration.php');
        self::assertStringContainsString('DASHBOARD_WARDROBE_SHOWCASE_V1', $catalog);
        self::assertStringContainsString('equipmentShowcaseApplicable', $catalog);
        self::assertStringContainsString('hasDashboardWardrobePin', $probe);
        self::assertStringContainsString('DASHBOARD_WARDROBE_SHOWCASE_V1', $seed);
        self::assertStringContainsString('back-office/dashboard-tenues', $catalog);
    }

    public function testOrgAdminAccessDoesNotRequirePlatformAdmin(): void
    {
        $root = $this->root();
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/DashboardWardrobePinsAdminController.php');
        $nav = (string) file_get_contents($root . '/config/navigation.php');
        $implication = (string) file_get_contents($root . '/app/Authorization/PermissionImplication.php');
        $access = (string) file_get_contents($root . '/app/Authorization/DashboardPinsAccess.php');
        $tenuesNav = '';
        if (preg_match("/dashboard-tenues'.+?\\],/s", $nav, $m)) {
            $tenuesNav = $m[0];
        }
        self::assertStringContainsString('DashboardPinsAccess', $controller);
        self::assertStringNotContainsString('admin.system', $controller);
        self::assertStringContainsString('admin.organization', $access);
        self::assertStringNotContainsString('admin.system', $access);
        self::assertStringContainsString("'dashboard.pins.manage'", $implication);
        self::assertStringContainsString('admin.organization', $tenuesNav);
        self::assertStringNotContainsString('admin.system', $tenuesNav);
    }
}
