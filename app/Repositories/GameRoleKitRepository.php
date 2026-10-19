<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Kits proposés par rôle (fonction / emploi de la communauté). Chaque kit est publié comme tenue d’arsenal.
 */
final class GameRoleKitRepository
{
    use LazyDatabaseConnection;

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function tableReady(): bool
    {
        if (self::$ready === true) {
            return true;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_role_kits' LIMIT 1"
            );
            $ok = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$ready = true;
        }

        return $ok;
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM game_role_kits WHERE tenant_id = ? ORDER BY job_role_id IS NOT NULL, name ASC, id ASC'
        );
        $st->execute([$tenantId]);

        return array_map([$this, 'map'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Kits pour ces rôles, plus les kits « tous les rôles ».
     *
     * @param list<int> $roleIds
     * @return list<array<string, mixed>>
     */
    public function listForRoles(int $tenantId, array $roleIds): array
    {
        if (!$this->tableReady()) {
            return [];
        }
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $id): bool => $id > 0)));
        $sql = 'SELECT * FROM game_role_kits WHERE tenant_id = ? AND (job_role_id IS NULL';
        $params = [$tenantId];
        if ($roleIds !== []) {
            $sql .= ' OR job_role_id IN (' . implode(', ', array_fill(0, count($roleIds), '?')) . ')';
            $params = array_merge($params, $roleIds);
        }
        $sql .= ') ORDER BY job_role_id IS NULL, name ASC, id ASC';
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return array_map([$this, 'map'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        if ($id < 1 || !$this->tableReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM game_role_kits WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->map($row) : null;
    }

    /**
     * @param array{job_role_id: int|null, name: string, description: string|null, slots: array<string, mixed>, payload_text: string, required_mods: list<string>} $data
     */
    public function create(int $tenantId, int $userId, array $data): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO game_role_kits
                (tenant_id, job_role_id, name, description, slots_json, payload_text, required_mods_json, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $st->execute([
            $tenantId,
            $data['job_role_id'],
            $data['name'],
            $data['description'],
            self::json($data['slots']),
            $data['payload_text'],
            self::json($data['required_mods']),
            $userId > 0 ? $userId : null,
            $userId > 0 ? $userId : null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array{job_role_id: int|null, name: string, description: string|null, slots: array<string, mixed>, payload_text: string, required_mods: list<string>} $data
     */
    public function update(int $tenantId, int $id, int $userId, array $data): bool
    {
        $st = $this->pdo()->prepare(
            'UPDATE game_role_kits SET job_role_id = ?, name = ?, description = ?, slots_json = ?, payload_text = ?,
                    required_mods_json = ?, updated_by = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([
            $data['job_role_id'],
            $data['name'],
            $data['description'],
            self::json($data['slots']),
            $data['payload_text'],
            self::json($data['required_mods']),
            $userId > 0 ? $userId : null,
            $tenantId,
            $id,
        ]);

        return $st->rowCount() > 0;
    }

    public function setWardrobe(int $tenantId, int $id, ?int $wardrobeId): void
    {
        $this->pdo()->prepare('UPDATE game_role_kits SET wardrobe_id = ? WHERE tenant_id = ? AND id = ?')
            ->execute([$wardrobeId !== null && $wardrobeId > 0 ? $wardrobeId : null, $tenantId, $id]);
    }

    public function delete(int $tenantId, int $id): bool
    {
        $st = $this->pdo()->prepare('DELETE FROM game_role_kits WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }

    private static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function map(array $row): array
    {
        $slots = json_decode((string) ($row['slots_json'] ?? ''), true);
        $mods = json_decode((string) ($row['required_mods_json'] ?? ''), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'job_role_id' => isset($row['job_role_id']) && (int) $row['job_role_id'] > 0 ? (int) $row['job_role_id'] : null,
            'name' => (string) ($row['name'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'slots' => is_array($slots) ? $slots : [],
            'payload_text' => (string) ($row['payload_text'] ?? ''),
            'required_mods' => is_array($mods) ? array_values(array_map('strval', $mods)) : [],
            'wardrobe_id' => isset($row['wardrobe_id']) && (int) $row['wardrobe_id'] > 0 ? (int) $row['wardrobe_id'] : null,
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
