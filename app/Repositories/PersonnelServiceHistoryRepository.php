<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PersonnelServiceHistoryRepository
{
    private PDO $pdo;
    private ?bool $reasonLabelColumnReady = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM personnel_service_history WHERE user_id = ? ORDER BY event_date DESC, id DESC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add(
        int $userId,
        string $eventType,
        string $title,
        string $description = '',
        string $eventDate = '',
        ?int $createdBy = null,
        ?string $reasonLabel = null
    ): int
    {
        $date = $eventDate ?: date('Y-m-d');
        $reasonLabel = $this->normalizeReasonLabel($reasonLabel);
        if ($this->reasonLabelColumnReady()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO personnel_service_history (user_id, event_type, title, reason_label, description, event_date, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$userId, $eventType, $title, $reasonLabel, $description, $date, $createdBy]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO personnel_service_history (user_id, event_type, title, description, event_date, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$userId, $eventType, $title, $description, $date, $createdBy]);
        }
        return (int) $this->pdo->lastInsertId();
    }

    private function reasonLabelColumnReady(): bool
    {
        if ($this->reasonLabelColumnReady !== null) {
            return $this->reasonLabelColumnReady;
        }
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_service_history'
                   AND COLUMN_NAME = 'reason_label' LIMIT 1"
            );
            $stmt->execute();
            $this->reasonLabelColumnReady = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            $this->reasonLabelColumnReady = false;
        }

        return $this->reasonLabelColumnReady;
    }

    private function normalizeReasonLabel(?string $reasonLabel): ?string
    {
        $reasonLabel = trim((string) $reasonLabel);
        if ($reasonLabel === '') {
            return null;
        }
        if (function_exists('mb_strlen') && mb_strlen($reasonLabel) > 255) {
            return mb_substr($reasonLabel, 0, 255);
        }
        if (strlen($reasonLabel) > 255) {
            return substr($reasonLabel, 0, 255);
        }

        return $reasonLabel;
    }
}
