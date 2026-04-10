<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOException;

final class TrainingCompetencyRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * Schéma complet module compétences : matrices (bootstrap PHP) + progression (modules / user_progress, SQL).
     */
    public function competencySchemaAvailable(): bool
    {
        return $this->hasTable('training_competency_matrices') && $this->hasTable('user_progress');
    }

    public function competencyMatricesSchemaAvailable(): bool
    {
        return $this->hasTable('training_competency_matrices');
    }

    public function competencyTrainerRolesSchemaAvailable(): bool
    {
        return $this->hasTable('training_trainer_roles');
    }

    private function hasTable(string $table): bool
    {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($t === '') {
            return false;
        }
        try {
            $st = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($t));

            return (bool) $st->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    /** @return list<array<string,mixed>> */
    public function listTrainerRoles(int $tenantId): array
    {
        if (!$this->hasTable('training_trainer_roles')) {
            return [];
        }
        $sql = "SELECT r.id, r.name, r.slug,
                       CASE WHEN ttr.role_id IS NULL THEN 0 ELSE 1 END AS is_trainer_role
                FROM roles r
                LEFT JOIN training_trainer_roles ttr ON ttr.tenant_id = r.tenant_id AND ttr.role_id = r.id
                WHERE r.tenant_id = ? AND r.role_layer IN ('community','intra')
                ORDER BY r.name ASC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param list<int> $roleIds */
    public function saveTrainerRolePicking(int $tenantId, array $roleIds, int $actorUserId): void
    {
        if (!$this->hasTable('training_trainer_roles')) {
            return;
        }
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $v): bool => $v > 0)));
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM training_trainer_roles WHERE tenant_id = ?')->execute([$tenantId]);
            if ($roleIds !== []) {
                $ins = $this->pdo->prepare('INSERT INTO training_trainer_roles (tenant_id, role_id, created_by_user_id, created_at) VALUES (?, ?, ?, NOW())');
                foreach ($roleIds as $rid) {
                    $ins->execute([$tenantId, $rid, $actorUserId > 0 ? $actorUserId : null]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<int> */
    public function trainerRoleIds(int $tenantId): array
    {
        if (!$this->hasTable('training_trainer_roles')) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT role_id FROM training_trainer_roles WHERE tenant_id = ? ORDER BY role_id ASC');
        $st->execute([$tenantId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return list<array<string,mixed>> */
    public function listMatrices(int $tenantId): array
    {
        if (!$this->hasTable('training_competency_matrices')) {
            return [];
        }
        $sql = "SELECT m.*, COUNT(a.user_id) AS assignment_count
                FROM training_competency_matrices m
                LEFT JOIN training_competency_matrix_assignments a ON a.matrix_id = m.id
                WHERE m.tenant_id = ?
                GROUP BY m.id
                ORDER BY m.updated_at DESC, m.id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findMatrix(int $tenantId, int $matrixId): ?array
    {
        if (!$this->hasTable('training_competency_matrices')) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM training_competency_matrices WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $matrixId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function saveMatrix(int $tenantId, int $actorUserId, string $name, string $description, array $autoRules): int
    {
        if (!$this->hasTable('training_competency_matrices')) {
            return 0;
        }
        $json = json_encode($autoRules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $st = $this->pdo->prepare('INSERT INTO training_competency_matrices (tenant_id, name, description, auto_detect_rules_json, is_active, created_by_user_id, updated_by_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?, NOW(), NOW())');
        $st->execute([
            $tenantId,
            mb_substr(trim($name), 0, 120),
            mb_substr(trim($description), 0, 1000),
            $json !== false ? $json : '{}',
            $actorUserId > 0 ? $actorUserId : null,
            $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param list<int> $userIds */
    public function assignMatrixToUsers(int $tenantId, int $matrixId, int $actorUserId, array $userIds, string $source = 'manual'): int
    {
        if (!$this->hasTable('training_competency_matrix_assignments')) {
            return 0;
        }
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $v): bool => $v > 0)));
        if ($userIds === []) {
            return 0;
        }
        $ins = $this->pdo->prepare('INSERT IGNORE INTO training_competency_matrix_assignments (tenant_id, matrix_id, user_id, assigned_by_user_id, source, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $count = 0;
        foreach ($userIds as $uid) {
            $ins->execute([$tenantId, $matrixId, $uid, $actorUserId > 0 ? $actorUserId : null, $source]);
            $count += $ins->rowCount() > 0 ? 1 : 0;
        }

        return $count;
    }

    /** @return list<int> */
    public function autoDetectCandidateUserIds(int $tenantId, array $autoRules): array
    {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $autoRules['role_ids_any'] ?? []), static fn (int $v): bool => $v > 0)));
        $minCompleted = max(0, (int) ($autoRules['min_completed_courses'] ?? 0));

        $where = ['u.tenant_id = ?'];
        $params = [$tenantId];
        if ($roleIds !== []) {
            $ph = implode(',', array_fill(0, count($roleIds), '?'));
            $where[] = "(u.role_id IN ($ph) OR EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id IN ($ph)))";
            $params = array_merge($params, $roleIds, $roleIds);
        }
        if ($minCompleted > 0) {
            $where[] = '(SELECT COUNT(*) FROM training_enrollments te WHERE te.user_id = u.id AND te.status = \'completed\') >= ?';
            $params[] = $minCompleted;
        }

        $sql = 'SELECT u.id FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.id ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return list<array<string,mixed>> */
    public function listTenantUsers(int $tenantId): array
    {
        $st = $this->pdo->prepare("SELECT id, display_name, email FROM users WHERE tenant_id = ? AND status <> 'deleted' ORDER BY display_name ASC");
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
