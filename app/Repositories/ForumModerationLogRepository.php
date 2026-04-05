<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Journal des décisions du moteur modération forum (ForumModerationEngine, futurs bots).
 */
final class ForumModerationLogRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_moderation_logs' LIMIT 1");

        return (bool) $stmt?->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecentForTenant(int $tenantId, int $limit = 40): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT l.id, l.tenant_id, l.user_id, l.post_id, l.rule_type, l.score, l.action_taken, l.detail_json, l.created_at,
                    u.display_name AS user_display_name, u.callsign AS user_callsign,
                    fp.topic_id AS post_topic_id
             FROM forum_moderation_logs l
             LEFT JOIN users u ON u.id = l.user_id
             LEFT JOIN forum_posts fp ON fp.id = l.post_id AND fp.tenant_id = l.tenant_id
             WHERE l.tenant_id = ?
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            if (!empty($row['detail_json']) && is_string($row['detail_json'])) {
                $decoded = json_decode($row['detail_json'], true);
                $row['detail_parsed'] = is_array($decoded) ? $decoded : null;
            } else {
                $row['detail_parsed'] = null;
            }
        }
        unset($row);

        return $rows;
    }
}
