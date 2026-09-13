<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SseAccessGateHydrationAssetTest extends TestCase
{
    public function testAccessServiceHydratesGateAndOffersEntryWithoutCode(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Sse/SseAccessCodeService.php');

        self::assertStringContainsString('function ensureGateHydrated(): void', $src);
        self::assertStringContainsString('setPermissionsForGateFromUserRow', $src);
        self::assertStringContainsString('$this->ensureGateHydrated()', $src);
        self::assertStringContainsString('function canEnterAsStaff(): bool', $src);
        self::assertStringContainsString('function canEnterAsMember(): bool', $src);
        self::assertStringContainsString('function canEnterWithoutCode(): bool', $src);
        self::assertStringContainsString('function establishMemberClearance(int $tenantId', $src);
        self::assertStringContainsString('is_platform_admin()', $src);
    }

    public function testPermissionCatalogContainsClearanceSlugs(): void
    {
        $catalog = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Authorization/TenantPermissionCatalog.php');

        self::assertStringContainsString('atak.sse.clearance.encadrement', $catalog);
        self::assertStringContainsString('atak.sse.clearance.confidentiel', $catalog);
        self::assertStringContainsString('atak.sse.clearance.tres_restreint', $catalog);
    }

    public function testBackOfficeRoutesExposeRenseignementAcces(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');

        self::assertStringContainsString('renseignement/acces', $routes);
        self::assertStringContainsString('AdminSseAccessController', $routes);
    }
}
