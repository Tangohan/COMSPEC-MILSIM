<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakSettingsScrollTopAssetTest extends TestCase
{
    public function testSettingsOpenOnIdentityCardWithoutAutoscroll(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena';
        $page = (string) file_get_contents($root . '/ui/settings_page.hpp');
        $opened = (string) file_get_contents($root . '/functions/fn_athena_settingsOnOpened.sqf');
        $update = (string) file_get_contents($root . '/functions/fn_athena_updateSettings.sqf');
        $cfg = (string) file_get_contents($root . '/config.cpp');
        $note = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/bugs/2026-09-19-parametres-scroll-bas.md');

        self::assertStringContainsString('versionStr = "1.0.153"', $cfg);
        self::assertStringContainsString('autoScrollEnabled = 0', $page);
        self::assertStringContainsString('Votre fiche', $page);
        self::assertStringContainsString('ctrlSetScrollValues [0, -1]', $opened);
        self::assertStringContainsString('Votre fiche', $update);
        self::assertGreaterThan(
            strpos($page, 'idc = 9846'),
            strpos($page, 'class BodyScroll')
        );
        self::assertStringContainsString('idc = 9842', $page);
        self::assertStringContainsString('Liaison au poste', $page);
        self::assertStringContainsString('scroll-bas', $note);
    }
}
