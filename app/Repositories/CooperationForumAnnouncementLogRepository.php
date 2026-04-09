<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CooperationForumAnnouncementLogRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cooperation_forum_announcement_log' LIMIT 1");

            return (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    public function secondsSinceLastPost(int $missionId, string $eventKey): ?int
    {
        if (!$this->tableExists() || $missionId < 1 || $eventKey === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT posted_at FROM cooperation_forum_announcement_log WHERE mission_id = ? AND event_key = ? LIMIT 1'
        );
        $stmt->execute([$missionId, $eventKey]);
        $at = $stmt->fetchColumn();
        if (!$at) {
            return null;
        }
        $ts = strtotime((string) $at);

        return $ts !== false ? max(0, time() - $ts) : null;
    }

    public function touch(int $missionId, string $eventKey): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $this->pdo->prepare(
            'INSERT INTO cooperation_forum_announcement_log (mission_id, event_key, posted_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE posted_at = VALUES(posted_at)'
        )->execute([$missionId, $eventKey]);
    }
}
