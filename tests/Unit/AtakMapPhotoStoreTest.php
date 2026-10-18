<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakMapPhotoStore;
use PHPUnit\Framework\TestCase;

/**
 * Fond « Photo COMSPEC » (capture de la carte du mod) : envoi ZIP par paquets, calque Leaflet, tuile manquante
 * découpée dans son ancêtre.
 */
final class AtakMapPhotoStoreTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        if (!class_exists(\ZipArchive::class) || !function_exists('imagewebp')) {
            self::markTestSkipped('zip ou GD WebP absent');
        }
        $this->root = sys_get_temp_dir() . '/mphoto-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (isset($this->root) && is_dir($this->root)) {
            exec('rm -rf ' . escapeshellarg($this->root));
        }
    }

    private function webp(int $r, int $g, int $b): string
    {
        $im = imagecreatetruecolor(256, 256);
        imagefill($im, 0, 0, imagecolorallocate($im, $r, $g, $b));
        // Quart nord-ouest d'une autre couleur : la découpe doit tomber dessus.
        imagefilledrectangle($im, 0, 0, 127, 127, imagecolorallocate($im, 250, 10, 10));
        ob_start();
        imagewebp($im, null, 90);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    /** @param array<string, string> $files */
    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mpz');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        foreach ($files as $name => $data) {
            $zip->addFromString($name, $data);
        }
        $zip->close();

        return $path;
    }

    public function testUploadInChunksPublishesOnlyWhenDone(): void
    {
        $store = new AtakMapPhotoStore($this->root);
        $meta = json_encode(['world' => 'altis', 'mode' => 'sat', 'extent' => 32768, 'tileSize' => 256, 'minZoom' => 0, 'maxZoom' => 3]);
        $a = $this->zip(['0/0/0.webp' => $this->webp(10, 120, 30), '../evil.php' => '<?php', '1/0/0.webp' => 'pas une image']);
        $r1 = $store->importZip('altis', 'sat', $a, true, false);
        self::assertTrue($r1['ok']);
        self::assertSame(1, $r1['files']);
        self::assertNull($store->meta('altis', 'sat'), 'pas publié avant le dernier paquet');
        $b = $this->zip(['meta.json' => (string) $meta]);
        $r2 = $store->importZip('altis', 'sat', $b, false, true);
        self::assertTrue($r2['published']);
        self::assertFileDoesNotExist($this->root . '/altis/evil.php');
        self::assertFileDoesNotExist($this->root . '/altis/sat/1/0/0.webp');
        $spec = $store->overlaySpec('altis', 'sat', 'https://athena.example/public');
        self::assertNotNull($spec);
        self::assertSame('https://athena.example/public/map-photo/altis/sat/{z}/{x}/{y}.webp', $spec['tilePattern']);
        self::assertEqualsWithDelta(256 / 32768, $spec['factorX'], 1e-12);
        self::assertSame(3, $spec['maxZoom']);
        @unlink($a);
        @unlink($b);
    }

    public function testMissingTileIsCutFromAncestor(): void
    {
        $store = new AtakMapPhotoStore($this->root);
        $zip = $this->zip([
            '0/0/0.webp' => $this->webp(10, 120, 30),
            'meta.json' => (string) json_encode(['extent' => 8192, 'tileSize' => 256, 'maxZoom' => 2]),
        ]);
        $store->importZip('stratis', 'sat', $zip, true, true);
        @unlink($zip);
        // z1 (0,0) = quart nord-ouest de la tuile 0 : rouge.
        $nw = $store->tileFile('stratis', 'sat', 1, 0, 0);
        self::assertNotNull($nw);
        $im = imagecreatefromwebp((string) $nw);
        $rgb = imagecolorat($im, 128, 128);
        self::assertGreaterThan(200, ($rgb >> 16) & 0xFF);
        // z2 (3,3) = coin sud-est : vert.
        $se = $store->tileFile('stratis', 'sat', 2, 3, 3);
        self::assertNotNull($se);
        $rgb = imagecolorat(imagecreatefromwebp((string) $se), 128, 128);
        self::assertGreaterThan(90, ($rgb >> 8) & 0xFF);
        self::assertLessThan(60, ($rgb >> 16) & 0xFF);
        // Hors fond.
        self::assertNull($store->tileFile('stratis', 'sat', 3, 0, 0));
        self::assertNull($store->tileFile('stratis', 'sat', 1, 2, 0));
        self::assertNull($store->tileFile('stratis', 'plan', 0, 0, 0));
    }

    public function testSlugs(): void
    {
        self::assertSame('altis', AtakMapPhotoStore::world('Altis'));
        self::assertNull(AtakMapPhotoStore::world('../etc'));
        self::assertNull(AtakMapPhotoStore::mode('3d'));
        self::assertSame('plan', AtakMapPhotoStore::mode('PLAN'));
    }
}
