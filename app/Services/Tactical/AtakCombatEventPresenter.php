<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakDataRepository;

/**
 * Traduit les rafales de combat remontées du terrain en lignes de journal d’analyse.
 * Pas d’invention : un événement n’existe que s’il a été agrégé côté jeu.
 */
final class AtakCombatEventPresenter
{
    /**
     * @param array<string, mixed> $extra
     * @return list<array{type: string, message: string, severity: string, debounce: int, payload: array<string, mixed>}>
     */
    public static function fromExtra(string $unitRef, array $extra): array
    {
        $unitRef = trim($unitRef);
        if ($unitRef === '') {
            return [];
        }
        if (self::skipWholeExtra($extra, $unitRef)) {
            return [];
        }

        $raw = $extra['combat_events'] ?? null;
        if (!is_array($raw) || $raw === []) {
            return [];
        }
        $list = self::isList($raw) ? $raw : [$raw];
        $out = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $built = self::one($unitRef, $row, $extra);
            if ($built !== null) {
                $out[] = $built;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function skipWholeExtra(array $extra, string $unitRef = ''): bool
    {
        if (!empty($extra['phone_geoloc']) || strtolower((string) ($extra['source'] ?? '')) === 'phone') {
            return true;
        }

        return AtakDataRepository::shouldHideEnemyAiContact($extra, $unitRef);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $extra
     * @return array{type: string, message: string, severity: string, debounce: int, payload: array<string, mixed>}|null
     */
    private static function one(string $unitRef, array $row, array $extra): ?array
    {
        $aff = strtolower(trim((string) ($row['aff'] ?? $row['affiliation'] ?? '')));
        if ($aff === 'hostile' && !AtakDataRepository::showEnemyAiEnabled($extra)) {
            return null;
        }
        $who = trim((string) ($row['u'] ?? $row['unit'] ?? $unitRef));
        if ($who === '') {
            $who = $unitRef;
        }
        $kind = strtolower(trim((string) ($row['t'] ?? $row['type'] ?? '')));
        $shots = self::count($row['n'] ?? $row['shots'] ?? null);
        $outcome = strtolower(trim((string) ($row['out'] ?? $row['outcome'] ?? '')));
        $exchange = !empty($row['exch']) || !empty($row['exchange']) || $kind === 'exchange';
        $x = self::num($row['x'] ?? null);
        $y = self::num($row['y'] ?? null);
        $payload = array_filter([
            'x' => $x,
            'y' => $y,
            'shots' => $shots > 0 ? $shots : null,
            'kind' => $kind !== '' ? $kind : null,
            'outcome' => $outcome !== '' ? $outcome : null,
        ], static fn ($v) => $v !== null);

        if ($kind === 'hit' || $kind === 'impact') {
            $msg = $shots > 1
                ? 'Impacts sur ' . $who
                : 'Impact sur ' . $who;

            return [
                'type' => 'UNIT_HIT',
                'message' => $msg,
                'severity' => 'alert',
                'debounce' => 5,
                'payload' => $payload,
            ];
        }

        if ($kind === 'missile' || $kind === 'lock') {
            return self::missile($who, $outcome !== '' ? $outcome : ($kind === 'lock' ? 'lock' : 'attempt'), $payload);
        }

        if ($kind === 'exchange' || ($exchange && ($kind === 'fire' || $kind === 'shot' || $kind === ''))) {
            return [
                'type' => 'UNIT_FIRE_EXCHANGE',
                'message' => $who . ' en échange de feu',
                'severity' => 'alert',
                'debounce' => 12,
                'payload' => $payload,
            ];
        }

        if ($kind === 'fire' || $kind === 'shot' || $kind === 'burst') {
            $msg = $shots >= 4
                ? $who . ' ouvre le feu (rafale)'
                : $who . ' ouvre le feu';

            return [
                'type' => 'UNIT_FIRING',
                'message' => $msg,
                'severity' => 'warn',
                'debounce' => 8,
                'payload' => $payload,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{type: string, message: string, severity: string, debounce: int, payload: array<string, mixed>}
     */
    private static function missile(string $who, string $outcome, array $payload): array
    {
        return match ($outcome) {
            'shot', 'launch', 'fired' => [
                'type' => 'UNIT_MISSILE',
                'message' => $who . ' tire un missile',
                'severity' => 'alert',
                'debounce' => 10,
                'payload' => $payload,
            ],
            'lock', 'locked' => [
                'type' => 'UNIT_MISSILE_LOCK',
                'message' => 'Verrouillage missile sur ' . $who,
                'severity' => 'warn',
                'debounce' => 15,
                'payload' => $payload,
            ],
            'miss', 'missed', 'evade' => [
                'type' => 'UNIT_MISSILE_MISS',
                'message' => 'Missile manqué près de ' . $who,
                'severity' => 'warn',
                'debounce' => 10,
                'payload' => $payload,
            ],
            default => [
                'type' => 'UNIT_MISSILE',
                'message' => 'Tentative de missile vers ' . $who,
                'severity' => 'alert',
                'debounce' => 10,
                'payload' => $payload,
            ],
        };
    }

    /**
     * @param array<mixed> $arr
     */
    private static function isList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_is_list($arr);
    }

    private static function count(mixed $v): int
    {
        $n = self::num($v);

        return $n === null ? 0 : max(0, (int) round($n));
    }

    private static function num(mixed $v): ?float
    {
        if ($v === null || $v === '' || is_bool($v)) {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return is_finite($f) ? $f : null;
    }
}
