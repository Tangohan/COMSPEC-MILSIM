<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

final class SseEdenProfileContractAssetTest extends TestCase
{
    /**
     * Le module « Profil d'identité SSE » et les attributs Eden SSE ont quitté l'addon
     * Overwatch connect pour le pack autonome @COMSPEC_SSE (zeus / eden). Le contrat reste
     * le même : module et attributs d'unité écrivent par un seul helper (edenWriteField).
     */
    public function testModuleAndUnitAttributesShareWriteHelper(): void
    {
        $root = dirname(__DIR__, 2);
        $sse = $root . '/mod/@COMSPEC_SSE/addons';
        $mod = (string) file_get_contents($sse . '/zeus/modules_seek.hpp');
        $edenCfg = (string) file_get_contents($sse . '/eden/config.cpp');
        $helper = (string) file_get_contents($sse . '/eden/functions/fn_edenWriteField.sqf');
        $apply = (string) file_get_contents($sse . '/zeus/functions/fn_moduleSubjectProfile.sqf');
        $zeusCfg = (string) file_get_contents($sse . '/zeus/config.cpp');

        $owRoot = $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';
        $cfg = (string) file_get_contents($owRoot . '/config.cpp');
        $owHelper = (string) file_get_contents($owRoot . '/functions/fn_sseEdenWriteField.sqf');
        $owEden = (string) file_get_contents($owRoot . '/modules/eden_sse_attributes.hpp');
        $relay = (string) file_get_contents($owRoot . '/modules/module_atak_relay.hpp');

        // Overwatch garde son helper (compatibilité) et ne duplique plus les attributs SSE.
        self::assertStringContainsString('class sseEdenWriteField {}', $cfg);
        ModVersion::assertAtLeast('1.6.9', ModVersion::fromConfigCpp($cfg));
        self::assertStringContainsString('COMSPEC_SSE_', $owHelper);
        self::assertStringContainsString('sseProfilePreset', $owHelper);
        self::assertStringContainsString('sseApplyProfile', $owHelper);
        self::assertStringContainsString('CAManBase', $owHelper);
        self::assertStringContainsString('pack', $owEden);
        self::assertStringContainsString('COMSPEC SSE', $owEden);

        // Pack SSE : module Zeus/Eden et attributs d'unité passent par le même helper.
        self::assertStringContainsString('class edenWriteField {}', $edenCfg);
        self::assertStringContainsString('class moduleSubjectProfile {}', $zeusCfg);
        self::assertStringContainsString('comspec_sse_fnc_moduleSubjectProfile', $mod);
        self::assertStringContainsString("[_this, 'Preset', _value] call comspec_sse_fnc_edenWriteField", $mod);
        self::assertStringContainsString("[_this, 'LastName', _value] call comspec_sse_fnc_edenWriteField", $mod);
        self::assertStringContainsString("[_this, 'Alias', _value] call comspec_sse_fnc_edenWriteField", $mod);
        self::assertStringContainsString("[_this, 'Preset', _value] call comspec_sse_fnc_edenWriteField", $edenCfg);
        self::assertStringContainsString("[_this, 'LastName', _value] call comspec_sse_fnc_edenWriteField", $edenCfg);

        self::assertStringNotContainsString("setVariable ['LastName'", $mod);
        self::assertStringNotContainsString("setVariable ['Preset'", $mod);

        self::assertStringContainsString('COMSPEC_SSE_', $helper);
        self::assertStringContainsString('comspec_sse_fnc_profilePreset', $helper);
        self::assertStringContainsString('comspec_sse_fnc_applyProfile', $helper);
        self::assertStringContainsString('CAManBase', $helper);

        self::assertStringContainsString('COMSPEC_SSE_LastName', $apply);
        self::assertStringContainsString('COMSPEC_SSE_Preset', $apply);
        self::assertStringContainsString('comspec_sse_fnc_applyProfile', $apply);
        self::assertStringContainsString('is3DEN', $apply);

        self::assertStringContainsString('canSetArea = 0;', $relay);
        self::assertStringContainsString('canSetAreaHeight = 0;', $relay);
        self::assertStringContainsString('canSetAreaShape = 0;', $relay);
    }
}
