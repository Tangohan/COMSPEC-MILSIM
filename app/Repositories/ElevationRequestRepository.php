<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Suivi des demandes d'élévation (grade / rôle / droits) — machine à états
 * consultable par le demandeur et par la personne concernée (voir /account/acces).
 */
class ElevationRequestRepository
{
    private PDO $pdo;

    /** @var list<string> */
    public const STATUSES = ['pending', 'in_review', 'approved', 'rejected'];

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * @param array{
     *   grade_id?: int|null,
     *   role_id?: int|null,
     *   job_role_id?: int|null,
     *   unit_id?: int|null
     * } $proposal
     */
    public function create(
        int $tenantId,
        int $targetUserId,
        int $requestedBy,
        string $kind,
        string $note,
        array $proposal = []
    ): int {
        $gradeId = $this->nullablePositiveId($proposal['grade_id'] ?? null);
        $roleId = $this->nullablePositiveId($proposal['role_id'] ?? null);
        $jobRoleId = $this->nullablePositiveId($proposal['job_role_id'] ?? null);
        $unitId = $this->nullablePositiveId($proposal['unit_id'] ?? null);

        if ($this->hasProposalColumns()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO elevation_requests (
                    tenant_id, target_user_id, requested_by, kind, note,
                    proposed_grade_id, proposed_role_id, proposed_job_role_id, proposed_unit_id,
                    status, created_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', NOW())'
            );
            $stmt->execute([
                $tenantId,
                $targetUserId,
                $requestedBy,
                $kind,
                $note !== '' ? $note : null,
                $gradeId,
                $roleId,
                $jobRoleId,
                $unitId,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO elevation_requests (tenant_id, target_user_id, requested_by, kind, note, status, created_at)
                 VALUES (?, ?, ?, ?, ?, \'pending\', NOW())'
            );
            $stmt->execute([$tenantId, $targetUserId, $requestedBy, $kind, $note !== '' ? $note : null]);
        }

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listForRequester(int $tenantId, int $requesterUserId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT er.*, u.display_name AS target_display_name, u.email AS target_email
             FROM elevation_requests er
             LEFT JOIN users u ON u.id = er.target_user_id
             WHERE er.tenant_id = ? AND er.requested_by = ?
             ORDER BY er.created_at DESC
             LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute([$tenantId, $requesterUserId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listForTarget(int $tenantId, int $targetUserId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT er.*, u.display_name AS requester_display_name, u.email AS requester_email
             FROM elevation_requests er
             LEFT JOIN users u ON u.id = er.requested_by
             WHERE er.tenant_id = ? AND er.target_user_id = ?
             ORDER BY er.created_at DESC
             LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute([$tenantId, $targetUserId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listOpenForTenant(int $tenantId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT er.*,
                    t.display_name AS target_display_name, t.email AS target_email,
                    t.grade_id AS target_current_grade_id, t.role_id AS target_legacy_role_id,
                    r.display_name AS requester_display_name, r.email AS requester_email
             FROM elevation_requests er
             LEFT JOIN users t ON t.id = er.target_user_id
             LEFT JOIN users r ON r.id = er.requested_by
             WHERE er.tenant_id = ? AND er.status IN ('pending', 'in_review')
             ORDER BY er.created_at ASC
             LIMIT " . max(1, min(300, $limit))
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listRecentForTenant(int $tenantId, int $limit = 300): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT er.*,
                    t.display_name AS target_display_name, t.email AS target_email,
                    t.grade_id AS target_current_grade_id, t.role_id AS target_legacy_role_id,
                    r.display_name AS requester_display_name, r.email AS requester_email
             FROM elevation_requests er
             LEFT JOIN users t ON t.id = er.target_user_id
             LEFT JOIN users r ON r.id = er.requested_by
             WHERE er.tenant_id = ?
             ORDER BY er.created_at DESC
             LIMIT ' . max(1, min(500, $limit))
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Évite les doublons : demande déjà ouverte (pending/in_review) du même demandeur pour la même cible. */
    public function findOpenForRequesterTarget(int $tenantId, int $requesterUserId, int $targetUserId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM elevation_requests
             WHERE tenant_id = ? AND requested_by = ? AND target_user_id = ? AND status IN ('pending', 'in_review')
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$tenantId, $requesterUserId, $targetUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM elevation_requests WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function countOpenForTenant(int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM elevation_requests WHERE tenant_id = ? AND status IN ('pending', 'in_review')"
        );
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    public function updateStatus(
        int $id,
        int $tenantId,
        string $status,
        int $resolvedBy,
        string $resolutionNote = ''
    ): bool {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }
        $isFinal = in_array($status, ['approved', 'rejected'], true);
        $stmt = $this->pdo->prepare(
            'UPDATE elevation_requests
             SET status = ?, resolution_note = ?, resolved_by = ?, resolved_at = ' . ($isFinal ? 'NOW()' : 'NULL') . ', updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([
            $status,
            $resolutionNote !== '' ? $resolutionNote : null,
            $resolvedBy,
            $id,
            $tenantId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Enregistre les choix du traitement (grade, rôle, fonction, affectation).
     *
     * @param array{
     *   grade_id?: int|null,
     *   role_id?: int|null,
     *   job_role_id?: int|null,
     *   unit_id?: int|null
     * } $proposal
     */
    public function saveProposalChoices(int $id, int $tenantId, array $proposal): bool
    {
        if (!$this->hasProposalColumns()) {
            return true;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE elevation_requests
             SET proposed_grade_id = ?,
                 proposed_role_id = ?,
                 proposed_job_role_id = ?,
                 proposed_unit_id = ?,
                 updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([
            $this->nullablePositiveId($proposal['grade_id'] ?? null),
            $this->nullablePositiveId($proposal['role_id'] ?? null),
            $this->nullablePositiveId($proposal['job_role_id'] ?? null),
            $this->nullablePositiveId($proposal['unit_id'] ?? null),
            $id,
            $tenantId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function hasProposalColumns(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $st = $this->pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'elevation_requests' AND COLUMN_NAME = 'proposed_role_id'
             LIMIT 1"
        );
        $st->execute();
        $cached = (bool) $st->fetchColumn();

        return $cached;
    }

    private function nullablePositiveId(mixed $value): ?int
    {
        $id = (int) ($value ?? 0);

        return $id > 0 ? $id : null;
    }
}
