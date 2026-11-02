<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tableau des sessions Arma (back-office/roleplay/sessions) : range chaque session en « à venir », « en cours » ou
 * « passée » et met en forme les dates dans la langue du lecteur.
 *
 * Règle : annulée ou terminée = passée ; démarrée (heure de début réelle) = en cours ; sinon = à venir.
 */
final class RoleplaySessionBoard
{
    public const UPCOMING = 'upcoming';
    public const LIVE = 'live';
    public const PAST = 'past';

    /** Une session ouverte depuis plus longtemps est signalée « à clôturer ». */
    public const STALE_HOURS = 12;

    /** @param array<string, mixed> $s */
    public static function phase(array $s): string
    {
        $status = (string) ($s['status'] ?? '');
        if ($status === 'closed' || $status === 'cancelled') {
            return self::PAST;
        }
        if (trim((string) ($s['started_at'] ?? '')) !== '') {
            return self::LIVE;
        }

        return self::UPCOMING;
    }

    /**
     * @param list<array<string, mixed>> $sessions
     * @return array{upcoming: list<array<string, mixed>>, live: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    public static function group(array $sessions): array
    {
        $out = [self::UPCOMING => [], self::LIVE => [], self::PAST => []];
        foreach ($sessions as $s) {
            $out[self::phase($s)][] = $s;
        }
        $ts = static fn (array $s, string ...$keys): int => self::timestamp($s, ...$keys) ?? PHP_INT_MAX;
        usort($out[self::UPCOMING], static fn (array $a, array $b): int => $ts($a, 'planned_starts_at', 'created_at') <=> $ts($b, 'planned_starts_at', 'created_at'));
        usort($out[self::LIVE], static fn (array $a, array $b): int => $ts($b, 'started_at') <=> $ts($a, 'started_at'));
        usort($out[self::PAST], static fn (array $a, array $b): int => $ts($b, 'ended_at', 'started_at', 'planned_starts_at', 'created_at') <=> $ts($a, 'ended_at', 'started_at', 'planned_starts_at', 'created_at'));

        return $out;
    }

    /** Date de référence affichée : début réel, sinon début prévu, sinon création. @param array<string, mixed> $s */
    public static function referenceDate(array $s): ?string
    {
        foreach (['started_at', 'planned_starts_at', 'created_at'] as $k) {
            $v = trim((string) ($s[$k] ?? ''));
            if ($v !== '' && strtotime($v) !== false) {
                return $v;
            }
        }

        return null;
    }

    /** Session démarrée depuis longtemps et jamais clôturée. @param array<string, mixed> $s */
    public static function isStale(array $s, ?int $now = null): bool
    {
        if (self::phase($s) !== self::LIVE) {
            return false;
        }
        $start = self::timestamp($s, 'started_at');

        return $start !== null && ($now ?? time()) - $start > self::STALE_HOURS * 3600;
    }

    /** Début prévu dépassé sans démarrage. @param array<string, mixed> $s */
    public static function isOverdue(array $s, ?int $now = null): bool
    {
        if (self::phase($s) !== self::UPCOMING) {
            return false;
        }
        $planned = self::timestamp($s, 'planned_starts_at');

        return $planned !== null && $planned < ($now ?? time());
    }

    /** « lun. 12 mars 2026 à 21:00 » / « Mon 12 March 2026 at 21:00 ». */
    public static function when(?string $dt): string
    {
        $t = $dt !== null && trim($dt) !== '' ? strtotime($dt) : false;
        if ($t === false) {
            return '';
        }

        return LocalizedCalendar::weekday((int) date('w', $t), true) . ' '
            . LocalizedCalendar::dateTime((int) date('j', $t), (int) date('n', $t), date('Y', $t), date('H:i', $t));
    }

    /** Jour et mois courts pour la pastille de date : ['12', 'mars']. @return array{0: string, 1: string} */
    public static function dayBadge(?string $dt): array
    {
        $t = $dt !== null && trim($dt) !== '' ? strtotime($dt) : false;
        if ($t === false) {
            return ['—', ''];
        }

        return [date('j', $t), LocalizedCalendar::month((int) date('n', $t), true)];
    }

    /** Heure seule « 21:00 ». */
    public static function time(?string $dt): string
    {
        $t = $dt !== null && trim($dt) !== '' ? strtotime($dt) : false;

        return $t === false ? '' : date('H:i', $t);
    }

    /** Durée lisible « 2 h 05 », « 45 min ». */
    public static function duration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds < 60) {
            return $seconds > 0 ? '< 1 min' : '0 min';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $h > 0 ? $h . ' h ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : $m . ' min';
    }

    /** Valeur pour un champ datetime-local. */
    public static function inputValue(?string $dt): string
    {
        $t = $dt !== null && trim($dt) !== '' ? strtotime($dt) : false;

        return $t === false ? '' : date('Y-m-d\TH:i', $t);
    }

    /** Champ datetime-local (ou texte saisi) vers DATETIME SQL ; null si vide ou invalide. */
    public static function parseInput(mixed $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        $t = strtotime(str_replace('T', ' ', $v));

        return $t === false ? null : date('Y-m-d H:i:s', $t);
    }

    /** @param array<string, mixed> $s */
    private static function timestamp(array $s, string ...$keys): ?int
    {
        foreach ($keys as $k) {
            $v = trim((string) ($s[$k] ?? ''));
            if ($v !== '') {
                $t = strtotime($v);
                if ($t !== false) {
                    return $t;
                }
            }
        }

        return null;
    }
}
