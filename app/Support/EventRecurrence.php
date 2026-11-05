<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Déplie une règle de répétition en une liste de créneaux (début, fin).
 *
 * Fréquences :
 *  - weekly   : chaque semaine, sur un ou plusieurs jours (1 = lundi … 7 = dimanche) ;
 *  - biweekly : une semaine sur deux, mêmes jours ;
 *  - monthly  : chaque mois, même quantième (un 31 saute les mois trop courts).
 *
 * La première occurrence est toujours le créneau saisi. La série s’arrête à la date
 * « jusqu’au » (incluse) ou après N occurrences, et jamais au-delà de MAX_OCCURRENCES.
 */
final class EventRecurrence
{
    public const MAX_OCCURRENCES = 52;

    public const FREQUENCIES = ['weekly', 'biweekly', 'monthly'];

    /** Vérifie la borne explicite d’une série avant de générer ses occurrences. */
    public static function validationError(string $starts, string $frequency, string $endMode, int $count, string $until): ?string
    {
        if ($frequency === '') {
            return null;
        }
        if (!in_array($frequency, self::FREQUENCIES, true)) {
            return 'La fréquence de répétition est invalide.';
        }
        if ($endMode === 'count') {
            return $count >= 2 && $count <= self::MAX_OCCURRENCES
                ? null
                : 'Choisissez entre 2 et ' . self::MAX_OCCURRENCES . ' occurrences.';
        }
        if ($endMode !== 'until') {
            return 'Choisissez une fin de série.';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $until);
        if ($date === false || $date->format('Y-m-d') !== $until) {
            return 'Choisissez une date de fin de série valide.';
        }
        if ($until < substr($starts, 0, 10)) {
            return 'La fin de série doit être à partir du premier créneau.';
        }

        return null;
    }

    /**
     * @param list<int> $weekdays Jours ISO (1–7) pour weekly/biweekly ; vide = jour du créneau saisi
     * @return list<array{starts: string, ends: ?string}>
     */
    public static function expand(
        string $starts,
        ?string $ends,
        string $frequency,
        ?string $untilDate,
        ?int $count,
        array $weekdays = []
    ): array {
        $startTs = strtotime($starts);
        if ($startTs === false) {
            return [];
        }
        $endTs = $ends !== null ? strtotime($ends) : false;
        $duration = $endTs !== false ? max(0, $endTs - $startTs) : null;
        $first = ['starts' => date('Y-m-d H:i:s', $startTs), 'ends' => $duration !== null ? date('Y-m-d H:i:s', $startTs + $duration) : null];

        if (!in_array($frequency, self::FREQUENCIES, true)) {
            return [$first];
        }

        $limit = $count !== null && $count > 0 ? min($count, self::MAX_OCCURRENCES) : self::MAX_OCCURRENCES;
        $untilTs = null;
        if ($untilDate !== null && trim($untilDate) !== '') {
            $u = strtotime(trim($untilDate) . ' 23:59:59');
            $untilTs = $u !== false ? $u : null;
        }
        if ($untilTs === null && ($count === null || $count <= 0)) {
            // Sans borne explicite : on s’arrête à une série raisonnable.
            $limit = min($limit, $frequency === 'monthly' ? 12 : 12);
        }

        $time = date('H:i:s', $startTs);
        $out = [$first];
        $make = static function (int $dayTs) use ($time, $duration): array {
            $ts = strtotime(date('Y-m-d', $dayTs) . ' ' . $time);
            $ts = $ts !== false ? $ts : $dayTs;

            return ['starts' => date('Y-m-d H:i:s', $ts), 'ends' => $duration !== null ? date('Y-m-d H:i:s', $ts + $duration) : null];
        };

        if ($frequency === 'monthly') {
            $day = (int) date('j', $startTs);
            $y = (int) date('Y', $startTs);
            $m = (int) date('n', $startTs);
            for ($guard = 0; $guard < 240 && count($out) < $limit; $guard++) {
                $m++;
                if ($m > 12) {
                    $m = 1;
                    $y++;
                }
                if (!checkdate($m, $day, $y)) {
                    continue;
                }
                $dayTs = mktime(12, 0, 0, $m, $day, $y);
                if ($untilTs !== null && $dayTs > $untilTs) {
                    break;
                }
                $out[] = $make($dayTs);
            }

            return $out;
        }

        $days = array_values(array_unique(array_filter(
            array_map('intval', $weekdays),
            static fn (int $d): bool => $d >= 1 && $d <= 7
        )));
        if ($days === []) {
            $days = [(int) date('N', $startTs)];
        }
        sort($days);
        $step = $frequency === 'biweekly' ? 2 : 1;
        // Lundi de la semaine du premier créneau, à midi (évite les pièges du changement d’heure).
        $weekStart = strtotime(date('Y-m-d', $startTs) . ' 12:00:00 -' . ((int) date('N', $startTs) - 1) . ' days');
        if ($weekStart === false) {
            return $out;
        }
        $firstDay = date('Y-m-d', $startTs);
        for ($w = 0; $w < 600 && count($out) < $limit; $w += $step) {
            foreach ($days as $d) {
                $dayTs = strtotime(date('Y-m-d', $weekStart) . ' 12:00:00 +' . ($w * 7 + $d - 1) . ' days');
                if ($dayTs === false || date('Y-m-d', $dayTs) <= $firstDay) {
                    continue;
                }
                if ($untilTs !== null && $dayTs > $untilTs) {
                    return $out;
                }
                $out[] = $make($dayTs);
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /**
     * Libellé court de la règle, pour les messages de confirmation.
     *
     * @param list<int> $weekdays
     */
    public static function describe(string $frequency, array $weekdays = []): string
    {
        $en = LocalizedCalendar::lang() === 'en';
        $list = [];
        foreach ($weekdays as $d) {
            if ((int) $d >= 1 && (int) $d <= 7) {
                $list[] = LocalizedCalendar::weekday((int) $d);
            }
        }
        $joined = $list === [] ? '' : ' (' . implode(', ', $list) . ')';

        return match ($frequency) {
            'weekly' => ($en ? 'every week' : 'chaque semaine') . $joined,
            'biweekly' => ($en ? 'every other week' : 'une semaine sur deux') . $joined,
            'monthly' => $en ? 'every month' : 'chaque mois',
            default => $en ? 'once' : 'une seule fois',
        };
    }
}
