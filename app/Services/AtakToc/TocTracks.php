<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

/**
 * Pistes de la carte du poste TOC web, préparées côté site (paramètre toc=1 de /api/atak/units, /api/atak/vehicles et
 * /api/atak/air-assets) : une seule ligne par joueur ou par nœud (le plus récent gagne), genre du symbole, âge sur
 * l'horloge du serveur. Les droits, le cloisonnement par communauté et le brouillage restent ceux de l'API ATAK.
 */
final class TocTracks
{
    private const RANK = ['linked' => 3, 'delayed' => 2, 'offline' => 1];

    /** @param mixed $extra */
    private static function extra($extra): array
    {
        if (is_string($extra) && $extra !== '') {
            $d = json_decode($extra, true);

            return is_array($d) ? $d : [];
        }

        return is_array($extra) ? $extra : [];
    }

    /**
     * Identifiant stable d'un nœud : le compte Steam du joueur, sinon son identifiant militaire, sinon l'indicatif
     * (IA, contacts sans compte), sinon l'id de la ligne.
     *
     * @param array<string, mixed> $row
     */
    public static function stableId(array $row): string
    {
        $ex = self::extra($row['extra'] ?? null);
        foreach (['steam_uid', 'steamId', 'player_uid'] as $k) {
            $v = trim((string) ($ex[$k] ?? ''));
            if ($v !== '' && $v !== '0') {
                return 's:' . strtolower($v);
            }
        }
        $gw = trim((string) ($row['gateway_id'] ?? ''));
        $cs = mb_strtolower(trim((string) ($row['call_sign'] ?? '')));
        if ($cs !== '') {
            return ($gw !== '' ? 'g' . $gw . ':' : 'c:') . $cs;
        }

        return 'i:' . (string) ($row['id'] ?? '');
    }

    /** @param array<string, mixed> $row */
    private static function age(array $row, int $now): ?int
    {
        if (isset($row['age_seconds']) && is_numeric($row['age_seconds'])) {
            return max(0, (int) $row['age_seconds']);
        }
        foreach (['last_seen_at', 'updated_at'] as $k) {
            $t = isset($row[$k]) && $row[$k] !== '' ? strtotime((string) $row[$k]) : false;
            if ($t !== false) {
                return max(0, $now - $t);
            }
        }

        return null;
    }

    /**
     * Une seule ligne par identifiant stable. Un joueur qui a changé d'indicatif, de groupe ou de session laisse ses
     * anciennes lignes dans atak_units : seule la plus vivante (en liaison > différée > hors liaison), puis la plus
     * récente, est gardée. L'ordre d'arrivée est conservé.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function dedupe(array $rows, ?int $now = null): array
    {
        $now ??= time();
        $best = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = self::stableId($row);
            if (!isset($best[$key])) {
                $best[$key] = $row;
                continue;
            }
            $cur = $best[$key];
            $rn = self::RANK[(string) ($row['status'] ?? '')] ?? 0;
            $rc = self::RANK[(string) ($cur['status'] ?? '')] ?? 0;
            $an = self::age($row, $now) ?? PHP_INT_MAX;
            $ac = self::age($cur, $now) ?? PHP_INT_MAX;
            if ($rn > $rc || ($rn === $rc && $an < $ac)) {
                $best[$key] = $row;
            }
        }

        return array_values($best);
    }

    /** @param mixed $v */
    private static function num($v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * Ajoute « toc » à une ligne de /api/atak/units : identifiant stable, genre du symbole, altitude, vitesse (m/s),
     * véhicule, équipage, IA, âge (s).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function decorateUnit(array $row, ?int $now = null): array
    {
        $now ??= time();
        $ex = self::extra($row['extra'] ?? null);
        $veh = $ex['vehicle'] ?? '';
        $vehName = is_array($veh) ? (string) ($veh['name'] ?? $veh['type'] ?? $veh['class'] ?? '') : (string) $veh;
        if ($vehName === '') {
            $vehName = (string) ($ex['vehicle_name'] ?? $ex['vehicle_type'] ?? $ex['vehicle_label'] ?? $ex['model'] ?? '');
        }
        $vehClass = (string) ($ex['vehicle_class'] ?? (is_array($veh) ? ($veh['class_kind'] ?? $veh['vehicle_class'] ?? '') : ''));
        $kind = TocSymbols::kindFor([
            'platform' => $ex['platform'] ?? '',
            'vehicle_class' => $vehClass,
            'aircraft_type' => $ex['aircraft_type'] ?? ($row['aircraft_type'] ?? ''),
            'vehicle' => $vehName,
            'in_vehicle' => $ex['in_vehicle'] ?? false,
            'role' => $row['role'] ?? ($ex['role'] ?? ''),
        ]);
        $occ = $ex['occupants'] ?? $ex['passengers_json'] ?? null;
        if (is_string($occ) && $occ !== '') {
            $occ = json_decode($occ, true);
        }
        $flag = static fn ($v): bool => $v === true || $v === 1 || $v === '1' || $v === 'true';
        $speed = self::num($row['speed'] ?? null) ?? self::num($ex['speed_ms'] ?? null) ?? self::num($ex['speed'] ?? null);
        $row['toc'] = [
            'uid' => self::stableId($row),
            'kind' => $kind,
            'air' => TocSymbols::isAir($kind),
            'alt' => self::num($ex['asl_z'] ?? $ex['pos_z'] ?? $ex['altitude'] ?? $ex['alt'] ?? null),
            'speed' => $speed,
            'vehicle' => mb_substr($vehName, 0, 60),
            'crew' => is_array($occ) ? count($occ) : (is_numeric($ex['crew_count'] ?? null) ? (int) $ex['crew_count'] : null),
            'ai' => $flag($ex['is_ai'] ?? false) || $flag($ex['ally_ai'] ?? false) || $flag($ex['enemy_ai'] ?? false),
            'hostile' => $flag($ex['enemy_ai'] ?? false) || ($ex['source'] ?? '') === 'enemy',
            'battery' => is_numeric($row['battery_pct'] ?? ($ex['battery_pct'] ?? ($ex['battery'] ?? null))) ? (int) ($row['battery_pct'] ?? ($ex['battery_pct'] ?? $ex['battery'])) : null,
            'age' => self::age($row, $now),
        ];

        return $row;
    }

    /**
     * Liste de /api/atak/units pour le poste : dédoublonnée puis décorée.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function units(array $rows, ?int $now = null): array
    {
        $now ??= time();

        return array_map(static fn (array $r): array => self::decorateUnit($r, $now), self::dedupe($rows, $now));
    }

    /**
     * Véhicules suivis (/api/atak/vehicles) : un par identifiant, genre, âge.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function vehicles(array $rows, ?int $now = null): array
    {
        $now ??= time();
        $out = [];
        foreach ($rows as $v) {
            if (!is_array($v)) {
                continue;
            }
            $key = strtolower(trim((string) ($v['vehicle_net_id'] ?? $v['vehicle_uid'] ?? '')));
            if ($key === '') {
                $cs = mb_strtolower(trim((string) ($v['vehicle_callsign'] ?? '')));
                $key = $cs !== '' ? 'c:' . $cs : 'i:' . (string) ($v['id'] ?? count($out));
            }
            $age = self::age($v, $now);
            if (isset($out[$key]) && (self::age($out[$key], $now) ?? PHP_INT_MAX) <= ($age ?? PHP_INT_MAX)) {
                continue;
            }
            $kind = TocSymbols::kindFor(['vehicle_class' => $v['vehicle_class'] ?? '', 'vehicle' => (string) ($v['vehicle_name'] ?? $v['vehicle_type'] ?? '')]);
            if ($kind === 'infantry') {
                $kind = 'motorized';
            }
            $v['toc'] = ['uid' => 'v:' . $key, 'kind' => $kind, 'air' => TocSymbols::isAir($kind), 'age' => $age];
            $out[$key] = $v;
        }

        return array_values($out);
    }

    /**
     * Aéronefs (/api/atak/air-assets) : un par indicatif, genre (hélicoptère, avion, drone), âge.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function air(array $rows, ?int $now = null): array
    {
        $now ??= time();
        $out = [];
        foreach ($rows as $a) {
            if (!is_array($a)) {
                continue;
            }
            $key = mb_strtolower(trim((string) ($a['callsign'] ?? '')));
            if ($key === '' || isset($out[$key])) {
                continue;
            }
            $kind = TocSymbols::kindFor(['aircraft_type' => $a['aircraft_type'] ?? '', 'vehicle' => (string) ($a['model'] ?? '')]);
            if (!TocSymbols::isAir($kind)) {
                $kind = 'fixed';
            }
            $a['toc'] = ['uid' => 'a:' . $key, 'kind' => $kind, 'air' => true, 'age' => self::age($a, $now)];
            $out[$key] = $a;
        }

        return array_values($out);
    }
}
