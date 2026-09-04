<?php
declare(strict_types=1);
namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

/** User-facing date rendering. API/storage values remain UTC and unchanged. */
final class ParisDateTime
{
    public static function format(?string $value, string $format = 'd/m/Y H:i:s', string $fallback = '—'): string
    {
        $date = self::parse($value);
        return $date?->setTimezone(new DateTimeZone('Europe/Paris'))->format($format) ?? $fallback;
    }

    public static function parse(?string $value): ?DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        try {
            // SQL timestamps without an offset are UTC storage values.
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
