<?php

declare(strict_types=1);

namespace App\Support;

/**
 * La connexion MySQL tourne en UTC (time_zone '+00:00', voir App\Core\Database) alors que PHP est réglé
 * sur APP_TIMEZONE (Europe/Paris). Une date écrite avec format() côté PHP puis comparée à NOW() côté SQL
 * était donc décalée d'une à deux heures (jetons e-mail valables 2 h de trop).
 */
final class DbTime
{
    /** Date PHP → « Y-m-d H:i:s » en UTC, comparable à NOW() / UTC_TIMESTAMP(). */
    public static function utc(\DateTimeInterface $at): string
    {
        return \DateTimeImmutable::createFromInterface($at)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    /** Colonne DATETIME écrite en UTC → horodatage Unix. */
    public static function utcToTimestamp(string $value): int
    {
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->getTimestamp();
        } catch (\Exception) {
            return 0;
        }
    }

    /** Colonne DATETIME écrite en UTC → date dans le fuseau du site, pour l'affichage. */
    public static function utcToLocal(string $value): ?\DateTimeImmutable
    {
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        } catch (\Exception) {
            return null;
        }
    }
}
