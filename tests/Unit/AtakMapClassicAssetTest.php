<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : fond de carte CLASSIQUE = la carte d'origine d'Arma 3 (contrôle carte du jeu, aucune image dessous),
 * et liste des fonds cohérente entre le code, les traductions et le guide. Rendu à juger en jeu.
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

    public function testClassicIsTheEngineMapWithNoPictureUnderIt(): void
    {
        $controls = $this->read('ui/controls.hpp');
        self::assertSame(1, preg_match('/class COMSPEC_RscMapAtakClassic: RscMapControl \{([^}]*)\};/', $controls, $m));
        $body = $m[1];
        // Couleurs et texture satellite du jeu : aucune n'est redéfinie (seuls les noms de lieux, redessinés par l'ATAK).
        preg_match_all('/\b(color\w+)\[\]/', $body, $c);
        self::assertSame(['colorNames'], $c[1]);
        foreach (['maxSatelliteAlpha', 'alphaFade', 'showCountourInterval'] as $attr) {
            self::assertStringNotContainsString($attr, $body);
        }
        self::assertStringContainsString('showMarkers=0;', $body);

        $disp = $this->read('ui/displays.hpp');
        self::assertStringContainsString('class MapClassic: COMSPEC_RscMapAtakClassic { idc=COMSPEC_ATAK_IDC_MAP_CLASSIC;', $disp);
        self::assertStringContainsString('#define COMSPEC_ATAK_IDC_MAP_CLASSIC 88532', $this->read('ui/ui_ids.hpp'));
        self::assertStringContainsString('case "CLASSIC": { 88532 };', $this->read('functions/map/fn_mapCtrl.sqf'));

        // Ni photo, ni voile : le dessin ne pose rien sous les symboles en CLASSIQUE.
        $draw = $this->read('functions/map/fn_mapOnDraw.sqf');
        self::assertSame(1, preg_match('/if \(_bgStyle in \[([^\]]*)\]\) then \{ \[_map, _bgStyle\] call comspec_atak_native_fnc_photoMap; \};/', $draw, $p));
        self::assertStringNotContainsString('"CLASSIC"', $p[1]);
        self::assertStringNotContainsString('case "CLASSIC"', $draw);
        self::assertStringNotContainsString('"CLASSIC"', $this->read('functions/map/fn_photoMap.sqf'));
    }

    public function testStyleSelectionAndFallbacks(): void
    {
        $style = $this->read('functions/map/fn_mapStyle.sqf');
        self::assertStringContainsString('if (_v isEqualTo "SAT") then { _v = "CLASSIC"; };', $style);
        self::assertStringContainsString('{(["info"] call comspec_atak_native_fnc_photoMap) isEqualTo []}) then { _v = "CLASSIC"; };', $style);
        // Défaut des nouveaux joueurs : TOPO (aucune image), inchangé.
        self::assertStringContainsString('_v = ["TOPO", "NIGHT"] select', $style);
        $keys = array_column($this->styles(), 1);
        self::assertSame(['TOPO', 'LIGHT', 'DARK', 'NIGHT', 'SLATE', 'CLASSIC', 'HYBRID', 'PHOTO'], $keys);
        foreach ($keys as $k) {
            self::assertStringContainsString('"' . $k . '"', (string) preg_replace('/.*if !\(_v in (\[[^\]]*\]).*/s', '$1', $style), $k);
        }
        // SATELLITE n'a un bouton qu'avec la photo de la carte ; sinon une ligne dit pourquoi.
        self::assertStringContainsString('"SATELLITE : la vraie photo de la carte (mod COMSPEC ATAK Photo)", (["info"] call comspec_atak_native_fnc_photoMap) isNotEqualTo []]', $style);
        $page = $this->read('functions/pages/fn_pageMap.sqf');
        self::assertStringContainsString('private _bgAll = ["LIST"] call comspec_atak_native_fnc_mapStyle;', $page);
        self::assertStringContainsString('SATELLITE : nécessite le mod COMSPEC ATAK Photo', $page);
        self::assertStringNotContainsString('["CLASSIQUE", "SAT"]', $page);
        $settings = $this->read('functions/pages/fn_pageSettings.sqf');
        self::assertStringContainsString('(["LIST"] call comspec_atak_native_fnc_mapStyle) select [4, 4]) select { _x select 3 }', $settings);
        self::assertStringNotContainsString('["CLASSIQUE", "SAT"]', $settings);
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
        self::assertStringContainsString("[b]CLASSIQUE[/b] : la carte d'origine d'Arma 3", $guide);
        self::assertStringContainsString('[b]CLASSIC[/b]: the original Arma 3 map', $guide);
        self::assertStringContainsString('["SATELLITE : nécessite le mod COMSPEC ATAK Photo", "SATELLITE: requires the COMSPEC ATAK Photo mod"]', $en);
    }
}
