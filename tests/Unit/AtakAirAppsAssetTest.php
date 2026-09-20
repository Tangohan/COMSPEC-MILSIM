<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakAirAppsAssetTest extends TestCase
{
    public function testCasAndManifestAreNativeAtakApps(): void
    {
        $root = dirname(__DIR__, 2);
        $cfgA = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp');
        $cfgC = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $casPage = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/cas_page.hpp');
        $mfPage = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/manifest_page.hpp');
        $showCas = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_casRequestShow.sqf');
        $showMf = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_flightManifestShow.sqf');
        $hide = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_hideForeignPages.sqf');
        $wiki = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateWiki.sqf');

        self::assertStringContainsString('versionStr = "1.0.158"', $cfgA);
        self::assertStringContainsString('versionStr = "1.6.4"', $cfgC);
        self::assertStringContainsString('text = "<t size=\'1\'>Appui aérien</t>"', $cfgA);
        self::assertStringContainsString('text = "<t size=\'1\'>Manifeste</t>"', $cfgA);
        self::assertStringContainsString('PAGE_CTRL = "COMSPEC_ATAK_Cas"', $cfgA);
        self::assertStringContainsString('PAGE_CTRL = "COMSPEC_ATAK_Manifest"', $cfgA);
        self::assertStringContainsString('class COMSPEC_ATAK_Cas', $casPage);
        self::assertStringContainsString('idc = 9701', $casPage);
        self::assertStringContainsString('class COMSPEC_ATAK_Manifest', $mfPage);
        self::assertStringContainsString('idc = 1507', $mfPage);
        self::assertStringContainsString('athena_openCas', $showCas);
        self::assertStringContainsString('athena_openManifest', $showMf);
        self::assertStringContainsString('atakcas', $hide);
        self::assertStringContainsString('atakmanifest', $hide);
        self::assertStringContainsString('Appui aérien et manifeste', $wiki);
        self::assertStringNotContainsString('json', strtolower($wiki));
    }
}
