<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** ATAK 4.15.2 : le flash de l'appareil photo ne brûle plus les photos, et sa puissance se règle par joueur. */
final class AtakPhotoFlashAssetTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    public function testBothCamerasUseTheSoftFlash(): void
    {
        foreach (['functions/pages/fn_photoMode.sqf', 'functions/pages/fn_photoCam.sqf'] as $file) {
            $s = (string) file_get_contents(self::MAIN . $file);
            // Plus de lumière ambiante, intensité plafonnée de près (terme constant), puissance réglable.
            self::assertStringContainsString('_light setLightAmbient [0, 0, 0];', $s, $file);
            self::assertStringContainsString('_light setLightBrightness (([0.22, 0.5] select (sunOrMoon < 0.5)) * _power);', $s, $file);
            self::assertStringContainsString('_light setLightAttenuation [0.4, 1, 0, 0.5, 7, 11];', $s, $file);
            self::assertStringContainsString('getVariable ["comspec_atak_native_photo_flash", 1]', $s, $file);
            self::assertStringNotContainsString('[0.9, 2.2]', $s, $file);
        }
    }

    public function testFlashPowerIsAPlayerSetting(): void
    {
        $pre = (string) file_get_contents(self::MAIN . 'XEH_preInit.sqf');
        self::assertStringContainsString('["comspec_atak_native_photo_flash", "SLIDER",', $pre);
        self::assertStringContainsString('[0.25, 2, 1, 0, true], 0] call comspec_atak_native_fnc_addSetting;', $pre);
        self::assertStringContainsString('["Puissance du flash", "Flash power"]', (string) file_get_contents(self::MAIN . 'i18n/en.txt'));
    }
}
