<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PhotoFaceBlur;
use PHPUnit\Framework\TestCase;

/**
 * Floutage des visages des photos du mode photo : cadres lus dans photo_extra, zone modifiée, reste de l'image intact.
 */
final class PhotoFaceBlurTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD absent');
        }
    }

    /** Damier contrasté : un flou y change forcément les pixels. */
    public function testGaussianBlurIsSmoothNotBlocky(): void
    {
        // Disque noir sur fond blanc : un agrandissement brut y laisse des marches nettes.
        $im = imagecreatetruecolor(400, 200);
        imagefilledrectangle($im, 0, 0, 399, 199, imagecolorallocate($im, 255, 255, 255));
        imagefilledellipse($im, 140, 100, 60, 80, imagecolorallocate($im, 0, 0, 0));
        PhotoFaceBlur::apply($im, ['type' => 'gauss', 'force' => 60, 'opacity' => 100, 'size' => 100, 'angle' => 0, 'faces' => [[0.2, 0.15, 0.3, 0.6]]]);
        // Au centre du cadre, deux pixels voisins ne doivent jamais faire un saut net (marche d'escalier d'un agrandissement brut).
        $jump = 0;
        for ($y = 70; $y < 130; $y += 3) {
            for ($x = 100; $x < 180; $x++) {
                $jump = max($jump, abs((imagecolorat($im, $x, $y) & 255) - (imagecolorat($im, $x + 1, $y) & 255)));
            }
        }
        imagedestroy($im);
        self::assertLessThan(12, $jump);
    }

    private function checker(): \GdImage
    {
        $im = imagecreatetruecolor(400, 200);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        for ($y = 0; $y < 200; $y += 4) {
            for ($x = 0; $x < 400; $x += 4) {
                imagefilledrectangle($im, $x, $y, $x + 3, $y + 3, (($x + $y) / 4) % 2 === 0 ? $white : $black);
            }
        }

        return $im;
    }

    public function testParsesSettingsAndFaces(): void
    {
        $spec = PhotoFaceBlur::fromExtra('mission=Op|blur=pixel;80;50;150;30|face1=0.1;0.2;0.05;0.08|face2=bad|face3=2;2;0.1;0.1');
        self::assertNotNull($spec);
        self::assertSame('pixel', $spec['type']);
        self::assertSame([80, 50, 150, 30], [$spec['force'], $spec['opacity'], $spec['size'], $spec['angle']]);
        self::assertCount(1, $spec['faces']);
    }

    public function testNothingWithoutFacesOrWithUnknownType(): void
    {
        self::assertNull(PhotoFaceBlur::fromExtra('blur=gauss;50;100;100;0'));
        self::assertNull(PhotoFaceBlur::fromExtra('blur=off|face1=0.1;0.1;0.1;0.1'));
        self::assertNull(PhotoFaceBlur::fromExtra(''));
    }

    public function testEveryTypeChangesOnlyTheFaceZone(): void
    {
        foreach (PhotoFaceBlur::TYPES as $type) {
            $im = $this->checker();
            $before = imagecolorat($im, 300, 150);
            // Échantillons sur des cases blanches et noires du damier (sinon le carré noir ne change rien).
            $center = [imagecolorat($im, 100, 50), imagecolorat($im, 104, 50), imagecolorat($im, 108, 50), imagecolorat($im, 112, 50)];
            $n = PhotoFaceBlur::apply($im, ['type' => $type, 'force' => 70, 'opacity' => 100, 'size' => 100, 'angle' => 0, 'faces' => [[0.2, 0.15, 0.1, 0.2]]]);
            self::assertSame(1, $n, $type);
            self::assertSame($before, imagecolorat($im, 300, 150), $type . ' hors cadre');
            $after = [imagecolorat($im, 100, 50), imagecolorat($im, 104, 50), imagecolorat($im, 108, 50), imagecolorat($im, 112, 50)];
            self::assertNotSame($center, $after, $type . ' dans le cadre');
            imagedestroy($im);
        }
    }

    public function testBlackBoxIsBlackAtFullOpacity(): void
    {
        $im = $this->checker();
        PhotoFaceBlur::apply($im, ['type' => 'black', 'force' => 50, 'opacity' => 100, 'size' => 100, 'angle' => 0, 'faces' => [[0.2, 0.15, 0.1, 0.2]]]);
        self::assertSame(0, imagecolorat($im, 100, 50) & 0xFFFFFF);
        imagedestroy($im);
    }
}
