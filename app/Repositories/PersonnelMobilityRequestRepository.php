<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Demandes de mobilité interne (unité, spécialité, poste, souhait d’évolution).
 */
final class PersonnelMobilityRequestRepository
{
    /** @var list<string> */
    public const TYPES = ['unit_change', 'specialty_change', 'job_application', 'career_wish'];

    /** @var array<string, string> */
    public const TYPE_LABELS = [
        'unit_change' => 'Changement d’unité',
        'specialty_change' => 'Changement de spécialité',
        'job_application' => 'Candidature à un poste',
        'career_wish' => 'Souhait d’évolution',
    ];

    /** @var list<string> */
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled', 'applied'];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tableExists(): bool
    {
        $st = $this->pdo->query(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_mobility_requests' LIMIT 1"
        );

        return (bool) ($st && $st->fetchColumn());
    }

    public function create(
        int $tenantId,
        int $userId,
        string $requestType,
        ?int $targetUnitId,
        ?int $targetJobRoleId,
        ?string $targetLabel,
        ?string $motivation,
        ?int $requestedBy
    ): int {
        if (!$this->tableExists()) {
            return 0;
        }
        $requestType = in_array($requestType, self::TYPES, true) ? $requestType : 'career_wish';
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_mobility_requests
             (tenant_id, user_id, request_type, target_unit_id, target_job_role_id, target_label, motivation, status, requested_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', ?, NOW())'
        );
        $st->execute([
            $tenantId,
            $userId,
            $requestType,
            $targetUnitId && $targetUnitId > 0 ? $targetUnitId : null,
            $targetJobRoleId && $targetJobRoleId > 0 ? $targetJobRoleId : null,
            $targetLabel !== null && trim($targetLabel) !== '' ? mb_substr(trim($targetLabel), 0, 200) : null,
            $motivation !== null && trim($motivation) !== '' ? trim($motivation) : null,
            $requestedBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id, int $tenantId): ?array
    {
        if (!$this->tableExists() || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM personnel_mobility_requests WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPendingForTenant(int $tenantId, int $limit = 50): array
    {
        return $this->listForTenant($tenantId, 'pending', $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, ?string $status, int $limit = 50): array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $where = 'm.tenant_id = ?';
        $params = [$tenantId];
        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $where .= ' AND m.status = ?';
            $params[] = $status;
        }
        $st = $this->pdo->prepare(
            "SELECT m.*,
                    u.display_name AS user_display_name, u.email AS user_email,
                    un.name AS target_unit_name
             FROM personnel_mobility_requests m
             LEFT JOIN users u ON u.id = m.user_id
             LEFT JOIN units un ON un.id = m.target_unit_id
             WHERE {$where}
             ORDER BY FIELD(m.status, 'pending', 'approved', 'applied', 'rejected', 'cancelled'), m.created_at ASC
             LIMIT {$limit}"
        );
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $tenantId, int $userId, int $limit = 30): array
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $st = $this->pdo->prepare(
            "SELECT * FROM personnel_mobility_requests
             WHERE tenant_id = ? AND user_id = ?
             ORDER BY created_at DESC LIMIT {$limit}"
        );
        $st->execute([$tenantId, $userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countPending(int $tenantId): int
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return 0;
        }
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM personnel_mobility_requests WHERE tenant_id = ? AND status = 'pending'"
        );
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    public function resolve(int $id, int $tenantId, string $status, int $reviewedBy, ?string $note = null): bool
    {
        if (!$this->tableExists() || $id < 1) {
            return false;
        }
        if (!in_array($status, ['approved', 'rejected', 'cancelled', 'applied'], true)) {
            return false;
        }
        $st = $this->pdo->prepare(
            "UPDATE personnel_mobility_requests
             SET status = ?, reviewed_by = ?, reviewed_at = NOW(), resolution_note = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = 'pending'"
        );
        $st->execute([
            $status,
            $reviewedBy,
            $note !== null && trim($note) !== '' ? trim($note) : null,
            $id,
            $tenantId,
        ]);

        return $st->rowCount() > 0;
    }
}
