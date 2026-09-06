<?php

declare(strict_types=1);

namespace App\Services\Rbac;

/**
 * Administration du site : un bit sur le compte, pas un rôle.
 *
 * Les habilitations communauté restent le RBAC (trois profils).
 * L’accès plateforme ne se choisit plus dans une table de rôles.
 */
final class PlatformAdminFlag
{
    public const COLUMN = 'is_platform_admin';

    public const LEGACY_ROLE_SLUG = 'site_super_admin';

    public const CONFIRM_GRANT = 'OUVRIR LE SITE';

    public const CONFIRM_REVOKE = 'RETIRER L ACCES';

    public static function confirmMatches(string $typed, string $expected): bool
    {
        $normalize = static function (string $value): string {
            $value = trim($value);
            $value = str_replace(['’', '`', '´'], "'", $value);
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

            return mb_strtoupper($value);
        };

        return $normalize($typed) === $normalize($expected);
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function isEnabled(array $user): bool
    {
        return !empty($user[self::COLUMN]) || !empty($user['is_super_admin']);
    }

    /**
     * Un rôle site ne peut plus accorder l’administration du site.
     *
     * @param list<string> $slugs
     * @return list<string>
     */
    public static function stripRoleGrants(array $slugs): array
    {
        $kept = [];
        foreach ($slugs as $slug) {
            $slug = strtolower(trim((string) $slug));
            if ($slug === '' || $slug === 'admin.system' || $slug === '*') {
                continue;
            }
            $kept[] = $slug;
        }

        return array_values(array_unique($kept));
    }

    /**
     * @param list<string> $slugs
     * @return list<string>
     */
    public static function mergeIntoPermissions(array $slugs, bool $isPlatformAdmin): array
    {
        $slugs = self::stripRoleGrants($slugs);
        if ($isPlatformAdmin) {
            $slugs[] = 'admin.system';
        }

        return array_values(array_unique($slugs));
    }

    public static function isLegacyRoleSlug(string $slug): bool
    {
        return strtolower(trim($slug)) === self::LEGACY_ROLE_SLUG;
    }
}
