<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseFacePortrait;
use PHPUnit\Framework\TestCase;

final class SseFacePortraitTest extends TestCase
{
    public function testParsesPortraitFlagAndQuality(): void
    {
        self::assertSame(
            ['angle' => 'trois_quarts', 'portrait' => true, 'quality' => 82],
            SseFacePortrait::parseAngle('trois_quarts@portrait,q=82')
        );
        self::assertSame(
            ['angle' => 'face', 'portrait' => false, 'quality' => null],
            SseFacePortrait::parseAngle('face')
        );
        self::assertSame(100, SseFacePortrait::parseAngle('profil@q=140')['quality']);
        self::assertSame('face', SseFacePortrait::parseAngle('')['angle']);
    }

    public function testCropBoxIsCentredThreeByFour(): void
    {
        self::assertSame(['x' => 555, 'y' => 0, 'w' => 810, 'h' => 1080], SseFacePortrait::cropBox(1920, 1080));
        self::assertNull(SseFacePortrait::cropBox(600, 800));
        self::assertNull(SseFacePortrait::cropBox(0, 0));
    }

    public function testWritesPortraitJpeg(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD absent');
        }
        $dir = sys_get_temp_dir() . '/sse_portrait_' . bin2hex(random_bytes(4));
        mkdir($dir);
        $src = $dir . '/shot.png';
        $im = imagecreatetruecolor(1920, 1080);
        imagefilledrectangle($im, 900, 400, 1020, 600, imagecolorallocate($im, 255, 255, 255));
        imagepng($im, $src);
        imagedestroy($im);

        $dest = $dir . '/portrait.jpg';
        self::assertTrue(SseFacePortrait::writePortrait($src, $dest));
        $size = getimagesize($dest);
        self::assertSame([750, 1000], [$size[0], $size[1]]);
        self::assertFalse(SseFacePortrait::writePortrait($dest, $dir . '/again.jpg'));

        array_map('unlink', glob($dir . '/*') ?: []);
        rmdir($dir);
    }
}
