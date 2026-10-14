<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ArmaPlaytimeRepository;
use App\Repositories\AtakScreenTimeRepository;
use App\Services\Atak\SquadSyncService;
use App\Support\AtakRoleDetection;
use App\Support\PlaytimeSheet;

/**
 * Back-office ATAK : ce que les téléphones remontent du jeu.
 *   /back-office/atak/escouades   escouades et équipes de feu par session (couleurs, icônes, descriptions, rôles)
 *   /back-office/atak/temps-ecran temps d'écran du téléphone et temps de jeu par rôle, par membre
 *   /back-office/atak/heures      tableur des heures par joueur, rôle, type et détection (export CSV)
 */
final class AdminAtakSquadsController
{
    private const PERIODS = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours', 0 => 'Tout'];

    public function __construct(
        private ?SquadSyncService $squads = null,
        private ?AtakScreenTimeRepository $screenTime = null,
        private ?ArmaPlaytimeRepository $playtime = null,
    ) {
        $this->squads ??= new SquadSyncService();
        $this->screenTime ??= new AtakScreenTimeRepository();
        $this->playtime ??= new ArmaPlaytimeRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $days = $this->period($request, 14, [1, 7, 14, 30]);
        $ready = $this->squads->schemaReady();
        $squads = $ready ? $this->squads->recentSquads($tenantId, $days) : [];
        // Une session jouée = une clé de mission ; la plus récente en tête.
        $sessions = [];
        foreach ($squads as $s) {
            $key = (string) $s['mission_hash'];
            $sessions[$key] ??= ['mission_key' => (string) $s['mission_key'], 'last' => (string) $s['synced_at'], 'squads' => []];
            $sessions[$key]['squads'][] = $s;
        }

        return Response::view('layout.main', [
            'content' => 'admin.atak.squads',
            'title' => 'Escouades en jeu',
            'pageTitle' => 'Escouades en jeu',
            'squadsReady' => $ready,
            'squadSessions' => array_values($sessions),
            'squadDays' => $days,
        ]);
    }

    public function screenTime(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $days = $this->period($request, 30, array_keys(self::PERIODS));
        $ready = $this->screenTime->schemaReady();

        return Response::view('layout.main', [
            'content' => 'admin.atak.screen_time',
            'title' => 'Temps d’écran et rôles',
            'pageTitle' => 'Temps d’écran et rôles',
            'screenReady' => $ready,
            'screenRows' => $ready ? $this->screenTime->summaryByUser($tenantId, $days) : [],
            'screenRoleTotals' => $ready ? $this->screenTime->roleTotals($tenantId, $days) : [],
            'screenDays' => $days,
            'screenPeriods' => self::PERIODS,
        ]);
    }

    /**
     * Tableur des heures : une ligne par joueur, type et élément (« Détail »), ou une ligne par joueur (« Par joueur »).
     * Filtres : jours (7, 30, 90, 0 = tout) ou du / au, membre ; ?format=csv exporte la vue affichée.
     */
    public function hours(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $from = self::dateParam($request->query('du'));
        $to = self::dateParam($request->query('au'));
        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        $custom = $from !== null || $to !== null;
        $days = $custom ? -1 : $this->period($request, 30, array_keys(self::PERIODS));
        if (!$custom && $days > 0) {
            $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        }
        $rawMember = $request->query('membre', 0);
        $member = max(0, is_scalar($rawMember) ? (int) $rawMember : 0);
        $view = $request->query('vue') === 'joueurs' ? 'joueurs' : 'detail';
        $ready = $this->screenTime->schemaReady();
        $rows = $ready ? $this->screenTime->sheetRows($tenantId, $from, $to, $member) : [];

        // Base des parts : temps de jeu (play/TOTAL) de chaque joueur sur la période.
        $playByUser = [];
        foreach ($rows as $r) {
            if ($r['kind'] === 'play' && $r['key'] === 'TOTAL') {
                $playByUser[$r['user_id']] = ($playByUser[$r['user_id']] ?? 0) + $r['seconds'];
            }
        }
        foreach ($rows as &$r) {
            $r['share'] = $r['kind'] === 'role' ? PlaytimeSheet::share($r['seconds'], $playByUser[$r['user_id']] ?? 0) : null;
        }
        unset($r);
        $userIds = array_values(array_unique(array_column($rows, 'user_id')));
        $players = PlaytimeSheet::pivot($rows, $userIds !== [] ? $this->playtime->summariesForUsers($tenantId, $userIds) : []);

        $filters = array_filter([
            'jours' => $custom ? null : (string) $days,
            'du' => $custom ? $from : null,
            'au' => $custom ? $to : null,
            'membre' => $member > 0 ? (string) $member : null,
        ], static fn (?string $v): bool => $v !== null && $v !== '');

        if ($request->query('format') === 'csv') {
            return $this->hoursCsv($view, $rows, $players);
        }

        return Response::view('layout.main', [
            'content' => 'admin.atak.hours',
            'title' => 'Heures de jeu (tableur)',
            'pageTitle' => 'Heures de jeu (tableur)',
            'hoursReady' => $ready,
            'hoursSourceTracked' => $ready && $this->screenTime->hasColumn('source_mask'),
            'hoursRows' => $rows,
            'hoursPlayers' => $players,
            'hoursView' => $view,
            'hoursDays' => $days,
            'hoursFrom' => $custom ? (string) $from : '',
            'hoursTo' => $custom ? (string) $to : '',
            'hoursMember' => $member,
            'hoursMembers' => $ready ? $this->screenTime->sheetMembers($tenantId) : [],
            'hoursFilters' => $filters,
            'hoursPeriods' => self::PERIODS,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $players
     */
    private function hoursCsv(string $view, array $rows, array $players): Response
    {
        $fh = fopen('php://temp', 'r+');
        $put = static function (array $cells) use ($fh): void {
            fputcsv($fh, array_map(static fn (mixed $c): string => PlaytimeSheet::csvCell((string) $c), $cells), ';', '"', '');
        };
        if ($view === 'joueurs') {
            $put(['Joueur', 'Indicatif', 'Temps de jeu (période, h)', 'Temps de jeu (période, h:mm)', 'Cumul total (h)', 'Serveur (h)', 'Zeus (h)', 'Éditeur (h)', 'Non ventilé (h)', 'Rôle principal', 'Part du rôle principal (%)', 'Rôles', 'Sessions', 'Dernière partie']);
            foreach ($players as $p) {
                $opt = static fn (?int $s): string => $s === null ? '' : PlaytimeSheet::decimal($s);
                $put([
                    $p['display_name'], $p['callsign'], PlaytimeSheet::decimal($p['play']), PlaytimeSheet::clock($p['play']),
                    $opt($p['total']), $opt($p['server']), $opt($p['zeus']), $opt($p['editor']), $opt($p['unsplit']),
                    $p['top_role'], PlaytimeSheet::frenchNumber($p['top_role_share']), $p['roles'], $p['sessions'], PlaytimeSheet::frenchDate($p['last_day']),
                ]);
            }
        } else {
            $put(['Joueur', 'Indicatif', 'Type', 'Rôle / élément', 'Détection', 'Remonté par', 'Heures (h:mm)', 'Heures décimales', 'Part du temps de jeu (%)', 'Sessions', 'Jours', 'Première', 'Dernière']);
            foreach ($rows as $r) {
                $put([
                    $r['display_name'], $r['callsign'], AtakRoleDetection::kindLabel($r['kind'], $r['key']), $r['label'],
                    $r['detection'] !== '' ? AtakRoleDetection::label($r['detection']) : '',
                    AtakRoleDetection::sourceLabel($r['source_mask']),
                    PlaytimeSheet::clock($r['seconds']), PlaytimeSheet::decimal($r['seconds']), PlaytimeSheet::frenchNumber($r['share']),
                    $r['sessions'], $r['days'], PlaytimeSheet::frenchDate($r['first_day']), PlaytimeSheet::frenchDate($r['last_day']),
                ]);
            }
        }
        rewind($fh);
        $csv = "\xEF\xBB\xBF" . (stream_get_contents($fh) ?: '');
        fclose($fh);
        $filename = 'heures-de-jeu-' . ($view === 'joueurs' ? 'par-joueur-' : '') . date('Ymd') . '.csv';

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    private static function dateParam(mixed $raw): ?string
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        return $dt !== false && $dt->format('Y-m-d') === $raw ? $raw : null;
    }

    /**
     * @param list<int> $allowed
     */
    private function period(Request $request, int $default, array $allowed): int
    {
        $raw = $request->query('jours');
        if ($raw === null || $raw === '') {
            return $default;
        }
        $n = (int) $raw;

        return in_array($n, $allowed, true) ? $n : $default;
    }
}
