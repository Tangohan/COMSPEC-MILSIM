<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class AtakPlainTextAssetTest extends TestCase
{
    public function testAtakPagesUseIcemanStructuredRender(): void
    {
        $root = dirname(__DIR__, 2);
        $fnDir = $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions';
        $helper = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_setPlainText.sqf'
        );
        $connectCfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        $athenaCfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $theme = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/atak_theme.hpp'
        );

        self::assertStringContainsString('ctrlSetStructuredText parseText', $helper);
        self::assertStringContainsString('case 38:', $helper);
        self::assertStringContainsString('case 60:', $helper);
        self::assertStringContainsString('case 62:', $helper);
        self::assertStringContainsString('_w < 0.02', $helper);
        self::assertStringContainsString('class setPlainText {}', $connectCfg);
        self::assertStringContainsString('1.0.165', $athenaCfg);
        self::assertStringContainsString('COMSPEC_ATAK_StructuredText: RscStructuredText', $theme);
        self::assertStringContainsString('Iceman_ReportsDetailText', $theme);

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fnDir));
        foreach ($it as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'sqf') {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            self::assertStringNotContainsString(
                'ctrlSetStructuredText parseText',
                $src,
                $file->getFilename()
            );
        }
    }
}
