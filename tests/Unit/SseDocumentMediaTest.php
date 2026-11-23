<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Media\SignatureImageService;
use App\Services\Sse\SseDocumentMedia;
use App\Services\Sse\SseIdImageService;
use App\Support\SseImageStorage;
use App\Support\SseRichText;
use PHPUnit\Framework\TestCase;

/**
 * Fichiers des documents SSE (sans base) : images et logos insérés (PNG, JPEG, WebP seulement, type lu dans le contenu, jamais SVG),
 * rangés dans le stockage privé sous un nom aléatoire préfixé par la communauté, jamais lus pour une autre communauté ; dessin du
 * pad de signature validé puis rangé au même endroit.
 */
final class SseDocumentMediaTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssemedia_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->dir);
    }

    private function rrmdir(string $d): void
    {
        if ($d === '' || !is_dir($d)) {
            return;
        }
        foreach (scandir($d) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') {
                $p = $d . DIRECTORY_SEPARATOR . $f;
                is_dir($p) ? $this->rrmdir($p) : @unlink($p);
            }
        }
        @rmdir($d);
    }

    /** PNG RVBA 8 bits sans entrelacement, $ink pixels opaques en haut à gauche. */
    public static function png(int $w = 40, int $h = 20, int $ink = 80): string
    {
        $raw = '';
        $n = 0;
        for ($y = 0; $y < $h; $y++) {
            $raw .= "\x00";
            for ($x = 0; $x < $w; $x++) {
                if ($n < $ink) {
                    $raw .= "\x10\x20\x60\xff";
                    $n++;
                } else {
                    $raw .= "\x00\x00\x00\x00";
                }
            }
        }
        $chunk = static fn (string $t, string $d): string => pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d));

        return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 6, 0, 0, 0)) . $chunk('IDAT', (string) gzcompress($raw)) . $chunk('IEND', '');
    }

    private function media(): SseDocumentMedia
    {
        return new SseDocumentMedia(new SseIdImageService($this->dir . '/public', false, $this->dir . '/private'));
    }

    private function priv(): string
    {
        return $this->dir . '/private';
    }

    public function testPngIsStoredPrivatelyUnderARandomTenantPrefixedName(): void
    {
        $r = $this->media()->storeBytes(self::png(), 12);
        self::assertTrue($r['ok'], (string) ($r['error'] ?? ''));
        self::assertMatchesRegularExpression(SseRichText::MEDIA_TOKEN, (string) $r['token']);
        self::assertStringStartsWith('12_', (string) $r['token']);
        $file = SseDocumentMedia::fileFor((string) $r['token'], 12, $this->priv());
        self::assertNotNull($file);
        self::assertStringContainsString('sse-doc-media', (string) $file);
        self::assertFalse(str_contains(str_replace('\\', '/', (string) $file), '/public/'), 'jamais sous le dossier public');
        $second = $this->media()->storeBytes(self::png(), 12);
        self::assertNotSame($r['token'], $second['token'], 'nom aléatoire');
    }

    public function testOtherCommunityCannotReadAToken(): void
    {
        $r = $this->media()->storeBytes(self::png(), 12);
        self::assertNull(SseDocumentMedia::fileFor((string) $r['token'], 13, $this->priv()));
        self::assertNull(SseDocumentMedia::fileFor('12_../../etc/passwd', 12, $this->priv()));
        self::assertNull(SseDocumentMedia::fileFor('', 12, $this->priv()));
    }

    public function testSvgGifPhpAndHtmlAreRefusedWhateverTheirName(): void
    {
        $evil = [
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle r="5"/></svg>',
            'gif' => "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;",
            'php' => '<?php system($_GET["c"]); ?>',
            'html' => '<html><script>alert(1)</script></html>',
            'png-header-only' => "\x89PNG\r\n\x1a\n<?php echo 1;",
        ];
        foreach ($evil as $name => $bytes) {
            $r = $this->media()->storeBytes($bytes, 12);
            self::assertFalse($r['ok'], $name);
            self::assertFalse(isset($r['token']), $name);
        }
        self::assertSame([], glob($this->priv() . '/sse-doc-media/*') ?: [], 'rien n’est écrit sur le disque');
    }

    public function testOversizedImageIsRefused(): void
    {
        $big = self::png(40, 20) . str_repeat("\0", 3 * 1024 * 1024 + 10);
        self::assertFalse($this->media()->storeBytes($big, 12)['ok']);
    }

    public function testExpandReplacesTheBlockByAnImageOnlyForTheOwnCommunity(): void
    {
        $r = $this->media()->storeBytes(self::png(), 12);
        $html = SseRichText::sanitize('<p>Avant</p><div class="sse-media" data-media="' . $r['token'] . '"></div><p>Après</p>');
        self::assertStringContainsString('data-media="' . $r['token'] . '"', $html);
        $pdf = SseDocumentMedia::expand($html, 12, 'pdf', $this->priv());
        self::assertStringContainsString('<img src="', $pdf);
        self::assertStringNotContainsString('sse-media', $pdf);
        $other = SseDocumentMedia::expand($html, 99, 'pdf', $this->priv());
        self::assertStringNotContainsString('<img', $other, 'autre communauté : aucune image');
        self::assertStringNotContainsString($r['token'], $other);
    }

    public function testSanitizerKeepsOnlyAValidMediaTokenAndNoImageTag(): void
    {
        $out = SseRichText::sanitize(
            '<div class="sse-media" data-media="12_0123456789abcdef" data-x="1" onclick="a()"></div>'
            . '<div class="sse-media" data-media="javascript:alert(1)"></div>'
            . '<div class="sse-media" data-media="12_0123456789abcdef&quot; onerror=&quot;x"></div>'
            . '<img src="https://evil.example/x.png"><span class="sse-media" data-media="12_0123456789abcdef">t</span>'
        );
        self::assertSame(1, substr_count($out, 'data-media='), $out);
        self::assertStringNotContainsString('<img', $out);
        self::assertStringNotContainsString('onclick', $out);
        self::assertStringNotContainsString('data-x', $out);
    }

    public function testSignatureDrawingGoesToThePrivateSignatureFolderAndIsNeverHtml(): void
    {
        $svc = new SignatureImageService(new SseIdImageService($this->dir . '/public', false, $this->priv()));
        $r = $svc->fromDataUrl(SignatureImageService::PREFIX . base64_encode(self::png(120, 40, 200)), 5, 'sse_document');
        self::assertTrue($r['ok'], (string) ($r['error'] ?? ''));
        self::assertMatchesRegularExpression('~^sse-private/sse-doc-signatures/5_[a-f0-9]{16}\.png$~', (string) $r['path']);
        self::assertTrue(SseImageStorage::isPrivatePath((string) $r['path']));
        self::assertNotNull(SseImageStorage::absolute((string) $r['path'], null, $this->priv()));
    }

    public function testSignatureRefusesNonPngEmptyAndSvgDataUrls(): void
    {
        $svc = new SignatureImageService(new SseIdImageService($this->dir . '/public', false, $this->priv()));
        $bad = [
            'svg' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>'),
            'jpeg' => 'data:image/jpeg;base64,' . base64_encode("\xff\xd8\xff\xe0abc"),
            'vide' => SignatureImageService::PREFIX . base64_encode(self::png(40, 20, 0)),
            'texte' => SignatureImageService::PREFIX . base64_encode('pas une image'),
        ];
        foreach ($bad as $name => $url) {
            self::assertFalse($svc->fromDataUrl($url, 5, 'sse_document')['ok'], $name);
        }
        self::assertFalse($svc->fromDataUrl(SignatureImageService::PREFIX . base64_encode(self::png(120, 40, 200)), 5, 'inconnu')['ok'], 'usage inconnu');
    }
}
