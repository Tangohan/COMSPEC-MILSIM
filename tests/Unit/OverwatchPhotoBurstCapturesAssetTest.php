<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchPhotoBurstCapturesAssetTest extends TestCase
{
    public function testCaptureThrottlesArmaShotsAndSkipsExistingComspecPng(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons';
        $capture = (string) file_get_contents($root . '/connect/functions/fn_captureReconImage.sqf');
        $bridge = (string) file_get_contents($root . '/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf');
        $note = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/bugs/2026-09-03-photos-rafale-captures.md');

        self::assertStringContainsString('COMSPEC_LastArmaShotAt', $capture);
        self::assertStringContainsString('2.8', $capture);
        self::assertStringContainsString('comspec_', $capture);
        self::assertStringContainsString('_skipArmaShot = true;', $capture);
        // Un PNG COMSPEC_* déjà pris n’est pas recliché ; les JPEG passent par _fnc_isJpegPath
        // (le pont IceMan les envoie déjà avec skipArmaShot=true, voir ci-dessous).
        self::assertStringContainsString('(_givenBase select [0, 8]) isEqualTo "comspec_" && {(_givenBase find ".png") >= 0}', $capture);
        self::assertStringContainsString('private _fnc_isJpegPath', $capture);
        self::assertStringContainsString('_path isEqualTo "" && {_skipArmaShot}', $capture);
        self::assertStringNotContainsString('BCE_fnc_screenShot', $capture);

        self::assertStringContainsString('private _skipShot = true;', $bridge);
        // Depuis docs/bugs/2026-09-13-quick-picture-discord-coupe.md, un JPEG BCE part tel quel
        // (skipArmaShot=true) : aucun PNG Arma supplémentaire, donc plus de rafale.
        self::assertStringContainsString(
            '[_filePath, _caption, _device, _feedId, true, false, true] call comspec_overwatch_connect_fnc_captureReconImage',
            $bridge
        );
        self::assertStringNotContainsString(
            'false, true, false] call comspec_overwatch_connect_fnc_captureReconImage',
            $bridge
        );

        self::assertStringContainsString('rafale', $note);
        self::assertStringContainsString('Captures', $note);
        self::assertStringNotContainsString('endpoint', $note);
    }
}
