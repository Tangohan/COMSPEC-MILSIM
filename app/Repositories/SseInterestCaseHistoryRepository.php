<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Historique des dossiers d'intérêt, en ajout seul : une ligne par champ modifié (avant / après), par changement d'état, par
 * lien ajouté ou retiré, par archivage, corbeille, restauration. Qui, quand, pourquoi.
 */
final class SseInterestCaseHistoryRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @param array{action: string, field_key?: ?string, old_value?: ?string, new_value?: ?string, reason?: ?string, actor_user_id?: ?int, actor_label?: ?string, version_after?: ?int} $entry
     */
    public function add(int $tenantId, int $caseId, array $entry): int
    {
        try {
            return (int) $this->db->insert(
                'INSERT INTO sse_interest_case_history
                    (tenant_id, interest_case_id, action, field_key, old_value, new_value, reason, actor_user_id, actor_label, version_after, created_at)
                 VALUES (:t, :c, :a, :f, :o, :n, :r, :u, :l, :v, :at)',
                [
                    't' => $tenantId,
                    'c' => $caseId,
                    'a' => mb_substr((string) $entry['action'], 0, 32),
                    'f' => isset($entry['field_key']) ? mb_substr((string) $entry['field_key'], 0, 40) : null,
                    'o' => self::cut($entry['old_value'] ?? null),
                    'n' => self::cut($entry['new_value'] ?? null),
                    'r' => isset($entry['reason']) && trim((string) $entry['reason']) !== '' ? mb_substr(trim((string) $entry['reason']), 0, 300) : null,
                    'u' => isset($entry['actor_user_id']) && (int) $entry['actor_user_id'] > 0 ? (int) $entry['actor_user_id'] : null,
                    'l' => isset($entry['actor_label']) ? mb_substr(trim((string) $entry['actor_label']), 0, 160) : null,
                    'v' => isset($entry['version_after']) ? (int) $entry['version_after'] : null,
                    'at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return list<array<string,mixed>> du plus récent au plus ancien */
    public function listForCase(int $tenantId, int $caseId, int $limit = 300, int $offset = 0): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT * FROM sse_interest_case_history WHERE tenant_id = :t AND interest_case_id = :c
                 ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)) . ' OFFSET ' . max(0, $offset),
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
                'SELECT COUNT(*) AS n FROM sse_interest_case_history WHERE tenant_id = :t AND interest_case_id = :c',
                ['t' => $tenantId, 'c' => $caseId]
            )['n'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function cut(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr((string) $value, 0, 4000);
    }
}
