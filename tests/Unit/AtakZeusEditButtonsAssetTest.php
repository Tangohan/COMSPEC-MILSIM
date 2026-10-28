<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

final class AtakZeusEditButtonsAssetTest extends TestCase
{
    public function testEditPanelButtonsCloseDisplayThenOpenZen(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons';
        $inject = (string) file_get_contents($root . '/connect/functions/fn_zeusAttributesInject.sqf');
        $register = (string) file_get_contents($root . '/connect/functions/fn_registerZeusAttributeButtons.sqf');
        $ally = (string) file_get_contents($root . '/connect/functions/fn_allyTrackConfigure.sqf');
        $photo = (string) file_get_contents($root . '/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf');
        $cfg = (string) file_get_contents($root . '/connect/config.cpp');

        self::assertStringContainsString('closeDisplay 2', $inject);
        self::assertStringContainsString('[_e, 0.32]', $inject);
        self::assertStringContainsString('Chef de section seulement', $ally);
        self::assertStringContainsString('Toute la section', $ally);
        // JPEG BCE : reuse the file as is (skip Arma shot, no POV alignment, one deferred retry).
        self::assertStringContainsString('true, false, true] call comspec_overwatch_connect_fnc_captureReconImage', $photo);
        self::assertStringContainsString('comspec_sse_face', $photo);
        $ath = (string) file_get_contents($root . '/atak_athena/config.cpp');
        ModVersion::assertAtLeast('1.0.80', ModVersion::fromConfigCpp($ath));
        ModVersion::assertAtLeast('1.5.16', ModVersion::fromConfigCpp($cfg));
        self::assertStringContainsString('"BCE_cTab_ATAK"', $ath);
        self::assertDoesNotMatchRegularExpression('/"BCE_cTab"/', $ath);
        self::assertStringContainsString('isKindOf "CAManBase"', $inject);
        self::assertStringContainsString('displayCtrl 30001', $inject);
        self::assertStringContainsString('editable object', $inject);
        self::assertStringContainsString('- _h - _pad', $inject);
        self::assertStringNotContainsString('_h * 1.28', $inject);
        self::assertStringNotContainsString('0.86 * safezoneH', $inject);
        self::assertStringNotContainsString('"RscDisplayAttributes"', $register);
        self::assertStringNotContainsString('allDisplays', $register);
        self::assertStringNotContainsString('findDisplay 315', $register);
        // CBA_fnc_addDisplayHandler targets the mission display ([type, code]): with a display class it threw "Unknown enum value".
        self::assertStringNotContainsString('] call CBA_fnc_addDisplayHandler', $register);
        self::assertStringContainsString('update editable', $inject);
    }
}
