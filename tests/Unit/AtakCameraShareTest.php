<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : partage des caméras (fixes et caméra du téléphone) par NFC, Bluetooth ou relais, à un ATAK ou à TOUS,
 * depuis l'app Vidéo live (fn_camShare), avec les conditions de chaque méthode affichées et le Manuel de terrain.
 */
final class AtakCameraShareTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testFunctionAndEventAreRegistered(): void
    {
        self::assertStringContainsString('class camShare {};', $this->read(self::MAIN . '/config.cpp'));
        self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . '/functions/camera/fn_camShare.sqf');
        self::assertStringContainsString('["comspec_atak_native_camShare", { ["recv", _this] call comspec_atak_native_fnc_camShare; }] call CBA_fnc_addEventHandler;', $this->read(self::MAIN . '/XEH_postInitClient.sqf'));
    }

    public function testThreeMethodsWithExplicitConditions(): void
    {
        $fn = $this->read(self::MAIN . '/functions/camera/fn_camShare.sqf');
        self::assertStringContainsString('["NFC", "NFC", format ["Téléphones dos à dos', $fn);
        self::assertStringContainsString('Aucun réseau nécessaire : marche en coupure totale', $fn);
        self::assertStringContainsString('["BT", "BLUETOOTH", format ["Portée réaliste de %1 m', $fn);
        self::assertStringContainsString('["RELAY", "RELAIS", "Par le réseau', $fn);
        // TOUS par relais : accès « ALL » pour tout le camp ; NFC / Bluetooth : chaque ATAK à portée.
        self::assertStringContainsString('private _all = _to isEqualTo "ALL" && {_m isEqualTo "RELAY"};', $fn);
    }

    public function testSettingsAndListsUseAccess(): void
    {
        $pre = $this->read(self::MAIN . '/XEH_preInit.sqf');
        self::assertMatchesRegularExpression('/\["comspec_atak_native_camera_share", "LIST",.*?\[\[0, 1\], \["Partage obligatoire", "Tout le camp voit tout"\], 0\], 1\]/s', $pre);
        self::assertMatchesRegularExpression('/\["comspec_atak_native_bt_range", "SLIDER",.*?\[5, 100, 15, 0\], 1\]/s', $pre);
        $cam = $this->read(self::MAIN . '/functions/camera/fn_camAction.sqf');
        self::assertStringContainsString('case "list": { (["listAll"] call _fn) select { ["can", _x] call comspec_atak_native_fnc_camShare } };', $cam);
        $page = $this->read(self::MAIN . '/functions/pages/fn_pageLivecam.sqf');
        self::assertStringContainsString('{["can", _x] call comspec_atak_native_fnc_camShare}', $page);
        self::assertStringContainsString('"PARTAGER", "FERMER LE PARTAGE"', $page);
    }

    public function testFieldManualPageIsTranslated(): void
    {
        $cfg = $this->read(self::MAIN . '/config.cpp');
        self::assertStringContainsString('class CameraShare {', $cfg);
        $st = $this->read(self::MAIN . '/stringtable.xml');
        foreach (['STR_COMSPEC_ATAK_CFG_BF7BE30836', 'STR_COMSPEC_ATAK_CFG_146C6595D4', 'STR_COMSPEC_ATAK_CFG_C9AF05C42B'] as $k) {
            self::assertStringContainsString('$' . $k, $cfg);
            self::assertStringContainsString('<Key ID="' . $k . '">', $st);
        }
    }
}
