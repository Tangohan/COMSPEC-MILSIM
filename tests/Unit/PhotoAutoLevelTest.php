<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PhotoAutoLevel;
use PHPUnit\Framework\TestCase;

/**
 * Correction de couleur des photos reçues du jeu : niveaux étirés, original gardé hors racine web,
 * pas de correction pour la vision nocturne, la thermique ou le drone.
 */
final class PhotoAutoLevelTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD absent');
        }
        $this->dir = sys_get_temp_dir() . '/autolevel_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir) && is_dir($this->dir)) {
            foreach (glob($this->dir . '/{,*/}*', GLOB_BRACE) ?: [] as $f) {
                if (is_file($f)) {
                    unlink($f);
                }
            }
            foreach (glob($this->dir . '/*', GLOB_ONLYDIR) ?: [] as $d) {
                rmdir($d);
            }
            rmdir($this->dir);
        }
    }

    /** Dégradé délavé : gris de 70 à 170. */
    private function washedJpeg(): string
    {
        $im = imagecreatetruecolor(200, 100);
        for ($x = 0; $x < 200; $x++) {
            $v = 70 + (int) round($x / 2);
            imagefilledrectangle($im, $x, 0, $x, 99, imagecolorallocate($im, $v, $v, (int) round($v * 0.9)));
        }
        $path = $this->dir . '/recon_test.jpg';
        imagejpeg($im, $path, 95);
        imagedestroy($im);

        return $path;
    }

    public function testStretchesWashedOutPhotoAndKeepsOriginal(): void
    {
        $path = $this->washedJpeg();
        $before = PhotoAutoLevel::percentiles(imagecreatefromjpeg($path));
        $originals = $this->dir . '/originals';

        self::assertTrue(PhotoAutoLevel::applyFile($path, $originals));

        $after = PhotoAutoLevel::percentiles(imagecreatefromjpeg($path));
        self::assertGreaterThan($before[1] - $before[0], $after[1] - $after[0]);
        self::assertLessThan($before[0], $after[0]);
        self::assertFileExists($originals . '/recon_test_orig.jpg');
    }

    public function testNoOriginalCopyWithoutFolder(): void
    {
        $path = $this->washedJpeg();
        self::assertTrue(PhotoAutoLevel::applyFile($path));
        self::assertFileDoesNotExist($this->dir . '/recon_test_orig.jpg');
    }

    public function testSkipsNightVisionThermalAndDrone(): void
    {
        self::assertTrue(PhotoAutoLevel::skipsProfile('nightvision', 'PHONE'));
        self::assertTrue(PhotoAutoLevel::skipsProfile('thermal_white', 'HELMET'));
        self::assertTrue(PhotoAutoLevel::skipsProfile('', 'UAV'));
        self::assertFalse(PhotoAutoLevel::skipsProfile('', 'PHONE'));
        self::assertFalse(PhotoAutoLevel::skipsProfile(null, 'CTAB'));
    }

    public function testUploadPathsApplyCorrection(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        self::assertSame(2, substr_count($src, 'PhotoAutoLevel::applyFile($path, \\App\\Support\\ReconImageStorage::originalsDir())'));
    }
}
