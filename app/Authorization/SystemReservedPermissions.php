<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * Habilitations réservées à l’administration de la plateforme.
 *
 * Invariant du produit : **aucun rôle de communauté (tenant) ne porte l’un de ces slugs**.
 * Ils ne peuvent être obtenus que par une attribution de rôle site (`site_role_assignments`
 * vers un rôle `roles.tenant_id IS NULL AND role_layer = 'site'`).
 *
 * Pourquoi une liste centrale : {@see PermissionImplication::isGranted()} traite `admin.system`
 * et `*` comme des laissez-passer universels, et `site.support` comme un agrégat transverse.
 * Un seul de ces slugs attaché — même par erreur de données ou de seed — à un rôle communauté
 * transformerait un administrateur de communauté en super-administrateur du site.
 *
 * La défense est appliquée à trois niveaux :
 *  1. **Runtime** — {@see \App\Services\Rbac\RbacService} filtre les slugs issus des rôles tenant,
 *     des surcharges utilisateur et des périmètres d’unité. Toute donnée héritée devient inerte.
 *  2. **Écriture** — {@see \App\Services\Admin\RolePermissionService::setPermissionsForOrganizationTenantRole()}
 *     refuse les identifiants de permission réservés, quel que soit l’appelant.
 *  3. **Régression** — `tests/Unit/SystemReservedPermissionsTest.php` verrouille l’invariant
 *     (catalogue tenant, profils automatiques, matrice des rôles).
 */
final class SystemReservedPermissions
{
    /**
     * Slugs réservés, en correspondance exacte.
     *
     * - `admin.system` : laissez-passer universel côté {@see PermissionImplication}.
     * - `*` : joker historique, également universel.
     * - `site.support` : agrégat de support transverse à toutes les communautés.
     *
     * @var list<string>
     */
    public const EXACT = [
        'admin.system',
        '*',
        'site.support',
    ];

    /**
     * Préfixes réservés : tout slug qui commence par l’un d’eux est réservé.
     *
     * @var list<string>
     */
    public const PREFIXES = [
        'site.',
        'platform.',
        'system.',
        'admin.system.',
    ];

    public static function isReserved(string $slug): bool
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return false;
        }
        if (in_array($slug, self::EXACT, true)) {
            return true;
        }
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($slug, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retire les slugs réservés d’une liste issue d’un périmètre communauté.
     *
     * @param list<string> $slugs
     * @return list<string>
     */
    public static function filter(array $slugs): array
    {
        $kept = [];
        foreach ($slugs as $slug) {
            $slug = (string) $slug;
            if ($slug !== '' && !self::isReserved($slug)) {
                $kept[] = $slug;
            }
        }

        return array_values($kept);
    }

    /**
     * Sous-ensemble réservé d’une liste — pour journaliser ou expliquer un refus.
     *
     * @param list<string> $slugs
     * @return list<string>
     */
    public static function reservedFrom(array $slugs): array
    {
        $found = [];
        foreach ($slugs as $slug) {
            $slug = (string) $slug;
            if ($slug !== '' && self::isReserved($slug)) {
                $found[] = $slug;
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Retire les slugs réservés des clés d’une carte `slug => valeur`
     * (utilisé pour les permissions à périmètre d’unité).
     *
     * @param array<string, mixed> $map
     * @return array<string, mixed>
     */
    public static function filterMapKeys(array $map): array
    {
        foreach (array_keys($map) as $slug) {
            if (self::isReserved((string) $slug)) {
                unset($map[$slug]);
            }
        }

        return $map;
    }

    /**
     * Fragment SQL `(...)` vrai lorsque la colonne de slug désignée est réservée.
     * Sert aux scripts de purge ; les motifs sont des littéraux constants, sans entrée utilisateur.
     */
    public static function sqlCondition(string $slugColumn): string
    {
        $parts = [];
        $quoted = array_map(static fn (string $s): string => "'" . $s . "'", self::EXACT);
        $parts[] = $slugColumn . ' IN (' . implode(', ', $quoted) . ')';
        foreach (self::PREFIXES as $prefix) {
            $parts[] = $slugColumn . " LIKE '" . $prefix . "%'";
        }

        return '(' . implode(' OR ', $parts) . ')';
    }
}
