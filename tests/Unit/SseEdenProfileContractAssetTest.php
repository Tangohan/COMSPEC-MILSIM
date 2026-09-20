<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SseEdenProfileContractAssetTest extends TestCase
{
    public function testModuleAndUnitAttributesShareWriteHelper(): void
    {
        $root = dirname(__DIR__, 2);
        $mod = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_sse.hpp'
        );
        $eden = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/eden_sse_attributes.hpp'
        );
        $helper = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseEdenWriteField.sqf'
        );
        $apply = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_moduleSseProfile.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        $relay = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_atak_relay.hpp'
        );

        self::assertStringContainsString('class sseEdenWriteField {}', $cfg);
        self::assertStringContainsString('versionStr = "1.6.4"', $cfg);

        self::assertStringContainsString("[_this, 'Preset', _value] call comspec_overwatch_connect_fnc_sseEdenWriteField", $mod);
        self::assertStringContainsString("[_this, 'LastName', _value] call comspec_overwatch_connect_fnc_sseEdenWriteField", $mod);
        self::assertStringContainsString("[_this, 'Alias', _value] call comspec_overwatch_connect_fnc_sseEdenWriteField", $mod);
        self::assertStringContainsString("[_this, 'Preset', _value] call comspec_overwatch_connect_fnc_sseEdenWriteField", $eden);
        self::assertStringContainsString("[_this, 'LastName', _value] call comspec_overwatch_connect_fnc_sseEdenWriteField", $eden);

        self::assertStringNotContainsString("setVariable ['LastName'", $mod);
        self::assertStringNotContainsString("setVariable ['Preset'", $mod);

        self::assertStringContainsString('COMSPEC_SSE_', $helper);
        self::assertStringContainsString('sseProfilePreset', $helper);
        self::assertStringContainsString('sseApplyProfile', $helper);
        self::assertStringContainsString('CAManBase', $helper);

        self::assertStringContainsString('COMSPEC_SSE_LastName', $apply);
        self::assertStringContainsString('COMSPEC_SSE_Preset', $apply);
        self::assertStringContainsString('sseApplyProfile', $apply);
        self::assertStringContainsString('is3DEN', $apply);

        self::assertStringContainsString('canSetArea = 0;', $relay);
        self::assertStringContainsString('canSetAreaHeight = 0;', $relay);
        self::assertStringContainsString('canSetAreaShape = 0;', $relay);
    }
}
