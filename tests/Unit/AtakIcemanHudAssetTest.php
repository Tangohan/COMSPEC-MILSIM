<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakIcemanHudAssetTest extends TestCase
{
    public function testMapHudStaysOnTheMapPane(): void
    {
        $root = dirname(__DIR__, 2);
        $upd = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf'
        );
        $install = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installMapHud.sqf'
        );
        $zoom = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_mapHudZoom.sqf'
        );
        $layout = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $post = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-01-atak-iceman-hud-chrome.md'
        );
        $changelog = (string) file_get_contents($root . '/CHANGELOG-ATAK.md');

        self::assertStringContainsString('athena_installMapHud', $cfg);
        self::assertStringContainsString('athena_updateMapHud', $cfg);
        self::assertStringContainsString('athena_mapHudZoom', $cfg);
        self::assertStringContainsString('1.0.58', $cfg);
        self::assertStringContainsString('athena_installMapHud', $post);
        self::assertStringContainsString('athena_updateMapHud', $layout);

        self::assertStringContainsString('DST', $upd);
        self::assertStringContainsString('ELEV', $upd);
        self::assertStringContainsString('BRG', $upd);
        self::assertStringContainsString('GROUP', $upd);
        self::assertStringContainsString('CALLSIGN', $upd);
        self::assertStringContainsString('#5EC7F2', $upd);
        self::assertStringContainsString('99887811', $upd);
        self::assertStringContainsString('_mx + _pad', $upd);
        self::assertStringContainsString('ctrlPosition _mapCtrl', $upd);
        self::assertStringContainsString('4660', $upd);

        self::assertStringNotContainsString('MQ-9', $upd);
        self::assertStringNotContainsString('RETASK', $upd);
        self::assertStringNotContainsString('POWER OFF', $upd);
        self::assertStringNotContainsString('LOCK SPI', $install);
        self::assertStringNotContainsString('LOCK SPI', $upd);

        self::assertStringContainsString('ctrlMapAnimAdd', $zoom);
        self::assertStringContainsString('cartouches', $bug);
        self::assertStringContainsString('tiroir', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
        self::assertStringContainsString('fond charbon', $changelog);
        self::assertStringContainsString('chiffres cyan', $changelog);
        self::assertStringContainsString('pas recouvert', $changelog);
    }
}
