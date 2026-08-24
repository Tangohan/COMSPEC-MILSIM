<?php

declare(strict_types=1);

namespace App\Services\Replay;

use App\Repositories\AtakDataRepository;

/**
 * Construit les instantanés de relecture : toutes les traces visibles au même moment.
 */
final class ReplayTimelineBuilder
{
    public const BUCKET_SECONDS = 2;
    public const HOLD_SECONDS = 90;

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{timestamp: string, units: list<array<string, mixed>>}>
     */
    public static function framesFromRows(array $rows, int $bucketSeconds = self::BUCKET_SECONDS, int $holdSeconds = self::HOLD_SECONDS): array
    {
        if ($rows === []) {
            return [];
        }

        $buckets = [];
        foreach ($rows as $row) {
            $ts = self::parseTs((string) ($row['logged_at'] ?? ''));
            if ($ts <= 0) {
                continue;
            }
            $bucket = intdiv($ts, $bucketSeconds) * $bucketSeconds;
            $buckets[$bucket][] = ['ts' => $ts, 'row' => $row];
        }
        if ($buckets === []) {
            return [];
        }
        ksort($buckets, SORT_NUMERIC);

        $last = [];
        $frames = [];
        foreach ($buckets as $bucketTs => $entries) {
            foreach ($entries as $entry) {
                $snap = self::snapshot($entry['row'], (int) $entry['ts']);
                $last[$snap['unitId']] = $snap;
            }
            $units = [];
            foreach ($last as $snap) {
                if (($bucketTs - (int) $snap['lastSeen']) > $holdSeconds) {
                    continue;
                }
                $units[] = self::publicUnit($snap);
            }
            usort($units, static fn (array $a, array $b): int => strcmp((string) $a['callsign'], (string) $b['callsign']));
            if ($units === []) {
                continue;
            }
            $frames[] = [
                'timestamp' => gmdate('Y-m-d H:i:s', $bucketTs),
                'units' => $units,
            ];
        }

        return $frames;
    }

    /**
     * @param array<string, mixed>|null $state
     */
    public static function inferKind(string $callsign, ?string $unitType, mixed $state): string
    {
        $ut = strtolower(trim((string) $unitType));
        if ($ut === 'gps_beacon') {
            $ut = 'gps';
        }
        if (in_array($ut, ['player', 'ally_ai', 'phone', 'gps'], true)) {
            return $ut;
        }

        $extra = self::decodeState($state);
        if (self::flagOn($extra['phone_geoloc'] ?? null) || strtolower((string) ($extra['source'] ?? '')) === 'phone') {
            return 'phone';
        }
        if (
            self::flagOn($extra['ally_ai'] ?? null)
            || self::flagOn($extra['is_ai'] ?? null)
            || strtolower((string) ($extra['source'] ?? '')) === 'ally'
        ) {
            return 'ally_ai';
        }
        $src = strtolower((string) ($extra['source'] ?? ''));
        if (self::flagOn($extra['gps_beacon'] ?? null) || in_array($src, ['gps', 'gps_beacon'], true)) {
            return 'gps';
        }
        if (AtakDataRepository::callSignLooksLikeProxy($callsign)) {
            $fold = function_exists('mb_strtoupper') ? mb_strtoupper($callsign, 'UTF-8') : strtoupper($callsign);
            if (str_starts_with($fold, 'ALLY-')) {
                return 'ally_ai';
            }
            if (str_starts_with($fold, 'GPS-')) {
                return 'gps';
            }

            return 'phone';
        }

        return 'player';
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'ally_ai' => 'Unité alliée',
            'phone' => 'Téléphone',
            'gps' => 'Balise GPS',
            default => 'Opérateur',
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function snapshot(array $row, int $ts): array
    {
        $callsign = trim((string) ($row['callsign'] ?? $row['unit_id'] ?? ''));
        $unitId = trim((string) ($row['unit_id'] ?? $callsign));
        if ($unitId === '') {
            $unitId = $callsign !== '' ? $callsign : ('u-' . $ts);
        }
        $extra = self::decodeState($row['state_json'] ?? null);
        $kind = self::inferKind($callsign, isset($row['unit_type']) ? (string) $row['unit_type'] : null, $extra);
        if ($kind === 'phone') {
            $extra['phone_geoloc'] = true;
            $extra['source'] = $extra['source'] ?? 'phone';
        } elseif ($kind === 'ally_ai') {
            $extra['ally_ai'] = true;
            $extra['source'] = $extra['source'] ?? 'ally';
        } elseif ($kind === 'gps') {
            $extra['gps_beacon'] = true;
            $extra['source'] = $extra['source'] ?? 'gps';
        }
        if (!isset($extra['affiliation']) && !isset($extra['affil'])) {
            $extra['affiliation'] = $kind === 'phone' ? 'unknown' : 'friend';
        }

        return [
            'unitId' => $unitId,
            'callsign' => $callsign !== '' ? $callsign : $unitId,
            'x' => (float) ($row['pos_x'] ?? 0),
            'y' => (float) ($row['pos_y'] ?? 0),
            'z' => (float) ($row['pos_z'] ?? 0),
            'heading' => isset($row['heading']) && $row['heading'] !== null && $row['heading'] !== ''
                ? (float) $row['heading']
                : null,
            'kind' => $kind,
            'extra' => $extra,
            'lastSeen' => $ts,
        ];
    }

    /**
     * @param array<string, mixed> $snap
     * @return array<string, mixed>
     */
    private static function publicUnit(array $snap): array
    {
        return [
            'unitId' => $snap['unitId'],
            'callsign' => $snap['callsign'],
            'x' => $snap['x'],
            'y' => $snap['y'],
            'z' => $snap['z'],
            'heading' => $snap['heading'],
            'kind' => $snap['kind'],
            'kindLabel' => self::kindLabel((string) $snap['kind']),
            'extra' => $snap['extra'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeState(mixed $state): array
    {
        if (is_array($state)) {
            return $state;
        }
        if (!is_string($state) || $state === '') {
            return [];
        }
        $decoded = json_decode($state, true);

        return is_array($decoded) ? $decoded : AtakDataRepository::decodeExtra($state);
    }

    private static function flagOn(mixed $val): bool
    {
        return $val === true || $val === 1 || $val === '1' || $val === 'true';
    }

    private static function parseTs(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0;
        }
        $t = strtotime($raw);

        return $t !== false ? $t : 0;
    }
}
