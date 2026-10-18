<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tableur des heures de jeu (/back-office/atak/heures) : formats et vue « par joueur », sans base de données.
 * Les lignes viennent de AtakScreenTimeRepository::sheetRows, les cumuls de ArmaPlaytimeRepository.
 */
final class PlaytimeSheet
{
    /** 20 580 s → « 5:43 » (heures:minutes, comme un tableur). */
    public static function clock(int $seconds): string
    {
        $m = intdiv(max(0, $seconds), 60);

        return intdiv($m, 60) . ':' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
    }

    /** 20 580 s → « 5,72 » (heures décimales, virgule française). */
    public static function decimal(int $seconds): string
    {
        return number_format(max(0, $seconds) / 3600, 2, ',', '');
    }

    /** Part en % (une décimale, plafonnée à 100), null sans base. */
    public static function share(int $part, int $whole): ?float
    {
        if ($whole < 1 || $part < 0) {
            return null;
        }

        return min(100.0, round(100 * $part / $whole, 1));
    }

    /** « 12.5 » → « 12,5 » pour l'export. */
    public static function frenchNumber(?float $value): string
    {
        return $value === null ? '' : rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',');
    }

    /** Cellule d'export : neutralise les formules (=, +, -, @) qu'un tableur exécuterait. */
    public static function csvCell(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t"], true) ? "'" . $value : $value;
    }

    /** Date AAAA-MM-JJ → JJ/MM/AAAA ('' si vide ou invalide). */
    public static function frenchDate(string $day): string
    {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $day);

        return $dt !== false ? $dt->format('d/m/Y') : '';
    }

    /**
     * Une ligne par joueur : temps de jeu de la période, cumul du jeu (user_arma_playtime) et sa ventilation,
     * rôle principal, nombre de rôles, sessions et dernière partie.
     *
     * @param list<array<string, mixed>> $rows lignes de AtakScreenTimeRepository::sheetRows
     * @param array<int, array{total_seconds: int, server_seconds: int, zeus_seconds: int, editor_seconds: int, last_report_at: ?string}> $summaries
     * @return list<array{user_id: int, display_name: string, callsign: string, play: int, total: ?int, server: ?int, zeus: ?int, editor: ?int, unsplit: ?int, top_role: string, top_role_seconds: int, top_role_share: ?float, roles: int, role_seconds: int, sessions: int, last_day: string}>
     */
    public static function pivot(array $rows, array $summaries): array
    {
        $players = [];
        foreach ($rows as $r) {
            $uid = (int) $r['user_id'];
            $players[$uid] ??= [
                'user_id' => $uid,
                'display_name' => (string) $r['display_name'],
                'callsign' => (string) $r['callsign'],
                'play' => 0, 'total' => null, 'server' => null, 'zeus' => null, 'editor' => null, 'unsplit' => null,
                'top_role' => '', 'top_role_seconds' => 0, 'top_role_share' => null,
                'roles' => 0, 'role_seconds' => 0, 'sessions' => 0, 'last_day' => '',
            ];
            $p = &$players[$uid];
            $sec = (int) $r['seconds'];
            $p['last_day'] = max($p['last_day'], (string) $r['last_day']);
            if ($r['kind'] === 'play' && $r['key'] === 'TOTAL') {
                $p['play'] += $sec;
                $p['sessions'] = max($p['sessions'], (int) $r['sessions']);
            } elseif ($r['kind'] === 'role') {
                $p['roles']++;
                $p['role_seconds'] += $sec;
                if ($sec > $p['top_role_seconds']) {
                    $p['top_role_seconds'] = $sec;
                    $p['top_role'] = (string) $r['label'];
                }
                // Sans ligne de temps de jeu (anciennes remontées), les sessions des rôles donnent un minimum.
                $p['sessions'] = max($p['sessions'], (int) $r['sessions']);
            }
            unset($p);
        }
        foreach ($players as $uid => &$p) {
            $p['top_role_share'] = $p['top_role_seconds'] > 0
                ? self::share($p['top_role_seconds'], max($p['play'], $p['role_seconds']))
                : null;
            $s = $summaries[$uid] ?? null;
            if (is_array($s)) {
                $p['total'] = (int) $s['total_seconds'];
                $p['server'] = (int) $s['server_seconds'];
                $p['zeus'] = (int) $s['zeus_seconds'];
                $p['editor'] = (int) $s['editor_seconds'];
                $p['unsplit'] = self::unsplit($s);
            }
        }
        unset($p);
        $list = array_values($players);
        usort($list, static fn (array $a, array $b): int => [$b['play'], $b['total'] ?? 0] <=> [$a['play'], $a['total'] ?? 0]);

        return $list;
    }

    /**
     * Temps du cumul d'avant le détail serveur / Zeus / éditeur (sessions remontées avant ces colonnes).
     *
     * @param array<string, mixed> $summary
     */
    public static function unsplit(array $summary): int
    {
        return max(0, (int) ($summary['total_seconds'] ?? 0)
            - (int) ($summary['server_seconds'] ?? 0)
            - (int) ($summary['zeus_seconds'] ?? 0)
            - (int) ($summary['editor_seconds'] ?? 0));
    }
}
