<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakAppDistinctIconsAssetTest extends TestCase
{
    public function testAthenaAppsUseDistinctLocalIcons(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena';
        $cfg = (string) file_get_contents($root . '/config.cpp');
        $icons = $root . '/data/icons';

        self::assertStringContainsString('versionStr = "1.0.152"', $cfg);
        self::assertStringNotContainsString('instructor_ca.paa', $cfg);
        self::assertStringContainsString('app_athena_ca.paa', $cfg);
        self::assertStringContainsString('app_briefing_ca.paa', $cfg);
        self::assertStringContainsString('app_wiki_ca.paa', $cfg);
        self::assertStringContainsString('app_comms_ca.paa', $cfg);
        self::assertStringContainsString('app_bda_ca.paa', $cfg);
        foreach ([
            'app_athena_ca.paa',
            'app_briefing_ca.paa',
            'app_wiki_ca.paa',
            'app_comms_ca.paa',
            'app_bda_ca.paa',
        ] as $file) {
            self::assertFileExists($icons . '/' . $file);
            self::assertGreaterThan(1000, filesize($icons . '/' . $file));
        }
    }
}
