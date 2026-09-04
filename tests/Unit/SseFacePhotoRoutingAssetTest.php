<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SseFacePhotoRoutingAssetTest extends TestCase
{
    public function testWatcherAndEnqueueSkipSeekFaceCaptures(): void
    {
        $cs = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('IsSseIdentityCaptureName', $cs);
        self::assertStringContainsString('COMSPEC_SSE_Face', $cs);
        self::assertStringContainsString('return "OK|ignored";', $cs);
        self::assertStringContainsString('if (IsSseIdentityCaptureName(fullPath)) return;', $cs);
        self::assertStringContainsString('if (IsSseNoteCaptureName(fullPath)) return;', $cs);
        self::assertStringContainsString('/api/sse/persons/', $cs);
        self::assertStringContainsString('ProcessSseFacePhotoUploadAsync', $cs);
        self::assertStringContainsString('1.18.12', $cs);
    }

    public function testPortalIgnoresFaceOnReconAndAcceptsMagicPngOnFiche(): void
    {
        $recon = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        $sse = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/SseApiController.php');
        $helper = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/TerrainUploadedImage.php');
        self::assertStringContainsString('HttpJsonBody::isMultipart', $recon);
        self::assertStringContainsString('isSseFaceFileName', $recon);
        self::assertStringContainsString("'ignored' => true", $recon);
        self::assertStringContainsString('TerrainUploadedImage::detectExtension', $sse);
        self::assertStringContainsString('TerrainUploadedImage::move', $sse);
        self::assertStringContainsString('comspec_sse_face', $helper);
        self::assertStringContainsString("\\x89PNG", $helper);
    }

    public function testGameDoesNotForwardSeekFaceToReconNotify(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons';
        $capture = (string) file_get_contents($root . '/connect/functions/fn_captureReconImage.sqf');
        $iceman = (string) file_get_contents($root . '/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf');
        self::assertStringContainsString('comspec_sse_face', $capture);
        self::assertStringContainsString('OK|IGNORED', $capture);
        self::assertStringContainsString('comspec_sse_face', $iceman);
    }
}
