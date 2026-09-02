<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Core\Session;

/** The sole authority for resolving the tenant used by business code. */
final class TenantContext
{
    private static bool $platformAdminVerified = false;

    public static function verifyPlatformAdmin(int $userId): void
    {
        self::$platformAdminVerified = $userId > 0
            && (int) Session::get('platform_admin_id') === $userId
            && Session::get('platform_admin_tenant_context') === true;
    }

    public static function clearVerification(): void
    {
        self::$platformAdminVerified = false;
    }

    public static function isIntervention(): bool
    {
        return self::$platformAdminVerified && (int) Session::get('admin_tenant_id') > 1;
    }

    public static function id(): int
    {
        return self::isIntervention()
            ? (int) Session::get('admin_tenant_id')
            : (int) Session::get('tenant_id');
    }

    /** @return array<string,mixed>|null */
    public static function intervention(): ?array
    {
        if (!self::isIntervention()) {
            return null;
        }
        return [
            'platform_admin_id' => (int) Session::get('platform_admin_id'),
            'admin_tenant_id' => (int) Session::get('admin_tenant_id'),
            'admin_tenant_started_at' => Session::get('admin_tenant_started_at'),
            'admin_tenant_session_id' => (int) Session::get('admin_tenant_session_id'),
            'admin_tenant_reason' => Session::get('admin_tenant_reason'),
        ];
    }
}
