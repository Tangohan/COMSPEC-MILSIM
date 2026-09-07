<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RolePermissionMatrixUxTest extends TestCase
{
    public function testMatrixExposesSearchRightsAndDirectAssignmentControls(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/roles_permissions/index.php');

        self::assertStringContainsString('data-role-search', $view);
        self::assertStringContainsString('Droits granulaires réellement accordés', $view);
        self::assertStringContainsString('data-assign-dialog', $view);
        self::assertStringContainsString("url('back-office/roles-permissions/assign')", $view);
    }

    public function testLegacyAccessScreensRedirectToEffectifsAccess(): void
    {
        $root = dirname(__DIR__, 2);
        $matrix = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/RolePermissionMatrixController.php');
        $roles = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/RoleAdminController.php');
        $access = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/AccessManagementController.php');
        $doctrine = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/RolesFunctionsAdminController.php');
        $droits = (string) file_get_contents($root . '/app/Controllers/Admin/EffectifsWorkspaceController.php');

        self::assertStringContainsString("return Response::redirect(effectifs_workspace_url('roles'));", $matrix);
        self::assertStringContainsString("return Response::redirect(effectifs_workspace_url('roles'));", $roles);
        self::assertStringContainsString("return Response::redirect(effectifs_workspace_url('roles'));", $access);
        self::assertStringContainsString("return Response::redirect(effectifs_workspace_url('fonctions'));", $doctrine);
        self::assertStringContainsString("return Response::redirect(effectifs_workspace_url('roles'));", $droits);
    }

    public function testAssignmentEndpointIsProtectedByOrganizationAdminMiddleware(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');

        self::assertMatchesRegularExpression(
            "~/back-office/roles-permissions/assign'.*RolePermissionMatrixController::class, 'assign'.*AuthMiddleware::class, OrganizationAdminMiddleware::class~",
            $routes
        );
    }

    public function testServerSearchIncludesConcretePermissionMetadata(): void
    {
        $repository = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/RolePermissionMatrixRepository.php');

        self::assertStringContainsString("\$permission['slug']", $repository);
        self::assertStringContainsString("\$permission['module']", $repository);
        self::assertStringContainsString("\$row['permissions']", $repository);
    }

    public function testMemberProfileGrantsPersonalAtakAndBackOfficeWithoutSseOrFinance(): void
    {
        $profile = \App\Services\Rbac\RolePermissionMatrixCatalog::defaultProfileForRoleSlug('member');
        self::assertNotNull($profile);
        self::assertSame(
            \App\Services\Rbac\RolePermissionMatrixCatalog::LEVEL_SA_FICHE,
            $profile['modules'][\App\Services\Rbac\RolePermissionMatrixCatalog::MODULE_ATAK]
        );
        self::assertSame(
            \App\Services\Rbac\RolePermissionMatrixCatalog::LEVEL_SA_FICHE,
            $profile['modules'][\App\Services\Rbac\RolePermissionMatrixCatalog::MODULE_SYSTEMS]
        );
        self::assertSame(
            \App\Services\Rbac\RolePermissionMatrixCatalog::LEVEL_NONE,
            $profile['modules'][\App\Services\Rbac\RolePermissionMatrixCatalog::MODULE_FINANCES]
        );

        $atak = \App\Services\Rbac\RolePermissionMatrixCatalog::permissionSlugsForModuleLevel(
            \App\Services\Rbac\RolePermissionMatrixCatalog::MODULE_ATAK,
            \App\Services\Rbac\RolePermissionMatrixCatalog::LEVEL_SA_FICHE
        );
        self::assertContains('atak.terminals.view', $atak);
        self::assertNotContains('atak.sse.access', $atak);
        self::assertNotContains('atak.terminals.manage', $atak);

        $systems = \App\Services\Rbac\RolePermissionMatrixCatalog::permissionSlugsForModuleLevel(
            \App\Services\Rbac\RolePermissionMatrixCatalog::MODULE_SYSTEMS,
            \App\Services\Rbac\RolePermissionMatrixCatalog::LEVEL_SA_FICHE
        );
        self::assertContains('admin.backoffice.view', $systems);
        self::assertNotContains('admin.organization', $systems);
        self::assertNotContains('admin.settings.manage', $systems);
    }
}
