<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SseAccessClearanceAssetTest extends TestCase
{
    public function testGateHydrationAndBoAccessSurface(): void
    {
        $root = dirname(__DIR__, 2);
        $svc = (string) file_get_contents($root . '/app/Services/Sse/SseAccessCodeService.php');
        $catalog = (string) file_get_contents($root . '/app/Authorization/TenantPermissionCatalog.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $gate = (string) file_get_contents($root . '/views/atak/sse/gate.php');
        $edit = (string) file_get_contents($root . '/views/personnel/edit.php');

        self::assertStringContainsString('ensureGateHydrated', $svc);
        self::assertStringContainsString('canEnterWithoutCode', $svc);
        self::assertStringContainsString('atak.sse.clearance.encadrement', $catalog);
        self::assertStringContainsString('atak.sse.clearance.confidentiel', $catalog);
        self::assertStringContainsString('atak.sse.clearance.tres_restreint', $catalog);
        self::assertStringContainsString('/back-office/renseignement/acces', $routes);
        self::assertStringContainsString('AdminSseAccessController', $routes);
        self::assertStringContainsString('Entrer sans code', $gate);
        self::assertStringContainsString('Niveau de diffusion renseignement', $edit);
        self::assertStringContainsString('clearance_level', $edit);
    }
}
