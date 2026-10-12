<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OperatorPortraits;
use PHPUnit\Framework\TestCase;

/**
 * Vignette du portrait opérateur pour le téléphone en jeu : JPEG carré (Arma ne lit ni PNG ni WebP).
 */
final class OperatorPortraitGameThumbnailTest extends TestCase
{
    public function testTallPngBecomesSquareJpeg(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD absent');
        }
        $dir = sys_get_temp_dir() . '/op-thumb-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $src = $dir . '/12_1700000000.png';
        $im = imagecreatetruecolor(300, 600);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 40, 40));
        imagepng($im, $src);
        imagedestroy($im);

        $dest = $dir . '/atak/12_1700000000.jpg';
        $m = new \ReflectionMethod(OperatorPortraits::class, 'writeSquareJpeg');
        self::assertTrue($m->invoke(null, $src, $dest, 256));

        $info = getimagesize($dest);
        self::assertSame([256, 256, IMAGETYPE_JPEG], [$info[0], $info[1], $info[2]]);

        unlink($dest);
        rmdir($dir . '/atak');
        unlink($src);
        rmdir($dir);
    }

    public function testUnreadableImageIsRefused(): void
    {
        $dir = sys_get_temp_dir() . '/op-thumb-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $src = $dir . '/bad.png';
        file_put_contents($src, 'not an image');
        $m = new \ReflectionMethod(OperatorPortraits::class, 'writeSquareJpeg');
        self::assertFalse($m->invoke(null, $src, $dir . '/out.jpg', 256));
        self::assertFileDoesNotExist($dir . '/out.jpg');
        unlink($src);
        rmdir($dir);
    }
}
