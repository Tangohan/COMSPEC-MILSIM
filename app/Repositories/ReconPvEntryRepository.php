<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Mini-PV de reconnaissance (fil chronologique, texte + captures d'écran) au sein d'une
 * session de transmission de renseignement.
 */
class ReconPvEntryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * Fil complet d'une session, pièces jointes incluses (regroupées par PV).
     *
     * @return list<array<string, mixed>>
     */
    public function listForSession(int $sessionId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pe.*, u.display_name AS author_name, u.email AS author_email, u.avatar_url AS author_avatar
             FROM recon_pv_entries pe
             LEFT JOIN users u ON u.id = pe.author_user_id
             WHERE pe.session_id = ? AND pe.tenant_id = ?
             ORDER BY pe.created_at ASC, pe.id ASC'
        );
        $stmt->execute([$sessionId, $tenantId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($entries === []) {
            return [];
        }

        $ids = array_map(static fn (array $e): int => (int) $e['id'], $entries);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $attStmt = $this->pdo->prepare(
            "SELECT * FROM recon_pv_attachments WHERE pv_entry_id IN ({$placeholders}) ORDER BY id ASC"
        );
        $attStmt->execute($ids);
        $attachmentsByEntry = [];
        foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $att) {
            $eid = (int) $att['pv_entry_id'];
            $attachmentsByEntry[$eid][] = $att;
        }

        foreach ($entries as &$entry) {
            $eid = (int) $entry['id'];
            $entry['attachments'] = $attachmentsByEntry[$eid] ?? [];
        }
        unset($entry);

        return $entries;
    }

    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM recon_pv_entries WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(int $tenantId, int $sessionId, int $authorUserId, string $body, ?string $gridRef): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recon_pv_entries (tenant_id, session_id, author_user_id, body, grid_ref, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $sessionId, $authorUserId, $body, $gridRef]);

        return (int) $this->pdo->lastInsertId();
    }

    public function addAttachment(int $tenantId, int $pvEntryId, string $storagePath, ?string $caption): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recon_pv_attachments (pv_entry_id, tenant_id, storage_path, caption, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$pvEntryId, $tenantId, $storagePath, $caption]);

        return (int) $this->pdo->lastInsertId();
    }

    public function countForSession(int $sessionId, int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM recon_pv_entries WHERE session_id = ? AND tenant_id = ?');
        $stmt->execute([$sessionId, $tenantId]);

        return (int) $stmt->fetchColumn();
    }
}
