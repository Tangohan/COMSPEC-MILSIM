<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMarkerDropperWebAssetTest extends TestCase
{
    public function testDropperInfantryIsForcedToThePost(): void
    {
        $root = dirname(__DIR__, 2);
        $sync = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncMapMarker.sqf');
        $nearby = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncNearbyMapMarkers.sqf');
        $isSync = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_isSyncableMapMarker.sqf');
        $xeh = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf');
        $reach = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installReachMap.sqf');
        $bridge = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeCtabMarkers.sqf');
        $js = (string) file_get_contents($root . '/public/assets/js/arma-map-markers.js');
        $cfgC = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $cfgA = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp');
        $note = (string) file_get_contents($root . '/docs/bugs/2026-09-19-marker-dropper-infanterie-web.md');

        self::assertStringContainsString('_isWidget', $sync);
        self::assertStringContainsString('if (!_force && {_isWidget}) then { _force = true; };', $sync);
        self::assertStringContainsString('class syncNearbyMapMarkers {}', $cfgC);
        self::assertStringContainsString('versionStr = "1.5.95"', $cfgC);
        self::assertStringContainsString('versionStr = "1.0.152"', $cfgA);

        self::assertStringContainsString('o_', $isSync);
        self::assertStringContainsString('_defined #', $isSync);

        self::assertStringContainsString('isFinal cTab_fnc_PlaceMarker', $xeh);
        self::assertStringContainsString('syncNearbyMapMarkers', $xeh);
        self::assertStringContainsString('[_n, false, true]', $xeh);

        self::assertStringContainsString('MouseButtonDblClick', $reach);
        self::assertStringContainsString('syncNearbyMapMarkers', $reach);
        self::assertStringContainsString('syncNearbyMapMarkers', $nearby);

        self::assertStringContainsString('allMapMarkers', $bridge);
        self::assertStringContainsString('o_', $bridge);

        self::assertStringContainsString("data.source === 'bce_widget'", $js);
        self::assertStringContainsString('losange', strtolower($note));
        self::assertStringNotContainsString('endpoint', $note);
    }
}
