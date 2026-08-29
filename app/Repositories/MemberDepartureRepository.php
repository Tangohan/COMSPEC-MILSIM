<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Offboarding structuré — historique des départs (motif, date, reprise d’accès).
 */
class MemberDepartureRepository
{
    /** @var list<string> */
    public const REASONS = ['end_of_engagement', 'exclusion', 'pause', 'other'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function create(
        int $tenantId,
        int $userId,
        ?int $initiatedBy,
        string $reason,
        string $reasonNote,
        string $departedAt
    ): int {
        $reason = in_array($reason, self::REASONS, true) ? $reason : 'other';
        $stmt = $this->pdo->prepare(
            'INSERT INTO member_departures (tenant_id, user_id, initiated_by, reason, reason_note, departed_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $userId,
            $initiatedBy,
            $reason,
            $reasonNote !== '' ? $reasonNote : null,
            $departedAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function markAccessRevoked(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE member_departures SET access_revoked = 1, access_revoked_at = NOW() WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function markDossierArchived(int $id, int $tenantId): bool
    {
        if (!$this->hasArchiveColumns()) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE member_departures
             SET dossier_archived = 1, dossier_archived_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function markReinstated(int $id, int $tenantId, int $reinstatedBy): bool
    {
        if (!$this->hasArchiveColumns()) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE member_departures
             SET reinstated_at = NOW(), reinstated_by = ?
             WHERE id = ? AND tenant_id = ? AND reinstated_at IS NULL'
        );
        $stmt->execute([$reinstatedBy, $id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    private function hasArchiveColumns(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $st = $this->pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_departures' AND COLUMN_NAME = 'dossier_archived'
             LIMIT 1"
        );
        $ready = (bool) ($st && $st->fetchColumn());

        return $ready;
    }

    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM member_departures WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string,mixed>|null Le départ le plus récent enregistré pour ce membre. */
    public function findLatestForUser(int $tenantId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM member_departures WHERE tenant_id = ? AND user_id = ? ORDER BY departed_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function listForTenant(int $tenantId, ?string $reasonFilter, int $limit, int $offset): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $where = 'md.tenant_id = ?';
        $params = [$tenantId];
        if ($reasonFilter !== null && in_array($reasonFilter, self::REASONS, true)) {
            $where .= ' AND md.reason = ?';
            $params[] = $reasonFilter;
        }
        $stmt = $this->pdo->prepare(
            "SELECT md.*,
                    u.display_name AS user_display_name, u.email AS user_email, u.status AS user_status,
                    i.display_name AS initiator_display_name, i.email AS initiator_email
             FROM member_departures md
             LEFT JOIN users u ON u.id = md.user_id
             LEFT JOIN users i ON i.id = md.initiated_by
             WHERE {$where}
             ORDER BY md.departed_at DESC, md.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countForTenant(int $tenantId, ?string $reasonFilter): int
    {
        $where = 'tenant_id = ?';
        $params = [$tenantId];
        if ($reasonFilter !== null && in_array($reasonFilter, self::REASONS, true)) {
            $where .= ' AND reason = ?';
            $params[] = $reasonFilter;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM member_departures WHERE {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
