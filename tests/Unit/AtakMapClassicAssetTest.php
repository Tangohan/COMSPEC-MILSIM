<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : fond de carte CLASSIQUE = la carte d'origine d'Arma 3, celle de la touche M (contrôle carte du jeu sans
 * aucune retouche, le moteur y écrit lui-même noms, grille et marqueurs), premier de la liste et fond par défaut, dans le
 * volet de la carte comme dans les Réglages. Rendu à juger en jeu.
 */
final class AtakMapClassicAssetTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';
    private const ROOT = __DIR__ . '/../..';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::MAIN . '/' . $path);
    }

    /** @return array<int, array{0: string, 1: string, 2: string}> libellé, valeur, description */
    private function styles(): array
    {
        $src = $this->read('functions/map/fn_mapStyle.sqf');
        preg_match_all('/^\s*\["([A-Z]+)", "([A-Z]+)", "([^"]+)", /m', $src, $m, PREG_SET_ORDER);

        return array_map(static fn (array $r): array => [$r[1], $r[2], $r[3]], $m);
    }

    public function testClassicIsTheVanillaMapWithoutAnyOverride(): void
    {
        $controls = $this->read('ui/controls.hpp');
        // Hérite de RscMapControl (base de la carte M, RscDisplayMainMap >> CA_Map) sans un seul attribut redéfini.
        self::assertStringContainsString("\nclass COMSPEC_RscMapAtakClassic: RscMapControl {};", str_replace("\r", '', $controls));

        $disp = $this->read('ui/displays.hpp');
        self::assertSame(1, preg_match('/class MapClassic: COMSPEC_RscMapAtakClassic \{([^\n]*)\};/', $disp, $d));
        // Le contrôle du téléphone n'ajoute que son idc, sa place et ses gestionnaires (aucune couleur ni taille).
        preg_match_all('/(?:^\s*|;\s*)(\w+)(?:\[\])?=/',preg_replace('/"[^"]*"/', '""', $d[1]), $a);
        $attrs = array_values(array_unique($a[1]));
        sort($attrs);
        self::assertSame(['idc', 'onDraw', 'onMouseButtonDblClick', 'onMouseButtonDown', 'onMouseButtonUp', 'onMouseMoving'], $attrs);
        self::assertStringContainsString('#define COMSPEC_ATAK_IDC_MAP_CLASSIC 88532', $this->read('ui/ui_ids.hpp'));
        self::assertStringContainsString('case "CLASSIC": { 88532 };', $this->read('functions/map/fn_mapCtrl.sqf'));

        // Ni photo, ni voile ; noms de lieux, numéros de grille, marqueurs du jeu et leurs noms laissés au moteur.
        $draw = $this->read('functions/map/fn_mapOnDraw.sqf');
        self::assertSame(1, preg_match('/if \(_bgStyle in \[([^\]]*)\]\) then \{[^{}]*?\[_map, _bgStyle\] call comspec_atak_native_fnc_photoMap;/', $draw, $p));
        self::assertStringNotContainsString('"CLASSIC"', $p[1]);
        self::assertStringNotContainsString('case "CLASSIC"', $draw);
        self::assertStringNotContainsString('"CLASSIC"', $this->read('functions/map/fn_photoMap.sqf'));
        self::assertStringContainsString('private _engineTxt = (ctrlIDC _map) isEqualTo 88532;', $draw);
        self::assertStringContainsString('if (!_engineTxt) then { [_map, _mk] call comspec_atak_native_fnc_mapGameMarkers; };', $draw);
        self::assertStringContainsString('[_map, _tsL, _kt, _scale, _tagged, _boxOn, _engineTxt] call comspec_atak_native_fnc_mapMarkerText;', $draw);
        self::assertStringContainsString('if (!_engineTxt && {_state getOrDefault ["interactive", false]}) then { [_map, _ts, _kt, _scale] call comspec_atak_native_fnc_mapPlaceText; };', $draw);
        self::assertStringContainsString('if (!_engineTxt && {_spanX > 1}', $draw);
        $txt = $this->read('functions/map/fn_mapMarkerText.sqf');
        self::assertStringContainsString('["_engineMk", false]];', $txt);
        self::assertStringContainsString('if (_engineMk && {_local}) then { continue };', $txt);
    }

    public function testClassicIsFirstAndTheDefault(): void
    {
        $style = $this->read('functions/map/fn_mapStyle.sqf');
        self::assertStringContainsString('if (_v isEqualTo "SAT") then { _v = "CLASSIC"; };', $style);
        self::assertStringContainsString('{(["info"] call comspec_atak_native_fnc_photoMap) isEqualTo []}) then { _v = "CLASSIC"; };', $style);
        // Défaut des nouveaux joueurs : CLASSIQUE ; choix enregistrés gardés.
        self::assertStringContainsString('_v = ["CLASSIC", "NIGHT"] select', $style);
        self::assertStringContainsString('"HYBRID", "SLATE"]) then { _v = "CLASSIC"; };', $style);
        $keys = array_column($this->styles(), 1);
        self::assertSame(['CLASSIC', 'TOPO', 'LIGHT', 'DARK', 'NIGHT', 'SLATE', 'HYBRID', 'PHOTO'], $keys);
        foreach ($keys as $k) {
            self::assertStringContainsString('"' . $k . '"', (string) preg_replace('/.*if !\(_v in (\[[^\]]*\]).*/s', '$1', $style), $k);
        }
        self::assertSame("CLASSIQUE : la carte d'origine d'Arma 3, celle de la touche M", $this->styles()[0][2]);
        self::assertStringContainsString('"SATELLITE : la vraie photo de la carte (mod COMSPEC ATAK Photo)", (["info"] call comspec_atak_native_fnc_photoMap) isNotEqualTo []]', $style);

        // Volet de la carte (CONFIGURATION) : liste dans l'ordre de fn_mapStyle, deux boutons par rangée, description dessous.
        $page = $this->read('functions/pages/fn_pageMap.sqf');
        self::assertStringContainsString('private _bgAll = ["LIST"] call comspec_atak_native_fnc_mapStyle;', $page);
        self::assertStringContainsString('private _styles = _bgAll select { _x select 3 };', $page);
        self::assertStringContainsString('for "_i" from 0 to ((count _opts) - 1) step 2 do { _rows pushBack ["segment", "", _opts select [_i, 2]]; };', $page);
        self::assertStringContainsString('_rows pushBack ["text", format ["<t size=\'0.8\' color=\'%1\'>%2</t>", _hDim, _cur select 2]];', $page);
        self::assertStringContainsString('SATELLITE : nécessite le mod COMSPEC ATAK Photo', $page);

        // Réglages > Carte : même liste, même ordre, deux par rangée, fond en service relu par fn_mapStyle, description.
        $settings = $this->read('functions/pages/fn_pageSettings.sqf');
        self::assertStringContainsString('private _bgNow = [] call comspec_atak_native_fnc_mapStyle;', $settings);
        self::assertStringContainsString('private _bgList = (["LIST"] call comspec_atak_native_fnc_mapStyle) select { _x select 3 };', $settings);
        self::assertStringContainsString('for "_i" from 0 to ((count _bgList) - 1) step 2 do {', $settings);
        self::assertStringContainsString('(_bgList select [_i, 2]) apply {', $settings);
        self::assertStringContainsString('_v isEqualTo _bgNow]', $settings);
        self::assertStringContainsString('_bgCur select 2]]; };', $settings);
        self::assertStringContainsString('_map insert [(_map findIf { (_x param [1, ""]) isEqualTo "Relief des bâtiments" }) max 0, _bgRows];', $settings);
        self::assertStringNotContainsString('select [4, 4]', $settings);
        self::assertStringNotContainsString('["CLASSIQUE", "SAT"]', $settings . $page);
    }

    public function testStylesAreTranslatedAndInTheGuide(): void
    {
        $en = $this->read('i18n/en.txt');
        $xx = (string) file_get_contents(self::ROOT . '/mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        $guide = (string) file_get_contents(self::ROOT . '/docs/steam/discussions/01_carte.txt');
        foreach ($this->styles() as [$label, , $desc]) {
            self::assertStringStartsWith($label . ' : ', $desc);
            self::assertStringContainsString('["' . $desc . '", "', $en, $desc);
            self::assertStringContainsString('["' . $desc . '", "", "', $xx, $desc);
            self::assertStringContainsString('[b]' . $label . '[/b]', $guide, $label);
            if (!in_array($label, ['TOPO', 'SATELLITE'], true)) {
                self::assertStringContainsString('["' . $label . '", "', $en, $label);
            }
        }
        self::assertStringContainsString('["CLASSIQUE : la carte d\'origine d\'Arma 3, celle de la touche M", "CLASSIC: the original Arma 3 map, the one on the M key"]', $en);
        self::assertStringContainsString("[b]CLASSIQUE[/b] : la carte d'origine d'Arma 3, celle de la touche M, par défaut", $guide);
        self::assertStringContainsString('[b]CLASSIC[/b]: the original Arma 3 map, the one on the M key, default', $guide);
        self::assertStringContainsString('["SATELLITE : nécessite le mod COMSPEC ATAK Photo", "SATELLITE: requires the COMSPEC ATAK Photo mod"]', $en);
        self::assertStringContainsString('["Fond de carte", "Map background"]', $en);
    }
}
