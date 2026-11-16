<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : en petit format (mini ou porté), la grille du joueur s'affiche sous son indicatif sur la carte
 * (seconde ligne de la plaque de fn_mapOnDraw), réglable, refaite seulement quand elle change.
 */
final class AtakMiniGridUnderCallsignAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const KEY = 'comspec_atak_native_mini_grid';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testTheSettingIsAClientCheckboxOnByDefault(): void
    {
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        self::assertMatchesRegularExpression('/\["' . self::KEY . '", "CHECKBOX",\s*\["Afficher ma grille sous l\'indicatif \(mode petit\)", "[^"]+"\],\s*\["COMSPEC", "ATAK · Carte"\], true, 0\] call comspec_atak_native_fnc_addSetting;/', $pre);
        self::assertStringContainsString('"' . self::KEY . '"', self::read(self::MAIN . 'functions/pages/fn_pageSettings.sqf'));
    }

    public function testTheGridComesFromTheExistingFunctionAndIsOnlyRefreshedOnChange(): void
    {
        $draw = self::read(self::MAIN . 'functions/map/fn_mapOnDraw.sqf');
        self::assertStringContainsString('(missionNamespace getVariable ["' . self::KEY . '", true]) isNotEqualTo false', $draw);
        self::assertStringContainsString('(([] call comspec_atak_native_fnc_layoutGet) get "mini")', $draw);
        // Même fonction que le bandeau de la carte (format choisi par le joueur).
        self::assertStringContainsString('["grid", [_selfGps, ""]] call comspec_atak_native_fnc_mapPrefs', $draw);
        // Texte gardé tant que la position, le format et l'état du GPS ne changent pas.
        self::assertStringContainsString('(_mgC select 0) isEqualTo _mgK', $draw);
        self::assertStringContainsString('COMSPEC_ATAK_MiniGridCache', $draw);
        // Position inconnue : jamais une ancienne valeur.
        self::assertStringContainsString('"---- ----"', $draw);
        self::assertStringContainsString('["NOFIX", "ACQ", "OFF"]', $draw);
    }

    public function testTheSpaceIsReservedInTheNameplateNotOverlaid(): void
    {
        $draw = self::read(self::MAIN . 'functions/map/fn_mapOnDraw.sqf');
        self::assertStringContainsString('_pLines pushBack [_mgTxt, "EtelkaMonospacePro", _lfs get "u2"', $draw);
        self::assertStringContainsString('["plate", [_pLines, _ubg,', $draw);
        self::assertStringContainsString('["cut", _mgTxt, 18]', $draw);
        // Seul mon indicatif (priorité 0) porte la seconde ligne.
        self::assertStringContainsString('_miniGrid && {_up < 1}', $draw);
    }

    public function testNoOnScreenNotificationIsAdded(): void
    {
        $draw = self::read(self::MAIN . 'functions/map/fn_mapOnDraw.sqf');
        self::assertDoesNotMatchRegularExpression('/\b(systemChat|hint|hintSilent|cutText|titleText)\b/', $draw);
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        $start = strpos($pre, '["' . self::KEY . '"');
        self::assertNotFalse($start);
        $block = substr($pre, $start, (int) strpos($pre, 'fnc_addSetting;', $start) - $start);
        self::assertDoesNotMatchRegularExpression('/\b(systemChat|hint|hintSilent|cutText|titleText)\b/', $block);
    }

    public function testEnglishEntriesExist(): void
    {
        $en = self::read(self::MAIN . 'i18n/en.txt');
        self::assertStringContainsString('["Afficher ma grille sous l\'indicatif (mode petit)", "Show my grid under the callsign (small mode)"],', $en);
        self::assertStringContainsString('["Grille sous l\'indicatif (mode petit)", "Grid under the callsign (small mode)"],', $en);
        self::assertStringContainsString('Your grid position under your callsign on the map, in small format', $en);
        self::assertMatchesRegularExpression('/\["[^\n]*"\]\n\]\s*$/', $en, 'aucune virgule après la dernière entrée');
    }
}
