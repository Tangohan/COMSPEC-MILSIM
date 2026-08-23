<?php

declare(strict_types=1);

namespace App\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Découpe les photos ATAK en « soirées » de 24 h, coupées à 10 h (Europe/Paris).
 *
 * Vendredi 21 h → samedi 02 h = la même soirée.
 * Samedi 10 h 01 = soirée suivante (partie du samedi soir).
 */
final class AtakPlayNight
{
    public const TIMEZONE = 'Europe/Paris';
    public const CUTOFF_HOUR = 10;
    public const CONFIRM_CLEAR_MAP = 'VIDER LA CARTE';
    public const CONFIRM_DELETE_PHOTOS = 'SUPPRIMER LES PHOTOS';

    public static function timezone(): DateTimeZone
    {
        return new DateTimeZone(self::TIMEZONE);
    }

    public static function currentKey(?DateTimeInterface $now = null): string
    {
        return self::keyFromDateTime($now ?? new DateTimeImmutable('now', self::timezone()));
    }

    public static function keyFromSql(?string $sqlDateTime): string
    {
        $raw = trim((string) $sqlDateTime);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return self::currentKey();
        }

        try {
            $tz = self::timezone();
            if (preg_match('/[zZ]|[+-]\d{2}:?\d{2}$/', $raw) === 1) {
                $dt = new DateTimeImmutable($raw);
            } else {
                $dt = new DateTimeImmutable(str_replace('T', ' ', $raw), $tz);
            }

            return self::keyFromDateTime($dt);
        } catch (\Throwable) {
            return self::currentKey();
        }
    }

    public static function keyFromDateTime(DateTimeInterface $dt): string
    {
        $local = DateTimeImmutable::createFromInterface($dt)->setTimezone(self::timezone());
        if ((int) $local->format('G') < self::CUTOFF_HOUR) {
            $local = $local->sub(new DateInterval('P1D'));
        }

        return $local->format('Y-m-d');
    }

    /** @return array{0: string, 1: string} bornes SQL locales [from, to[ */
    public static function sqlRange(string $nightKey): array
    {
        $key = self::normalizeKey($nightKey) ?? self::currentKey();
        $from = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $key . ' ' . sprintf('%02d:00:00', self::CUTOFF_HOUR), self::timezone());
        if (!$from instanceof DateTimeImmutable) {
            $from = new DateTimeImmutable('today ' . self::CUTOFF_HOUR . ':00:00', self::timezone());
        }
        $to = $from->add(new DateInterval('P1D'));

        return [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];
    }

    public static function label(string $nightKey): string
    {
        $key = self::normalizeKey($nightKey);
        if ($key === null) {
            return 'Soirée en cours';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $key, self::timezone());
        if (!$dt instanceof DateTimeImmutable) {
            return 'Soirée';
        }
        $days = [
            'Monday' => 'Lundi',
            'Tuesday' => 'Mardi',
            'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi',
            'Friday' => 'Vendredi',
            'Saturday' => 'Samedi',
            'Sunday' => 'Dimanche',
        ];
        $months = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];
        $dow = $days[$dt->format('l')] ?? $dt->format('l');
        $month = $months[(int) $dt->format('n')] ?? $dt->format('F');

        return $dow . ' ' . (int) $dt->format('j') . ' ' . $month;
    }

    public static function normalizeKey(?string $nightKey): ?string
    {
        $raw = trim((string) $nightKey);
        if ($raw === '' || $raw === 'current' || $raw === 'all') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            return null;
        }

        return $raw;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $dateFields
     * @return array<string, mixed>
     */
    public static function decorateRow(array $row, array $dateFields = ['captured_at', 'created_at']): array
    {
        $sql = null;
        foreach ($dateFields as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                $sql = $value;
                break;
            }
        }
        $key = self::keyFromSql($sql);
        $row['play_night'] = $key;
        $row['play_night_label'] = self::label($key);

        return $row;
    }
}
