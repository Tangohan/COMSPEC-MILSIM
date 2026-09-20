<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchReconPhotoDateActionsAssetTest extends TestCase
{
    public function testGameSendsWallClockInsteadOfMissionTime(): void
    {
        $root = dirname(__DIR__, 2);
        $capture = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/ReconImageRepository.php');
        $js = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-beta.js');
        $css = (string) file_get_contents($root . '/public/assets/css/atak-overwatch-beta.css');
        $mig = (string) file_get_contents($root . '/bootstrap/atak_recon_images_actions_migration.php');

        self::assertStringContainsString('wallClockSeconds', $capture);
        self::assertStringContainsString('2440588', $capture);
        self::assertStringNotContainsString('private _capturedAt = str (floor time);', $capture);
        self::assertStringContainsString('versionStr = "1.6.5"', $cfg);
        self::assertStringContainsString('ReconCapturedAt::unixFromPosted', $ctrl);
        self::assertStringContainsString('ReconCapturedAt::displayFromRow', $ctrl);
        self::assertStringContainsString('ReconCapturedAt::sqlDateTime', $repo);
        self::assertStringContainsString("captured_at < '2000-01-01 00:00:00'", $mig);
        self::assertStringContainsString('data-photo-op="blur"', $js);
        self::assertStringContainsString('data-photo-op="sse"', $js);
        self::assertStringContainsString('data-photo-op="delete"', $js);
        self::assertStringContainsString('Passer en SSE', $js);
        self::assertStringContainsString('Flouter', $js);
        self::assertStringContainsString('Agrandir', $js);
        self::assertStringContainsString('ow-photo-lightbox', $js);
        self::assertStringContainsString('.ow-photo-actions', $css);
    }
}
