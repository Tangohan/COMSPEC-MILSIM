<?php

declare(strict_types=1);

namespace App\Services\Deployment;

/**
 * Comparaison semver et contrôles de compatibilité PHP.
 */
final class VersionCompatibility
{
    public static function compare(string $a, string $b): int
    {
        return version_compare(self::normalize($a), self::normalize($b));
    }

    public static function satisfiesMinimum(string $current, string $minimum): bool
    {
        if (trim($minimum) === '') {
            return true;
        }

        return self::compare($current, $minimum) >= 0;
    }

    public static function isNewerThan(string $candidate, string $current): bool
    {
        return self::compare($candidate, $current) > 0;
    }

    public static function phpCompatible(?string $phpMin, ?string $phpMax = null): bool
    {
        $current = PHP_VERSION;
        if ($phpMin !== null && trim($phpMin) !== '' && version_compare($current, trim($phpMin), '<')) {
            return false;
        }
        if ($phpMax !== null && trim($phpMax) !== '' && version_compare($current, trim($phpMax), '>')) {
            return false;
        }

        return true;
    }

    private static function normalize(string $version): string
    {
        $v = trim($version);
        if (preg_match('/^(\d+\.\d+\.\d+)/', $v, $m)) {
            return $m[1];
        }

        return $v;
    }
}
