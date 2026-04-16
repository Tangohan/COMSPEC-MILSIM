<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use DateTimeImmutable;

final class SeniorityEngine
{
    /**
     * @param array{calc_mode?: string} $definition
     * @param list<array{start_date: string, end_date?: ?string, status?: ?string}> $periods
     *
     * @return array{formatted: string, days: int, reference_date: string}
     */
    public function compute(array $definition, array $periods, ?DateTimeImmutable $referenceDate = null): array
    {
        $referenceDate = $referenceDate ?? new DateTimeImmutable('today');
        $mode = (string) ($definition['calc_mode'] ?? 'from_start');

        return match ($mode) {
            'from_start' => $this->computeFromStart($periods, $referenceDate),
            'sum_periods' => $this->computeSumPeriods($periods, $referenceDate),
            'active_only' => $this->computeActiveOnly($periods, $referenceDate),
            'custom_rule' => $this->computeCustomRule($periods, $referenceDate),
            default => [
                'formatted' => '—',
                'days' => 0,
                'reference_date' => $referenceDate->format('Y-m-d'),
            ],
        };
    }

    /**
     * @param list<array{start_date: string, end_date?: ?string, status?: ?string}> $periods
     *
     * @return array{formatted: string, days: int, reference_date: string}
     */
    private function computeFromStart(array $periods, DateTimeImmutable $referenceDate): array
    {
        if ($periods === []) {
            return ['formatted' => '-', 'days' => 0, 'reference_date' => $referenceDate->format('Y-m-d')];
        }

        usort($periods, static fn (array $a, array $b): int => strcmp($a['start_date'], $b['start_date']));

        $start = new DateTimeImmutable($periods[0]['start_date']);
        $days = max(0, (int) $start->diff($referenceDate)->days);

        return [
            'formatted' => $this->formatDays($days),
            'days' => $days,
            'reference_date' => $referenceDate->format('Y-m-d'),
        ];
    }

    /**
     * @param list<array{start_date: string, end_date?: ?string, status?: ?string}> $periods
     *
     * @return array{formatted: string, days: int, reference_date: string}
     */
    private function computeSumPeriods(array $periods, DateTimeImmutable $referenceDate): array
    {
        $normalized = [];
        foreach ($periods as $period) {
            $start = new DateTimeImmutable($period['start_date']);
            $endRaw = $period['end_date'] ?? null;
            $end = $endRaw ? new DateTimeImmutable($endRaw) : $referenceDate;

            if ($end < $start) {
                continue;
            }

            $normalized[] = ['start' => $start, 'end' => $end];
        }

        if ($normalized === []) {
            return [
                'formatted' => '—',
                'days' => 0,
                'reference_date' => $referenceDate->format('Y-m-d'),
            ];
        }

        usort(
            $normalized,
            static fn (array $a, array $b): int => $a['start'] <=> $b['start']
        );

        $merged = [];
        foreach ($normalized as $range) {
            $lastIndex = count($merged) - 1;
            if ($lastIndex < 0) {
                $merged[] = $range;
                continue;
            }

            $last = $merged[$lastIndex];
            if ($range['start'] <= $last['end']) {
                if ($range['end'] > $last['end']) {
                    $merged[$lastIndex]['end'] = $range['end'];
                }
                continue;
            }

            $merged[] = $range;
        }

        $days = 0;
        foreach ($merged as $range) {
            $days += (int) $range['start']->diff($range['end'])->days;
        }

        return [
            'formatted' => $this->formatDays($days),
            'days' => $days,
            'reference_date' => $referenceDate->format('Y-m-d'),
        ];
    }

    /**
     * @param list<array{start_date: string, end_date?: ?string, status?: ?string}> $periods
     *
     * @return array{formatted: string, days: int, reference_date: string}
     */
    private function computeActiveOnly(array $periods, DateTimeImmutable $referenceDate): array
    {
        $filtered = array_values(array_filter(
            $periods,
            static fn (array $period): bool => (($period['status'] ?? 'active') === 'active')
        ));

        return $this->computeSumPeriods($filtered, $referenceDate);
    }

    /**
     * @param list<array{start_date: string, end_date?: ?string, status?: ?string}> $periods
     *
     * @return array{formatted: string, days: int, reference_date: string}
     */
    private function computeCustomRule(array $periods, DateTimeImmutable $referenceDate): array
    {
        // Les règles avancées sont injectées via seniority_rules ; fallback temporaire.
        return $this->computeSumPeriods($periods, $referenceDate);
    }

    private function formatDays(int $days): string
    {
        if ($days < 1) {
            return '—';
        }
        $years = intdiv($days, 365);
        $remaining = $days % 365;
        $months = intdiv($remaining, 30);
        $finalDays = $remaining % 30;

        $parts = [];
        if ($years > 0) {
            $parts[] = $years === 1 ? '1 an' : $years . ' ans';
        }
        if ($months > 0) {
            $parts[] = $months . ' mois';
        }
        if ($finalDays > 0) {
            $parts[] = $finalDays === 1 ? '1 jour' : $finalDays . ' jours';
        }

        return $parts !== [] ? implode(', ', $parts) : '—';
    }
}
