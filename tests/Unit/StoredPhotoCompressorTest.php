<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cron\Jobs\PhotoCompressCronJob;
use App\Support\StoredPhotoCompressor;
use PHPUnit\Framework\TestCase;

/**
 * Photos terrain allégées : même nom de fichier (donc même URL), jamais de perte,
 * et un deuxième passage ne réencode rien.
 */
final class StoredPhotoCompressorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
            self::markTestSkipped('GD absent');
        }
        $this->root = sys_get_temp_dir() . '/photo_compress_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/public/uploads/recon', 0777, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->root) && is_dir($this->root)) {
            exec('rm -rf ' . escapeshellarg($this->root));
        }
    }

    public function testLargePngIsShrunkToJpegUnderTheSameName(): void
    {
        $png = $this->root . '/public/uploads/recon/recon_shot.png';
        $this->writeNoisyImage($png, 3840, 2160, 'png');
        $before = filesize($png);
        touch($png, time() - 3600);

        $r = StoredPhotoCompressor::compressFile($png);

        self::assertSame('compressed', $r['status'], $r['reason']);
        self::assertFileExists($png);
        $info = getimagesize($png);
        self::assertSame(IMAGETYPE_JPEG, $info[2]);
        self::assertSame(1920, $info[0]);
        self::assertSame(1080, $info[1]);
        self::assertLessThan($before, filesize($png));
        self::assertSame([], glob($this->root . '/public/uploads/recon/.*.tmp'));

        $again = StoredPhotoCompressor::compressFile($png);
        self::assertSame('skipped', $again['status']);
    }

    public function testRecentFilesAreLeftForTheNextRun(): void
    {
        $png = $this->root . '/public/uploads/recon/fresh.png';
        $this->writeNoisyImage($png, 2560, 1440, 'png');

        $r = StoredPhotoCompressor::compressFile($png, minAgeSeconds: 600);

        self::assertSame('skipped', $r['status']);
        self::assertSame(IMAGETYPE_PNG, getimagesize($png)[2]);
    }

    public function testDryRunTouchesNothing(): void
    {
        $png = $this->root . '/public/uploads/recon/dry.png';
        $this->writeNoisyImage($png, 2560, 1440, 'png');
        $hash = md5_file($png);

        $r = StoredPhotoCompressor::compressFile($png, dryRun: true);

        self::assertSame('compressed', $r['status']);
        self::assertSame($hash, md5_file($png));
    }

    public function testNonImageIsNeverTouched(): void
    {
        $bad = $this->root . '/public/uploads/recon/broken.png';
        file_put_contents($bad, str_repeat('x', 4096));

        $r = StoredPhotoCompressor::compressFile($bad);

        self::assertSame('skipped', $r['status']);
        self::assertSame(str_repeat('x', 4096), file_get_contents($bad));
    }

    public function testUploadIsConvertedToJpeg(): void
    {
        $tmp = $this->root . '/upload.tmp';
        $this->writeNoisyImage($tmp, 3840, 2160, 'png');

        self::assertTrue(StoredPhotoCompressor::compressUploadToJpeg($tmp));
        self::assertSame(IMAGETYPE_JPEG, getimagesize($tmp)[2]);
        self::assertSame(1920, getimagesize($tmp)[0]);
    }

    public function testCronJobReportsSavings(): void
    {
        $png = $this->root . '/public/uploads/recon/old.png';
        $this->writeNoisyImage($png, 3840, 2160, 'png');
        touch($png, time() - 3600);

        $result = (new PhotoCompressCronJob($this->root))->run();

        self::assertTrue($result['ok']);
        self::assertSame(1, $result['details']['compressed']);
        self::assertGreaterThan(0, $result['details']['bytes_saved']);
        self::assertSame(0, $result['details']['remaining']);
    }

    /** Image « paysage » : petite mosaïque aléatoire agrandie en dégradés, comme une capture de jeu. */
    private function writeNoisyImage(string $path, int $w, int $h, string $fmt): void
    {
        $small = imagecreatetruecolor(96, 54);
        mt_srand(7);
        for ($y = 0; $y < 54; $y++) {
            for ($x = 0; $x < 96; $x++) {
                imagesetpixel($small, $x, $y, imagecolorallocate($small, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }
        $im = imagecreatetruecolor($w, $h);
        imagecopyresampled($im, $small, 0, 0, 0, 0, $w, $h, 96, 54);
        imagedestroy($small);
        // Grain de texture (semi-transparent), qui rend le PNG lourd comme une vraie capture.
        $grain = imagecreatetruecolor(256, 256);
        imagealphablending($grain, false);
        imagesavealpha($grain, true);
        for ($y = 0; $y < 256; $y++) {
            for ($x = 0; $x < 256; $x++) {
                $v = mt_rand(0, 255);
                imagesetpixel($grain, $x, $y, imagecolorallocatealpha($grain, $v, $v, $v, 90));
            }
        }
        imagealphablending($im, true);
        imagesettile($im, $grain);
        imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, IMG_COLOR_TILED);
        imagedestroy($grain);
        $fmt === 'png' ? imagepng($im, $path, 6) : imagejpeg($im, $path, 95);
        imagedestroy($im);
    }
}
