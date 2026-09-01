<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakSoundsConsoleAssetTest extends TestCase
{
    public function testEventClipsExistOnDiskAndAreNotPreloadedAllAtOnce(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/atak-sounds.js');
        $ht = (string) file_get_contents($root . '/public/.htaccess');

        self::assertStringContainsString("a.preload = 'none'", $js);
        self::assertStringNotContainsString("a.preload = 'auto'", $js);
        self::assertStringContainsString('function primeClip(a)', $js);
        self::assertStringContainsString('primeClip(getAudio(pref));', $js);
        self::assertStringNotContainsString('Object.keys(EVENTS).forEach', $js);
        self::assertStringNotContainsString('atak_disconnect.ogg', $js);
        self::assertStringNotContainsString('medevac.mp3', $js);
        self::assertStringContainsString("disconnect: { file: 'atak_beep.ogg'", $js);
        self::assertStringContainsString("order: { file: 'atak_order_receive.ogg'", $js);
        self::assertStringContainsString("order_ack: { file: 'atak_deep_chime.ogg'", $js);

        self::assertStringContainsString('AddType audio/ogg .ogg', $ht);

        foreach ([
            'atak_order_receive.ogg',
            'atak_deep_chime.ogg',
            'atak_beep.ogg',
            'atak_start.ogg',
            'atak_no_activyt_health.ogg',
            'sound_1_stalker.ogg',
        ] as $file) {
            self::assertFileExists($root . '/public/assets/sounds/' . $file);
        }
    }

    public function testServiceWorkerLetsAudioBypassTheShellCache(): void
    {
        $sw = (string) file_get_contents(dirname(__DIR__, 2) . '/public/sw.js');
        self::assertStringContainsString("path.indexOf('/assets/sounds/')", $sw);
        self::assertStringContainsString("dest === 'audio'", $sw);
        self::assertStringContainsString("headers.get('range')", $sw);
        self::assertStringContainsString("ct.indexOf('audio/')", $sw);
        self::assertStringContainsString('athena-shell-v7', $sw);
    }
}
