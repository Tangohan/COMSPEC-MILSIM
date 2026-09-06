<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PlatformAdminUiAssetTest extends TestCase
{
    public function testClosedListOwnsSiteAdminAndDossiersHaveNoCheckbox(): void
    {
        $root = dirname(__DIR__, 2);
        $list = (string) file_get_contents($root . '/views/admin/system/platform_admins.php');
        $person = (string) file_get_contents($root . '/views/admin/system/user_person.php');
        $edit = (string) file_get_contents($root . '/views/admin/system/user_edit.php');
        $assignments = (string) file_get_contents($root . '/views/admin/system/site_role_assignments.php');
        $sidebar = (string) file_get_contents($root . '/views/partials/platform_admin_sidebar.php');
        $nav = (string) file_get_contents($root . '/config/navigation.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemUsersController.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/UserRepository.php');
        $rbac = (string) file_get_contents($root . '/app/Services/Rbac/RbacService.php');
        $flag = (string) file_get_contents($root . '/app/Services/Rbac/PlatformAdminFlag.php');

        self::assertStringContainsString('Liste fermée', $list);
        self::assertStringContainsString('Administrateurs du site', $list);
        self::assertStringContainsString('OUVRIR LE SITE', $list);
        self::assertStringContainsString('RETIRER L ACCES', $list);
        self::assertStringContainsString('admin/users/platform-admin', $list);
        self::assertStringNotContainsString('name="is_platform_admin"', $list);

        self::assertStringContainsString('Administration du site', $person);
        self::assertStringContainsString('admin/system/administrateurs-site', $person);
        self::assertStringContainsString('Administrateur du site', $person);
        self::assertStringNotContainsString('name="is_platform_admin"', $person);
        self::assertStringNotContainsString('site_super_admin', $person);

        self::assertStringContainsString('Administration du site', $edit);
        self::assertStringContainsString('admin/system/administrateurs-site', $edit);
        self::assertStringNotContainsString('name="is_platform_admin"', $edit);

        self::assertStringContainsString('n’est plus un rôle', $assignments);
        self::assertStringContainsString('liste fermée', $assignments);
        self::assertStringNotContainsString('Slug :', $assignments);

        self::assertStringContainsString('admin/system/administrateurs-site', $sidebar);
        self::assertStringContainsString('admin/system/administrateurs-site', $nav);

        self::assertStringContainsString("'/admin/system/administrateurs-site'", $routes);
        self::assertStringContainsString("'/admin/users/platform-admin'", $routes);
        self::assertStringContainsString('platformAdmins', $controller);
        self::assertStringContainsString('setPlatformAdmin', $controller);
        self::assertStringContainsString('setPlatformAdminForEmail', $repo);
        self::assertStringContainsString('is_platform_admin', $rbac);
        self::assertStringContainsString('CONFIRM_GRANT', $flag);
        self::assertFileExists($root . '/bootstrap/users_platform_admin_flag_migration.php');
        self::assertFileExists($root . '/app/Services/Rbac/PlatformAdminFlag.php');
    }
}
