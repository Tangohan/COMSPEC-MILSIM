<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdvancedFicheEditGrantAssetTest extends TestCase
{
    public function testMigrationAndRepositoryExist(): void
    {
        $migration = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/user_advanced_edit_grants_migration.php');
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserAdvancedEditGrantRepository.php');
        $runner = (string) file_get_contents(dirname(__DIR__, 2) . '/run-migrations.php');

        self::assertStringContainsString('user_advanced_edit_grants', $migration);
        self::assertStringContainsString('run_user_advanced_edit_grants_migration', $migration);
        self::assertStringContainsString('run_user_advanced_edit_grants_migration($pdo)', $runner);
        self::assertStringContainsString('DURATION_HOURS = 24', $repo);
        self::assertStringContainsString('findActiveForUser', $repo);
        self::assertStringContainsString('revokeActiveForUser', $repo);
        self::assertStringContainsString('listActiveGlobal', $repo);
        self::assertStringContainsString('revokeById', $repo);
    }

    public function testHelpersAndBannerPartial(): void
    {
        $helpers = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/helpers.php');
        $banner = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/advanced_fiche_edit_banner.php');
        $layout = (string) file_get_contents(dirname(__DIR__, 2) . '/views/layout/main.php');

        self::assertStringContainsString('function user_has_advanced_fiche_edit', $helpers);
        self::assertStringContainsString('function user_advanced_fiche_edit_grant', $helpers);
        self::assertStringContainsString('admin/system/advanced-fiche-edit', $helpers);
        self::assertStringContainsString('Un administrateur a activé le mode avancée de modification de fiche', $banner);
        self::assertStringContainsString('advanced-fiche-edit-modal', $banner);
        self::assertStringContainsString('Ouvrir l’édition de ma fiche', $banner);
        self::assertStringContainsString("advanced_fiche_edit_banner.php", $layout);
        self::assertGreaterThanOrEqual(2, substr_count($layout, 'advanced_fiche_edit_banner.php'));
    }

    public function testAdminRoutesSidebarAndController(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $boSidebar = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/ath_sidebar_nav.php');
        $platformSidebar = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/platform_admin_sidebar.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AdvancedFicheEditGrantController.php');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/system/advanced_fiche_edit.php');
        $container = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Container.php');
        $usersRepo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');

        self::assertStringContainsString('admin/system/advanced-fiche-edit', $routes);
        self::assertStringContainsString('SystemAdminMiddleware::class', $routes);
        self::assertStringContainsString('AdvancedFicheEditGrantController', $routes);
        self::assertStringNotContainsString("url('back-office/personnel/advanced-edit')", $boSidebar);
        self::assertStringContainsString('Édition avancée de fiche', $platformSidebar);
        self::assertStringContainsString('admin/system/advanced-fiche-edit', $platformSidebar);
        self::assertStringContainsString('searchAccountsForPlatformOperator', $controller);
        self::assertStringContainsString('admin.system', $controller);
        self::assertStringContainsString('Activer pour un membre', $view);
        self::assertStringContainsString('toutes communautés', $view);
        self::assertStringContainsString('UserAdvancedEditGrantRepository::class', $container);
        self::assertStringContainsString('AdvancedFicheEditGrantController::class', $container);
        self::assertStringContainsString('legalIdentityJoinFragments', $usersRepo);
        self::assertStringContainsString('uli.first_name', $usersRepo);
        self::assertStringNotContainsString('u.first_name, u.last_name', $usersRepo);
        self::assertStringContainsString('pp.character_name', $usersRepo);
    }

    public function testPersonnelEditUnlocksClearanceAndMatriculeButNotAthena(): void
    {
        $edit = (string) file_get_contents(dirname(__DIR__, 2) . '/views/personnel/edit.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/PersonnelController.php');

        self::assertStringContainsString('advancedEditActive', $edit);
        self::assertStringContainsString('name="clearance_level"', $edit);
        self::assertStringContainsString('name="matricule_internal"', $edit);
        self::assertStringContainsString('Identifiant Athena', $edit);
        self::assertStringContainsString('Non modifiable', $edit);
        self::assertStringContainsString('advancedEditActive', $controller);
        self::assertStringContainsString("data['clearance_level']", $controller);
        self::assertStringContainsString("data['matricule_internal']", $controller);
        self::assertStringContainsString('athena_identifier volontairement ignoré', $controller);
    }
}
