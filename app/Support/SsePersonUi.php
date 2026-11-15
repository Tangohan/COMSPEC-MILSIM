<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Petits formats d'affichage de la page Personnes (dates courtes, menace d'un avis), sans base ni session : testés seuls.
 */
final class SsePersonUi
{
    private const MONTHS = [
        'fr' => ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
        'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    ];

    /** Langue d'affichage : les dates sont écrites ici (le traducteur de pages ne les connaît pas). */
    private static function lang(): string
    {
        return function_exists('site_lang') && site_lang() !== 'fr' ? 'en' : 'fr';
    }

    private static function dayLabel(int $ts, int $now, bool $withYear): string
    {
        $en = self::lang() === 'en';
        $month = self::MONTHS[$en ? 'en' : 'fr'][(int) date('n', $ts) - 1];
        $label = $en ? $month . ' ' . (int) date('j', $ts) : (int) date('j', $ts) . ' ' . $month;

        return $withYear && date('Y', $ts) !== date('Y', $now) ? $label . ' ' . date('Y', $ts) : $label;
    }

    /** Dernier jour affiché en relatif (« Auj. », « Hier »). */
    public static function when(?string $utc, int $now): string
    {
        $ts = self::ts($utc);
        if ($ts === null) {
            return '—';
        }
        $day = static fn (int $t): string => date('Y-m-d', $t);
        $hm = date('H:i', $ts);
        $en = self::lang() === 'en';
        if ($day($ts) === $day($now)) {
            return ($en ? 'Today ' : 'Auj. ') . $hm;
        }
        if ($day($ts) === $day($now - 86400)) {
            return ($en ? 'Yesterday ' : 'Hier ') . $hm;
        }
        $label = self::dayLabel($ts, $now, true);

        return date('Y', $ts) === date('Y', $now) ? $label . ' ' . $hm : $label;
    }

    /** Jour seul (« Auj. », « Hier », « 11 nov. »). */
    public static function day(?string $utc, int $now): string
    {
        $ts = self::ts($utc);
        if ($ts === null) {
            return '—';
        }
        $day = static fn (int $t): string => date('Y-m-d', $t);
        $en = self::lang() === 'en';
        if ($day($ts) === $day($now)) {
            return $en ? 'Today' : 'Auj.';
        }
        if ($day($ts) === $day($now - 86400)) {
            return $en ? 'Yesterday' : 'Hier';
        }

        return self::dayLabel($ts, $now, true);
    }

    /** Date complète pour l'infobulle. */
    public static function full(?string $utc): string
    {
        $ts = self::ts($utc);

        return $ts === null ? '' : date('d/m/Y H:i', $ts);
    }

    private static function ts(?string $utc): ?int
    {
        $utc = trim((string) $utc);
        if ($utc === '' || str_starts_with($utc, '0000')) {
            return null;
        }
        $ts = strtotime($utc . ' UTC');

        return $ts === false ? null : $ts;
    }

    /** Libellé français d'un niveau de menace (clé de SseWantedCatalog::THREATS). */
    public static function threat(string $code): string
    {
        return SseWantedCatalog::THREATS[$code][0] ?? $code;
    }

    /** Ton visuel d'un niveau de menace. */
    public static function threatTone(string $code): string
    {
        return match ($code) {
            'critical', 'high' => 'bad',
            'medium' => 'warn',
            default => 'info',
        };
    }

    /** Ton d'une classification (CUI neutre, puis du plus doux au plus fort). */
    public static function classTone(?string $code): string
    {
        return match (SsePersonWork::rank($code)) {
            3 => 'bad',
            2 => 'warn',
            1 => 'info',
            0 => 'muted',
            default => 'none',
        };
    }
}
