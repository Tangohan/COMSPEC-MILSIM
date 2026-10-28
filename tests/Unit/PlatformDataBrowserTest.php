<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Platform\PlatformDataBrowserService;
use PHPUnit\Framework\TestCase;

final class PlatformDataBrowserTest extends TestCase
{
    public function testSecretsAreMaskedAndNeverEditable(): void
    {
        foreach (['password', 'password_hash', 'api_key', 'remember_token', 'totp_secret', 'salt', 'refresh_token'] as $col) {
            self::assertTrue(PlatformDataBrowserService::isSensitiveColumn($col), $col);
        }
        foreach (['email', 'display_name', 'label', 'created_at', 'tenant_id'] as $col) {
            self::assertFalse(PlatformDataBrowserService::isSensitiveColumn($col), $col);
        }
        self::assertSame('•••••• (masqué)', PlatformDataBrowserService::displayValue('abc', true));
        self::assertSame('NULL', PlatformDataBrowserService::displayValue(null, false));
        self::assertSame('abc…', PlatformDataBrowserService::displayValue('abcdef', false, 3));
    }

    public function testAccountsCommunitiesAndAuditAreNeverEmptied(): void
    {
        foreach (['users', 'tenants', 'roles', 'permissions', 'audit_logs'] as $t) {
            self::assertTrue(PlatformDataBrowserService::isProtected($t), $t);
        }
        self::assertTrue(PlatformDataBrowserService::isReadOnly('audit_logs'));
        self::assertFalse(PlatformDataBrowserService::isReadOnly('users'));
        self::assertFalse(PlatformDataBrowserService::isProtected('atak_pings'));
    }

    public function testRoutesArePlatformAdminOnly(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        preg_match_all("#router->(get|post)\('/admin/system/storage/donnees[^\n]*#", $routes, $m);
        self::assertCount(6, $m[0]);
        foreach ($m[0] as $line) {
            self::assertStringContainsString('SystemAdminMiddleware::class', $line);
        }
    }
}
