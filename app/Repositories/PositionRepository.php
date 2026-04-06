<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PositionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function tableExists(): bool
    {
        static $ok;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'positions' LIMIT 1");
            $ok = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT * FROM positions WHERE tenant_id = ? ORDER BY name ASC');
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(int $tenantId, string $name, ?string $description, bool $isTemporary): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO positions (tenant_id, name, description, is_temporary, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $st->execute([$tenantId, mb_substr($name, 0, 160), $description !== null ? mb_substr($description, 0, 500) : null, $isTemporary ? 1 : 0]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $positionId): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $st = $this->pdo->prepare('DELETE FROM positions WHERE id = ? AND tenant_id = ?');

        return $st->execute([$positionId, $tenantId]) && $st->rowCount() > 0;
    }

    public function assignUser(int $tenantId, int $userId, int $positionId, string $startsAt, ?string $endsAt, ?int $assignedBy): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO user_positions (tenant_id, user_id, position_id, starts_at, ends_at, assigned_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );

        return $st->execute([
            $tenantId,
            $userId,
            $positionId,
            $startsAt,
            $endsAt !== null && $endsAt !== '' ? $endsAt : null,
            $assignedBy,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listActiveForUser(int $tenantId, int $userId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT up.*, p.name AS position_name FROM user_positions up
             INNER JOIN positions p ON p.id = up.position_id AND p.tenant_id = up.tenant_id
             WHERE up.tenant_id = ? AND up.user_id = ?
             AND up.starts_at <= CURDATE() AND (up.ends_at IS NULL OR up.ends_at >= CURDATE())
             ORDER BY up.starts_at DESC'
        );
        $st->execute([$tenantId, $userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
