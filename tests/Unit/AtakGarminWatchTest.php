<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : montre Garmin au poignet ouverte avec la touche « Montre » du jeu (O) à la place de la montre d'Arma,
 * vraie interface (cadran, cardio, altimètre, mini-carte, boussole, téléphone, journée).
 */
final class AtakGarminWatchTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::MAIN . '/' . $path);
    }

    public function testWatchKeyOpensTheGarminInsteadOfTheGameWatch(): void
    {
        self::assertStringContainsString('class garminTick {}; class pageGarmin {}; class watch {};', $this->read('config.cpp'));
        $post = $this->read('XEH_postInitClient.sqf');
        self::assertStringContainsString('(actionKeys "Watch")', $post);
        self::assertStringContainsString('["down"] call comspec_atak_native_fnc_watch', $post);
        self::assertStringContainsString('["up"] call comspec_atak_native_fnc_watch', $post);
        self::assertStringContainsString('"GarminPrev"', $post);
        self::assertStringContainsString('"GarminClose"', $post);
        self::assertStringContainsString('class COMSPEC_RscTitleGarmin', $this->read('ui/displays.hpp'));
        $pre = $this->read('XEH_preInit.sqf');
        self::assertStringContainsString('"comspec_atak_native_watch_size", "LIST"', $pre);
        self::assertStringContainsString('"comspec_atak_native_watch_side", "LIST"', $pre);
    }

    public function testWatchNeedsTheRealItemAndHasEveryScreen(): void
    {
        $w = $this->read('functions/garmin/fn_watch.sqf');
        self::assertStringContainsString('"COMSPEC_ATAK_GarminWatch", configFile >> "CfgWeapons"', $w);
        self::assertStringContainsString('"COMSPEC_ATAK_GarminWatchItem" in (items player)', $w);
        self::assertStringContainsString('["FACE", "HR", "ALT", "MAP", "COMPASS", "PHONE", "DAY"]', $w);
        self::assertStringContainsString('COMSPEC_RscMapAtak', $w);
        self::assertStringContainsString('comspec_atak_native_fnc_gpsPos', $w);
        self::assertStringContainsString('notifHistory', $w);
        self::assertStringContainsString('"COMSPEC_ATAK_Bt"', $w);
        foreach (['gw_frame', 'gw_disc', 'gw_alt', 'gw_bt', 'gw_phone', 'gw_bolt', 'gw_arrow', 'gw_pin'] as $t) {
            self::assertFileExists(self::MAIN . '/data/' . $t . '.paa', $t);
        }
    }

    public function testGarminTickKeepsLiveCurvesAscentAndWatchBattery(): void
    {
        $t = $this->read('functions/garmin/fn_garminTick.sqf');
        self::assertStringContainsString('"hrl"', $t);
        self::assertStringContainsString('"altl"', $t);
        self::assertStringContainsString('["up_m", _altNow - _a0] call _add', $t);
        self::assertStringContainsString('["down_m", _a0 - _altNow] call _add', $t);
        self::assertStringContainsString('"wbat"', $t);
        // Courbes en direct : pas gardées dans le profil du joueur.
        self::assertStringContainsString('"worn", "hrl", "altl", "alt0", "live"]', $t);
    }

    public function testWatchTextsHaveEnglishEntries(): void
    {
        $en = $this->read('i18n/en.txt');
        foreach (['FRÉQUENCE CARDIAQUE', 'ALTIMÈTRE', 'MA JOURNÉE', 'ATAK connecté', 'Bluetooth du téléphone éteint', 'Taille de la montre Garmin (touche O)'] as $fr) {
            self::assertStringContainsString('"' . $fr . '"', $en, $fr);
        }
    }
}
