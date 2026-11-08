<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : tracé de routes et de zones. Vérifications statiques (le rendu et le réseau se jugent en jeu).
 */
final class AtakZonesRoutesTraceTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::MAIN . '/' . $path);
    }

    private function code(string $path): string
    {
        $t = (string) preg_replace('~/\*.*?\*/~s', '', $this->read($path));

        return (string) preg_replace('~^\s*//.*$~m', '', $t);
    }

    /** SQF ne distingue pas la casse : ces deux noms sont une seule variable (RPT : fn_zoneSnap ligne 24). */
    public function testSnapDoesNotShadowItsCellSizeByCase(): void
    {
        $snap = $this->code('functions/zones/fn_zoneSnap.sqf');
        self::assertStringNotContainsString('private _C =', $snap);
        self::assertStringContainsString('(_i + 0.5) * _cellM', $snap);
        self::assertStringContainsString('(_p select 0) isEqualType 0', $snap);
        self::assertSame(0, preg_match('/\b_C\b/', $snap));
    }

    public function testThemeFillOpacityIsNotOverwrittenByCase(): void
    {
        $zone = $this->code('functions/zones/fn_zoneAction.sqf');
        self::assertStringNotContainsString('private _fA =', $zone);
        self::assertStringContainsString('[_fa, _fillSet] select (_fillSet >= 0)', $zone);
        self::assertSame(0, preg_match('/\b_OWN\b/', $zone));
    }

    /** RPT : keys sur une valeur simple, SQF évalue les deux branches d'un [a, b] select : empaquetage, profil et partage perdus. */
    public function testPlanPackDoesNotEvaluateKeysOnPlainValues(): void
    {
        $zone = $this->code('functions/zones/fn_zoneAction.sqf');
        self::assertStringNotContainsString('[_w, (keys _w) apply', $zone);
        self::assertStringContainsString('if (_w isEqualType createHashMap) then { [_x, (keys _w) apply { [_x, _w get _x] }] } else { [_x, _w] }', $zone);
    }

    public function testNewPlanIsNotNamedObjectiveByDefault(): void
    {
        $zone = $this->code('functions/zones/fn_zoneAction.sqf');
        self::assertStringNotContainsString('_name = "OBJ " + _code', $zone);
        self::assertStringContainsString('["name", [_name, 24] call _clean]', $zone);
        self::assertStringContainsString('_arg params [["_name", ""], ["_pre", "PLAN"]];', $zone);
        self::assertStringContainsString('["new", ["", "TRAITS"]]', $zone);
        self::assertStringContainsString('case "pruneAuto":', $zone);
        self::assertStringContainsString('case "cleanup":', $zone);
    }

    public function testPlanNameIsNeverAnOrphanLabel(): void
    {
        $draw = $this->code('functions/zones/fn_zoneDraw.sqf');
        self::assertStringContainsString('{_isEdit || {(count (_b get "zones")) > 0}}', $draw);
    }

    public function testEmptyPlanIsNotSharedAndNothingLeavesDuringComputation(): void
    {
        $zone = $this->code('functions/zones/fn_zoneAction.sqf');
        self::assertStringContainsString('if !([_b] call _hasContent) exitWith', $zone);
        self::assertStringContainsString('{(_b getOrDefault ["busy", ""]) isNotEqualTo ""}) exitWith {};', $zone);
    }

    public function testDeletedPlanDoesNotComeBack(): void
    {
        $zone = $this->code('functions/zones/fn_zoneAction.sqf');
        self::assertStringContainsString('COMSPEC_ATAK_ZnGone', $zone);
        self::assertStringContainsString('(_b get "rev") <= _goneRev', $zone);
        self::assertStringContainsString('(_b get "rev") < (_old getOrDefault ["rev", 0])', $zone);
    }

    public function testNativeMarkersOfTracesAreNotRedrawnOnThePhone(): void
    {
        self::assertStringContainsString('77000%2', $this->code('functions/map/fn_markerOwned.sqf'));
        foreach (['fn_mapGameMarkers', 'fn_mapMarkerText', 'fn_markerAt', 'fn_mapOnDraw'] as $f) {
            self::assertStringContainsString('"/77000"', $this->code('functions/map/' . $f . '.sqf'), $f);
        }
        foreach (['functions/route/fn_routeAction.sqf', 'functions/zones/fn_zoneAction.sqf'] as $f) {
            $c = $this->code($f);
            self::assertStringNotContainsString('9000 + _index', $c, $f);
            self::assertStringNotContainsString('deleteMarker _x', $c, $f);
        }
        self::assertStringContainsString('class markerOwned {};', $this->read('config.cpp'));
        self::assertStringContainsString('["gone", _m] call comspec_atak_native_fnc_markerOwned', $this->read('XEH_postInitClient.sqf'));
    }

    public function testPrivateTracesStayLocal(): void
    {
        $own = $this->code('functions/map/fn_markerOwned.sqf');
        self::assertStringContainsString('createMarkerLocal [format ["comspec_shape_prv_', $own);
        self::assertStringContainsString('setMarkerColorLocal', $own);
        self::assertStringContainsString('if (_sc > 0) then { { [_x] call comspec_atak_native_fnc_markerWeb; } forEach _mk; };', $this->code('functions/route/fn_routeAction.sqf'));
    }

    public function testWebDeletionsReachTheGame(): void
    {
        self::assertStringContainsString('game_del', $this->code('functions/map/fn_markerWebSweep.sqf'));
        self::assertStringContainsString('"game_del") then { continue };', $this->code('functions/network/fn_pollMarkers.sqf'));
        $api = (string) file_get_contents(__DIR__ . '/../../app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString("'source' => 'game_del'", $api);
        $repo = (string) file_get_contents(__DIR__ . '/../../app/Repositories/AtakDataRepository.php');
        self::assertStringContainsString('function recentlySuppressedGameMarkers', $repo);
        self::assertStringContainsString('tenant_id = ? AND map_id = ?', $repo);
    }

    public function testShapesKeepOldProfilesAndHaveTextures(): void
    {
        $prefs = $this->code('functions/map/fn_mapPrefs.sqf');
        self::assertStringContainsString('getVariable ["COMSPEC_ATAK_SelfShape", "ARROW"]', $prefs);
        self::assertStringContainsString('if !(_shape isEqualType "") then { _shape = "ARROW"; };', $prefs);
        self::assertStringContainsString('getVariable ["COMSPEC_ATAK_AllyShape", "AUTO"]', $prefs);
        foreach (['map_shape_tri', 'map_shape_diamond', 'map_shape_square', 'map_shape_cross', 'map_preview_bg'] as $tex) {
            self::assertFileExists(self::MAIN . '/data/' . $tex . '.paa');
        }
        self::assertStringContainsString('case "shapes":', $this->code('functions/ui/fn_formRender.sqf'));
        self::assertStringContainsString('class mapShape {};', $this->read('config.cpp'));
    }

    /** Aucune nouvelle collision de casse dans les fichiers du tracé qui partagent une portée. */
    public function testZoneFilesHaveNoSameScopeCaseCollisionOnKnownNames(): void
    {
        $route = $this->code('functions/route/fn_routeAction.sqf');
        preg_match_all('/\b_[A-Za-z][A-Za-z0-9_]*\b/', $route, $m);
        $byLower = [];
        foreach (array_unique($m[0]) as $v) {
            $byLower[strtolower($v)][] = $v;
        }
        $dups = array_filter($byLower, static fn (array $l): bool => \count($l) > 1);
        unset($dups['_x'], $dups['_y'], $dups['_foreachindex']);
        self::assertSame([], $dups);
    }
}
