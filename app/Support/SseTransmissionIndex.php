<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * Journal des transmissions terrain : tri, compteurs cliquables et périodes rapides de la page d'index.
 * Travaille sur la liste déjà filtrée et autorisée par le contrôleur ; ne lit ni n'écrit rien.
 */
final class SseTransmissionIndex
{
    public const SORT_RECENT = 'recent';
    public const SORT_OLDEST = 'ancien';
    public const SORT_NATURE = 'nature';
    public const SORT_OPERATOR = 'operateur';

    /** @return list<string> */
    public static function sorts(): array
    {
        return [self::SORT_RECENT, self::SORT_OLDEST, self::SORT_NATURE, self::SORT_OPERATOR];
    }

    public static function normalizeSort(string $sort): string
    {
        $sort = strtolower(trim($sort));

        return in_array($sort, self::sorts(), true) ? $sort : self::SORT_RECENT;
    }

    /**
     * @param list<array<string,mixed>> $events
     * @return list<array<string,mixed>>
     */
    public static function sort(array $events, string $sort): array
    {
        $sort = self::normalizeSort($sort);
        $time = static fn (array $e): string => (string) ($e['event_time'] ?? '');
        $text = static fn (array $e, string $k): string => mb_strtolower(trim((string) ($e[$k] ?? '')));
        $events = array_values($events);
        usort($events, static function (array $a, array $b) use ($sort, $time, $text): int {
            $byTime = $time($b) <=> $time($a);
            $byId = (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
            $recent = $byTime !== 0 ? $byTime : $byId;

            return match ($sort) {
                self::SORT_OLDEST => -$recent,
                self::SORT_NATURE => ($text($a, 'event_type_label') <=> $text($b, 'event_type_label')) ?: $recent,
                self::SORT_OPERATOR => self::operatorRank($a, $b, $text) ?: $recent,
                default => $recent,
            };
        });

        return $events;
    }

    /** Opérateurs renseignés d'abord, par ordre alphabétique ; les entrées sans opérateur ferment la liste. */
    private static function operatorRank(array $a, array $b, callable $text): int
    {
        $na = $text($a, 'author_label');
        $nb = $text($b, 'author_label');
        if ($na === '' || $nb === '') {
            return ($na === '' ? 1 : 0) <=> ($nb === '' ? 1 : 0);
        }

        return $na <=> $nb;
    }

    /**
     * Date « depuis le » des périodes rapides.
     *
     * @return array{today:string,week:string,month:string}
     */
    public static function periods(DateTimeImmutable $now): array
    {
        return [
            'today' => $now->format('Y-m-d'),
            'week' => $now->modify('-6 days')->format('Y-m-d'),
            'month' => $now->modify('-29 days')->format('Y-m-d'),
        ];
    }

    /**
     * Compteurs affichés en tête de page (sur la liste chargée) et nombre par nature, du plus fréquent au plus rare.
     *
     * @param list<array<string,mixed>> $events
     * @return array{total:int,today:int,week:int,natures:array<string,int>}
     */
    public static function counters(array $events, DateTimeImmutable $now): array
    {
        $p = self::periods($now);
        $today = 0;
        $week = 0;
        $natures = [];
        foreach ($events as $event) {
            $day = substr((string) ($event['event_time'] ?? ''), 0, 10);
            if ($day >= $p['today']) {
                ++$today;
            }
            if ($day >= $p['week']) {
                ++$week;
            }
            $label = trim((string) ($event['event_type_label'] ?? ''));
            if ($label !== '') {
                $natures[$label] = ($natures[$label] ?? 0) + 1;
            }
        }
        arsort($natures);

        return ['total' => count($events), 'today' => $today, 'week' => $week, 'natures' => $natures];
    }

    /** « 15/11 14:32 » pour la liste, avec l'année quand elle n'est pas l'année courante. */
    public static function shortWhen(string $eventTime, DateTimeImmutable $now): string
    {
        $eventTime = trim($eventTime);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $eventTime, $m) !== 1) {
            return $eventTime;
        }
        $date = $m[3] . '/' . $m[2] . ($m[1] !== $now->format('Y') ? '/' . $m[1] : '');

        return $date . ' ' . $m[4] . ':' . $m[5];
    }

    /** Une recherche, une nature, une source autre que le terrain ou une date sont-elles posées ? */
    public static function hasActiveFilter(array $filters): bool
    {
        foreach (['q', 'event_type', 'since'] as $k) {
            if (trim((string) ($filters[$k] ?? '')) !== '') {
                return true;
            }
        }
        $source = strtoupper(trim((string) ($filters['source'] ?? 'TERRAIN')));

        return $source !== '' && $source !== 'TERRAIN';
    }
}
