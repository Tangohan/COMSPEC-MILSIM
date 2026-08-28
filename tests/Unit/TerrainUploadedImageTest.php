<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TerrainUploadedImage;
use PHPUnit\Framework\TestCase;

final class TerrainUploadedImageTest extends TestCase
{
    public function testDetectsSseFaceFileNames(): void
    {
        self::assertTrue(TerrainUploadedImage::isSseFaceFileName('COMSPEC_SSE_Face_915_36699.png'));
        self::assertTrue(TerrainUploadedImage::isSseFaceFileName('C:\\Users\\x\\Documents\\Arma 3\\Screenshots\\COMSPEC_SSE_Face_1_2.png'));
        self::assertFalse(TerrainUploadedImage::isSseFaceFileName('COMSPEC_AthenaFeed_12.png'));
        self::assertFalse(TerrainUploadedImage::isSseFaceFileName('recon_op.png'));
    }

    public function testDetectsPngAndJpegMagicBytes(): void
    {
        $png = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'comspec_mime_' . bin2hex(random_bytes(4)) . '.bin';
        $jpg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'comspec_mime_' . bin2hex(random_bytes(4)) . '.bin';
        try {
            file_put_contents($png, "\x89PNG\r\n\x1a\n" . str_repeat("\0", 24));
            file_put_contents($jpg, "\xFF\xD8\xFF\xE0" . str_repeat("\0", 24));
            self::assertSame('png', TerrainUploadedImage::detectExtension($png, 'capture.bin'));
            self::assertSame('jpg', TerrainUploadedImage::detectExtension($jpg, 'capture.bin'));
            self::assertNull(TerrainUploadedImage::detectExtension($png . '.missing', 'x.png'));
        } finally {
            @unlink($png);
            @unlink($jpg);
        }
    }
}
