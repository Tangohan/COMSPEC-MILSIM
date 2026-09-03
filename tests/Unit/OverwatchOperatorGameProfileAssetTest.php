<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchOperatorGameProfileAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function connect(string $file): string
    {
        return (string) file_get_contents($this->root() . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/' . $file);
    }

    public function testConnectRegistersOperatorProfileFunctionsAndBumpsVersion(): void
    {
        $cfg = $this->connect('config.cpp');
        self::assertStringContainsString('versionStr = "1.5.16"', $cfg);
        foreach ([
            'class jsonValue',
            'class collectOperatorIdentity',
            'class collectOperatorFace',
            'class collectOperatorMedical',
            'class collectOperatorLoadout',
            'class collectOperatorVersions',
            'class collectOperatorEnvironment',
            'class operatorProfileFingerprint',
            'class buildOperatorProfile',
            'class applyOperatorProfileResponse',
            'class syncOperatorProfile',
            'class operatorProfileTick',
            'class initOperatorProfileSync',
        ] as $cls) {
            self::assertStringContainsString($cls, $cfg);
        }
    }

    public function testIdentityCollectorUsesSteamNotPlayerNameAsLinkKey(): void
    {
        $src = $this->connect('functions/fn_collectOperatorIdentity.sqf');
        self::assertStringContainsString('getPlayerUID', $src);
        self::assertStringContainsString('_out set ["steam_uid"', $src);
        self::assertStringContainsString('ne servent jamais à associer un compte', $src);
        self::assertStringContainsString('callsign', $src);
        self::assertStringContainsString('sex_detected', $src);
        self::assertStringNotContainsString('_out set ["steam_uid", name', $src);
    }

    public function testFaceCollectorStoresClassAndTextureWithoutRequiringPreview(): void
    {
        $src = $this->connect('functions/fn_collectOperatorFace.sqf');
        self::assertStringContainsString('face _unit', $src);
        self::assertStringContainsString('CfgFaces', $src);
        self::assertStringContainsString('face_texture', $src);
        self::assertStringContainsString('Ne plante pas', $src);
    }

    public function testMedicalCollectorDoesNotInventBloodType(): void
    {
        $src = $this->connect('functions/fn_collectOperatorMedical.sqf');
        self::assertStringContainsString('ace_dogtags_fnc_getDogtagData', $src);
        self::assertStringContainsString('ace_medical_bloodType', $src);
        self::assertStringContainsString('Pas de SpO2 inventé', $src);
        self::assertStringNotContainsString('_out set ["spo2"', $src);
    }

    public function testLoadoutCollectorKeepsRawGetUnitLoadout(): void
    {
        $src = $this->connect('functions/fn_collectOperatorLoadout.sqf');
        self::assertStringContainsString('getUnitLoadout', $src);
        self::assertStringContainsString('uniform_class', $src);
        self::assertStringContainsString('nvgs_class', $src);
        self::assertStringContainsString('acre_api_fnc_getCurrentRadioList', $src);
    }

    public function testPositionUpdateDoesNotSendFullLoadout(): void
    {
        $src = $this->connect('functions/fn_updatePosition.sqf');
        self::assertStringNotContainsString('getUnitLoadout', $src);
        self::assertStringNotContainsString('OperatorRegister', $src);
        self::assertStringContainsString('fn_syncOperatorProfile', $src);
        self::assertStringContainsString('UpdatePosition', $src);
    }

    public function testSyncUsesSteamJsonValueAndSeparateRegisterSyncCommands(): void
    {
        $src = $this->connect('functions/fn_syncOperatorProfile.sqf');
        self::assertStringContainsString('OperatorRegister', $src);
        self::assertStringContainsString('OperatorSync', $src);
        self::assertStringContainsString('Steam UID absent', $src);
        self::assertStringContainsString('Ne doit PAS être appelé à chaque tick de position', $src);
        self::assertStringContainsString('COMSPEC_OperatorFingerprint', $src);
        self::assertStringContainsString('fnc_jsonValue', $src);
        self::assertStringContainsString('COMSPEC_OperatorProfileFailCount', $src);
        self::assertStringContainsString('503', $src);
        self::assertStringNotContainsString('hashMapToJson', $src);
    }

    public function testBuilderEmitsRegisterSyncAliasesAndRootLoadout(): void
    {
        $src = $this->connect('functions/fn_buildOperatorProfile.sqf');
        self::assertStringContainsString('steam_id', $src);
        self::assertStringContainsString('player_name', $src);
        self::assertStringContainsString('face_class', $src);
        self::assertStringContainsString('_payload set ["loadout"', $src);
        self::assertStringContainsString('server_name', $src);
        self::assertStringContainsString('mission_id', $src);
        self::assertStringContainsString('primary_weapon', $src);
    }

    public function testConnectDoesNotRegisterBeforeAthenaIsReady(): void
    {
        $src = $this->connect('functions/fn_connect.sqf');
        self::assertStringNotContainsString('syncOperatorProfile', $src);
        $init = $this->connect('functions/fn_initOperatorProfileSync.sqf');
        self::assertStringContainsString('COMSPEC_AthenaReady', $init);
        self::assertStringContainsString('first_connect', $init);
    }

    public function testLoopsAndManualResyncHookOperatorProfile(): void
    {
        $loops = $this->connect('functions/fn_startSyncLoops.sqf');
        $force = $this->connect('functions/fn_forceSyncData.sqf');
        $init = $this->connect('functions/fn_initOperatorProfileSync.sqf');
        self::assertStringContainsString('initOperatorProfileSync', $loops);
        self::assertStringContainsString('fiche opérateur', $force);
        self::assertStringContainsString('first_connect', $init);
        self::assertStringContainsString('loadout_changed', $init);
    }

    public function testExtensionOperatorHandlersKeepSteamAndTenantAndTolerateMissingRoute(): void
    {
        $dll = (string) file_get_contents($this->root() . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('private const string ExtensionVersion = "1.18.10"', $dll);
        self::assertGreaterThanOrEqual(2, substr_count($dll, 'ApplySteamUid(args[3])'));
        self::assertStringContainsString('if (!isProxyContact && steamNorm.Length == 0)', $dll);
        self::assertStringContainsString('OperatorRegister', $dll);
        self::assertStringContainsString('OperatorSync', $dll);
        self::assertStringContainsString('HandleOperatorProfile', $dll);
        self::assertStringContainsString('or "OperatorProfile"', $dll);
        self::assertStringContainsString('/api/atak/operator/register', $dll);
        self::assertStringContainsString('/api/atak/operator/sync', $dll);
        self::assertStringContainsString('WriteString("steam_id"', $dll);
        self::assertStringContainsString('steam_required', $dll);
        self::assertStringContainsString('tenant_id', $dll);
        self::assertStringContainsString('steam_uid_session', $dll);
        self::assertStringContainsString('FormatAtakExtArray("OK", "pending")', $dll);
        self::assertStringContainsString('operator_linked', $dll);
        self::assertStringContainsString('update_required', $dll);
    }
}
