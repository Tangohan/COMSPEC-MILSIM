<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Media\VideoSourceProbe;
use PHPUnit\Framework\TestCase;

final class VideoSourceProbeTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vsprobe_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function testRejectsQuickTimeHevcAsUnplayable(): void
    {
        $path = $this->writeBlob('hevc.mp4', $this->ftypBox('qt  ') . str_repeat("\0", 64) . 'hvc1' . str_repeat("\0", 32) . 'mp4a');
        $result = VideoSourceProbe::inspect($path);

        self::assertFalse($result['playable']);
        self::assertSame('hvc1', $result['codec']);
        self::assertSame('qt', $result['brand']);
        self::assertStringContainsString('video/quicktime', $result['mime']);
        self::assertStringContainsString('codecs="hvc1"', $result['mime']);
    }

    public function testAcceptsIsoBmffH264AsPlayable(): void
    {
        $path = $this->writeBlob('h264.mp4', $this->ftypBox('isom') . str_repeat("\0", 64) . 'avc1' . str_repeat("\0", 32) . 'mp4a');
        $result = VideoSourceProbe::inspect($path);

        self::assertTrue($result['playable']);
        self::assertSame('avc1', $result['codec']);
        self::assertStringContainsString('video/mp4', $result['mime']);
        self::assertStringContainsString('codecs="avc1"', $result['mime']);
    }

    public function testWebmIsTrustedWithoutDeepProbe(): void
    {
        $path = $this->writeBlob('clip.webm', "\x1a\x45\xdf\xa3fake-webm");
        $result = VideoSourceProbe::inspect($path);

        self::assertTrue($result['playable']);
        self::assertSame('video/webm', $result['mime']);
    }

    public function testMissingFileFallsBackOptimistically(): void
    {
        $result = VideoSourceProbe::inspect($this->tmpDir . DIRECTORY_SEPARATOR . 'missing.mp4');

        self::assertTrue($result['playable']);
        self::assertSame('video/mp4', $result['mime']);
        self::assertNull($result['codec']);
    }

    private function ftypBox(string $brand): string
    {
        $brand = str_pad(substr($brand, 0, 4), 4, ' ');
        // size(4) + 'ftyp' + major_brand(4) + minor_version(4)
        return pack('N', 16) . 'ftyp' . $brand . pack('N', 0);
    }

    private function writeBlob(string $name, string $bytes): string
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $bytes);

        return $path;
    }
}
