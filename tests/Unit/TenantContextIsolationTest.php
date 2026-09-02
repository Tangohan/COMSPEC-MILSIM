<?php

declare(strict_types=1);
namespace Tests\Unit;
use App\Core\{Gate,Session};
use App\Services\Tenant\TenantContext;
use PHPUnit\Framework\TestCase;
final class TenantContextIsolationTest extends TestCase {
 protected function setUp(): void { $_SESSION=[];TenantContext::clearVerification();Gate::getInstance()->setPermissions([]);Session::set('tenant_id',7);Session::set('user_id',41); }
 public function test_browser_style_tenant_value_cannot_activate_cross_tenant_context(): void { Session::set('admin_tenant_id',99);Session::set('platform_admin_tenant_context',true);TenantContext::verifyPlatformAdmin(41);self::assertFalse(TenantContext::isIntervention());self::assertSame(7,TenantContext::id()); }
 public function test_verified_platform_admin_context_is_request_local_and_bypasses_rbac(): void { Session::set('platform_admin_id',41);Session::set('admin_tenant_id',99);Session::set('platform_admin_tenant_context',true);TenantContext::verifyPlatformAdmin(41);self::assertSame(99,TenantContext::id());self::assertTrue(Gate::getInstance()->allows('any.tenant.permission'));TenantContext::clearVerification();self::assertSame(7,TenantContext::id());self::assertFalse(Gate::getInstance()->allows('any.tenant.permission')); }
 public function test_different_authenticated_admin_cannot_reuse_context(): void { Session::set('platform_admin_id',40);Session::set('admin_tenant_id',99);Session::set('platform_admin_tenant_context',true);TenantContext::verifyPlatformAdmin(41);self::assertFalse(TenantContext::isIntervention()); }
}
