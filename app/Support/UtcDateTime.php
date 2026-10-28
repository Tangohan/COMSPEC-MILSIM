<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Dates écrites en UTC (gmdate, UTC_TIMESTAMP()) et relues plus tard.
 *
 * strtotime() lit une date sans fuseau à l'heure du serveur PHP. Avec date.timezone = Europe/Paris,
 * un jeton jeu valable 2 h (gmdate(time() + 7200)) paraissait expiré dès sa création en été
 * (UTC+2), et un code OTP de 10 min expiré depuis 1 h 50.
 */
final class UtcDateTime
{
    /** Horodatage Unix d'une date UTC « Y-m-d H:i:s » (null si vide ou illisible). */
    public static function timestamp(mixed $raw): ?int
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return null;
        }
        $ts = strtotime($s . ' UTC');

        return $ts === false ? null : $ts;
    }

    /** Vrai si la date UTC est passée. Une date vide ou illisible compte comme passée. */
    public static function isPast(mixed $raw, ?int $now = null): bool
    {
        $ts = self::timestamp($raw);

        return $ts === null || $ts < ($now ?? time());
    }
}
