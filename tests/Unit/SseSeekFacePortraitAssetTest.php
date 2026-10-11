<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Terminal SEEK : cadrage sans déformation, viseur du visage, cliché portrait jusqu'au site.
 */
final class SseSeekFacePortraitAssetTest extends TestCase
{
    private const CONNECT = '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . $rel);
    }

    public function testTerminalIsSquareAndFramedOnScreen(): void
    {
        $hpp = $this->read(self::CONNECT . '/display_sse_person.hpp');
        self::assertStringContainsString('#define SEEK_W      (SEEK_H * 3 / 4)', $hpp);
        self::assertStringContainsString('#define SEEK_H      (1.70 * safezoneH)', $hpp);
        self::assertStringContainsString('class COMSPEC_SeekText: RscStructuredText', $hpp);
        self::assertStringContainsString('seek_screen_bg_co.paa', $hpp);
        self::assertStringContainsString('seek_tile_photo_ca.paa', $hpp);
        self::assertStringContainsString('r2t(comspecseekface,0.75)', $hpp);
        self::assertStringContainsString('seek_face_guide_ca.paa', $hpp);
        self::assertStringContainsString("['stop'] call comspec_overwatch_connect_fnc_sseFaceViewfinder;", $hpp);

        foreach (['seek_screen_bg_co', 'seek_tile_subject_ca', 'seek_tile_photo_ca', 'seek_face_guide_ca', 'seek_face_empty_ca'] as $paa) {
            self::assertFileExists(dirname(__DIR__, 2) . self::CONNECT . '/img/device/' . $paa . '.paa');
        }
    }

    public function testViewfinderAndCaptureShareFraming(): void
    {
        $cfg = $this->read(self::CONNECT . '/config.cpp');
        $page = $this->read(self::CONNECT . '/functions/fn_sseTerminalPage.sqf');
        $vf = $this->read(self::CONNECT . '/functions/fn_sseFaceViewfinder.sqf');
        $cap = $this->read(self::CONNECT . '/functions/fn_sseCaptureFacePhoto.sqf');
        $submit = $this->read(self::CONNECT . '/functions/fn_ssePersonDialogSubmit.sqf');

        self::assertStringContainsString('class sseFaceFraming {};', $cfg);
        self::assertStringContainsString('class sseFaceViewfinder {};', $cfg);
        self::assertStringContainsString('9574, 9575, 9576', $page);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_sseFaceFraming', $vf);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_sseFaceFraming', $cap);
        self::assertStringContainsString('@portrait,q=', $cap);
        self::assertStringContainsString('StageCapture', $cap);
        self::assertStringContainsString('COMSPEC_SsePerson_PhotoAngle', $submit);
    }

    public function testNativeAtakSseAppTakesFacePhoto(): void
    {
        $root = '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages';
        self::assertStringContainsString("['face'] call comspec_atak_native_fnc_sseAction", $this->read($root . '/fn_pageSse.sqf'));
        self::assertStringContainsString('comspec_overwatch_connect_fnc_sseCaptureFacePhoto', $this->read($root . '/fn_sseAction.sqf'));
    }

    public function testPortalCropsPortrait(): void
    {
        $sse = $this->read('/app/Controllers/Api/SseApiController.php');
        self::assertStringContainsString('SseFacePortrait::parseAngle', $sse);
        self::assertStringContainsString('SseFacePortrait::writePortrait', $sse);
        self::assertStringContainsString("'source_image_path'", $sse);
    }
}
