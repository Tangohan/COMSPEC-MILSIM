<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Horodatage des photos terrain : le jeu envoyait le temps de mission
 * (quelques secondes depuis le briefing), interprété comme une date Unix → 1970.
 */
final class ReconCapturedAt
{
    /** Avant sept. 2001 : trop tôt pour une photo COMSPEC réelle. */
    public const MIN_UNIX = 1_000_000_000;

    public static function unixFromPosted(mixed $value, ?int $now = null): int
    {
        $now ??= time();
        if ($value === null || $value === false || $value === '') {
            return $now;
        }
        if (is_numeric($value)) {
            $n = (int) $value;
            if ($n > 1_000_000_000_000) {
                $n = (int) floor($n / 1000);
            }
            if ($n < self::MIN_UNIX || $n > ($now + 86400 * 2)) {
                return $now;
            }

            return $n;
        }
        if (is_string($value)) {
            $parsed = strtotime($value);
            if ($parsed !== false && $parsed >= self::MIN_UNIX && $parsed <= ($now + 86400 * 2)) {
                return $parsed;
            }
        }

        return $now;
    }

    public static function sqlDateTime(mixed $value, ?int $now = null): string
    {
        return date('Y-m-d H:i:s', self::unixFromPosted($value, $now));
    }

    public static function isEpochEra(?string $sql): bool
    {
        $raw = trim((string) $sql);
        if ($raw === '' || str_starts_with($raw, '0000-') || str_starts_with($raw, '1970-')) {
            return true;
        }
        $parsed = strtotime($raw);

        return $parsed === false || $parsed < self::MIN_UNIX;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function displayFromRow(array $row): string
    {
        $captured = trim((string) ($row['captured_at'] ?? ''));
        $created = trim((string) ($row['created_at'] ?? ''));
        if (self::isEpochEra($captured)) {
            return $created;
        }

        return $captured !== '' ? $captured : $created;
    }
}
