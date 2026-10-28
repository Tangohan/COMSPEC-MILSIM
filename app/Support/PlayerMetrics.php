<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Page back-office « Métriques joueurs » : réunit par membre ce que le jeu remonte déjà ailleurs
 * (temps de jeu et d'écran des téléphones, distances et heures de pilote comptées par le serveur Arma, montre Garmin)
 * et en tire les totaux de la communauté.
 */
final class PlayerMetrics
{
    /** Tris proposés : clé => libellé. */
    public const SORTS = [
        'jeu' => 'Temps de jeu',
        'ecran' => 'Temps d’écran',
        'distance' => 'Distance totale',
        'pied' => 'Distance à pied',
        'vol' => 'Heures de vol',
        'pas' => 'Pas (montre)',
        'nom' => 'Nom',
    ];

    /**
     * @param list<array<string, mixed>> $screenRows AtakScreenTimeRepository::summaryByUser
     * @param array<int, array<string, mixed>> $distanceRows AtakDistanceRepository::summaryByUser
     * @param array<int, int> $pilotByUser secondes de pilote par membre
     * @param array<int, array<string, mixed>> $garminRows AtakGarminRepository::summaryByUser
     * @return list<array{user_id: int, display_name: string, callsign: string, name: string, play: int, screen: int, top_role: string, foot_m: int, land_m: int, sea_m: int, air_m: int, halo_m: int, halo_jumps: int, total_m: int, steps: int, sessions: int, pilot_s: int, garmin: ?array<string, mixed>, last_day: string}>
     */
    public static function merge(array $screenRows, array $distanceRows, array $pilotByUser, array $garminRows, string $sort = 'jeu'): array
    {
        $rows = [];
        $row = static function (int $uid, array $src) use (&$rows): void {
            $rows[$uid] ??= [
                'user_id' => $uid, 'display_name' => '', 'callsign' => '', 'name' => '',
                'play' => 0, 'screen' => 0, 'top_role' => '',
                'foot_m' => 0, 'land_m' => 0, 'sea_m' => 0, 'air_m' => 0, 'halo_m' => 0, 'halo_jumps' => 0, 'total_m' => 0,
                'steps' => 0, 'sessions' => 0, 'pilot_s' => 0, 'garmin' => null, 'last_day' => '',
            ];
            foreach (['display_name', 'callsign'] as $k) {
                if ($rows[$uid][$k] === '' && trim((string) ($src[$k] ?? '')) !== '') {
                    $rows[$uid][$k] = trim((string) $src[$k]);
                }
            }
        };
        foreach ($screenRows as $s) {
            $uid = (int) ($s['user_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $row($uid, $s);
            $rows[$uid]['play'] = (int) ($s['play'] ?? 0);
            $rows[$uid]['screen'] = (int) ($s['screen'] ?? 0);
            $rows[$uid]['top_role'] = (string) ($s['roles'][0]['label'] ?? '');
            $rows[$uid]['last_day'] = max($rows[$uid]['last_day'], (string) ($s['last_day'] ?? ''));
        }
        foreach ($distanceRows as $uid => $d) {
            $uid = (int) $uid;
            if ($uid < 1) {
                continue;
            }
            $row($uid, $d);
            $t = is_array($d['totals'] ?? null) ? $d['totals'] : [];
            foreach (['foot_m', 'land_m', 'sea_m', 'air_m', 'halo_m', 'halo_jumps', 'steps'] as $k) {
                $rows[$uid][$k] = (int) ($t[$k] ?? 0);
            }
            $rows[$uid]['total_m'] = (int) ($d['total_m'] ?? 0);
            $rows[$uid]['sessions'] = (int) ($d['sessions'] ?? 0);
            $rows[$uid]['last_day'] = max($rows[$uid]['last_day'], (string) ($d['last_day'] ?? ''));
        }
        foreach ($garminRows as $uid => $g) {
            $uid = (int) $uid;
            if ($uid < 1) {
                continue;
            }
            $row($uid, $g);
            $rows[$uid]['garmin'] = $g;
        }
        // Les heures de vol sont un cumul : on ne crée pas de ligne pour un membre sans autre activité sur la période.
        foreach ($pilotByUser as $uid => $sec) {
            if (isset($rows[(int) $uid])) {
                $rows[(int) $uid]['pilot_s'] = (int) $sec;
            }
        }
        foreach ($rows as &$r) {
            $r['name'] = $r['callsign'] !== '' ? $r['callsign'] : ($r['display_name'] !== '' ? $r['display_name'] : 'Membre #' . $r['user_id']);
        }
        unset($r);

        return self::sort(array_values($rows), $sort);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function sort(array $rows, string $sort): array
    {
        $key = match ($sort) {
            'ecran' => 'screen',
            'distance' => 'total_m',
            'pied' => 'foot_m',
            'vol' => 'pilot_s',
            'pas' => null,
            'nom' => '',
            default => 'play',
        };
        usort($rows, static function (array $a, array $b) use ($key): int {
            if ($key === '') {
                return strnatcasecmp($a['name'], $b['name']);
            }
            $va = $key === null ? (int) ($a['garmin']['steps'] ?? 0) : (int) $a[$key];
            $vb = $key === null ? (int) ($b['garmin']['steps'] ?? 0) : (int) $b[$key];

            return [$vb, $b['play'], $a['name']] <=> [$va, $a['play'], $b['name']];
        });

        return $rows;
    }

    /**
     * Totaux de la communauté sur les lignes fusionnées.
     *
     * @param list<array<string, mixed>> $rows
     * @return array{members: int, play: int, screen: int, total_m: int, foot_m: int, land_m: int, sea_m: int, air_m: int, halo_m: int, halo_jumps: int, steps: int, pilot_s: int, watches: int, watch_steps: int}
     */
    public static function totals(array $rows): array
    {
        $t = ['members' => count($rows), 'play' => 0, 'screen' => 0, 'total_m' => 0, 'foot_m' => 0, 'land_m' => 0, 'sea_m' => 0, 'air_m' => 0, 'halo_m' => 0, 'halo_jumps' => 0, 'steps' => 0, 'pilot_s' => 0, 'watches' => 0, 'watch_steps' => 0];
        foreach ($rows as $r) {
            foreach (['play', 'screen', 'total_m', 'foot_m', 'land_m', 'sea_m', 'air_m', 'halo_m', 'halo_jumps', 'steps', 'pilot_s'] as $k) {
                $t[$k] += (int) ($r[$k] ?? 0);
            }
            if (is_array($r['garmin'] ?? null)) {
                $t['watches']++;
                $t['watch_steps'] += (int) ($r['garmin']['steps'] ?? 0);
            }
        }

        return $t;
    }

    /** « 12,4 km » ou « 850 m ». */
    public static function distance(int $m): string
    {
        if ($m < 1000) {
            return $m . ' m';
        }

        return number_format($m / 1000, $m < 100000 ? 1 : 0, ',', ' ') . ' km';
    }

    /** « 45 min », « 3 h 05 ». */
    public static function duration(int $sec): string
    {
        if ($sec < 60) {
            return $sec . ' s';
        }
        $m = intdiv($sec, 60);
        if ($m < 60) {
            return $m . ' min';
        }

        return intdiv($m, 60) . ' h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
    }
}
