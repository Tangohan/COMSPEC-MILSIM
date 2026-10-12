<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif 1.45.0 : relais sur la carte, couverture réseau, triangulation par les relais, champs de vision
 * (point, équipier, drone, secteur, bâtiments) et calques de la carte qui disent ce qu'ils montrent.
 */
final class AtakRelayCoverageViewTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testRelayNetIsRegisteredWithItsActions(): void
    {
        self::assertStringContainsString('class relayNet {};', $this->read(self::MAIN . '/config.cpp'));
        $net = $this->read(self::MAIN . '/functions/network/fn_relayNet.sqf');
        foreach (['"list"', '"link"', '"coverage"', '"at"', '"locate"', '"trilatClear"'] as $act) {
            self::assertStringContainsString('case ' . $act . ':', $net, $act);
        }
        self::assertStringContainsString('COMSPEC_AtakRelays', $net);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_Coverage"', $net);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_Trilat"', $net);
    }

    public function testMapShowsRelaysCoverageAndFixes(): void
    {
        $draw = $this->read(self::MAIN . '/functions/map/fn_meshDraw.sqf');
        self::assertStringContainsString('["list"] call comspec_atak_native_fnc_relayNet', $draw);
        self::assertStringContainsString('COMSPEC_ATAK_Coverage', $draw);
        self::assertStringContainsString('COMSPEC_ATAK_Trilat', $draw);
        $ctx = $this->read(self::MAIN . '/functions/map/fn_mapCtxMenu.sqf');
        self::assertStringContainsString('["locate", _c getOrDefault ["unit", ""]] call comspec_atak_native_fnc_relayNet;', $ctx);
        self::assertStringContainsString('["compute", [_pos select 0, _pos select 1]] call comspec_atak_native_fnc_reliefAction;', $ctx);
        self::assertStringContainsString('["at", _p] call comspec_atak_native_fnc_relayNet', $this->read(self::MAIN . '/functions/map/fn_mapOverlayUpdate.sqf'));
        self::assertStringContainsString('["COUVERTURE", "COV"] call _tabBtn', $this->read(self::MAIN . '/functions/pages/fn_pageWaveRelay.sqf'));
    }

    public function testFieldOfViewOptions(): void
    {
        $act = $this->read(self::MAIN . '/functions/pages/fn_reliefAction.sqf');
        foreach (['rlHeight', 'rlSector', 'rlObjects', 'lineIntersectsSurfaces'] as $k) {
            self::assertStringContainsString($k, $act, $k);
        }
        self::assertStringContainsString('DEPUIS LE DRONE', $this->read(self::MAIN . '/functions/pages/fn_pageRelief.sqf'));
        self::assertStringContainsString('_sector', $this->read(self::MAIN . '/functions/map/fn_reliefDraw.sqf'));
    }

    public function testLayerToggleExplainsEmptyLayers(): void
    {
        $map = $this->read(self::MAIN . '/functions/pages/fn_pageMap.sqf');
        self::assertStringContainsString('Zones Athena : aucune zone reçue pour cette carte', $map);
        self::assertStringContainsString('["coverage"] call comspec_atak_native_fnc_relayNet;', $map);
        self::assertStringContainsString('["compute"] call comspec_atak_native_fnc_reliefAction;', $map);
    }

    public function testVersionAndEnglish(): void
    {
        self::assertStringContainsString('#define VERSION_STR "1.45.0"', $this->read(self::MAIN . '/script_component.hpp'));
        $en = $this->read(self::MAIN . '/i18n/en.txt');
        self::assertStringContainsString('"Localiser par relais", "Locate by relays"', $en);
        self::assertStringContainsString('"Champ de vision ici", "Field of view here"', $en);
    }
}
