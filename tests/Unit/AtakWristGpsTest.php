<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** GPS de poignet (fn_wristGps) : objets, touches, autonomie vis-à-vis du téléphone, aucune marque. */
final class AtakWristGpsTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testObjetsEtFonctionDeclares(): void
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        self::assertStringContainsString('class COMSPEC_ATAK_WristGps: ItemGPS', $cfg);
        self::assertStringContainsString('class COMSPEC_ATAK_WristGpsItem: ItemCore', $cfg);
        self::assertStringContainsString('class Item_COMSPEC_ATAK_WristGps: Item_Base_F', $cfg);
        self::assertStringContainsString('"Item_COMSPEC_ATAK_WristGps","Item_COMSPEC_ATAK_WristGpsItem"', $cfg);
        self::assertStringContainsString('class wristGps {};', $cfg);
        self::assertStringContainsString('class COMSPEC_RscTitleWristGps', self::read(self::MAIN . 'ui/displays.hpp'));
        self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . 'data/item_wristgps.paa');
    }

    public function testBoutonsDeLAppareilSurDesTouches(): void
    {
        $post = self::read(self::MAIN . 'XEH_postInitClient.sqf');
        foreach (['toggle', 'big', 'page', 'enter', 'up', 'down', 'goto', 'mark'] as $action) {
            self::assertStringContainsString(sprintf('{ ["%s"] call comspec_atak_native_fnc_wristGps }', $action), $post);
        }
    }

    public function testAutonomeSansTelephoneEtSansMarque(): void
    {
        $src = self::read(self::MAIN . 'functions/garmin/fn_wristGps.sqf');
        self::assertStringNotContainsString('fnc_gpsPos', $src, 'fn_gpsPos suit l\'état du téléphone');
        self::assertStringNotContainsString('fnc_gpsFix', $src);
        self::assertStringNotContainsString('hasDevice', $src);
        // Le téléphone ne sert qu'à lire les routes de l'ATAK.
        self::assertSame(1, substr_count($src, 'comspec_atak_native_fnc_canUse'));

        $strings = self::read(self::MAIN . 'stringtable.xml');
        self::assertStringContainsString('<French>GPS de poignet</French>', $strings);
        self::assertStringNotContainsString('Garmin', strstr($src, 'params [["_act"') ?: '');
    }

    public function testTraductionsDesPages(): void
    {
        $en = self::read(self::MAIN . 'i18n/en.txt');
        foreach (['BOUSSOLE', 'AUCUN POINT ACTIF', 'ATAK NON RELIÉ', 'GPS de poignet : MARK'] as $fr) {
            self::assertStringContainsString('["' . $fr . '", ', $en);
        }
    }
}
