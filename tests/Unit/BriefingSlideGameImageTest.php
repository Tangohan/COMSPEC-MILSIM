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

    public function testMissingSourceGivesReason(): void
    {
        self::assertSame('uploads/briefing-slides/0/absent-x.png', BriefingSlideGameImage::path('uploads/briefing-slides/0/absent-x.png'));
        self::assertSame('source_missing', BriefingSlideGameImage::$lastError);
    }

    public function testUploadMakesGameCopyAndWarns(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/AdminBriefingSlidesController.php');
        self::assertStringContainsString('BriefingSlideGameImage::path($destRel) === $destRel', $src);
        self::assertStringContainsString("Session::flash('warning', self::gameCopyWarning(BriefingSlideGameImage::\$lastError))", $src);
    }
}
