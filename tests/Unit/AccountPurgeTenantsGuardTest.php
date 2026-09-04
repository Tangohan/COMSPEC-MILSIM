<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Account\AccountPurgeService;
use PHPUnit\Framework\TestCase;

final class AccountPurgeTenantsGuardTest extends TestCase
{
    public function testTenantsTableIsSkippedFromOwnershipPurge(): void
    {
        $ref = new \ReflectionClass(AccountPurgeService::class);
        $prop = $ref->getConstant('SKIP_TABLES');
        self::assertIsArray($prop);
        self::assertContains('tenants', $prop);
    }
}
