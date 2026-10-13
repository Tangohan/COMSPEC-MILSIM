<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BriefingSlideGameImage;
use PHPUnit\Framework\TestCase;

/**
 * Diapositive de briefing pour le téléphone en jeu : JPEG 2048 x 1024 (Arma n'affiche pas les PNG).
 */
final class BriefingSlideGameImageTest extends TestCase
{
    public function testPngSlideBecomesPowerOfTwoJpeg(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD absent');
        }
        $dir = sys_get_temp_dir() . '/brief-slide-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $src = $dir . '/slide-abc.png';
        $im = imagecreatetruecolor(1920, 1080);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 40, 40));
        imagepng($im, $src);
        imagedestroy($im);

        $dest = $dir . '/atak/slide-abc.jpg';
        self::assertTrue(BriefingSlideGameImage::write($src, $dest));

        $info = getimagesize($dest);
        self::assertSame([2048, 1024, IMAGETYPE_JPEG], [$info[0], $info[1], $info[2]]);

        unlink($dest);
        rmdir($dir . '/atak');
        unlink($src);
        rmdir($dir);
    }

    public function testApiServesGameCopy(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString("'image_url' => url(BriefingSlideGameImage::path(\$imagePath))", $src);
    }
}
