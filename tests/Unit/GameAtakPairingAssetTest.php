<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GameAtakPairingAssetTest extends TestCase
{
    public function testPairingAndRecoveryRoutesArePublicAndWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $exempt = (string) file_get_contents($root . '/config/tactical_api.php');
        $svc = (string) file_get_contents($root . '/app/Services/Game/GameAtakPairingService.php');
        $auth = (string) file_get_contents($root . '/app/Services/Game/GameAuthService.php');
        $recovery = (string) file_get_contents($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_networkRecoveryCode.sqf');
        $redeem = (string) file_get_contents($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_networkRedeemPairingCode.sqf');
        $mapShow = (string) file_get_contents($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_webMapShow.sqf');
        $phone = (string) file_get_contents($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/web/phone.html');
        $view = (string) file_get_contents($root . '/views/atak.php');
        $integrations = (string) file_get_contents($root . '/app/Core/ContainerIntegrations.php');

        self::assertStringContainsString('/api/atak/pair/start', $routes);
        self::assertStringContainsString('/api/atak/pair/status', $routes);
        self::assertStringContainsString('/api/atak/pair/redeem', $routes);
        self::assertStringContainsString('/api/atak/recovery/redeem', $routes);
        self::assertStringContainsString('/atak/game-link/confirm-pair', $routes);
        self::assertStringContainsString("'/api/atak/pair/start'", $exempt);
        self::assertStringContainsString("'/api/atak/recovery/redeem'", $exempt);
        self::assertStringContainsString('redeemPortalCode', $svc);
        self::assertStringContainsString('approveFromWeb', $svc);
        self::assertStringContainsString('function issueForUser', $auth);
        self::assertStringContainsString('RedeemGameLink', $recovery);
        self::assertStringContainsString('RedeemGameLink', $redeem);
        self::assertStringContainsString('COMSPEC_fnc_webMapRaise', $mapShow);
        self::assertFileExists($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_webMapRaise.sqf');
        self::assertStringContainsString('pairingWebCodeInput', $phone);
        self::assertStringContainsString('atak-game-link-confirm', $view);
        self::assertStringContainsString('GameAtakPairingApiController', $integrations);
        self::assertFileExists($root . '/bootstrap/athena_atak_pair_migration.php');
    }
}
