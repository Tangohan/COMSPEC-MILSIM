<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakSoundButtonsAssetTest extends TestCase
{
    public function testAtakButtonsKeepOpaqueTileFill(): void
    {
        $root = dirname(__DIR__, 2);
        $theme = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/atak_theme.hpp'
        );
        $sound = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/sound_page.hpp'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-08-28-atak-sons-boutons-transparents.md'
        );

        self::assertStringContainsString('animTextureNormal = "#(argb,8,8,3)color(1,1,1,1)"', $theme);
        self::assertStringNotContainsString('color(1,1,1,0)"', $theme);
        self::assertStringContainsString('SOUND_BTN_DIM {0.20, 0.20, 0.20, 1}', $sound);
        self::assertStringContainsString('text = "Tester"', $sound);
        self::assertStringContainsString('1.0.78', $cfg);
        self::assertStringContainsString('Sons', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
