<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use PDO;

/**
 * Résolution des libellés / ordre d’affichage des rôles pour l’UI (forum, profils).
 */
final class UserDisplayRoleService
{
    /**
     * @param list<int> $assignedRoleIds
     * @return list<array{id:int,name:string,slug:string,semantic_tier:string,role_layer:string}>
     */
    public static function loadRolesForDisplay(PDO $pdo, int $tenantId, array $assignedRoleIds): array
    {
        $assignedRoleIds = array_values(array_unique(array_filter(array_map('intval', $assignedRoleIds), static fn (int $x): bool => $x > 0)));
        if ($assignedRoleIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($assignedRoleIds), '?'));
        $st = $pdo->prepare(
            "SELECT id, name, slug, COALESCE(semantic_tier, 'function') AS semantic_tier, role_layer,
                    COALESCE(display_group, 2) AS display_group,
                    COALESCE(display_priority, 0) AS display_priority,
                    COALESCE(display_weight, 0) AS display_weight
             FROM roles WHERE tenant_id = ? AND id IN ({$ph})"
        );
        $st->execute(array_merge([$tenantId], $assignedRoleIds));
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        usort($rows, static function (array $a, array $b): int {
            $la = (string) ($a['role_layer'] ?? '');
            $lb = (string) ($b['role_layer'] ?? '');
            $sa = $la === 'site' ? 0 : 1;
            $sb = $lb === 'site' ? 0 : 1;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            $ta = self::tierOrder((string) ($a['semantic_tier'] ?? 'function'));
            $tb = self::tierOrder((string) ($b['semantic_tier'] ?? 'function'));
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }
            $ga = (int) ($a['display_group'] ?? 2);
            $gb = (int) ($b['display_group'] ?? 2);
            if ($ga !== $gb) {
                return $ga <=> $gb;
            }
            $pa = (int) ($a['display_priority'] ?? 0);
            $pb = (int) ($b['display_priority'] ?? 0);
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            $wa = (int) ($a['display_weight'] ?? 0);
            $wb = (int) ($b['display_weight'] ?? 0);
            if ($wa !== $wb) {
                return $wa <=> $wb;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'name' => (string) ($r['name'] ?? ''),
                'slug' => (string) ($r['slug'] ?? ''),
                'semantic_tier' => (string) ($r['semantic_tier'] ?? 'function'),
                'role_layer' => (string) ($r['role_layer'] ?? 'intra'),
            ];
        }

        return $out;
    }

    private static function tierOrder(string $tier): int
    {
        return match ($tier) {
            'authority' => 1,
            'function' => 2,
            'specialty' => 3,
            'status' => 4,
            default => 2,
        };
    }

    /**
     * Libellé principal à afficher (badge unique).
     *
     * @param list<int> $assignedRoleIds
     */
    public static function primaryDisplayLabel(
        PDO $pdo,
        int $tenantId,
        array $assignedRoleIds,
        ?int $preferredRoleId,
        bool $respectFounderOverride = true
    ): string {
        $roles = self::loadRolesForDisplay($pdo, $tenantId, $assignedRoleIds);
        if ($roles === []) {
            return '';
        }
        if ($preferredRoleId !== null && $preferredRoleId > 0) {
            foreach ($roles as $r) {
                if ($r['id'] === $preferredRoleId && $r['name'] !== '') {
                    return $r['name'];
                }
            }
        }
        if ($respectFounderOverride) {
            foreach ($roles as $r) {
                if (($r['slug'] ?? '') === 'founder' && $r['name'] !== '') {
                    return $r['name'];
                }
            }
        }
        foreach ($roles as $r) {
            if ($r['name'] !== '') {
                return $r['name'];
            }
        }

        return '';
    }
}
