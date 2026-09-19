<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchGearUplinkAssetTest extends TestCase
{
    public function testFirstTerminalDoesNotDumpOrdersAndMarkers(): void
    {
        $root = dirname(__DIR__, 2);
        $base = $root . '/mod/UptoDate/Sources/comspec-overwatch-addons';
        $quiet = (string) file_get_contents($base . '/connect/functions/fn_uplinkQuiet.sqf');
        $loops = (string) file_get_contents($base . '/connect/functions/fn_startSyncLoops.sqf');
        $orders = (string) file_get_contents($base . '/connect/functions/fn_pollOrders.sqf');
        $receive = (string) file_get_contents($base . '/connect/functions/fn_receiveOrder.sqf');
        $markers = (string) file_get_contents($base . '/connect/functions/fn_pollAthenaMarkers.sqf');
        $shapes = (string) file_get_contents($base . '/connect/functions/fn_pollMapShapes.sqf');
        $post = (string) file_get_contents($base . '/atak_athena/XEH_postInitClient.sqf');
        $sync = (string) file_get_contents($base . '/atak_athena/functions/fn_athena_syncOrdersToGroupChat.sqf');
        $cfgC = (string) file_get_contents($base . '/connect/config.cpp');
        $cfgA = (string) file_get_contents($base . '/atak_athena/config.cpp');
        $bug = (string) file_get_contents($root . '/docs/bugs/2026-09-18-atak-crash-prise-equipement.md');

        self::assertStringContainsString('ace_arsenal_display', $quiet);
        self::assertStringContainsString('< 8', $quiet);

        self::assertStringContainsString('COMSPEC_TerminalAcquiredAt', $loops);
        self::assertStringContainsString('Téléphone pris', $loops);
        self::assertStringContainsString('uplinkQuiet', $loops);
        self::assertStringContainsString(', 8, 4, "orders"]', $loops);
        self::assertStringContainsString(', 8, 8, "webmk"]', $loops);
        self::assertStringContainsString('COMSPEC_OrdersPollBootstrapped', $loops);

        self::assertStringContainsString('hasTerminal', $orders);
        self::assertStringContainsString('COMSPEC_OrdersPollBootstrapped', $orders);
        self::assertStringContainsString('COMSPEC_OrdersBeforeSession', $orders);
        self::assertStringContainsString('COMSPEC_OrdersSessionPrimed', $orders);
        self::assertStringContainsString('COMSPEC_OrdersSessionStartedAt', $orders);
        self::assertStringContainsString('hors partie', $orders);
        self::assertStringContainsString('athena_phoneDisplay', $orders);

        self::assertStringContainsString('uplinkQuiet', $receive);
        self::assertStringContainsString('], 3] call CBA_fnc_waitAndExecute', $receive);

        self::assertStringContainsString('COMSPEC_WebMarkersBootstrapped', $markers);
        self::assertStringContainsString('_created >= 10', $markers);
        self::assertStringContainsString('première lecture', $markers);

        self::assertStringContainsString('COMSPEC_MapShapesBootstrapped', $shapes);
        self::assertStringContainsString('_shaped < 6', $shapes);

        self::assertStringContainsString('hasTerminal', $post);
        self::assertStringContainsString('uplinkQuiet', $post);
        self::assertStringContainsString('athena_phoneDisplay', $post);

        self::assertStringContainsString('athena_phoneDisplay', $sync);
        self::assertStringContainsString('uplinkQuiet', $sync);

        self::assertStringContainsString('class uplinkQuiet {}', $cfgC);
        self::assertStringContainsString('1.5.96', $cfgC);
        self::assertStringContainsString('1.0.153', $cfgA);
        self::assertStringContainsString('_fnc_markSeen', $orders);
        self::assertStringContainsString('_newOnes select 0', $orders);
        self::assertStringContainsString('isEqualTo "web") then { continue }', $orders);
        self::assertStringContainsString('photo de la dernière lecture', $orders);

        self::assertStringContainsString('équipement', mb_strtolower($bug));
        self::assertStringContainsString('corrigé', mb_strtolower($bug));
        self::assertStringNotContainsString('endpoint', $bug);
        self::assertStringNotContainsString('callExtension', $bug);
        self::assertStringNotContainsString('JSON', $bug);
    }
}
