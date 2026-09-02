<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\OperatorGame\OperatorGameObservationNormalizer;
use PHPUnit\Framework\TestCase;

final class OperatorGameObservationNormalizerTest extends TestCase
{
    public function testNestedOverwatchPayloadMapsToRegisterSyncContract(): void
    {
        $payload = (new OperatorGameObservationNormalizer())->normalize([
            'steam_uid' => '76561198000000001',
            'player_uid' => '76561198000000001',
            'identity' => [
                'steam_uid' => '76561198000000001',
                'arma_player_uid' => '76561198000000001',
                'arma_player_name' => 'Eagle Actual',
                'callsign' => 'EAGLE-2',
                'display_name' => 'John DOE',
                'sex_detected' => 'M',
                'role' => 'Team Leader',
                'group_name' => 'Alpha',
                'faction' => 'BLU_F',
                'side' => 'WEST',
            ],
            'face' => [
                'face_class' => 'WhiteHead_01',
                'face_texture' => 'a3\data\face.paa',
            ],
            'medical' => ['blood_type' => 'O+'],
            'equipment' => [
                'uniform_class' => 'U_B_CombatUniform_mcam',
                'vest_class' => 'V_PlateCarrier1_rgr',
                'backpack_class' => 'B_AssaultPack_mcamo',
                'helmet_class' => 'H_HelmetB',
                'goggles_class' => 'G_Tactical_Clear',
                'nvgs_class' => 'NVGoggles',
                'primary' => ['class' => 'arifle_MX_F'],
                'secondary' => ['class' => 'launch_NLAW_F'],
                'handgun' => ['class' => 'hgun_P07_F'],
                'loadout' => [['arifle_MX_F', '', '', '', [], [], '']],
            ],
            'versions' => ['overwatch' => '1.5.13', 'atak' => '1.0.58', 'arma' => '2.18'],
            'environment' => [
                'server_name' => 'TOE',
                'mission_name' => 'op_eagle',
                'briefing_name' => 'Opération Aigle',
                'world_name' => 'Altis',
            ],
        ]);

        self::assertSame('76561198000000001', $payload['steam_id']);
        self::assertSame('Eagle Actual', $payload['identity']['player_name']);
        self::assertSame('M', $payload['identity']['sex']);
        self::assertSame('WhiteHead_01', $payload['identity']['face_class']);
        self::assertSame('a3\data\face.paa', $payload['identity']['face_texture']);
        self::assertSame('U_B_CombatUniform_mcam', $payload['equipment']['uniform']);
        self::assertSame('H_HelmetB', $payload['equipment']['headgear']);
        self::assertSame('arifle_MX_F', $payload['equipment']['primary_weapon']);
        self::assertSame('hgun_P07_F', $payload['equipment']['handgun_weapon']);
        self::assertSame(['class' => 'hgun_P07_F'], $payload['equipment']['handgun']);
        self::assertSame([['arifle_MX_F', '', '', '', [], [], '']], $payload['loadout']);
        self::assertSame('TOE', $payload['server_name']);
        self::assertSame('Opération Aigle', $payload['mission_name']);
        self::assertSame('op_eagle', $payload['mission_id']);
        self::assertSame('Altis', $payload['world_name']);
    }

    public function testCodexFlatPayloadIsLeftIntact(): void
    {
        $payload = (new OperatorGameObservationNormalizer())->normalize([
            'steam_id' => '76561198000000002',
            'identity' => [
                'player_uid' => '76561198000000002',
                'player_name' => 'Bravo',
                'sex' => 'F',
                'face_class' => 'AfricanHead_01',
            ],
            'medical' => ['blood_type' => 'A-'],
            'equipment' => [
                'uniform' => 'U_I_CombatUniform',
                'handgun' => 'hgun_ACPC2_F',
            ],
            'loadout' => [1, 2, 3],
            'server_name' => 'Srv',
            'mission_name' => 'Brief',
            'mission_id' => 'folder',
            'world_name' => 'Tanoa',
        ]);

        self::assertSame('76561198000000002', $payload['steam_id']);
        self::assertSame('Bravo', $payload['identity']['player_name']);
        self::assertSame('F', $payload['identity']['sex']);
        self::assertSame('AfricanHead_01', $payload['identity']['face_class']);
        self::assertSame('U_I_CombatUniform', $payload['equipment']['uniform']);
        self::assertSame('hgun_ACPC2_F', $payload['equipment']['handgun']);
        self::assertSame([1, 2, 3], $payload['loadout']);
        self::assertSame('folder', $payload['mission_id']);
    }

    public function testObservedForReconcileUsesMedicalBloodAndDoesNotInvent(): void
    {
        $normalizer = new OperatorGameObservationNormalizer();
        $payload = $normalizer->normalize([
            'identity' => ['sex_detected' => '', 'callsign' => 'EAGLE-2'],
            'medical' => ['blood_type' => ''],
            'versions' => ['atak' => '1.8.2'],
        ]);
        $observed = $normalizer->observedForReconcile($payload, '76561198000000001');

        self::assertSame('76561198000000001', $observed['steam_id']);
        self::assertNull($observed['blood_type']);
        self::assertNull($observed['sex']);
        self::assertSame('EAGLE-2', $observed['callsign']);
        self::assertSame('1.8.2', $observed['versions']['atak']);
    }
}
