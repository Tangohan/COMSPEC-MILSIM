<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

final class GradeDefinitionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('grade_definitions');
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, bool $includeArchived = false): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $sql = 'SELECT d.*,
                       f.label AS filiere_label, f.code AS filiere_code,
                       q.name AS required_qualification_name, q.code AS required_qualification_code
                FROM grade_definitions d
                LEFT JOIN grade_filiere_definitions f ON f.id = d.filiere_id
                LEFT JOIN personnel_qualification_definitions q ON q.id = d.required_qualification_id
                WHERE d.tenant_id = ?';
        if (!$includeArchived) {
            $sql .= ' AND d.archived_at IS NULL';
        }
        $sql .= ' ORDER BY d.rank_order ASC, d.label ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        if (!$this->schemaReady() || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT d.*,
                    f.label AS filiere_label, f.code AS filiere_code,
                    q.name AS required_qualification_name, q.code AS required_qualification_code
             FROM grade_definitions d
             LEFT JOIN grade_filiere_definitions f ON f.id = d.filiere_id
             LEFT JOIN personnel_qualification_definitions q ON q.id = d.required_qualification_id
             WHERE d.tenant_id = ? AND d.id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCode(int $tenantId, string $code): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM grade_definitions WHERE tenant_id = ? AND code = ? LIMIT 1'
        );
        $st->execute([$tenantId, strtoupper(trim($code))]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function countForTenant(int $tenantId): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM grade_definitions WHERE tenant_id = ? AND archived_at IS NULL'
        );
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    /**
     * Grade immédiatement inférieur (même filière si le grade visé en a une).
     *
     * @param array<string, mixed> $target
     * @return array<string, mixed>|null
     */
    public function findImmediatePredecessor(int $tenantId, array $target): ?array
    {
        $rank = (int) ($target['rank_order'] ?? 0);
        $filiereId = isset($target['filiere_id']) && (int) $target['filiere_id'] > 0
            ? (int) $target['filiere_id']
            : null;
        $sql = 'SELECT * FROM grade_definitions
                WHERE tenant_id = ? AND archived_at IS NULL AND rank_order < ?';
        $params = [$tenantId, $rank];
        if ($filiereId !== null) {
            $sql .= ' AND (filiere_id = ? OR filiere_id IS NULL)';
            $params[] = $filiereId;
        }
        $sql .= ' ORDER BY rank_order DESC, id DESC LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Grade immédiatement supérieur (même filière si le grade actuel en a une).
     *
     * @param array<string, mixed> $current
     * @return array<string, mixed>|null
     */
    public function findImmediateSuccessor(int $tenantId, array $current): ?array
    {
        $rank = (int) ($current['rank_order'] ?? 0);
        $filiereId = isset($current['filiere_id']) && (int) $current['filiere_id'] > 0
            ? (int) $current['filiere_id']
            : null;
        $sql = 'SELECT * FROM grade_definitions
                WHERE tenant_id = ? AND archived_at IS NULL AND rank_order > ?';
        $params = [$tenantId, $rank];
        if ($filiereId !== null) {
            $sql .= ' AND (filiere_id = ? OR filiere_id IS NULL)';
            $params[] = $filiereId;
        }
        $sql .= ' ORDER BY rank_order ASC, id ASC LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO grade_definitions
                (tenant_id, code, label, short_label, filiere_id, rank_order,
                 advancement_seniority_enabled, advancement_choice_enabled,
                 min_time_in_previous_grade_months, required_qualification_id,
                 required_qualification_level_id, source_catalog_grade_id, template_key,
                 created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        );
        $st->execute([
            $tenantId,
            strtoupper(trim((string) ($data['code'] ?? ''))),
            trim((string) ($data['label'] ?? '')),
            ($s = trim((string) ($data['short_label'] ?? ''))) === '' ? null : $s,
            isset($data['filiere_id']) && (int) $data['filiere_id'] > 0 ? (int) $data['filiere_id'] : null,
            (int) ($data['rank_order'] ?? 0),
            !empty($data['advancement_seniority_enabled']) ? 1 : 0,
            !empty($data['advancement_choice_enabled']) ? 1 : 0,
            isset($data['min_time_in_previous_grade_months']) && $data['min_time_in_previous_grade_months'] !== ''
                ? (int) $data['min_time_in_previous_grade_months']
                : null,
            isset($data['required_qualification_id']) && (int) $data['required_qualification_id'] > 0
                ? (int) $data['required_qualification_id']
                : null,
            isset($data['required_qualification_level_id']) && (int) $data['required_qualification_level_id'] > 0
                ? (int) $data['required_qualification_level_id']
                : null,
            isset($data['source_catalog_grade_id']) && (int) $data['source_catalog_grade_id'] > 0
                ? (int) $data['source_catalog_grade_id']
                : null,
            ($t = trim((string) ($data['template_key'] ?? ''))) === '' ? null : $t,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $tenantId, int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            'UPDATE grade_definitions SET
                code = ?, label = ?, short_label = ?, filiere_id = ?, rank_order = ?,
                advancement_seniority_enabled = ?, advancement_choice_enabled = ?,
                min_time_in_previous_grade_months = ?, required_qualification_id = ?,
                required_qualification_level_id = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND archived_at IS NULL'
        );
        $st->execute([
            strtoupper(trim((string) ($data['code'] ?? ''))),
            trim((string) ($data['label'] ?? '')),
            ($s = trim((string) ($data['short_label'] ?? ''))) === '' ? null : $s,
            isset($data['filiere_id']) && (int) $data['filiere_id'] > 0 ? (int) $data['filiere_id'] : null,
            (int) ($data['rank_order'] ?? 0),
            !empty($data['advancement_seniority_enabled']) ? 1 : 0,
            !empty($data['advancement_choice_enabled']) ? 1 : 0,
            isset($data['min_time_in_previous_grade_months']) && $data['min_time_in_previous_grade_months'] !== ''
                ? (int) $data['min_time_in_previous_grade_months']
                : null,
            isset($data['required_qualification_id']) && (int) $data['required_qualification_id'] > 0
                ? (int) $data['required_qualification_id']
                : null,
            isset($data['required_qualification_level_id']) && (int) $data['required_qualification_level_id'] > 0
                ? (int) $data['required_qualification_level_id']
                : null,
            $tenantId,
            $id,
        ]);
    }

    public function archive(int $tenantId, int $id): void
    {
        $st = $this->pdo->prepare(
            'UPDATE grade_definitions SET archived_at = NOW(), updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND archived_at IS NULL'
        );
        $st->execute([$tenantId, $id]);
    }

    /** @param list<int> $orderedIds */
    public function reorder(int $tenantId, array $orderedIds): void
    {
        $upd = $this->pdo->prepare(
            'UPDATE grade_definitions SET rank_order = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?'
        );
        $order = 10;
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            $upd->execute([$order, $tenantId, $id]);
            $order += 10;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);

            return (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
