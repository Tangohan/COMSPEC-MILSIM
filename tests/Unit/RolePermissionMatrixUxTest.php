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

    public function testAssignmentEndpointIsProtectedByOrganizationAdminMiddleware(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');

        self::assertMatchesRegularExpression(
            "~/back-office/roles-permissions/assign'.*RolePermissionMatrixController::class, 'assign'.*AuthMiddleware::class, OrganizationAdminMiddleware::class~",
            $routes
        );
    }
}
