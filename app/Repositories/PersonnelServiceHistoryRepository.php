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
    public function listForUser(int $userId, int $limit = 50, ?int $tenantId = null): array
    {
        $sql = 'SELECT * FROM personnel_service_history WHERE user_id = ?';
        $args = [$userId];
        if ($tenantId !== null && $tenantId > 0 && $this->tenantColumnReady()) {
            // Une autre communauté ne lit pas cet historique : les lignes sans communauté (anciennes) ne comptent
            // que depuis l'arrivée du membre dans celle-ci, pour ne pas ramener un séjour précédent.
            $since = (new UserCommunityMembershipRepository())->currentPeriodStart($userId, $tenantId);
            $sql .= ' AND (tenant_id = ?' . ($since !== null ? ' OR (tenant_id IS NULL AND event_date >= ?)' : '') . ')';
            $args[] = $tenantId;
            if ($since !== null) {
                $args[] = substr($since, 0, 10);
            }
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY event_date DESC, id DESC LIMIT ' . max(1, (int) $limit));
        $stmt->execute($args);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add(
        int $userId,
        string $eventType,
        string $title,
        string $description = '',
        string $eventDate = '',
        ?int $createdBy = null,
        ?string $reasonLabel = null,
        ?int $tenantId = null
    ): int
    {
        $date = $eventDate ?: date('Y-m-d');
        $reasonLabel = $this->normalizeReasonLabel($reasonLabel);
        $cols = ['user_id', 'event_type', 'title'];
        $vals = [$userId, $eventType, $title];
        if ($this->tenantColumnReady()) {
            $tid = $tenantId ?? (int) \App\Core\Session::get('tenant_id');
            $cols[] = 'tenant_id';
            $vals[] = $tid > 0 ? $tid : null;
        }
        if ($this->reasonLabelColumnReady()) {
            $cols[] = 'reason_label';
            $vals[] = $reasonLabel;
        }
        array_push($cols, 'description', 'event_date', 'created_by');
        array_push($vals, $description, $date, $createdBy);
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_service_history (' . implode(', ', $cols) . ', created_at) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ', NOW())'
        );
        $stmt->execute($vals);
        return (int) $this->pdo->lastInsertId();
    }

    private ?bool $tenantColumnReady = null;

    private function tenantColumnReady(): bool
    {
        if ($this->tenantColumnReady === null) {
            try {
                $stmt = $this->pdo->query(
                    "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_service_history' AND COLUMN_NAME = 'tenant_id' LIMIT 1"
                );
                $this->tenantColumnReady = $stmt !== false && (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                $this->tenantColumnReady = false;
            }
        }

        return $this->tenantColumnReady;
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
