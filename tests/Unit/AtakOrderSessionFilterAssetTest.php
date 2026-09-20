<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakOrderSessionFilterAssetTest extends TestCase
{
    public function testGameOrdersAskOnlyThoseCreatedThisSession(): void
    {
        $root = dirname(__DIR__, 2);
        $repo = (string) file_get_contents($root . '/app/Repositories/AtakOrderRepository.php');
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $orders = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollOrders.sqf');
        $ai = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollAiOrders.sqf');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $bug = (string) file_get_contents($root . '/docs/bugs/2026-09-18-atak-crash-prise-equipement.md');

        self::assertStringContainsString('?string $createdAfter = null', $repo);
        self::assertStringContainsString('AND created_at >= ?', $repo);

        self::assertStringContainsString("query('created_after')", $api);
        self::assertStringContainsString("'created_after' => true", $api);

        self::assertStringContainsString('created_after=', $ext);
        self::assertStringContainsString('2.0.48', $ext);

        self::assertStringContainsString('COMSPEC_OrdersSessionStartedAt', $orders);
        self::assertStringContainsString('systemTimeUTC', $orders);
        self::assertStringContainsString('["GetOrders", [_mapId, "40", _callsign, _sessionStart]]', $orders);
        self::assertStringContainsString('isEqualTo "web") then { continue }', $orders);

        self::assertStringContainsString('["GetAiOrders", [_mapId, _sessionStart]]', $ai);

        self::assertStringContainsString('versionStr = "1.6.4"', $cfg);

        self::assertStringContainsString('1.5.87', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
        self::assertStringNotContainsString('JSON', $bug);
    }
}
