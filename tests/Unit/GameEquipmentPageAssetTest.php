<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * « Équipement des joueurs » et « Kits par rôle » : routes, câblage, navigation, recherche, styles,
 * migration, et enregistrement de l’usage branché sur le register/sync du profil opérateur.
 */
final class GameEquipmentPageAssetTest extends TestCase
{
    private function read(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testRoutesAreRegisteredForOrganizationAdmins(): void
    {
        $routes = $this->read('routes/web.php');

        foreach ([
            "get('/back-office/equipement-joueurs', [GameEquipmentAdminController::class, 'index']",
            "get('/back-office/kits-roles', [GameEquipmentAdminController::class, 'kits']",
            "get('/back-office/kits-roles/nouveau', [GameEquipmentAdminController::class, 'create']",
            "post('/back-office/kits-roles', [GameEquipmentAdminController::class, 'store']",
            "get('/back-office/kits-roles/{id}', [GameEquipmentAdminController::class, 'edit']",
            "post('/back-office/kits-roles/{id}', [GameEquipmentAdminController::class, 'update']",
            "post('/back-office/kits-roles/{id}/supprimer', [GameEquipmentAdminController::class, 'delete']",
            "post('/back-office/kits-roles/{id}/publier', [GameEquipmentAdminController::class, 'publish']",
        ] as $needle) {
            self::assertStringContainsString($needle, $routes);
        }
        self::assertLessThan(
            strpos($routes, "'/back-office/kits-roles/{id}'"),
            strpos($routes, "'/back-office/kits-roles/nouveau'"),
            '« nouveau » doit précéder {id}'
        );
        self::assertMatchesRegularExpression(
            "~equipement-joueurs', \[GameEquipmentAdminController::class, 'index'\], \[AuthMiddleware::class, OrganizationAdminMiddleware::class\]~",
            $routes
        );
    }

    public function testContainerNavSearchAndPageConfig(): void
    {
        $container = $this->read('app/Core/Container.php');
        self::assertStringContainsString('\App\Controllers\Admin\Organization\GameEquipmentAdminController::class => new', $container);
        self::assertStringContainsString('\App\Services\GameEquipment\RoleKitService::class => new', $container);

        $nav = $this->read('views/partials/ath_sidebar_nav.php');
        self::assertStringContainsString("url('back-office/equipement-joueurs')", $nav);
        self::assertStringContainsString("url('back-office/kits-roles')", $nav);

        $search = $this->read('app/Services/Portal/BackOfficeSearchService.php');
        self::assertStringContainsString("url('back-office/equipement-joueurs')", $search);
        self::assertStringContainsString("url('back-office/kits-roles')", $search);

        $pages = $this->read('config/back_office_pages.php');
        self::assertStringContainsString("'path' => 'back-office/equipement-joueurs'", $pages);
        self::assertStringContainsString("'path' => 'back-office/kits-roles'", $pages);
        self::assertStringContainsString("'back-office-game-equipment.css'", $pages);
    }

    public function testViewsLoadAssetsAndKeepFormFields(): void
    {
        $this->read('public/assets/css/back-office-game-equipment.css');
        $this->read('public/assets/js/back-office-game-equipment.js');

        foreach (['game_equipment', 'game_role_kits', 'game_role_kit_form'] as $view) {
            self::assertStringContainsString('back-office-game-equipment.js', $this->read('views/admin/organization/' . $view . '.php'));
        }
        $form = $this->read('views/admin/organization/game_role_kit_form.php');
        foreach (['name="_csrf_token"', 'name="name"', 'name="job_role_id"', 'name="description"', "'slot['", "name=\"cargo['", 'data-ge-catalogue', 'data-ge-picker'] as $needle) {
            self::assertStringContainsString($needle, $form);
        }
        $list = $this->read('views/admin/organization/game_role_kits.php');
        self::assertStringContainsString("'/supprimer'", $list);
        self::assertStringContainsString('data-ge-confirm', $list);

        $css = $this->read('public/assets/css/back-office-game-equipment.css');
        self::assertStringContainsString('html[data-bo-theme="dark"] .bo-ge', $css);
        self::assertStringContainsString('@media (max-width: 560px)', $css);
    }

    public function testMigrationCreatesTables(): void
    {
        $sql = $this->read('migrations/20261018000001_game_item_usage.sql');
        foreach (['game_item_catalog', 'game_item_usage', 'game_detected_mods', 'game_player_mods', 'game_role_kits'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `' . $table . '`', $sql);
        }
        self::assertStringContainsString('UNIQUE KEY `uk_game_item_catalog_class` (`tenant_id`,`class_name`)', $sql);
        self::assertStringContainsString('PRIMARY KEY (`tenant_id`,`steam_id`,`class_name`,`usage_day`)', $sql);
    }

    public function testOperatorSyncRecordsUsageWithoutBreaking(): void
    {
        $api = $this->read('app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('GameItemUsageRecorder())->record($tenantId, $steamId, $payload)', $api);

        $recorder = $this->read('app/Services/GameEquipment/GameItemUsageRecorder.php');
        self::assertStringContainsString('catch (\Throwable $e)', $recorder);
        self::assertStringContainsString('error_log(', $recorder);
    }

    public function testMemberHubShowsKitsForMyRole(): void
    {
        self::assertStringContainsString('Kits proposés pour mon rôle', $this->read('views/equipment/hub.php'));
        self::assertStringContainsString("'roleKits' => \$roleKits", $this->read('app/Controllers/Web/ArsenalWardrobeController.php'));
    }
}
