<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : relais posés par la mission (module Eden / Zeus « ATAK · Relais » et son tutoriel), passerelles de
 * véhicule et PC mobile coupés par défaut, répéteurs, saturation, batterie et actions ACE sur le relais.
 */
final class AtakRelayModuleTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testVehicleGatewaysAreOffByDefault(): void
    {
        $pre = $this->read(self::MAIN . '/XEH_preInit.sqf');
        self::assertMatchesRegularExpression('/\["comspec_atak_native_vehicle_gateways", "CHECKBOX",.*?_sim, false, 1\]/s', $pre);
        $gw = $this->read(self::MAIN . '/functions/vehicle/fn_vehicleGateway.sqf');
        self::assertStringContainsString('private _on = missionNamespace getVariable ["comspec_atak_native_vehicle_gateways", false];', $gw);
        self::assertStringContainsString('if (!_on || {!([_v] call _ok)}) exitWith { false };', $gw);
        self::assertStringContainsString('if (!_on) exitWith { [] };', $gw);
    }

    public function testRelayModuleWithEdenTutorial(): void
    {
        $mod = $this->read(self::MAIN . '/modules.hpp');
        self::assertStringContainsString('class COMSPEC_ATAK_Module_Relay: COMSPEC_ATAK_Module_DataBase {', $mod);
        self::assertStringContainsString('function = "comspec_atak_native_fnc_moduleRelay";', $mod);
        self::assertStringContainsString('canSetArea = 1;', $mod);
        foreach (['Kind', 'Side', 'Throughput', 'Height', 'Slots', 'Autonomy', 'Uplink', 'StartOn', 'Indestructible', 'Required'] as $a) {
            self::assertStringContainsString("_this setVariable ['" . $a . "',_value,true];", $mod, $a);
        }
        $cfg = $this->read(self::MAIN . '/config.cpp');
        self::assertStringContainsString('class moduleRelay {};', $cfg);
        self::assertStringContainsString('class COMSPEC_ATAK_Network: NO_CATEGORY', $cfg);
        self::assertStringContainsString('class CfgHints {', $cfg);
        $fn = $this->read(self::MAIN . '/functions/network/fn_moduleRelay.sqf');
        self::assertStringContainsString('_logic getVariable ["objectArea", []]', $fn);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_AtakRelays", _list, true];', $fn);
        self::assertStringContainsString('comspec_atak_native_fnc_zeusForm', $fn);
        self::assertStringContainsString('Tutoriel', $this->read(self::MAIN . '/stringtable.xml'));
    }

    public function testRelayNetworkRules(): void
    {
        $net = $this->read(self::MAIN . '/functions/network/fn_relayNet.sqf');
        foreach (['"best"', '"power"', '"info"', '"sweep"'] as $act) {
            self::assertStringContainsString('case ' . $act . ':', $net, $act);
        }
        foreach (['COMSPEC_AtakRelayHeight', 'COMSPEC_AtakRelayRoot', 'COMSPEC_AtakRelaySlots', 'COMSPEC_AtakRelayBatt', 'COMSPEC_AtakRelayOn', 'COMSPEC_AtakRelaySide', '"sans liaison"'] as $k) {
            self::assertStringContainsString($k, $net, $k);
        }
        self::assertStringContainsString('["sweep"] call comspec_atak_native_fnc_relayNet;', $this->read(self::MAIN . '/XEH_postInitServer.sqf'));
        $lq = $this->read(self::MAIN . '/functions/core/fn_linkQuality.sqf');
        self::assertStringContainsString('COMSPEC_ATAK_RelaysRequired', $lq);
        self::assertStringNotContainsString('getNearestAtakRelay', $lq);
        $ace = $this->read(self::MAIN . '/functions/shell/fn_aceMenu.sqf');
        foreach (['COMSPEC_ATAK_RelayInfo', 'COMSPEC_ATAK_RelayOff', 'COMSPEC_ATAK_RelayOn', 'COMSPEC_ATAK_RelayBatt'] as $id) {
            self::assertStringContainsString('"' . $id . '"', $ace, $id);
        }
    }
}
