<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use PDO;

/**
 * Cohérence métier des ensembles de rôles organisation (hors résolution Gate).
 */
final class RoleCoherenceValidator
{
    /**
     * @param list<int> $roleIds IDs de rôles du tenant (déjà filtrés community/intra).
     */
    public static function validateOrgRoleSet(PDO $pdo, int $tenantId, array $roleIds): ?string
    {
        if ($roleIds === []) {
            return null;
        }
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $x): bool => $x > 0)));
        if ($roleIds === []) {
            return null;
        }
        $ph = implode(',', array_fill(0, count($roleIds), '?'));
        $st = $pdo->prepare("SELECT id, slug, semantic_tier FROM roles WHERE tenant_id = ? AND id IN ({$ph})");
        $st->execute(array_merge([$tenantId], $roleIds));
        $slugs = [];
        $tiers = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $slug = strtolower(trim((string) ($row['slug'] ?? '')));
            $slugs[$slug] = true;
            $tiers[$slug] = (string) ($row['semantic_tier'] ?? 'function');
        }

        if (isset($slugs['suspended_status'])) {
            foreach ($tiers as $s => $tier) {
                if ($s === 'suspended_status') {
                    continue;
                }
                if ($tier === 'authority') {
                    return 'Un compte marqué comme suspendu ne peut pas conserver un rôle d’autorité. Retirez le statut suspendu ou les rôles de gouvernance.';
                }
            }
        }

        if (isset($slugs['probation_member']) && isset($slugs['elite_member'])) {
            return 'Les statuts « en période probatoire » et « membre d’élite » ne peuvent pas être combinés.';
        }

        return null;
    }

    /**
     * Vérifie qu’un rôle d’autorité a au moins une permission (avertissement édition rôle).
     */
    public static function authorityRoleHasPermissions(PDO $pdo, int $roleId): bool
    {
        $st = $pdo->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? LIMIT 1');
        $st->execute([$roleId]);

        return (bool) $st->fetchColumn();
    }
}
