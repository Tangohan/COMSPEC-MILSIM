<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Sessions de transmission de renseignement (reconnaissance → PV → PoE), généralement
 * ouvertes en amont d'une mission planifiée (community_events), mais utilisables librement.
 */
class ReconTransmissionSessionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, ?string $status = null, int $limit = 100): array
    {
        $sql = "SELECT rts.*, ce.title AS event_title, ce.starts_at AS event_starts_at,
                       u.display_name AS opened_by_name, u.email AS opened_by_email,
                       (SELECT COUNT(*) FROM recon_pv_entries pe WHERE pe.session_id = rts.id) AS entry_count
                FROM recon_transmission_sessions rts
                LEFT JOIN community_events ce ON ce.id = rts.community_event_id
                LEFT JOIN users u ON u.id = rts.opened_by
                WHERE rts.tenant_id = ?";
        $params = [$tenantId];
        if ($status !== null) {
            $sql .= ' AND rts.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY rts.created_at DESC LIMIT ' . max(1, min(300, $limit));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rts.*, ce.title AS event_title, ce.starts_at AS event_starts_at
             FROM recon_transmission_sessions rts
             LEFT JOIN community_events ce ON ce.id = rts.community_event_id
             WHERE rts.id = ? AND rts.tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(int $tenantId, string $title, int $openedBy, ?int $communityEventId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recon_transmission_sessions (tenant_id, community_event_id, title, status, opened_by, created_at)
             VALUES (?, ?, ?, \'open\', ?, NOW())'
        );
        $stmt->execute([$tenantId, $communityEventId, $title, $openedBy]);

        return (int) $this->pdo->lastInsertId();
    }

    public function close(int $id, int $tenantId, int $closedBy): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE recon_transmission_sessions
             SET status = 'closed', closed_by = ?, closed_at = NOW(), updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = 'open'"
        );
        $stmt->execute([$closedBy, $id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function reopen(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE recon_transmission_sessions
             SET status = 'open', closed_by = NULL, closed_at = NULL, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = 'closed'"
        );
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function countOpenForTenant(int $tenantId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM recon_transmission_sessions WHERE tenant_id = ? AND status = 'open'");
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }
}
