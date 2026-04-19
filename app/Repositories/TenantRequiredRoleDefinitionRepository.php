<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Fonctions doctrinales (role_definitions) marquées obligatoires pour un tenant.
 */
final class TenantRequiredRoleDefinitionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_required_role_definitions' LIMIT 1");

            return (bool) $st && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<int> */
    public function listDefinitionIdsForTenant(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tableExists()) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT role_definition_id FROM tenant_required_role_definitions WHERE tenant_id = ? ORDER BY role_definition_id ASC');
        $st->execute([$tenantId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @param list<int> $definitionIds
     */
    public function replaceForTenant(int $tenantId, array $definitionIds): void
    {
        if ($tenantId < 1 || !$this->tableExists()) {
            return;
        }
        $definitionIds = array_values(array_unique(array_filter(array_map('intval', $definitionIds), static fn (int $id): bool => $id > 0)));
        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM tenant_required_role_definitions WHERE tenant_id = ?');
            $del->execute([$tenantId]);
            if ($definitionIds !== []) {
                $chk = $this->pdo->prepare('SELECT id FROM role_definitions WHERE id = ? LIMIT 1');
                $ins = $this->pdo->prepare('INSERT INTO tenant_required_role_definitions (tenant_id, role_definition_id, created_at) VALUES (?, ?, NOW())');
                foreach ($definitionIds as $defId) {
                    $chk->execute([$defId]);
                    if ($chk->fetchColumn()) {
                        $ins->execute([$tenantId, $defId]);
                    }
                }
            }
            $this->pdo->commit();
        } catch (\Throwable) {
            $this->pdo->rollBack();
            throw;
        }
    }

    /**
     * Détail couverture pour les définitions obligatoires du tenant.
     *
     * @param list<int> $requiredDefinitionIds
     *
     * @return list<array{
     *   definition_id: int,
     *   slug: string,
     *   name_fr: string,
     *   filled: bool,
     *   holder_count: int,
     *   holders: list<array{user_id: int, display_name: string, email: string}>,
     *   roles_for_definition: list<array{id: int, name: string, slug: string}>
     * }>
     */
    public function coverageForRequiredDefinitions(int $tenantId, array $requiredDefinitionIds): array
    {
        if ($tenantId < 1 || $requiredDefinitionIds === []) {
            return [];
        }
        $requiredDefinitionIds = array_values(array_unique(array_filter(array_map('intval', $requiredDefinitionIds), static fn (int $id): bool => $id > 0)));
        if ($requiredDefinitionIds === []) {
            return [];
        }

        $phDef = implode(',', array_fill(0, count($requiredDefinitionIds), '?'));
        $params = array_merge([$tenantId], $requiredDefinitionIds);

        $stRoles = $this->pdo->prepare(
            "SELECT id, name, slug, definition_id FROM roles
             WHERE tenant_id = ? AND role_layer IN ('community','intra') AND definition_id IN ({$phDef})
             ORDER BY name ASC"
        );
        $stRoles->execute($params);
        $rolesByDef = [];
        while ($row = $stRoles->fetch(PDO::FETCH_ASSOC)) {
            $did = (int) ($row['definition_id'] ?? 0);
            if ($did < 1) {
                continue;
            }
            $rolesByDef[$did][] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
            ];
        }

        $holdersByDef = [];
        if ($this->hasTenantUserRoles()) {
            $stH = $this->pdo->prepare(
                "SELECT DISTINCT r.definition_id AS definition_id, u.id AS user_id,
                        COALESCE(NULLIF(TRIM(u.display_name), ''), u.email) AS label, u.email AS email
                 FROM tenant_user_roles tur
                 INNER JOIN users u ON u.id = tur.user_id AND u.tenant_id = tur.tenant_id
                 INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = tur.tenant_id
                 WHERE tur.tenant_id = ? AND tur.org_unit_id IS NULL
                   AND r.definition_id IN ({$phDef})
                   AND u.status = 'active'"
            );
            $stH->execute($params);
            while ($row = $stH->fetch(PDO::FETCH_ASSOC)) {
                $did = (int) ($row['definition_id'] ?? 0);
                if ($did < 1) {
                    continue;
                }
                $uid = (int) ($row['user_id'] ?? 0);
                if ($uid < 1) {
                    continue;
                }
                $holdersByDef[$did][$uid] = [
                    'user_id' => $uid,
                    'display_name' => (string) ($row['label'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                ];
            }
        }

        $stMeta = $this->pdo->prepare("SELECT id, slug, name_fr FROM role_definitions WHERE id IN ({$phDef}) ORDER BY sort_order ASC, name_fr ASC");
        $stMeta->execute($requiredDefinitionIds);
        $metaById = [];
        while ($row = $stMeta->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $metaById[$id] = [
                    'slug' => (string) ($row['slug'] ?? ''),
                    'name_fr' => (string) ($row['name_fr'] ?? ''),
                ];
            }
        }

        $out = [];
        foreach ($requiredDefinitionIds as $defId) {
            $holders = array_values($holdersByDef[$defId] ?? []);
            $out[] = [
                'definition_id' => $defId,
                'slug' => (string) ($metaById[$defId]['slug'] ?? ''),
                'name_fr' => (string) ($metaById[$defId]['name_fr'] ?? ''),
                'filled' => $holders !== [],
                'holder_count' => count($holders),
                'holders' => $holders,
                'roles_for_definition' => $rolesByDef[$defId] ?? [],
            ];
        }

        return $out;
    }

    private function hasTenantUserRoles(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_user_roles' LIMIT 1");

            return (bool) $st && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
