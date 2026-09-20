<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SseIndependentEdenContractAssetTest extends TestCase
{
    public function testStandaloneSseHonorsAuthoredIdentityAndVerdict(): void
    {
        $root = dirname(__DIR__, 2);
        $eden = (string) file_get_contents($root . '/mod/@COMSPEC_SSE/addons/eden/config.cpp');
        $helper = (string) file_get_contents($root . '/mod/@COMSPEC_SSE/addons/eden/functions/fn_edenWriteField.sqf');
        $query = (string) file_get_contents($root . '/mod/@COMSPEC_SSE/addons/biometrics/functions/fn_identifySubject.sqf');
        $content = (string) file_get_contents($root . '/mod/@COMSPEC_SSE/addons/generator/functions/fn_applyAuthoredContent.sqf');
        $owHelper = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseEdenWriteField.sqf'
        );
        $ver = (string) file_get_contents($root . '/mod/@COMSPEC_SSE/addons/main/script_mod.hpp');

        self::assertStringContainsString('class edenWriteField {}', $eden);
        self::assertStringContainsString("[_this, 'Preset', _value] call comspec_sse_fnc_edenWriteField", $eden);
        self::assertStringContainsString("[_this, 'LastName', _value] call comspec_sse_fnc_edenWriteField", $eden);
        self::assertStringContainsString("[_this, 'Nationality', _value] call comspec_sse_fnc_edenWriteField", $eden);
        self::assertStringContainsString('Recherché — correspondance confirmée', $eden);

        self::assertStringContainsString('COMSPEC_SSE_MatchResult', $helper);
        self::assertStringContainsString('comspec_sse_fnc_setIdentity', $helper);
        self::assertStringContainsString('recherche', $helper);

        self::assertStringContainsString('COMSPEC_SSE_MatchResult', $query);
        self::assertStringContainsString('Recherché — correspondance confirmée', $query);
        self::assertStringContainsString('Signalé — correspondance partielle', $query);
        self::assertStringContainsString('Inconnu des bases', $query);

        self::assertStringContainsString('COMSPEC_SSE_LastName', $content);
        self::assertStringContainsString('COMSPEC_SSE_FirstName', $content);

        self::assertStringContainsString('comspec_sse_fnc_edenWriteField', $owHelper);
        self::assertStringContainsString('0.7.22', $ver);
    }
}
