<?php

declare(strict_types=1);

namespace Tests\Contract\Api;

use PHPUnit\Framework\TestCase;

final class AccessControlApiContractTest extends TestCase
{
    public function testWebRoutesExposeAccessControlApiEndpoints(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../../routes/web.php');
        self::assertIsString($routes);
        self::assertStringContainsString('/api/access-control/roles', $routes);
        self::assertStringContainsString('/api/access-control/permissions', $routes);
        self::assertStringContainsString('/api/access-control/role-permissions', $routes);
        self::assertStringContainsString('/api/access-control/rules', $routes);
        self::assertStringContainsString('/api/access-control/scopes', $routes);
        self::assertStringContainsString('/api/access-control/simulation', $routes);
    }
}
