<?php

declare(strict_types=1);

namespace App\Services\Deployment;

/**
 * Chemins jamais écrasés par un package de mise à jour.
 */
final class ProtectedPathPolicy
{
    /** @var list<string> */
    private const PREFIXES = [
        '.env',
        'storage/',
        'uploads/',
        'logs/',
        'backups/',
        'public/uploads/',
        'app/Config/database.local.php',
        'node_modules/',
        '.git/',
    ];

    public static function isProtected(string $relativePath): bool
    {
        $norm = self::normalize($relativePath);
        if ($norm === '' || str_contains($norm, '..')) {
            return true;
        }

        foreach (self::PREFIXES as $prefix) {
            if ($prefix === '.env') {
                if ($norm === '.env' || str_starts_with($norm, '.env.')) {
                    return true;
                }
                continue;
            }
            if ($norm === rtrim($prefix, '/') || str_starts_with($norm, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function prefixes(): array
    {
        return self::PREFIXES;
    }

    public static function normalize(string $relativePath): string
    {
        $p = str_replace('\\', '/', trim($relativePath));
        $p = ltrim($p, '/');
        while (str_starts_with($p, './')) {
            $p = substr($p, 2);
        }

        return $p;
    }
}
