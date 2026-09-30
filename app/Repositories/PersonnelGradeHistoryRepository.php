<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

final class PersonnelGradeHistoryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('personnel_grade_history');
    }

    /** @return array<string, mixed>|null */
    public function currentForPersonnel(int $tenantId, int $personnelId): ?array
    {
        if (!$this->schemaReady() || $personnelId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT h.*, d.code AS grade_code, d.label AS grade_label, d.short_label AS grade_short_label,
                    d.rank_order, d.filiere_id, d.advancement_seniority_enabled, d.advancement_choice_enabled,
                    d.min_time_in_previous_grade_months, d.required_qualification_id,
                    d.required_qualification_level_id, d.source_catalog_grade_id
             FROM personnel_grade_history h
             JOIN grade_definitions d ON d.id = h.grade_id
             WHERE h.tenant_id = ? AND h.personnel_id = ? AND h.ends_at IS NULL
             ORDER BY h.obtained_at DESC, h.id DESC
             LIMIT 1'
        );
        $st->execute([$tenantId, $personnelId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listForPersonnel(int $tenantId, int $personnelId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT h.*, d.code AS grade_code, d.label AS grade_label, d.short_label AS grade_short_label,
                    d.rank_order, f.label AS filiere_label
             FROM personnel_grade_history h
             JOIN grade_definitions d ON d.id = h.grade_id
             LEFT JOIN grade_filiere_definitions f ON f.id = d.filiere_id
             WHERE h.tenant_id = ? AND h.personnel_id = ?
             ORDER BY h.obtained_at DESC, h.id DESC'
        );
        $st->execute([$tenantId, $personnelId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listActiveForTenant(int $tenantId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT h.*, d.code AS grade_code, d.label AS grade_label, d.short_label AS grade_short_label,
                    d.rank_order, d.filiere_id, d.advancement_seniority_enabled,
                    d.min_time_in_previous_grade_months, d.required_qualification_id,
                    d.required_qualification_level_id, d.source_catalog_grade_id,
                    u.display_name, u.email, u.grade_id AS user_legacy_grade_id
             FROM personnel_grade_history h
             JOIN grade_definitions d ON d.id = h.grade_id
             JOIN users u ON u.id = h.personnel_id
             WHERE h.tenant_id = ? AND h.ends_at IS NULL
             ORDER BY d.rank_order ASC, u.display_name ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array{obtained_at: string, obtained_via: string, candidacy_id?: ?int, created_by?: ?int} $data
     */
    public function append(int $tenantId, int $personnelId, int $gradeId, array $data): int
    {
        $obtainedAt = substr((string) ($data['obtained_at'] ?? date('Y-m-d')), 0, 10);
        $this->pdo->beginTransaction();
        try {
            $close = $this->pdo->prepare(
                'UPDATE personnel_grade_history
                 SET ends_at = ?
                 WHERE tenant_id = ? AND personnel_id = ? AND ends_at IS NULL'
            );
            $close->execute([$obtainedAt, $tenantId, $personnelId]);

            $ins = $this->pdo->prepare(
                'INSERT INTO personnel_grade_history
                    (tenant_id, personnel_id, grade_id, obtained_at, obtained_via, candidacy_id, created_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)'
            );
            $ins->execute([
                $tenantId,
                $personnelId,
                $gradeId,
                $obtainedAt,
                (string) ($data['obtained_via'] ?? 'initial'),
                isset($data['candidacy_id']) && (int) $data['candidacy_id'] > 0 ? (int) $data['candidacy_id'] : null,
                isset($data['created_by']) && (int) $data['created_by'] > 0 ? (int) $data['created_by'] : null,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
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
