<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Support\MaintenanceService;
use PDO;
use PHPUnit\Framework\TestCase;

final class MaintenanceBypassTest extends TestCase
{
    protected function tearDown(): void
    {
        Gate::reset();
        parent::tearDown();
    }

    public function testAdminBypassNeedsALoggedInUserThisRequest(): void
    {
        $service = new MaintenanceService(new PDO('sqlite::memory:'));
        $rule = ['allow_admin_bypass' => 1];

        Gate::getInstance()->setPermissions(['admin.system']);

        self::assertFalse($service->shouldBypass($rule, null, '203.0.113.10'));
        self::assertFalse($service->shouldBypass($rule, [], '203.0.113.10'));
        self::assertTrue($service->shouldBypass($rule, ['user_id' => 4], '203.0.113.10'));
    }

    public function testGlobalMaintenanceIsLoadedForPublicIndex(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec(
            'CREATE TABLE app_maintenance (
                id INTEGER PRIMARY KEY,
                scope TEXT NOT NULL,
                is_enabled INTEGER NOT NULL,
                starts_at TEXT NULL,
                ends_at TEXT NULL,
                priority INTEGER NOT NULL
            )'
        );
        $pdo->exec(
            "INSERT INTO app_maintenance (id, scope, is_enabled, starts_at, ends_at, priority)
             VALUES (1, 'global', 1, NULL, NULL, 100)"
        );

        $service = new MaintenanceService($pdo);
        $active = $service->getActiveMaintenance('/', null);

        self::assertNotNull($active);
        self::assertSame(1, (int) $active['id']);
    }
}
