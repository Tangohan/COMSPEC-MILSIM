<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakDistanceRepository;
use PHPUnit\Framework\TestCase;

final class AtakDistanceTest extends TestCase
{
    public function testBatchIsValidatedCappedAndMerged(): void
    {
        $players = AtakDistanceRepository::normalizeBatch([
            [
                'player_uid' => '76561198000000001', 'call_sign' => "Viper\n1", 'steps' => 1250.4, 'foot_m' => 980, 'foot_s' => 600,
                'air_m' => 99999999, 'air_s' => 300, 'halo_jumps' => 1, 'land_m' => -50,
                'pilot' => [
                    ['class' => 'RHS_CH_47F', 'label' => 'CH-47F Chinook', 'kind' => 'heli', 'seconds' => 300, 'meters' => 12000],
                    ['class' => 'bad class!', 'seconds' => 10],
                    ['class' => 'RHS_UH60M', 'seconds' => 0],
                ],
            ],
            ['player_uid' => '76561198000000001', 'steps' => 50, 'pilot' => [['class' => 'RHS_CH_47F', 'label' => '', 'kind' => 'heli', 'seconds' => 60, 'meters' => 1000]]],
            ['player_uid' => '123', 'steps' => 10],
            ['player_uid' => '76561198000000002', 'steps' => 0],
            'nope',
        ]);

        self::assertCount(1, $players, 'Steam invalide et joueur sans rien à compter écartés');
        $p = $players[0];
        self::assertSame('Viper 1', $p['call_sign']);
        self::assertSame(1300, $p['stats']['steps']);
        self::assertSame(980, $p['stats']['foot_m']);
        self::assertSame(AtakDistanceRepository::COUNTERS['air_m'], $p['stats']['air_m'], 'plafond par envoi');
        self::assertSame(0, $p['stats']['land_m'], 'négatif ramené à 0');
        self::assertCount(1, $p['pilot']);
        self::assertSame(360, $p['pilot'][0]['seconds']);
        self::assertSame(13000, $p['pilot'][0]['meters']);
    }

    public function testClientRelayKeepsOnlyTheLinkedPlayer(): void
    {
        $batch = AtakDistanceRepository::normalizeBatch([
            ['player_uid' => '76561198000000001', 'foot_m' => 100],
            ['player_uid' => '76561198000000002', 'pilot' => [['class' => 'RHS_UH60M', 'seconds' => 600]]],
        ]);

        $mine = AtakDistanceRepository::onlyPlayer($batch, '76561198000000002');
        self::assertCount(1, $mine);
        self::assertSame('76561198000000002', $mine[0]['player_uid']);
        self::assertSame([], AtakDistanceRepository::onlyPlayer($batch, null), 'sans Steam lié, rien n’est gardé');
        self::assertSame([], AtakDistanceRepository::onlyPlayer($batch, '76561198000000009'));
    }

    public function testAircraftFamiliesFromClassname(): void
    {
        self::assertSame('ch47', AtakDistanceRepository::aircraftFamily('RHS_CH_47F', 'CH-47F')['key']);
        self::assertSame('ch47', AtakDistanceRepository::aircraftFamily('B_Heli_Transport_03_F', 'CH-67 Huron')['key']);
        self::assertSame('uh60', AtakDistanceRepository::aircraftFamily('RHS_UH60M', 'UH-60M')['key']);
        self::assertSame('uh60', AtakDistanceRepository::aircraftFamily('RHS_MH60M_DAP', '')['key']);
        self::assertSame('mh6', AtakDistanceRepository::aircraftFamily('RHS_MELB_MH6M', 'MH-6M Little Bird')['key']);
        self::assertSame('mh6', AtakDistanceRepository::aircraftFamily('B_Heli_Light_01_F', 'MH-9 Hummingbird')['key']);
        self::assertSame('ah64', AtakDistanceRepository::aircraftFamily('RHS_AH64D', 'AH-64D')['key']);
        self::assertSame('uh1', AtakDistanceRepository::aircraftFamily('RHS_UH1Y', 'UH-1Y Venom')['key']);
        self::assertSame('mi8', AtakDistanceRepository::aircraftFamily('RHS_Mi8MT_vvs', 'Mi-8MT')['key']);
        self::assertSame('a10', AtakDistanceRepository::aircraftFamily('RHS_A10', 'A-10A')['key']);
        $other = AtakDistanceRepository::aircraftFamily('B_Heli_Transport_04_F', 'Mohawk');
        self::assertSame('class:b_heli_transport_04_f', $other['key']);
        self::assertSame('Mohawk', $other['label']);
    }

    public function testPilotGroupedByFamilyAndSummaryAverages(): void
    {
        $pilot = AtakDistanceRepository::groupPilot([
            ['vehicle_class' => 'RHS_UH60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'seconds' => 3600, 'meters' => 100000, 'last_flown_at' => '2026-10-10 20:00:00'],
            ['vehicle_class' => 'RHS_UH60M_MEV', 'vehicle_label' => 'UH-60M MEV', 'vehicle_kind' => 'heli', 'seconds' => 1800, 'meters' => 50000, 'last_flown_at' => '2026-10-12 20:00:00'],
            ['vehicle_class' => 'RHS_CH_47F', 'vehicle_label' => 'CH-47F', 'vehicle_kind' => 'heli', 'seconds' => 7200, 'meters' => 0, 'last_flown_at' => '2026-10-01 20:00:00'],
        ]);
        self::assertSame(['ch47', 'uh60'], array_column($pilot, 'key'));
        self::assertSame(5400, $pilot[1]['seconds']);
        self::assertSame(['UH-60M', 'UH-60M MEV'], $pilot[1]['variants']);
        self::assertSame('2026-10-12 20:00:00', $pilot[1]['last_flown_at']);

        $sum = AtakDistanceRepository::buildSummary(['sessions' => 3, 'foot_sessions' => 2, 'foot_s' => 3000, 'foot_m' => 4000, 'air_m' => 6000], $pilot);
        self::assertSame(1500, $sum['avg_foot_s']);
        self::assertSame(10000, $sum['total_m']);
    }
}
