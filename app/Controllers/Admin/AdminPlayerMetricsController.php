<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDistanceRepository;
use App\Repositories\AtakGarminRepository;
use App\Repositories\AtakScreenTimeRepository;
use App\Support\PlayerMetrics;
use App\Support\PlaytimeSheet;

/**
 * /back-office/metriques-joueurs : temps de jeu et d'écran, distances parcourues, heures de vol et montre Garmin,
 * par membre et pour toute la communauté, sur une période (?jours=7|30|90|0), triable (?tri=) et exportable (?format=csv).
 * Les pages détaillées existantes (Temps d'écran et rôles, Heures de jeu, fiche du membre) restent la source du détail.
 */
final class AdminPlayerMetricsController
{
    private const PERIODS = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours', 0 => 'Tout'];

    public function __construct(
        private ?AtakScreenTimeRepository $screenTime = null,
        private ?AtakDistanceRepository $distance = null,
        private ?AtakGarminRepository $garmin = null,
    ) {
        $this->screenTime ??= new AtakScreenTimeRepository();
        $this->distance ??= new AtakDistanceRepository();
        $this->garmin ??= new AtakGarminRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $rawDays = $request->query('jours');
        $days = is_scalar($rawDays) && $rawDays !== '' && array_key_exists((int) $rawDays, self::PERIODS) ? (int) $rawDays : 30;
        $rawSort = $request->query('tri');
        $sort = is_string($rawSort) && isset(PlayerMetrics::SORTS[$rawSort]) ? $rawSort : 'jeu';

        $sources = [
            'screen' => $this->screenTime->schemaReady(),
            'distance' => $this->distance->schemaReady(),
            'garmin' => $this->garmin->schemaReady(),
        ];
        $pilot = $sources['distance'] ? $this->distance->pilotTotals($tenantId) : ['by_user' => [], 'families' => []];
        $rows = PlayerMetrics::merge(
            $sources['screen'] ? $this->screenTime->summaryByUser($tenantId, $days) : [],
            $sources['distance'] ? $this->distance->summaryByUser($tenantId, $days) : [],
            $pilot['by_user'],
            $sources['garmin'] ? $this->garmin->summaryByUser($tenantId, $days) : [],
            $sort
        );

        if ($request->query('format') === 'csv') {
            return $this->csv($rows, $days);
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.player_metrics',
            'title' => 'Métriques joueurs',
            'pageTitle' => 'Métriques joueurs',
            'pmRows' => $rows,
            'pmTotals' => PlayerMetrics::totals($rows),
            'pmAircraft' => $pilot['families'],
            'pmRoles' => $sources['screen'] ? $this->screenTime->roleTotals($tenantId, $days) : [],
            'pmSources' => $sources,
            'pmDays' => $days,
            'pmSort' => $sort,
            'pmPeriods' => self::PERIODS,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function csv(array $rows, int $days): Response
    {
        $fh = fopen('php://temp', 'r+');
        $put = static function (array $cells) use ($fh): void {
            fputcsv($fh, array_map(static fn (mixed $c): string => PlaytimeSheet::csvCell((string) $c), $cells), ';', '"', '');
        };
        $km = static fn (int $m): string => PlaytimeSheet::frenchNumber(round($m / 1000, 2));
        $put(['Joueur', 'Indicatif', 'Temps de jeu (h)', 'Temps d’écran (h)', 'Rôle principal', 'Distance totale (km)', 'À pied (km)', 'Véhicule terrestre (km)', 'Bateau (km)', 'Vol (km)', 'HALO (km)', 'Sauts HALO', 'Pas (jeu)', 'Sessions', 'Heures de vol, cumul (h)', 'Pas (montre)', 'Distance à pied (montre, km)', 'Courses (montre)', 'Sommeil moyen (montre, h)', 'Dernière activité']);
        foreach ($rows as $r) {
            $g = is_array($r['garmin']) ? $r['garmin'] : null;
            $put([
                $r['display_name'], $r['callsign'], PlaytimeSheet::decimal($r['play']), PlaytimeSheet::decimal($r['screen']), $r['top_role'],
                $km($r['total_m']), $km($r['foot_m']), $km($r['land_m']), $km($r['sea_m']), $km($r['air_m']), $km($r['halo_m']), $r['halo_jumps'],
                $r['steps'], $r['sessions'], PlaytimeSheet::decimal($r['pilot_s']),
                $g === null ? '' : $g['steps'], $g === null ? '' : $km((int) $g['foot_m']), $g === null ? '' : $g['run_n'],
                $g === null ? '' : PlaytimeSheet::decimal((int) $g['avg_sleep_s']),
                PlaytimeSheet::frenchDate($r['last_day']),
            ]);
        }
        rewind($fh);
        $csv = "\xEF\xBB\xBF" . (stream_get_contents($fh) ?: '');
        fclose($fh);
        $filename = 'metriques-joueurs-' . ($days > 0 ? $days . 'j-' : 'tout-') . date('Ymd') . '.csv';

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }
}
