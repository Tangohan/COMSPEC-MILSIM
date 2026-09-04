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
        self::assertStringContainsString('(_lowGiven find ".jpg") >= 0', $capture);
        self::assertStringContainsString('_path isEqualTo "" && {_skipArmaShot}', $capture);
        self::assertStringNotContainsString('BCE_fnc_screenShot', $capture);

        self::assertStringContainsString('private _skipShot = true;', $bridge);
        self::assertStringContainsString(
            '[_path, _caption, _device, _feedId, false, true, false] call comspec_overwatch_connect_fnc_captureReconImage',
            $bridge
        );

        self::assertStringContainsString('rafale', $note);
        self::assertStringContainsString('Captures', $note);
        self::assertStringNotContainsString('endpoint', $note);
    }
}
