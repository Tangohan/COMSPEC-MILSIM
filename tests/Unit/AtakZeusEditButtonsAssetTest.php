<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakZeusEditButtonsAssetTest extends TestCase
{
    public function testEditPanelButtonsCloseDisplayThenOpenZen(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons';
        $inject = (string) file_get_contents($root . '/connect/functions/fn_zeusAttributesInject.sqf');
        $ally = (string) file_get_contents($root . '/connect/functions/fn_allyTrackConfigure.sqf');
        $photo = (string) file_get_contents($root . '/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf');
        $cfg = (string) file_get_contents($root . '/connect/config.cpp');

        self::assertStringContainsString('closeDisplay 2', $inject);
        self::assertStringContainsString('[_e, 0.32]', $inject);
        self::assertStringContainsString('Chef de section seulement', $ally);
        self::assertStringContainsString('Toute la section', $ally);
        self::assertStringContainsString('false, false, false] call comspec_overwatch_connect_fnc_captureReconImage', $photo);
        self::assertStringContainsString('comspec_sse_face', $photo);
        $ath = (string) file_get_contents($root . '/atak_athena/config.cpp');
        self::assertStringContainsString('1.0.56', $ath);
        self::assertStringContainsString('1.4.95', $cfg);
    }
}
