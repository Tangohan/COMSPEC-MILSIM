<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Éléments liés à un dossier d'intérêt (personnes, identités, sites, véhicules, transmissions, fiches, documents, dossiers)
 * et abonnés au dossier. Le lien ne retient que le type, l'identifiant de la cible et un libellé de repli : le titre affiché
 * et le droit de le voir sont revérifiés à chaque affichage (SseInterestCaseService::presentLinks).
 */
final class SseInterestCaseLinkRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** @return list<array<string,mixed>> */
    public function listForCase(int $tenantId, int $caseId): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT * FROM sse_interest_case_links WHERE tenant_id = :t AND interest_case_id = :c ORDER BY id ASC',
                ['t' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function countForCase(int $tenantId, int $caseId): int
    {
        try {
            return (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS n FROM sse_interest_case_links WHERE tenant_id = :t AND interest_case_id = :c',
                ['t' => $tenantId, 'c' => $caseId]
            )['n'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function find(int $tenantId, int $caseId, int $linkId): ?array
    {
        try {
            return $this->db->fetchOne(
                'SELECT * FROM sse_interest_case_links WHERE id = :id AND tenant_id = :t AND interest_case_id = :c',
                ['id' => $linkId, 't' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function exists(int $tenantId, int $caseId, string $type, string $targetId): bool
    {
        try {
            return $this->db->fetchOne(
                'SELECT 1 AS x FROM sse_interest_case_links WHERE tenant_id = :t AND interest_case_id = :c AND link_type = :ty AND target_id = :id',
                ['t' => $tenantId, 'c' => $caseId, 'ty' => $type, 'id' => $targetId]
            ) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public function add(int $tenantId, int $caseId, string $type, string $targetId, string $label, ?string $note, ?int $userId): int
    {
        return (int) $this->db->insert(
            'INSERT INTO sse_interest_case_links (tenant_id, interest_case_id, link_type, target_id, label, note, created_by, created_at)
             VALUES (:t, :c, :ty, :id, :l, :n, :u, :at)',
            [
                't' => $tenantId,
                'c' => $caseId,
                'ty' => $type,
                'id' => $targetId,
                'l' => mb_substr($label, 0, 200),
                'n' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 300) : null,
                'u' => $userId !== null && $userId > 0 ? $userId : null,
                'at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    public function remove(int $tenantId, int $caseId, int $linkId): bool
    {
        return $this->db->execute(
            'DELETE FROM sse_interest_case_links WHERE id = :id AND tenant_id = :t AND interest_case_id = :c',
            ['id' => $linkId, 't' => $tenantId, 'c' => $caseId]
        ) > 0;
    }

    /** @return list<int> */
    public function watchers(int $tenantId, int $caseId): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT user_id FROM sse_interest_case_watchers WHERE tenant_id = :t AND interest_case_id = :c ORDER BY id ASC',
                ['t' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn (array $r): int => (int) $r['user_id'], $rows);
    }

    public function isWatching(int $tenantId, int $caseId, int $userId): bool
    {
        return in_array($userId, $this->watchers($tenantId, $caseId), true);
    }

    public function watch(int $tenantId, int $caseId, int $userId): bool
    {
        if ($userId < 1 || $this->isWatching($tenantId, $caseId, $userId)) {
            return false;
        }
        try {
            $this->db->insert(
                'INSERT INTO sse_interest_case_watchers (tenant_id, interest_case_id, user_id, created_at) VALUES (:t, :c, :u, :at)',
                ['t' => $tenantId, 'c' => $caseId, 'u' => $userId, 'at' => date('Y-m-d H:i:s')]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function unwatch(int $tenantId, int $caseId, int $userId): bool
    {
        try {
            return $this->db->execute(
                'DELETE FROM sse_interest_case_watchers WHERE tenant_id = :t AND interest_case_id = :c AND user_id = :u',
                ['t' => $tenantId, 'c' => $caseId, 'u' => $userId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
