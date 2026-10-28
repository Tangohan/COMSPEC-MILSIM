<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakDistanceRepository;
use App\Support\PlayerMetrics;
use PHPUnit\Framework\TestCase;

final class PlayerMetricsTest extends TestCase
{
    public function testMergeJoinsScreenDistanceAndGarminPerMember(): void
    {
        $screen = [
            ['user_id' => 4, 'display_name' => 'Jean Dupont', 'callsign' => 'Viper', 'play' => 7200, 'screen' => 1800, 'roles' => [['label' => 'Pilote', 'seconds' => 3600]], 'last_day' => '2026-10-10'],
        ];
        $distance = [
            4 => ['user_id' => 4, 'display_name' => '', 'callsign' => '', 'totals' => ['foot_m' => 5000, 'land_m' => 12000, 'steps' => 6500, 'halo_jumps' => 1], 'total_m' => 17000, 'sessions' => 2, 'last_day' => '2026-10-12'],
            9 => ['user_id' => 9, 'display_name' => 'Marie', 'callsign' => '', 'totals' => ['foot_m' => 9000], 'total_m' => 9000, 'sessions' => 1, 'last_day' => '2026-10-11'],
        ];
        $garmin = [9 => ['user_id' => 9, 'display_name' => 'Marie', 'callsign' => '', 'steps' => 12000, 'foot_m' => 8000, 'avg_sleep_s' => 25200, 'body_battery' => 60]];
        // Le membre 12 n'a que des heures de vol (cumul) : pas de ligne pour lui sur la période.
        $rows = PlayerMetrics::merge($screen, $distance, [4 => 5400, 12 => 999], $garmin);

        self::assertCount(2, $rows);
        self::assertSame('Viper', $rows[0]['name']);
        self::assertSame('Pilote', $rows[0]['top_role']);
        self::assertSame(17000, $rows[0]['total_m']);
        self::assertSame(5400, $rows[0]['pilot_s']);
        self::assertSame('2026-10-12', $rows[0]['last_day']);
        self::assertSame('Marie', $rows[1]['name']);
        self::assertSame(12000, $rows[1]['garmin']['steps']);

        self::assertSame(9, PlayerMetrics::sort($rows, 'pied')[0]['user_id']);
        self::assertSame(9, PlayerMetrics::sort($rows, 'pas')[0]['user_id']);
        self::assertSame(9, PlayerMetrics::sort($rows, 'nom')[0]['user_id']);

        $t = PlayerMetrics::totals($rows);
        self::assertSame(2, $t['members']);
        self::assertSame(26000, $t['total_m']);
        self::assertSame(14000, $t['foot_m']);
        self::assertSame(1, $t['watches']);
        self::assertSame(1, $t['halo_jumps']);
    }

    public function testFormatting(): void
    {
        self::assertSame('850 m', PlayerMetrics::distance(850));
        self::assertSame('12,4 km', PlayerMetrics::distance(12400));
        self::assertSame('3 h 05', PlayerMetrics::duration(3 * 3600 + 300));
    }

    public function testPilotTotalsCountPilotsPerAircraftType(): void
    {
        $out = AtakDistanceRepository::pilotTotalsFromRows([
            ['user_id' => 1, 'vehicle_class' => 'B_Heli_Transport_03_F', 'vehicle_label' => 'CH-67 Huron', 'vehicle_kind' => 'heli', 'seconds' => 600, 'meters' => 1000, 'last_flown_at' => ''],
            ['user_id' => 2, 'vehicle_class' => 'RHS_CH_47F', 'vehicle_label' => 'CH-47F', 'vehicle_kind' => 'heli', 'seconds' => 300, 'meters' => 500, 'last_flown_at' => ''],
            ['user_id' => 2, 'vehicle_class' => 'B_Heli_Light_01_F', 'vehicle_label' => 'MH-9 Hummingbird', 'vehicle_kind' => 'heli', 'seconds' => 100, 'meters' => 50, 'last_flown_at' => ''],
        ]);

        self::assertSame([1 => 600, 2 => 400], $out['by_user']);
        self::assertSame('CH-47 Chinook', $out['families'][0]['label']);
        self::assertSame(2, $out['families'][0]['pilots']);
        self::assertSame(900, $out['families'][0]['seconds']);
    }

    public function testPageIsReachableFromTheBackOfficeMenu(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $sidebar = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');

        self::assertStringContainsString("'/back-office/metriques-joueurs', [\\App\\Controllers\\Admin\\AdminPlayerMetricsController::class, 'index'], [AuthMiddleware::class, TenantResourceAdminMiddleware::class]", $routes);
        self::assertStringContainsString("'label' => 'Métriques joueurs'", $sidebar);
        foreach (['back-office/metriques-joueurs', 'back-office/atak/temps-ecran', 'back-office/atak/heures'] as $path) {
            self::assertStringContainsString("url('" . $path . "')", $sidebar);
        }
        self::assertStringContainsString("'path' => 'back-office/metriques-joueurs'", $pages);
        self::assertFileExists($root . '/views/admin/organization/player_metrics.php');
        self::assertFileExists($root . '/public/assets/css/back-office-player-metrics.css');

        // Vue en tableur (comme Heures de jeu) : tri par en-tête, total communauté, pleine largeur.
        $view = (string) file_get_contents($root . '/views/admin/organization/player_metrics.php');
        self::assertStringContainsString('akhr__table pm__table', $view);
        self::assertStringContainsString('data-sort-type=', $view);
        self::assertStringContainsString('<tfoot>', $view);
        self::assertStringContainsString("'back-office-atak-squads.css', 'back-office-atak-hours.css', 'back-office-player-metrics.css'", $pages);
        foreach (['back-office-player-metrics.css' => '.pm .aksq__frame', 'back-office-atak-hours.css' => '.akhr .aksq__frame'] as $css => $rule) {
            self::assertMatchesRegularExpression('/' . preg_quote($rule, '/') . ' \{[^}]*max-width: none/', (string) file_get_contents($root . '/public/assets/css/' . $css));
        }
    }
}
