<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : fond de carte ARDOISE et style tactique des marqueurs et traits (rendu à juger en jeu).
 */
final class AtakMapSlateThemeTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::MAIN . '/' . $path);
    }

    public function testSlateBackgroundHasItsOwnMapControl(): void
    {
        self::assertStringContainsString('"HYBRID", "SLATE"]) then', $this->read('functions/map/fn_mapStyle.sqf'));
        $ctrl = $this->read('functions/map/fn_mapCtrl.sqf');
        self::assertStringContainsString('case "SLATE": { 88534 };', $ctrl);
        self::assertStringContainsString('[88530, 88532, 88533, 88534] apply', $ctrl);
        self::assertStringContainsString('forEach [88530, 88532, 88533, 88534];', $this->read('functions/ui/fn_layoutApply.sqf'));
        self::assertStringContainsString('#define COMSPEC_ATAK_IDC_MAP_SLATE 88534', $this->read('ui/ui_ids.hpp'));
        $display = $this->read('ui/displays.hpp');
        self::assertStringContainsString('class MapSlate: COMSPEC_RscMapAtakSlate { idc=COMSPEC_ATAK_IDC_MAP_SLATE;', $display);
        $controls = $this->read('ui/controls.hpp');
        foreach (['colorSea[]={0.020,0.047,0.074,1}', 'colorMainRoadsFill[]={0.30,0.61,0.57,1}', 'class Legend: Legend'] as $needle) {
            self::assertStringContainsString($needle, $controls);
        }
        // Le fond ne coûte rien aux images : pas de bâtiments ni de relief recalculés, un voile seulement sans le contrôle.
        self::assertStringContainsString('case "SLATE": { [[0.06, 0.08, 0.095, 0.72], []] select ((ctrlIDC _map) isEqualTo 88534) };', $this->read('functions/map/fn_mapOnDraw.sqf'));
    }

    public function testBackgroundIsOfferedInBothMenus(): void
    {
        self::assertStringContainsString('["ARDOISE", "SLATE"]', $this->read('functions/pages/fn_pageSettings.sqf'));
        self::assertStringContainsString('["ARDOISE", "SLATE"]', $this->read('functions/pages/fn_pageMap.sqf'));
    }

    public function testTacticalStyleIsOnByDefaultOnlyWithSlate(): void
    {
        $col = $this->read('functions/map/fn_mapColors.sqf');
        self::assertStringContainsString('profileNamespace getVariable ["COMSPEC_ATAK_MapDrawStyle", "AUTO"]', $col);
        self::assertStringContainsString('_drawStyle isEqualTo "TACTICAL" || {_drawStyle isNotEqualTo "STANDARD" && {_bg isEqualTo "SLATE"}}', $col);
        // Le style fait partie de la signature : pas de lecture supplémentaire dans les fonctions de dessin.
        self::assertStringContainsString('_bgA, _drawStyle];', $col);
        $settings = $this->read('functions/pages/fn_pageSettings.sqf');
        self::assertStringContainsString('["COMSPEC_ATAK_MapDrawStyle", "AUTO", "Marqueurs et traits", [["AUTO", "AUTO"], ["TACTIQUE", "TACTICAL"], ["STANDARD", "STANDARD"]]', $settings);
        self::assertStringContainsString('"COMSPEC_ATAK_MapStyle", "COMSPEC_ATAK_MapDrawStyle", "COMSPEC_ATAK_LblEni"', $settings);
    }

    public function testRenderUsesEngineMeansAndKeepsClustering(): void
    {
        $markers = $this->read('functions/map/fn_mapGameMarkers.sqf');
        self::assertStringContainsString('private _clustOn = (ctrlMapScale _map) >= 0.12;', $markers);
        self::assertStringContainsString('_map drawRectangle [_pos, _w, _h, _dir, _c, _tacTex];', $markers);
        self::assertStringContainsString('if (_tac && {!_clustOn} && {(count _keyC) > 0})', $markers);
        self::assertStringContainsString('markerbrushes\\bdiagonal_ca.paa', $this->read('functions/map/fn_mapColors.sqf'));
        $draw = $this->read('functions/map/fn_mapOnDraw.sqf');
        self::assertStringContainsString('_map drawIcon [_tacTick, [1, 1, 1, _pa], _pos, _ps * 1.5, _ps * 1.5, _entity get "heading", "", 0];', $draw);
        self::assertStringContainsString('["0", "EtelkaMonospaceProBold", 0.02] call comspec_atak_native_fnc_mapTextW', $draw);
        // Halo sans jointures : le coût reste un rectangle par tronçon, plafonné pour les longs traits.
        self::assertStringContainsString('(count _pts) <= 300', $this->read('functions/map/fn_strokeDraw.sqf'));
        self::assertStringContainsString('_w + 5, _dash, false, false, _mpx, false]', $this->read('functions/route/fn_routeDraw.sqf'));
        foreach (['map_pawn_disc', 'map_pawn_ring', 'map_pawn_tick', 'map_keyring'] as $texture) {
            self::assertFileExists(self::MAIN . '/data/' . $texture . '.paa');
        }
    }

    public function testNewLabelsAreTranslated(): void
    {
        $en = $this->read('i18n/en.txt');
        foreach (['["ARDOISE", "SLATE"]', '["Marqueurs et traits", "Markers and lines"]', '["CAPITALES", "CAPITALS"]'] as $needle) {
            self::assertStringContainsString($needle, $en);
        }
    }

    public function testDefaultColourFollowsTheMarkerTypeOnEveryBackground(): void
    {
        $fn = $this->read('functions/map/fn_markerColor.sqf');
        self::assertStringContainsString('(toLower _cls) isEqualTo "default"', $fn);
        self::assertStringContainsString('class markerColor {};', $this->read('config.cpp'));
        foreach (['fn_mapGameMarkers', 'fn_mapMarkerText', 'fn_mapOnDraw'] as $f) {
            self::assertStringContainsString('call comspec_atak_native_fnc_markerColor', $this->read('functions/map/' . $f . '.sqf'), $f);
        }
        self::assertStringContainsString('["default", [0.82, 0.84, 0.84]]', $this->read('functions/map/fn_mapColors.sqf'));
    }

    public function testOneDisplayedPositionForEverythingAroundMyPoint(): void
    {
        self::assertStringContainsString('(getPosASLVisual (vehicle player)) vectorAdd', $this->read('functions/core/fn_gpsPos.sqf'));
        $draw = $this->read('functions/map/fn_mapOnDraw.sqf');
        self::assertLessThan(
            strpos($draw, 'call comspec_atak_native_fnc_meshDraw'),
            strpos($draw, 'uiNamespace setVariable ["COMSPEC_ATAK_SelfGpsPos", _selfGps];')
        );
        self::assertStringNotContainsString('getPosASLVisual player', $draw);
        $mesh = $this->read('functions/map/fn_meshDraw.sqf');
        self::assertStringContainsString('if (_u in _selfObjs) then { _selfP }', $mesh);
        self::assertStringNotContainsString('getPosASLVisual (vehicle player)', $mesh);
        self::assertStringNotContainsString('getPosATL vehicle player', $this->read('functions/route/fn_routeDraw.sqf'));
    }

    public function testHeldLeftButtonIsALivePointerNotASingleShot(): void
    {
        $draw = $this->read('functions/map/fn_mapOnDraw.sqf');
        self::assertStringContainsString('["pointStart", _map] call comspec_atak_native_fnc_mapPing;', $draw);
        self::assertStringContainsString('private _hCur = getMousePosition;', $draw);
        self::assertStringNotContainsString('getOrDefault ["cursorPos", _hW]', $draw);
        $ping = $this->read('functions/map/fn_mapPing.sqf');
        foreach (['case "pointTick":', 'case "pointStop":', 'case "pointRecv":', '_dt >= 0.25'] as $needle) {
            self::assertStringContainsString($needle, $ping);
        }
        self::assertStringContainsString('["pointStop"] call comspec_atak_native_fnc_mapPing', $this->read('functions/map/fn_mapMouseButtonUp.sqf'));
        self::assertStringContainsString('"comspec_atak_native_mapPoint"', $this->read('XEH_postInitClient.sqf'));
    }

    public function testRouteListCanShareAndDeleteForGood(): void
    {
        $page = $this->read('functions/route/fn_pageRoute.sqf');
        foreach (['case "rowDel":', 'case "rowShare":', 'case "rowSend":', '["PARTAGER", ["rowShare", _rid] call _ui1'] as $needle) {
            self::assertStringContainsString($needle, $page);
        }
        $action = $this->read('functions/route/fn_routeAction.sqf');
        // Retirée partout dès la suppression, pierre tombale même sans réception préalable, repartage en révision supérieure.
        self::assertStringContainsString('_bin set [_arg, [_r, _tok, _sc0]];', $action);
        self::assertStringContainsString('[_arg, _to, _r getOrDefault ["rev", 0]], "Route", 1, 1,', $action);
        self::assertStringContainsString('if !(_pk in _rx) exitWith {};', $action);
        self::assertStringContainsString('if ((_r get "scope") isEqualTo 0) then { _r set ["rev"', $action);
    }

    public function testBearingHasADirectionAndDefaultMarkerNameIsNeutral(): void
    {
        self::assertStringContainsString('[_az, 8] call comspec_atak_native_fnc_cardinal', $this->read('functions/map/fn_mapOnDraw.sqf'));
        $drop = $this->read('functions/map/fn_markerDrop.sqf');
        self::assertStringContainsString('default { (["MK", "REP"] select _isFr) + " {n}" };', $drop);
        self::assertStringNotContainsString('_prefix', $drop);
        $auto = $this->read('functions/map/fn_markerAutoName.sqf');
        self::assertStringContainsString('(_w select 0) in ["REP", "MK"]', $auto);
        // Marqueurs déjà posés : toujours relus dans la langue du téléphone.
        self::assertStringContainsString('private _pfxFr = ["ENI", "AMI", "NEU", "INC"];', $auto);
    }
}
