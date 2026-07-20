<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Mini-PV de reconnaissance (fil chronologique, structure MRT/TAMMUC simplifiée + captures)
 * au sein d'une session de transmission de renseignement.
 */
class ReconPvEntryRepository
{
    public const URGENCY_IMMEDIATE = 'immediate';
    public const URGENCY_DEFERRED = 'deferred';

    /** @var list<string> */
    public const URGENCY_VALUES = [self::URGENCY_IMMEDIATE, self::URGENCY_DEFERRED];

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

    /**
     * @param array{
     *   body?: string,
     *   grid_ref?: ?string,
     *   captured_at?: ?string,
     *   terrain_text?: ?string,
     *   adversary_text?: ?string,
     *   mission_text?: ?string,
     *   means_text?: ?string,
     *   urgency?: ?string,
     *   engagement_frame_text?: ?string
     * } $fields
     */
    public function create(int $tenantId, int $sessionId, int $authorUserId, array $fields): int
    {
        $body = (string) ($fields['body'] ?? '');
        $gridRef = $fields['grid_ref'] ?? null;
        $capturedAt = $fields['captured_at'] ?? null;
        $terrain = $fields['terrain_text'] ?? null;
        $adversary = $fields['adversary_text'] ?? null;
        $mission = $fields['mission_text'] ?? null;
        $means = $fields['means_text'] ?? null;
        $urgency = $fields['urgency'] ?? null;
        $engagement = $fields['engagement_frame_text'] ?? null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO recon_pv_entries (
                tenant_id, session_id, author_user_id, body, grid_ref,
                captured_at, terrain_text, adversary_text, mission_text,
                means_text, urgency, engagement_frame_text, created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $sessionId,
            $authorUserId,
            $body,
            $gridRef,
            $capturedAt,
            $terrain,
            $adversary,
            $mission,
            $means,
            $urgency,
            $engagement,
        ]);

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
