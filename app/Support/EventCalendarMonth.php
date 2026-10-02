<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Grille mensuelle (lundi → dimanche) pour les calendriers d’événements
 * (agenda du back-office et « Ma situation › Événements »).
 */
final class EventCalendarMonth
{
    /**
     * @param list<array<string, mixed>> $events
     * @return array{
     *   mois: string,
     *   label: string,
     *   prev: string,
     *   next: string,
     *   today: string,
     *   weeks: list<list<array{ymd: string, in_month: bool, is_today: bool, day: int, events: list<array<string, mixed>>}>>
     * }
     */
    public static function build(string $mois, array $events): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
            $mois = date('Y-m');
        }
        $firstTs = strtotime($mois . '-01 12:00:00');
        if ($firstTs === false) {
            $firstTs = strtotime(date('Y-m-01') . ' 12:00:00') ?: time();
            $mois = date('Y-m', $firstTs);
        }
        $monthsFr = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
            7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];
        $monthNum = (int) date('n', $firstTs);
        $label = ($monthsFr[$monthNum] ?? date('F', $firstTs)) . ' ' . date('Y', $firstTs);

        $prevTs = strtotime($mois . '-01 -1 month');
        $nextTs = strtotime($mois . '-01 +1 month');
        $prev = $prevTs !== false ? date('Y-m', $prevTs) : $mois;
        $next = $nextTs !== false ? date('Y-m', $nextTs) : $mois;
        $today = date('Y-m-d');

        $byDay = [];
        foreach ($events as $ev) {
            $startsRaw = isset($ev['starts_at']) ? (string) $ev['starts_at'] : '';
            $ts = $startsRaw !== '' ? strtotime($startsRaw) : false;
            if ($ts === false) {
                continue;
            }
            $ymd = date('Y-m-d', $ts);
            if (!isset($byDay[$ymd])) {
                $byDay[$ymd] = [];
            }
            $byDay[$ymd][] = $ev;
        }

        /* Lundi = début de grille (N = 1..7 lundi..dimanche en PHP avec format 'N'). */
        $startDow = (int) date('N', $firstTs);
        $gridStartTs = strtotime('-' . ($startDow - 1) . ' days', $firstTs);
        if ($gridStartTs === false) {
            $gridStartTs = $firstTs;
        }
        $daysInMonth = (int) date('t', $firstTs);
        $lastTs = strtotime($mois . '-' . str_pad((string) $daysInMonth, 2, '0', STR_PAD_LEFT) . ' 12:00:00');
        if ($lastTs === false) {
            $lastTs = $firstTs;
        }
        $endDow = (int) date('N', $lastTs);
        $gridEndTs = strtotime('+' . (7 - $endDow) . ' days', $lastTs);
        if ($gridEndTs === false) {
            $gridEndTs = $lastTs;
        }

        $weeks = [];
        $cursor = $gridStartTs;
        while ($cursor <= $gridEndTs) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $ymd = date('Y-m-d', $cursor);
                $inMonth = date('Y-m', $cursor) === $mois;
                $week[] = [
                    'ymd' => $ymd,
                    'in_month' => $inMonth,
                    'is_today' => $ymd === $today,
                    'day' => (int) date('j', $cursor),
                    'events' => $byDay[$ymd] ?? [],
                ];
                $nextDay = strtotime('+1 day', $cursor);
                $cursor = $nextDay !== false ? $nextDay : ($cursor + 86400);
            }
            $weeks[] = $week;
        }

        return [
            'mois' => $mois,
            'label' => $label,
            'prev' => $prev,
            'next' => $next,
            'today' => $today,
            'weeks' => $weeks,
        ];
    }

    /**
     * Bornes [début, fin[ du mois au format Y-m-d H:i:s (fin exclue).
     *
     * @return array{0: string, 1: string}
     */
    public static function range(string $mois): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
            $mois = date('Y-m');
        }
        $first = $mois . '-01 00:00:00';
        $nextTs = strtotime($mois . '-01 +1 month');

        return [$first, ($nextTs !== false ? date('Y-m-01', $nextTs) : $mois . '-28') . ' 00:00:00'];
    }
}
