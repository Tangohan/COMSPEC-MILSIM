<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakGarminRepository;
use PHPUnit\Framework\TestCase;

final class AtakGarminTest extends TestCase
{
    public function testReportIsValidatedAndCapped(): void
    {
        $r = AtakGarminRepository::normalizeReport([
            'player_uid' => '76561198000000001',
            'status' => ['bb' => 140, 'hr' => 72.4, 'stress' => -5, 'rest_hr' => 58, 'goal' => 12000],
            'days' => [
                ['day' => '2026-10-17', 'steps' => 8400.6, 'foot_m' => 6100, 'hr_max' => 400, 'hr_rest' => 999, 'stress_avg' => 31, 'run_n' => 1],
                ['day' => '2026-10-18', 'steps' => 9999999, 'sleep_s' => 1200, 'hr_rest' => 55],
                ['day' => '2026-02-30', 'steps' => 10],
                ['day' => '2025-01-01', 'steps' => 10],
                ['day' => '2026-10-25', 'steps' => 10],
                'nope',
            ],
            'acts' => [
                ['day' => '2026-10-18', 'at' => '9:05', 'type' => 'run', 'place' => "Kavala\nport", 'world' => 'Altis', 'm' => 5200, 'sec' => 1500],
                ['day' => '2026-10-18', 'at' => '25:00', 'type' => 'RUN', 'm' => 400],
                ['day' => '2026-10-18', 'at' => '10:00', 'type' => 'TELEPORT', 'm' => 400],
                ['day' => '2026-10-18', 'at' => '11:00', 'type' => 'SWIM', 'm' => 999999, 'sec' => 600],
            ],
        ], '2026-10-18');

        self::assertSame(['body_battery' => 100, 'heart_rate' => 72, 'stress' => 0, 'rest_hr' => 58, 'step_goal' => 12000], $r['status']);
        self::assertSame(['2026-10-18', '2026-10-17'], array_column($r['days'], 'day'), 'dates impossibles, trop anciennes ou futures écartées, plus récent en premier');
        self::assertSame(AtakGarminRepository::DAY_COUNTERS['steps'], $r['days'][0]['steps']);
        self::assertSame(55, $r['days'][0]['hr_rest']);
        self::assertSame(8401, $r['days'][1]['steps']);
        self::assertSame(230, $r['days'][1]['hr_max']);
        self::assertNull($r['days'][1]['hr_rest'], '999 = pas de mesure au repos');
        self::assertCount(2, $r['activities']);
        self::assertSame(['day' => '2026-10-18', 'started_at' => '09:05', 'type' => 'RUN', 'place' => 'Kavala port', 'world' => 'Altis', 'meters' => 5200, 'seconds' => 1500], $r['activities'][0]);
        self::assertSame(AtakGarminRepository::ACTIVITY_TYPES['SWIM'][1], $r['activities'][1]['meters']);
    }

    public function testEmptyOrBrokenBodyGivesDefaults(): void
    {
        $r = AtakGarminRepository::normalizeReport('nope', '2026-10-18');
        self::assertSame(10000, $r['status']['step_goal']);
        self::assertSame([], $r['days']);
        self::assertSame([], $r['activities']);
    }

    public function testSummaryAveragesTheWeekAndLabelsActivities(): void
    {
        $sum = AtakGarminRepository::buildSummary(
            ['body_battery' => 64, 'heart_rate' => 80, 'stress' => 22, 'rest_hr' => 58, 'step_goal' => 8000, 'synced_at' => '2026-10-18 10:00:00'],
            [
                ['day' => '2026-10-18', 'steps' => 9000, 'foot_m' => 6000, 'sleep_s' => 0, 'stress_avg' => 30, 'bb_up' => 10, 'bb_down' => 20, 'hr_rest' => 56],
                ['day' => '2026-10-17', 'steps' => 3000, 'foot_m' => 2000, 'sleep_s' => 1800, 'stress_avg' => 10, 'bb_up' => 5, 'bb_down' => 3, 'hr_rest' => null],
            ],
            ['days' => 2, 'steps' => 12000, 'foot_m' => 8000, 'run_n' => 1, 'run_m' => 5000, 'sleep_s' => 1800],
            [['day' => '2026-10-18', 'started_at' => '09:05', 'type' => 'FLIGHT', 'place' => '', 'world' => 'Altis', 'meters' => 40000, 'seconds' => 900]]
        );
        self::assertSame(6000, $sum['avg_steps']);
        self::assertSame(900, $sum['avg_sleep_s']);
        self::assertSame(20, $sum['avg_stress']);
        self::assertSame(56, $sum['avg_rest_hr'], 'jour sans mesure au repos ignoré');
        self::assertSame('Vol', $sum['activities'][0]['label']);
        self::assertSame(5000, $sum['totals']['run_m']);
    }
}
