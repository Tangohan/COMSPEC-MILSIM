<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class SeniorityRepository
{
    private function pdo(): PDO
    {
        return Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seniority_definitions' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listVisibleDefinitionsForTenant(int $tenantId): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM seniority_definitions
             WHERE tenant_id = ? AND is_active = 1 AND is_visible = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $st->execute([$tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array{start_date: string, end_date: ?string, status: ?string}>
     */
    public function listPeriodsForUserAndDefinition(int $userId, int $definitionId): array
    {
        if (!$this->schemaReady() || $userId < 1 || $definitionId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT start_date, end_date, status FROM seniority_periods
             WHERE user_id = ? AND definition_id = ?
             ORDER BY start_date ASC'
        );
        $st->execute([$userId, $definitionId]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'start_date' => (string) ($row['start_date'] ?? ''),
                'end_date' => isset($row['end_date']) && $row['end_date'] !== null ? (string) $row['end_date'] : null,
                'status' => isset($row['status']) ? (string) $row['status'] : null,
            ];
        }

        return $out;
    }
}
