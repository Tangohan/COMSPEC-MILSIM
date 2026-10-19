<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MusicGameCheck;
use PHPUnit\Framework\TestCase;

final class MusicGameCheckTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/music_game_check_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    /** 20 trames MPEG-1 Layer III, 128 kb/s, 44,1 kHz (417 octets chacune). */
    private static function frames(int $n = 20): string
    {
        return str_repeat("\xFF\xFB\x90\x00" . str_repeat("\x00", 413), $n);
    }

    /** Balise ID3v2.3 de $size octets (taille « syncsafe »). */
    private static function id3(int $size): string
    {
        $syncsafe = chr(($size >> 21) & 0x7F) . chr(($size >> 14) & 0x7F) . chr(($size >> 7) & 0x7F) . chr($size & 0x7F);

        return 'ID3' . "\x03\x00\x00" . $syncsafe . str_repeat("\xFF", $size);
    }

    private function file(string $data, string $name = 'a.mp3'): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $data);

        return $path;
    }

    public function testPlainMp3IsOk(): void
    {
        $path = $this->file(self::frames());
        $r = MusicGameCheck::check($path, 'mp3');
        self::assertSame(MusicGameCheck::OK, $r['status']);
        self::assertSame(self::frames(), file_get_contents($path));
    }

    public function testBigCoverTagIsStripped(): void
    {
        // Pochette de 300 Ko pleine d'octets 0xFF (faux débuts de trame) : le son commence après.
        $path = $this->file(self::id3(300 * 1024) . self::frames());
        self::assertSame(10 + 300 * 1024, MusicGameCheck::firstFrameOffset((string) file_get_contents($path)));
        $r = MusicGameCheck::check($path, 'mp3');
        self::assertSame(MusicGameCheck::FIXED, $r['status']);
        self::assertSame(self::frames(), file_get_contents($path));
        self::assertSame(MusicGameCheck::OK, MusicGameCheck::check($path, 'mp3')['status']);
    }

    public function testCheckWithoutRepairLeavesFile(): void
    {
        $data = self::id3(2048) . self::frames();
        $path = $this->file($data);
        self::assertSame(MusicGameCheck::WARN, MusicGameCheck::check($path, 'mp3', false)['status']);
        self::assertSame($data, file_get_contents($path));
    }

    public function testRenamedM4aIsRefused(): void
    {
        $path = $this->file("\x00\x00\x00\x20ftypM4A " . str_repeat("\x00", 4000));
        $r = MusicGameCheck::check($path, 'mp3');
        self::assertSame(MusicGameCheck::BAD, $r['status']);
        self::assertStringContainsString('M4A', $r['message']);
    }

    public function testNoAudioFramesIsRefused(): void
    {
        $path = $this->file(self::id3(100) . str_repeat("\x12\x34", 4000));
        self::assertSame(MusicGameCheck::BAD, MusicGameCheck::check($path, 'mp3')['status']);
    }

    public function testFrameLength(): void
    {
        self::assertSame(417, MusicGameCheck::frameLength("\xFF\xFB\x90\x00", 0));
        self::assertSame(0, MusicGameCheck::frameLength("\xFF\xFB\xF0\x00", 0));
    }
}
