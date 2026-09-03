<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HatchetCockpitAceGuardAssetTest extends TestCase
{
    public function testOverwatchAndSseReleaseHatchetCockpitControls(): void
    {
        $root = dirname(__DIR__, 2);
        $isHatchet = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_isHatchetVehicle.sqf');
        $hide = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_hideAceMenu.sqf');
        $track = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initVehicleTracking.sqf');
        $exploit = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/sse_ace/functions/fn_sseCanExploit.sqf');
        $inspect = (string) file_get_contents($root . '/mod/@COMSPEC_SSE/addons/interaction/functions/fn_canInspect.sqf');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $sseCfg = (string) file_get_contents($root . '/mod/@COMSPEC_SSE/addons/core/config.cpp');

        self::assertStringContainsString('"hct"', $isHatchet);
        self::assertStringContainsString('ace_interact_menu_fnc_hideMenu', $hide);
        self::assertStringContainsString('hideAceMenu', $track);
        self::assertStringContainsString('isHatchetVehicle', $exploit);
        self::assertStringContainsString('playerInHatchetVehicle', $inspect);
        self::assertStringContainsString('class isHatchetVehicle {};', $cfg);
        self::assertStringContainsString('class hideAceMenu {};', $cfg);
        self::assertStringContainsString('class isHatchetVehicle {};', $sseCfg);
        self::assertStringContainsString('versionStr = "1.5.16"', $cfg);
    }
}
