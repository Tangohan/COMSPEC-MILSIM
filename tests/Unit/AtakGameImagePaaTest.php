<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GameEquipment\PaaDecoder;
use App\Support\AtakGameImage;
use App\Support\PaaEncoder;
use PHPUnit\Framework\TestCase;

/**
 * Images du site vues dans le téléphone en jeu : conversion en texture .paa (DXT1 / DXT5 + mipmaps), relue par le
 * décodeur du site, et service du .paa à COMSPEC Link seulement quand elle l'annonce.
 */
final class AtakGameImagePaaTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_COMSPEC_IMAGE']);
        AtakGameImage::resetBudget();
    }

    private static function gradient(int $w, int $h, bool $alpha): \GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, intdiv($x * 255, $w), intdiv($y * 255, $h), 90, $alpha ? intdiv($x * 127, $w) : 0));
            }
        }

        return $im;
    }

    public function testOpaqueImageBecomesDxt1WithFullMipChainAndHemttLayout(): void
    {
        $paa = PaaEncoder::encode(self::gradient(64, 32, false));
        self::assertSame(PaaEncoder::TYPE_DXT1, unpack('v', $paa)[1]);
        self::assertSame('GGATCGVA', substr($paa, 2, 8));
        self::assertStringNotContainsString('GGATGALF', $paa);
        self::assertSame(str_repeat("\0", 6), substr($paa, -6));
        // SFFO : la première position pointe sur le premier mipmap (64 x 32), les suivants s'enchaînent jusqu'à 8 x 4.
        $at = strpos($paa, 'GGATSFFO');
        $offsets = array_values(unpack('V16', substr($paa, $at + 12, 64)));
        self::assertSame([64, 32], array_values(unpack('v2', substr($paa, $offsets[0], 4))));
        self::assertSame([8, 4], array_values(unpack('v2', substr($paa, $offsets[3], 4))));
        self::assertSame(0, $offsets[4]);

        $back = PaaDecoder::decode($paa, 64);
        self::assertSame([64, 32], [imagesx($back), imagesy($back)]);
        $c = imagecolorsforindex($back, imagecolorat($back, 40, 20));
        self::assertEqualsWithDelta(intdiv(40 * 255, 64), $c['red'], 12);
        self::assertEqualsWithDelta(intdiv(20 * 255, 32), $c['green'], 12);
        self::assertSame(0, $c['alpha']);
    }

    public function testTransparentImageBecomesDxt5AndKeepsAlpha(): void
    {
        $paa = PaaEncoder::encode(self::gradient(32, 32, true));
        self::assertSame(PaaEncoder::TYPE_DXT5, unpack('v', $paa)[1]);
        self::assertStringContainsString('GGATGALF', $paa);
        $back = PaaDecoder::decode($paa, 32);
        self::assertLessThan(10, imagecolorsforindex($back, imagecolorat($back, 0, 5))['alpha']);
        self::assertGreaterThan(110, imagecolorsforindex($back, imagecolorat($back, 31, 5))['alpha']);
    }

    public function testRejectsSidesThatAreNotPowersOfTwo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PaaEncoder::encode(imagecreatetruecolor(100, 64));
    }

    public function testGameFitsUsePowerOfTwoSides(): void
    {
        $src = imagecreatetruecolor(1920, 1080);
        $slide = AtakGameImage::fit($src, AtakGameImage::FIT_SLIDE);
        self::assertSame([2048, 1024], [imagesx($slide), imagesy($slide)]);
        $photo = AtakGameImage::fit($src, AtakGameImage::FIT_PHOTO);
        self::assertSame([1024, 512], [imagesx($photo), imagesy($photo)]);
        $tall = AtakGameImage::fit(imagecreatetruecolor(600, 900), AtakGameImage::FIT_PHOTO);
        self::assertSame([512, 1024], [imagesx($tall), imagesy($tall)]);
        $portrait = AtakGameImage::fit(imagecreatetruecolor(300, 500), AtakGameImage::FIT_PORTRAIT);
        self::assertSame([256, 256], [imagesx($portrait), imagesy($portrait)]);
    }

    public function testConvertsAPngFileIntoAReadablePaa(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'paa') . '.png';
        imagepng(self::gradient(300, 200, false), $file);
        try {
            $paa = AtakGameImage::convert($file, AtakGameImage::FIT_PHOTO);
        } finally {
            @unlink($file);
        }
        self::assertNotNull($paa);
        $back = PaaDecoder::decode($paa, 1024);
        self::assertSame([1024, 512], [imagesx($back), imagesy($back)]);
    }

    public function testPathsOfTheGameCopy(): void
    {
        self::assertSame('uploads/sse/persons/3/face.png', AtakGameImage::uploadsRel('https://athena.ttrd.fr/public/uploads/sse/persons/3/face.png?v=2'));
        self::assertSame('uploads/portraits/p.webp', AtakGameImage::uploadsRel('/var/www/site/storage/uploads/portraits/p.webp'));
        self::assertNull(AtakGameImage::uploadsRel('https://cdn.example.com/x.png'));
        self::assertNull(AtakGameImage::uploadsRel('uploads/../config/.env'));
        self::assertSame('uploads/briefing-slides/4/atak/slide-ab.paa', AtakGameImage::paaRel('uploads/briefing-slides/4/slide-ab.png'));
        self::assertSame('uploads/portraits/atak/p.paa', AtakGameImage::paaRel('uploads/portraits/atak/p.jpg'));
    }

    public function testWebJpegSitsNextToThePaaAndStaysLight(): void
    {
        self::assertSame('uploads/briefing-slides/4/atak/slide-ab.jpg', AtakGameImage::webRel('uploads/briefing-slides/4/atak/slide-ab.paa'));
        $jpg = AtakGameImage::webJpeg(AtakGameImage::fit(self::gradient(1920, 1080, true), AtakGameImage::FIT_SLIDE));
        self::assertNotNull($jpg);
        self::assertSame("\xFF\xD8\xFF", substr($jpg, 0, 3));
        // Moins de 1,4 Mo : limite de COMSPEC Link (ImageDataChunk) pour une page data: de Chromium.
        self::assertLessThan(1_400_000, strlen($jpg));
        $back = imagecreatefromstring($jpg);
        self::assertSame([1600, 800], [imagesx($back), imagesy($back)]);
    }

    public function testPaaIsOnlyServedToALinkThatAnnouncesIt(): void
    {
        self::assertFalse(AtakGameImage::clientAcceptsPaa());
        $_SERVER['HTTP_X_COMSPEC_IMAGE'] = 'jpg, PAA';
        self::assertTrue(AtakGameImage::clientAcceptsPaa());
    }

    public function testLinkAnnouncesAndKeepsPaaImages(): void
    {
        $cs = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('TryAddWithoutValidation("X-COMSPEC-Image", "paa")', $cs);
        self::assertStringContainsString('else if (isPaa) ext = ".paa";', $cs);
        foreach (['Api/AtakApiController.php', 'Api/SseApiController.php', 'Api/SseFieldNoteApiController.php'] as $c) {
            self::assertStringContainsString('AtakGameImage::clientAcceptsPaa()', (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/' . $c), $c);
        }
    }
}
