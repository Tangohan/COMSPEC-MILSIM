<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOException;

/**
 * Accès aux données du cadre compétences (tables modules / tenant_modules / user_progress, etc.).
 * Distinct du dépôt LMS {@see TrainingProgressRepository} (training_progress par leçon).
 */
class CompetencyUserProgressRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function competencySchemaAvailable(): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'user_progress'");

            return (bool) $stmt->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Modules activés pour le tenant avec progression utilisateur éventuelle.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchTenantModuleRows(int $tenantId, int $userId): array
    {
        $sql = 'SELECT
                    m.id AS module_id,
                    m.code AS module_code,
                    m.name AS module_name,
                    m.module_type,
                    m.delivery_mode,
                    m.description AS module_description,
                    tm.is_mandatory,
                    tm.custom_order,
                    tm.recurrence_override_type,
                    tm.recurrence_override_days,
                    up.id AS progress_id,
                    up.status AS progress_status,
                    up.score,
                    up.expires_at,
                    up.validated_at,
                    up.last_activity_at,
                    up.started_at
                FROM tenant_modules tm
                INNER JOIN modules m ON m.id = tm.module_id AND m.tenant_id = tm.tenant_id
                LEFT JOIN user_progress up ON up.module_id = m.id AND up.user_id = ? AND up.tenant_id = tm.tenant_id
                WHERE tm.tenant_id = ? AND tm.is_active = 1 AND m.is_active = 1
                ORDER BY (tm.custom_order IS NULL), tm.custom_order ASC,
                    CASE m.module_type
                        WHEN \'ALPHA\' THEN 1 WHEN \'BRAVO\' THEN 2 WHEN \'CHARLIE\' THEN 3 WHEN \'DELTA\' THEN 4 ELSE 5 END,
                    m.code ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param list<int> $moduleIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function fetchDependenciesForModules(array $moduleIds): array
    {
        $moduleIds = array_values(array_unique(array_filter(array_map('intval', $moduleIds), static fn (int $id): bool => $id > 0)));
        if ($moduleIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($moduleIds), '?'));
        $sql = "SELECT d.id, d.module_id, d.requires_module_id, d.dependency_type,
                       req.name AS requires_name, req.code AS requires_code
                FROM module_dependencies d
                INNER JOIN modules req ON req.id = d.requires_module_id
                WHERE d.module_id IN ($placeholders)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($moduleIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            $mid = (int) ($row['module_id'] ?? 0);
            if ($mid < 1) {
                continue;
            }
            if (!isset($out[$mid])) {
                $out[$mid] = [];
            }
            $out[$mid][] = $row;
        }

        return $out;
    }

    /**
     * @param list<int> $moduleIds
     * @return array<int, array<string, mixed>>
     */
    public function fetchRecurrenceByModules(array $moduleIds): array
    {
        $moduleIds = array_values(array_unique(array_filter(array_map('intval', $moduleIds), static fn (int $id): bool => $id > 0)));
        if ($moduleIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($moduleIds), '?'));
        $sql = "SELECT id, module_id, recurrence_type, interval_days, mandatory, grace_days
                FROM recurrence_rules
                WHERE module_id IN ($placeholders)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($moduleIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            $mid = (int) ($row['module_id'] ?? 0);
            if ($mid > 0) {
                $out[$mid] = $row;
            }
        }

        return $out;
    }
}
