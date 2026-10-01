<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class DutyRosterRepository
{
    private function pdo(): PDO
    {
        return Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute(['duty_roster_slots']);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCurrentHolder(int $tenantId, string $positionSlug): ?array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return null;
        }
        $slug = strtolower(trim($positionSlug));
        if ($slug === '') {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM duty_roster_slots
             WHERE tenant_id = ? AND position_slug = ?
               AND starts_at <= NOW() AND ends_at > NOW()
             ORDER BY starts_at DESC
             LIMIT 1'
        );
        $st->execute([$tenantId, $slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<string>
     */
    public function currentPositionSlugsForUser(int $tenantId, int $userId): array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT DISTINCT position_slug FROM duty_roster_slots
             WHERE tenant_id = ? AND user_id = ?
               AND starts_at <= NOW() AND ends_at > NOW()'
        );
        $st->execute([$tenantId, $userId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $slug = strtolower(trim((string) ($row['position_slug'] ?? '')));
            if ($slug !== '') {
                $out[] = $slug;
            }
        }

        return $out;
    }
}
