<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * SEEK ADN / iris / empreintes : kits BII [SSE] requis, animation à genoux, écran refermé puis rouvert.
 */
final class SseBiiKitCollectAssetTest extends TestCase
{
    public function testSeekCollectRequiresBiiKitsAndClosesDevice(): void
    {
        $sqf = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseBiometricSample.sqf'
        );
        $hpp = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_sse_person.hpp'
        );
        $onLoad = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogOnLoad.sqf'
        );

        self::assertStringContainsString('DNACollectionKit', $sqf);
        self::assertStringContainsString('EyeScannerKit', $sqf);
        self::assertStringContainsString('FingerprintCollectionKit', $sqf);
        self::assertStringContainsString('FingerprintScannerKit', $sqf);
        self::assertStringContainsString('AinvPknlMstpSnonWnonDnon_medicUp1', $sqf);
        self::assertStringContainsString('AmovPknlMstpSnonWnonDnon', $sqf);
        self::assertStringContainsString('COMSPEC_SsePerson_SuspendUnload', $sqf);
        self::assertStringContainsString('closeDisplay 2', $sqf);
        self::assertStringContainsString('COMSPEC_SsePerson_ResumeCollect', $sqf);
        self::assertStringContainsString('DNADraw', $sqf);
        self::assertStringContainsString('EyeScannerDraw', $sqf);
        self::assertStringContainsString('COMSPEC_SsePerson_SuspendUnload', $hpp);
        self::assertStringContainsString('COMSPEC_SsePerson_ResumeCollect', $onLoad);
        self::assertStringContainsString('[3] call comspec_overwatch_connect_fnc_sseTerminalPage', $onLoad);
        self::assertStringContainsString('1.5.0', (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        ));
    }

    public function testSsePackRegistersBiiKitsAndIrisRole(): void
    {
        $aliases = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/core/functions/fn_getEquipmentAliases.sqf'
        );
        $has = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/core/functions/fn_hasEquipment.sqf'
        );
        $iris = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/biometrics/functions/fn_captureIris.sqf'
        );
        $reg = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiRegisterEquipment.sqf'
        );

        self::assertStringContainsString('"iris", "iris"', $aliases);
        self::assertStringContainsString('DNACollectionKit', $aliases);
        self::assertStringContainsString('EyeScannerKit', $aliases);
        self::assertStringContainsString('FingerprintScannerKit', $aliases);
        self::assertStringContainsString('private _owRoles = ["camera", "face", "seek", "terminal", "sse_terminal", "seekii"];', $has);
        self::assertStringNotContainsString('find "fingerprint"', $has);
        self::assertStringContainsString('[_player, "iris"]', $iris);
        self::assertStringContainsString('EyeScannerKit', $reg);
            self::assertStringContainsString('0.7.19', (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/main/script_mod.hpp'
        ));
    }
}
