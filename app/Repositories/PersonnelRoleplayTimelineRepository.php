<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PersonnelRoleplayTimelineRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'personnel_roleplay_timeline_events'");

        return (bool) ($stmt && $stmt->fetchColumn());
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listForUser(int $tenantId, int $userId, int $limit = 50): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT e.*, u.display_name AS actor_display_name, u.callsign AS actor_callsign
             FROM personnel_roleplay_timeline_events e
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.tenant_id = ? AND e.user_id = ?
             ORDER BY COALESCE(e.event_date, DATE(e.created_at)) DESC, e.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addEvent(
        int $tenantId,
        int $userId,
        string $eventType,
        string $title,
        ?string $detail,
        ?string $eventDate,
        ?string $dueDate,
        string $status,
        ?int $progressDelta,
        ?int $createdBy
    ): void {
        if (!$this->tableExists()) {
            return;
        }
        $evType = trim($eventType);
        $label = trim($title);
        if ($evType === '' || $label === '') {
            return;
        }
        $st = trim($status);
        if (!in_array($st, ['planned', 'completed', 'blocked', 'cancelled'], true)) {
            $st = 'planned';
        }
        $delta = $progressDelta;
        if ($delta !== null) {
            $delta = max(-100, min(100, $delta));
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_roleplay_timeline_events
            (tenant_id, user_id, event_type, title, detail, event_date, due_date, status, progress_delta, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $userId,
            $evType,
            $label,
            $detail !== null && trim($detail) !== '' ? trim($detail) : null,
            $eventDate !== null && trim($eventDate) !== '' ? trim($eventDate) : null,
            $dueDate !== null && trim($dueDate) !== '' ? trim($dueDate) : null,
            $st,
            $delta,
            $createdBy,
        ]);
    }
}
