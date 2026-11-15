<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Media\SignatureImageService;
use App\Services\Sse\SseIdImageService;
use App\Services\Sse\SseSignatureStrokes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Signature tracée au SEEK II : validation stricte des traits, rendu PNG sans GD, rangement par le service du pad.
 */
final class SseSignatureStrokesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'seeksig_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->dir);
    }

    /** @return array<string, mixed> */
    private static function good(): array
    {
        $pts = [];
        for ($i = 0; $i < 40; $i++) {
            $pts[] = [$i * 20, 500 + (int) (300 * sin($i / 4))];
        }

        return ['strokes' => [$pts, [[100, 100], [300, 800], [500, 200]]], 'aspect' => 2.5];
    }

    public function testValidSignatureIsAccepted(): void
    {
        $v = SseSignatureStrokes::validate(self::good());
        $this->assertTrue($v['ok']);
        $this->assertCount(2, $v['strokes']);
        $this->assertSame(2.5, $v['aspect']);
    }

    /** @return iterable<string, array{0: mixed}> */
    public static function badProvider(): iterable
    {
        $g = self::good();
        yield 'pas un tableau' => ['x'];
        yield 'sans traits' => [['aspect' => 2.5]];
        yield 'traits vides' => [['strokes' => []]];
        yield 'point hors borne' => [['strokes' => [[[0, 0], [1001, 5], [10, 10], [20, 20], [30, 30], [40, 40]]]]];
        yield 'point negatif' => [['strokes' => [[[0, -1], [5, 5], [10, 10], [20, 20], [30, 30], [40, 40]]]]];
        yield 'point texte' => [['strokes' => [[['a', 'b'], [5, 5], [10, 10], [20, 20], [30, 30], [40, 40]]]]];
        yield 'point a trois valeurs' => [['strokes' => [[[1, 2, 3], [5, 5], [10, 10], [20, 20], [30, 30], [40, 40]]]]];
        yield 'trop court' => [['strokes' => [[[1, 1], [2, 2]]]]];
        yield 'point immobile' => [['strokes' => [array_fill(0, 10, [500, 500])]]];
        yield 'aspect nul' => [['strokes' => $g['strokes'], 'aspect' => 0]];
        yield 'aspect enorme' => [['strokes' => $g['strokes'], 'aspect' => 99]];
        yield 'trop de traits' => [['strokes' => array_fill(0, SseSignatureStrokes::MAX_STROKES + 1, [[1, 1], [900, 900]])]];
        yield 'trait trop long' => [['strokes' => [array_fill(0, SseSignatureStrokes::MAX_POINTS_PER_STROKE + 1, [1, 900])]]];
        yield 'trop de points au total' => [['strokes' => array_fill(0, 3, array_map(static fn (int $i): array => [$i, $i], range(0, 300)))]];
    }

    #[DataProvider('badProvider')]
    public function testInvalidSignatureIsRefused(mixed $raw): void
    {
        $this->assertFalse(SseSignatureStrokes::validate($raw)['ok']);
    }

    public function testRenderedPngPassesTheDrawnSignatureControls(): void
    {
        $v = SseSignatureStrokes::validate(self::good());
        $url = SseSignatureStrokes::renderDataUrl($v['strokes'], $v['aspect']);
        $this->assertStringStartsWith(SignatureImageService::PREFIX, $url);
        $this->assertTrue(SignatureImageService::normalize($url)['ok']);
    }

    public function testStoreWritesAPngUnderTheIdentitySignatureFolder(): void
    {
        $svc = new SignatureImageService(new SseIdImageService($this->dir, false));
        $res = SseSignatureStrokes::store(self::good(), 7, $svc);
        $this->assertTrue($res['ok'], (string) ($res['error'] ?? ''));
        $this->assertMatchesRegularExpression('#^uploads/sse-id-signatures/7_[0-9a-f]+\.png$#', (string) $res['path']);
        $this->assertFileExists($this->dir . '/' . $res['path']);
    }

    public function testStoreRefusesBadInputAndBadTenant(): void
    {
        $svc = new SignatureImageService(new SseIdImageService($this->dir, false));
        $this->assertFalse(SseSignatureStrokes::store(['strokes' => [[[1, 2]]]], 7, $svc)['ok']);
        $this->assertFalse(SseSignatureStrokes::store(self::good(), 0, $svc)['ok']);
    }
}
