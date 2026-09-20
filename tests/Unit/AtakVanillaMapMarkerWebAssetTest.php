<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ArmaMarkerLabel;
use PHPUnit\Framework\TestCase;

final class AtakVanillaMapMarkerWebAssetTest extends TestCase
{
    public function testUserMapMarkerIsForcedToThePostOnMapClose(): void
    {
        $root = dirname(__DIR__, 2);
        $syncUser = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncUserMapMarkers.sqf');
        $resolve = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_resolveMarkerEhName.sqf');
        $sync = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncMapMarker.sqf');
        $resync = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_resyncAllMapMarkers.sqf');
        $loops = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf');
        $xeh = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf');
        $athena = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf');
        $cfgC = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $cfgA = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp');
        $note = (string) file_get_contents($root . '/docs/bugs/2026-09-20-marqueur-carte-arma-web.md');

        self::assertStringContainsString('class syncUserMapMarkers {}', $cfgC);
        self::assertStringContainsString('class resolveMarkerEhName {}', $cfgC);
        self::assertStringContainsString('versionStr = "1.6.5"', $cfgC);
        self::assertStringContainsString('versionStr = "1.0.160"', $cfgA);

        self::assertStringContainsString('_user_defined', $syncUser);
        self::assertStringContainsString('syncMapMarker', $syncUser);

        self::assertStringContainsString('_p0 isEqualType ""', $resolve);

        self::assertStringContainsString('__H__', $sync);
        self::assertStringContainsString('_wireName', $sync);

        self::assertStringContainsString('_next set [_name, ""]', $resync);

        self::assertStringContainsString('EH Map fermée', $xeh);
        self::assertStringContainsString('syncUserMapMarkers', $xeh);
        self::assertStringContainsString('resolveMarkerEhName', $xeh);

        self::assertStringContainsString('syncUserMapMarkers', $loops);

        self::assertStringContainsString('if (_sent) then', $athena);

        self::assertTrue(ArmaMarkerLabel::isTechnicalName('_USER_DEFINED #0/1/0'));
        self::assertTrue(ArmaMarkerLabel::isTechnicalName('_USER_DEFINED __H__0/1/0'));
        self::assertSame('Repère tactique', ArmaMarkerLabel::displayLabel('_USER_DEFINED #0/1/0', ['type' => 'mil_dot']));

        self::assertStringContainsString('fermer la carte', strtolower($note));
        self::assertStringNotContainsString('endpoint', $note);
    }
}
