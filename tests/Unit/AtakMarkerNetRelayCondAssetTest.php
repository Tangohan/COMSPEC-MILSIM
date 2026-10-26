<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : marqueurs diffusés par le seul réseau ATAK (hors carte d'Arma), heure de pose sur la fiche du marqueur,
 * relais actifs seulement sous condition (mât déployé, à l'arrêt, moteur coupé, code de mission).
 */
final class AtakMarkerNetRelayCondAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testMarkerDistributionSettingDefaultsToArmaMap(): void
    {
        $pre = $this->read(self::MAIN . '/XEH_preInit.sqf');
        self::assertMatchesRegularExpression('/\["comspec_atak_native_marker_net", "LIST",.*?\[\["arma", "atak"\],.*?, 0\], 1\]/s', $pre);
        self::assertStringContainsString('class markerNet {};', $this->read(self::MAIN . '/config.cpp'));
    }

    public function testEveryPhoneMarkerGoesThroughMarkerNet(): void
    {
        foreach (['fn_markerDrop', 'fn_markerStroke', 'fn_mapToolRun', 'fn_markerEditSave'] as $f) {
            $src = $this->read(self::MAIN . '/functions/map/' . $f . '.sqf');
            self::assertStringContainsString('["create", [', $src, $f);
            self::assertStringContainsString('call comspec_atak_native_fnc_markerNet', $src, $f);
            self::assertStringNotContainsString('createMarker [', $src, $f);
        }
        $web = $this->read(self::MAIN . '/functions/map/fn_markerWeb.sqf');
        self::assertStringContainsString('["touch", _this] call comspec_atak_native_fnc_markerNet;', $web);
    }

    public function testAtakModeIsLocalAndGatedByThePhoneNetwork(): void
    {
        $net = $this->read(self::MAIN . '/functions/map/fn_markerNet.sqf');
        self::assertStringContainsString('if !(_on) exitWith { createMarker [_name, _pos, _channel, player] };', $net);
        self::assertStringContainsString('createMarkerLocal [_name, _pos]', $net);
        self::assertStringContainsString('comspec_atak_native_fnc_netSend', $net);
        self::assertStringContainsString('if !([] call comspec_atak_native_fnc_p2pReachable) exitWith {', $net);
        self::assertStringContainsString('COMSPEC_ATAK_TacMkHold', $net);
        foreach (['"sync"', '"del"', '"set"', '"flush"', '"srvMeta"'] as $op) {
            self::assertStringContainsString('case ' . $op . ':', $net, $op);
        }
        $cli = $this->read(self::MAIN . '/XEH_postInitClient.sqf');
        self::assertStringContainsString('["comspec_atak_native_tacMk", { ["recv", _this] call comspec_atak_native_fnc_markerNet; }] call CBA_fnc_addEventHandler;', $cli);
        self::assertStringContainsString('["flush"] call comspec_atak_native_fnc_markerNet;', $cli);
        $srv = $this->read(self::MAIN . '/XEH_postInitServer.sqf');
        self::assertStringContainsString('["comspec_atak_native_tacMkSrv", { ["srv", _this] call comspec_atak_native_fnc_markerNet; }] call CBA_fnc_addEventHandler;', $srv);
    }

    public function testMarkerCardShowsPlacementTime(): void
    {
        $map = $this->read(self::MAIN . '/functions/pages/fn_pageMap.sqf');
        self::assertStringContainsString('COMSPEC_ATAK_MarkerMeta', $map);
        self::assertStringContainsString('Posé à %1:%2 · il y a %3 min', $map);
        self::assertStringContainsString('["Posé à %1:%2 · il y a %3 min", "Placed at %1:%2 · %3 min ago"]', $this->read(self::MAIN . '/i18n/en.txt'));
    }

    public function testRelayConditionIsWiredToRelaysGatewaysAndModule(): void
    {
        $cond = $this->read(self::MAIN . '/functions/network/fn_relayCond.sqf');
        foreach (['case "stopped":', 'case "engineoff":', 'case "mast":', 'animationSourcePhase', 'COMSPEC_AtakRelayCondText'] as $k) {
            self::assertStringContainsString($k, $cond, $k);
        }
        $net = $this->read(self::MAIN . '/functions/network/fn_relayNet.sqf');
        self::assertSame(2, substr_count($net, 'call comspec_atak_native_fnc_relayCond;'));
        $mod = $this->read(self::MAIN . '/modules.hpp');
        self::assertStringContainsString("_this setVariable ['Cond',_value,true];", $mod);
        self::assertStringContainsString("_this setVariable ['CondCode',_value,true];", $mod);
        $fn = $this->read(self::MAIN . '/functions/network/fn_moduleRelay.sqf');
        self::assertStringContainsString('_x setVariable ["COMSPEC_AtakRelayCond", trim _cond, true];', $fn);
        self::assertStringContainsString('["COMBO", "Actif seulement si", _conds, _cur select 14]', $fn);
        self::assertStringContainsString('STR_COMSPEC_ATAK_CFG_RELAYCOND_NAME', $this->read(self::MAIN . '/stringtable.xml'));
        self::assertStringContainsString('class relayCond {};', $this->read(self::MAIN . '/config.cpp'));
    }
}
