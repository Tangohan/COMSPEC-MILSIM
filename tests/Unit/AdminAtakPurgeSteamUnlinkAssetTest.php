<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminAtakPurgeSteamUnlinkAssetTest extends TestCase
{
    public function testPurgeFormOffersAnExplicitSteamUnlinkOption(): void
    {
        $view = file_get_contents(base_path('views/admin/atak-config/index.php'));

        self::assertIsString($view);
        self::assertStringContainsString('name="unlink_steam"', $view);
        self::assertStringContainsString('Désynchroniser aussi tous les comptes Steam', $view);
        self::assertStringContainsString('Chaque opérateur devra refaire sa liaison', $view);
    }

    public function testPurgePropagatesSteamChoiceAndReportsTheResult(): void
    {
        $controller = file_get_contents(base_path('app/Controllers/Admin/AdminAtakConfigController.php'));
        $service = file_get_contents(base_path('app/Services/Tactical/AtakTenantDataService.php'));

        self::assertIsString($controller);
        self::assertIsString($service);
        self::assertStringContainsString("input('unlink_steam', '0')", $controller);
        self::assertStringContainsString('purgeAll($tenantId, $unlinkSteam)', $controller);
        self::assertStringContainsString('unlinkSteamAccounts($tenantId)', $service);
        self::assertStringContainsString('UPDATE users SET steam_id = NULL', $service);
        self::assertStringContainsString('UPDATE game_sessions SET revoked_at = NOW()', $service);
        self::assertStringContainsString('UPDATE game_device_pairings SET revoked_at = NOW()', $service);
    }
}
