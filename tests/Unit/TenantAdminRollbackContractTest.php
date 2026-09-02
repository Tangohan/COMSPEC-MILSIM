<?php

declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class TenantAdminRollbackContractTest extends TestCase {
 public function test_rollback_has_tenant_lock_conflict_guard_and_audit_linkage(): void { $s=file_get_contents(dirname(__DIR__,2).'/app/Services/Tenant/TenantAdminAuditService.php');self::assertStringContainsString('AND tenant_id=? FOR UPDATE',$s);self::assertStringContainsString('modifiée depuis cette action',$s);self::assertStringContainsString("'rollback_of_action_id'",$s);self::assertStringContainsString('beginTransaction()',$s); }
 public function test_audit_redacts_secret_families(): void { $s=file_get_contents(dirname(__DIR__,2).'/app/Services/Tenant/TenantAdminAuditService.php');foreach(['password','token','secret','api_key','cookie','private_key','jwt'] as $secret)self::assertStringContainsString("'$secret'",$s); }
}
