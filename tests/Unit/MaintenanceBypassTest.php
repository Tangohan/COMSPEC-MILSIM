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
}
