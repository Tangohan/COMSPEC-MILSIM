<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Audit\AuditAction;
use PHPUnit\Framework\TestCase;

final class AuditActionTest extends TestCase
{
    public function testRolePermissionsUpdatedConstant(): void
    {
        $this->assertSame('role.permissions_updated', AuditAction::ROLE_PERMISSIONS_UPDATED);
    }
}
