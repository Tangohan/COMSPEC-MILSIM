<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use DateInterval;
use DateTimeImmutable;
use Throwable;

/**
 * Regroupe les tranches d’affectation issues des enregistrements successifs du dossier
 * (même unité, mêmes jours, rôles qui basculent le jour même) pour l’affichage et le
 * recalcul des durées. Les vrais changements de fonction sur plusieurs jours restent séparés.
 */
final class PersonnelAssignmentHistoryCoalescer
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function coalesceForDisplay(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $unitId = (int) ($row['unit_id'] ?? 0);
            $start = self::parseDate($row['started_at'] ?? $row['created_at'] ?? null);
            if ($unitId < 1 || $start === null) {
                $normalized[] = $row;
                continue;
            }
            $end = self::parseDate($row['ended_at'] ?? null);
            $open = self::isOpen($row, $end);
            $normalized[] = array_merge($row, [
                '_coal_unit' => $unitId,
                '_coal_role' => self::normalizeRole((string) ($row['role_name'] ?? '')),
                '_coal_start' => $start,
                '_coal_end' => $end,
                '_coal_open' => $open,
                '_coal_same_day' => self::isSameDaySlice($start, $end, $open),
            ]);
        }

        $plain = [];
        $byUnit = [];
        foreach ($normalized as $row) {
            if (!isset($row['_coal_unit'])) {
                $plain[] = $row;
                continue;
            }
            $byUnit[$row['_coal_unit']][] = $row;
        }

        $out = $plain;
        foreach ($byUnit as $unitRows) {
            foreach (self::mergeUnitRows($unitRows) as $merged) {
                unset(
                    $merged['_coal_unit'],
                    $merged['_coal_role'],
                    $merged['_coal_start'],
                    $merged['_coal_end'],
                    $merged['_coal_open'],
                    $merged['_coal_same_day']
                );
                $out[] = $merged;
            }
        }

        usort($out, static function (array $a, array $b): int {
            $sa = self::sortKey($a);
            $sb = self::sortKey($b);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }

            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        });

        return array_values($out);
    }

    /**
     * @param list<array<string, mixed>> $unitRows
     * @return list<array<string, mixed>>
     */
    private static function mergeUnitRows(array $unitRows): array
    {
        usort($unitRows, static function (array $a, array $b): int {
            $cmp = $a['_coal_start'] <=> $b['_coal_start'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        $merged = [];
        foreach ($unitRows as $row) {
            $lastIndex = count($merged) - 1;
            if ($lastIndex < 0) {
                $merged[] = self::seedMerged($row);
                continue;
            }
            $last = $merged[$lastIndex];
            if (self::shouldMerge($last, $row)) {
                $merged[$lastIndex] = self::absorb($last, $row);
                continue;
            }
            $merged[] = self::seedMerged($row);
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $incoming
     */
    private static function shouldMerge(array $current, array $incoming): bool
    {
        if (!self::rangesTouch($current, $incoming)) {
            return false;
        }
        if ((string) $current['_coal_role'] === (string) $incoming['_coal_role']) {
            return true;
        }

        return !empty($current['_coal_same_day']) || !empty($incoming['_coal_same_day']);
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $incoming
     */
    private static function rangesTouch(array $current, array $incoming): bool
    {
        /** @var DateTimeImmutable $currentStart */
        $currentStart = $current['_coal_start'];
        /** @var DateTimeImmutable $incomingStart */
        $incomingStart = $incoming['_coal_start'];
        $currentEnd = !empty($current['_coal_open'])
            ? $incomingStart
            : ($current['_coal_end'] ?? $currentStart);
        $incomingEnd = !empty($incoming['_coal_open'])
            ? $incomingStart
            : ($incoming['_coal_end'] ?? $incomingStart);
        if (!$currentEnd instanceof DateTimeImmutable) {
            $currentEnd = $currentStart;
        }
        if (!$incomingEnd instanceof DateTimeImmutable) {
            $incomingEnd = $incomingStart;
        }
        $currentTouch = $currentEnd->add(new DateInterval('P1D'));

        return $incomingStart <= $currentTouch && $currentStart <= $incomingEnd->add(new DateInterval('P1D'));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function seedMerged(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $row['coalesced_from_ids'] = $id > 0 ? [$id] : [];
        $row['coalesced_roles'] = [(string) $row['_coal_role']];

        return $row;
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    private static function absorb(array $current, array $incoming): array
    {
        $winner = self::preferWinner($current, $incoming);
        $loser = $winner === $current ? $incoming : $current;
        $start = $current['_coal_start'] <= $incoming['_coal_start']
            ? $current['_coal_start']
            : $incoming['_coal_start'];
        $open = !empty($current['_coal_open']) || !empty($incoming['_coal_open']);
        $end = null;
        if (!$open) {
            $ends = array_values(array_filter([
                $current['_coal_end'] ?? null,
                $incoming['_coal_end'] ?? null,
            ], static fn ($d): bool => $d instanceof DateTimeImmutable));
            if ($ends !== []) {
                usort($ends, static fn (DateTimeImmutable $a, DateTimeImmutable $b): int => $b <=> $a);
                $end = $ends[0];
            }
        }
        $ids = array_values(array_unique(array_filter(array_merge(
            is_array($current['coalesced_from_ids'] ?? null) ? $current['coalesced_from_ids'] : [],
            is_array($incoming['coalesced_from_ids'] ?? null) ? $incoming['coalesced_from_ids'] : [],
            [(int) ($current['id'] ?? 0), (int) ($incoming['id'] ?? 0)]
        ), static fn (int $id): bool => $id > 0)));
        $roles = array_values(array_unique(array_filter(array_merge(
            is_array($current['coalesced_roles'] ?? null) ? $current['coalesced_roles'] : [],
            is_array($incoming['coalesced_roles'] ?? null) ? $incoming['coalesced_roles'] : [],
            [(string) $current['_coal_role'], (string) $incoming['_coal_role']]
        ), static fn (string $r): bool => $r !== '')));

        $out = $winner;
        $out['_coal_start'] = $start;
        $out['_coal_end'] = $end;
        $out['_coal_open'] = $open;
        $out['_coal_same_day'] = self::isSameDaySlice($start, $end, $open);
        $out['started_at'] = $start->format('Y-m-d');
        $out['ended_at'] = $open || $end === null ? null : $end->format('Y-m-d');
        $out['status'] = $open ? 'active' : (string) ($out['status'] ?? 'inactive');
        $out['coalesced_from_ids'] = $ids;
        $out['coalesced_roles'] = $roles;
        if ($loser !== $winner && trim((string) ($out['role_name'] ?? '')) === '') {
            $out['role_name'] = $loser['role_name'] ?? 'Membre';
            $out['_coal_role'] = self::normalizeRole((string) $out['role_name']);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    private static function preferWinner(array $a, array $b): array
    {
        $aOpen = !empty($a['_coal_open']);
        $bOpen = !empty($b['_coal_open']);
        if ($aOpen !== $bOpen) {
            return $aOpen ? $a : $b;
        }
        $aSameDay = !empty($a['_coal_same_day']);
        $bSameDay = !empty($b['_coal_same_day']);
        if ($aSameDay !== $bSameDay) {
            return $aSameDay ? $b : $a;
        }

        return ((int) ($a['id'] ?? 0)) >= ((int) ($b['id'] ?? 0)) ? $a : $b;
    }

    public static function normalizeRole(string $role): string
    {
        $role = trim($role);

        return $role !== '' ? $role : 'Membre';
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $desired
     */
    public static function sameActiveAssignment(array $current, array $desired): bool
    {
        if ((int) ($current['unit_id'] ?? 0) < 1 || (int) ($desired['unit_id'] ?? 0) < 1) {
            return false;
        }
        if ((int) $current['unit_id'] !== (int) $desired['unit_id']) {
            return false;
        }

        return self::normalizeRole((string) ($current['role_name'] ?? ''))
            === self::normalizeRole((string) ($desired['role_name'] ?? ''));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function isOpen(array $row, ?DateTimeImmutable $end): bool
    {
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($status === 'inactive') {
            return false;
        }
        if ($end === null) {
            return $status === 'active' || $status === '';
        }
        $today = new DateTimeImmutable('today');

        return ($status === 'active' || $status === '') && $end >= $today;
    }

    private static function isSameDaySlice(DateTimeImmutable $start, ?DateTimeImmutable $end, bool $open): bool
    {
        if ($open) {
            return false;
        }
        if ($end === null) {
            return true;
        }

        return $start->format('Y-m-d') === $end->format('Y-m-d');
    }

    private static function parseDate(mixed $raw): ?DateTimeImmutable
    {
        if (!is_string($raw) && !is_numeric($raw)) {
            return null;
        }
        $value = trim((string) $raw);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }
        try {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
                return new DateTimeImmutable($m[1]);
            }

            return (new DateTimeImmutable($value))->setTime(0, 0);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function sortKey(array $row): string
    {
        $start = trim((string) ($row['started_at'] ?? ''));
        if ($start !== '') {
            return $start;
        }

        return trim((string) ($row['created_at'] ?? ''));
    }
}
