<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OperatorBackOfficeAccessTest extends TestCase
{
    public function testBackOfficeRootProvidesAReadOnlyOperatorOverview(): void
    {
        $middleware = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Middleware/OrganizationAdminMiddleware.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/Organization/OrganizationDashboardController.php');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/operator_overview.php');

        self::assertStringContainsString("\$path === '/back-office'", $middleware);
        self::assertStringContainsString('return $this->operatorOverview', $controller);
        self::assertStringContainsString("(int) (\$terminal['user_id'] ?? 0) === \$userId", $controller);
        self::assertStringContainsString('Il ne donne aucun droit d’administration.', $view);
        self::assertStringContainsString('Mes données ATAK', $view);
        self::assertStringContainsString('Mes données RP', $view);
        self::assertStringNotContainsString('pairing_token', $view);
    }
}
