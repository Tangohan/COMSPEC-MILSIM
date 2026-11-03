<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Sse\SseIdImageService;
use App\Services\Sse\SseIdMassGeneratorService;
use App\Services\Sse\SseIdWorkshopService;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Images des papiers fictifs (COMSPEC IDENTITY) : vrai type contrôlé, taille maximale, métadonnées EXIF retirées
 * (réencodage GD et repli sans GD), photo recadrée au format 35 × 45, rangement par communauté, images reprises dans
 * l'aperçu sous le filigrane SIMULATION.
 */
final class SseIdImageServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'idimg_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    private function file(string $bytes): string
    {
        $p = $this->dir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(4)) . '.bin';
        file_put_contents($p, $bytes);

        return $p;
    }

    /** Structure JPEG minimale (sans GD), avec un segment EXIF (position GPS) inséré après SOI. */
    private static function jpegWithExif(): string
    {
        $jpeg = "\xFF\xD8"
            . "\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            . "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00" . 'imagedata'
            . "\xFF\xD9";
        $payload = "Exif\0\0GPSLatitude 48.8566 SECRET-POSITION";

        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($jpeg, 2);
    }

    private static function pngWithText(): string
    {
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $data = "Comment\0SECRET-AUTHOR";
        $chunk = pack('N', strlen($data)) . 'tEXt' . $data . pack('N', crc32('tEXt' . $data));

        return substr($png, 0, 33) . $chunk . substr($png, 33);
    }

    public function testRefusesFakeTypesAndOversizedFiles(): void
    {
        $svc = new SseIdImageService($this->dir, false);
        $fake = $svc->storeFile($this->file("<?php echo 'x';"), 'photo', 1);
        self::assertFalse($fake['ok']);
        self::assertStringContainsString('PNG, JPG ou WebP', (string) $fake['error']);
        self::assertFalse($svc->storeFile($this->file('GIF89a' . str_repeat("\0", 40)), 'photo', 1)['ok'], 'GIF refusé');
        self::assertFalse($svc->storeFile($this->file("\x89PNG\r\n\x1a\nnot really a png"), 'photo', 1)['ok'], 'PNG mal formé');
        $big = $svc->storeFile($this->file("\xFF\xD8\xFF" . str_repeat('a', 4 * 1024 * 1024)), 'photo', 1);
        self::assertFalse($big['ok']);
        self::assertStringContainsString('4 Mo', (string) $big['error']);
        self::assertFalse($svc->storeFile($this->file(self::pngWithText()), 'signature', 0)['ok'], 'communauté obligatoire');
        self::assertFalse($svc->storeFile($this->file(self::pngWithText()), 'script', 1)['ok'], 'usage inconnu');
        self::assertSame(['ok' => false, 'none' => true], $svc->storeUpload(null, 'photo', 1));
        self::assertFalse($svc->storeUpload(['error' => UPLOAD_ERR_OK, 'tmp_name' => $this->file(self::pngWithText()), 'size' => 10], 'photo', 1)['ok'], 'fichier non téléversé refusé');
        self::assertSame([], glob($this->dir . '/uploads/*/*') ?: []);
    }

    public function testFallbackWithoutGdStripsExifAndTextMetadataPerTenant(): void
    {
        $svc = new SseIdImageService($this->dir, false);
        $res = $svc->storeFile($this->file(self::jpegWithExif()), 'photo', 7);
        self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));
        self::assertMatchesRegularExpression('#^uploads/sse-id-photos/7_[a-f0-9]{16}\.jpg$#', (string) $res['path']);
        $out = (string) file_get_contents($this->dir . '/' . $res['path']);
        self::assertStringNotContainsString('Exif', $out);
        self::assertStringNotContainsString('SECRET-POSITION', $out);
        self::assertStringStartsWith("\xFF\xD8\xFF\xE0", $out, 'JFIF gardé');
        self::assertStringEndsWith("\xFF\xD9", $out, 'données de l’image gardées');

        $png = $svc->storeFile($this->file(self::pngWithText()), 'signature', 7);
        self::assertTrue($png['ok']);
        $out = (string) file_get_contents($this->dir . '/' . $png['path']);
        self::assertStringNotContainsString('SECRET-AUTHOR', $out);
        self::assertStringContainsString('IDAT', $out);
        self::assertStringStartsWith('uploads/sse-id-signatures/7_', (string) $png['path']);

        $webp = 'RIFF' . pack('V', 4 + 18 + 18 + 14) . 'WEBP'
            . 'VP8X' . pack('V', 10) . "\x0C" . str_repeat("\0", 9)
            . 'EXIF' . pack('V', 10) . 'SECRET-EXF'
            . 'VP8L' . pack('V', 6) . "\x2F\0\0\0\0\0";
        self::assertSame('image/webp', SseIdImageService::sniff($webp));
        $clean = (string) SseIdImageService::stripMetadata($webp, 'image/webp');
        self::assertStringNotContainsString('SECRET-EXF', $clean);
        self::assertSame(0, ord($clean[20]) & 0x0C, 'drapeaux EXIF / XMP retirés');
        self::assertSame(strlen($clean) - 8, unpack('V', substr($clean, 4, 4))[1]);
    }

    public function testGdReencodesWithoutMetadataAndCropsThePhotoTo35x45(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD requis');
        }
        $im = imagecreatetruecolor(400, 900);
        imagefill($im, 0, 0, (int) imagecolorallocate($im, 200, 180, 160));
        ob_start();
        imagejpeg($im, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($im);
        $payload = "Exif\0\0GPSLatitude SECRET-POSITION";
        $jpeg = substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($jpeg, 2);

        $res = (new SseIdImageService($this->dir, true))->storeFile($this->file($jpeg), 'photo', 3);
        self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));
        $out = (string) file_get_contents($this->dir . '/' . $res['path']);
        self::assertStringNotContainsString('SECRET-POSITION', $out);
        self::assertStringNotContainsString('Exif', $out);
        $size = getimagesizefromstring($out);
        self::assertIsArray($size);
        self::assertEqualsWithDelta(35 / 45, $size[0] / $size[1], 0.01, 'recadrée au format 35 × 45');

        $wide = imagecreatetruecolor(3000, 1000);
        ob_start();
        imagepng($wide);
        $png = (string) ob_get_clean();
        imagedestroy($wide);
        $bg = (new SseIdImageService($this->dir, true))->storeFile($this->file($png), 'background', 3);
        self::assertTrue($bg['ok']);
        $size = getimagesizefromstring((string) file_get_contents($this->dir . '/' . $bg['path']));
        self::assertSame([1600, 533], [$size[0], $size[1]], 'fond réduit, pas recadré');
    }

    public function testImagesAreTenantScopedAndShownUnderTheSimulationWatermark(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        SseIdentitySqlite::open();
        SseIdMassGeneratorService::useAuditSink(static function (array $e): void {
        });
        try {
            $svc = new SseIdWorkshopService();
            $res = $svc->save(1, [
                'last_name' => 'Marek', 'first_name' => 'Ana', 'sex' => 'F', 'birth_date' => '1991-02-03', 'nationality' => 'ALT',
                'doc_type' => 'id_card', 'country_code' => 'ALT', 'doc_number' => '', 'layout' => 'custom',
            ], null, null, false, 3, 'uploads/sse-id-photos/1_aaaaaaaaaaaaaaaa.jpg');
            self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));
            $docs = new SseIdDocumentRepository();
            $ids = new SseIdentityRepository();
            $doc = (array) $docs->find((int) $res['id'], 1);
            self::assertSame('custom', $doc['layout']);
            $iid = (int) $doc['identity_id'];
            self::assertFalse($ids->setImages($iid, 2, ['signature_path' => 'uploads/sse-id-signatures/2_x.png']), 'autre communauté');
            self::assertFalse($docs->setBackground((int) $doc['id'], 2, 'uploads/sse-id-backgrounds/2_x.jpg'), 'autre communauté');
            self::assertTrue($ids->setImages($iid, 1, ['signature_path' => 'uploads/sse-id-signatures/1_bbbbbbbbbbbbbbbb.png']));
            self::assertTrue($docs->setBackground((int) $doc['id'], 1, 'uploads/sse-id-backgrounds/1_cccccccccccccccc.jpg'));
            $identity = (array) $ids->find($iid, 1);
            self::assertSame('uploads/sse-id-photos/1_aaaaaaaaaaaaaaaa.jpg', $identity['photo_path'], 'signature sans toucher à la photo');
            self::assertSame('uploads/sse-id-signatures/1_bbbbbbbbbbbbbbbb.png', $identity['signature_path']);
            $doc = (array) $docs->find((int) $doc['id'], 1);
            $copy = (array) $docs->find((int) $svc->duplicate(1, $doc, 3), 1);
            self::assertSame($doc['background_path'], $copy['background_path'], 'la copie garde le fond');

            $preview = SseIdWorkshopService::previewModel($doc, $identity, null);
            self::assertStringContainsString('1_aaaaaaaaaaaaaaaa.jpg', (string) $preview['identity']['photo_url']);
            self::assertStringContainsString('1_bbbbbbbbbbbbbbbb.png', (string) $preview['identity']['signature_url']);
            self::assertStringContainsString('1_cccccccccccccccc.jpg', (string) $preview['background_url']);
            $html = (static function (array $preview): string {
                ob_start();
                require dirname(__DIR__, 2) . '/views/atak/sse/papers/_doc_preview.php';

                return (string) ob_get_clean();
            })($preview);
            preg_match_all('#<figure class="idw-face[^"]*"[^>]*>(.*?)</figure>#s', $html, $faces);
            self::assertCount(5, $faces[1]);
            foreach ($faces[1] as $face) {
                $face = trim($face);
                self::assertStringStartsWith('<div class="idw-bg" data-f-bg', $face, 'fond en première couche');
                self::assertStringEndsWith('SIMULATION</span></div>', $face, 'filigrane en dernière couche');
                foreach (['data-f-photo', 'data-f-signature', 'data-f-bg'] as $img) {
                    if (str_contains($face, $img)) {
                        self::assertLessThan(strrpos($face, 'class="idw-watermark"'), strpos($face, $img), $img . ' sous le filigrane');
                    }
                }
            }
            self::assertStringContainsString('src="' . $preview['identity']['photo_url'] . '"', $html);
            // CSS : le filigrane a le plus grand z-index des faces.
            $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/sse-id-workshop.css');
            preg_match('#\.idw-face \.idw-watermark \{ z-index: (\d+); \}#', $css, $wm);
            preg_match_all('#z-index:\s*(\d+)#', $css, $all);
            self::assertSame(max(array_map('intval', $all[1])), (int) ($wm[1] ?? 0));
        } finally {
            SseIdMassGeneratorService::useAuditSink(null);
            SseIdentitySqlite::close();
        }
    }
}
