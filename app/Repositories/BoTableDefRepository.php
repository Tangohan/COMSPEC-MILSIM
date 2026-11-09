<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Tableaux de données enregistrés (bo_table_defs) : la définition seulement, jamais les données. Toute lecture et
 * écriture filtre sur la communauté ; seul l'auteur modifie ou supprime ; un tableau partagé est lisible par la communauté
 * mais ses données sont recalculées dans les droits de la personne qui l'ouvre.
 */
class BoTableDefRepository
{
    public const MAX_PER_OWNER = 30;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** @return array{id: int, tenant_id: int, owner_user_id: int, title: string, source: string, def: array<string, mixed>, shared: bool, updated_at: string}|null */
    public function find(int $tenantId, int $id): ?array
    {
        if ($tenantId < 1 || $id < 1) {
            return null;
        }
        try {
            $st = $this->pdo()->prepare('SELECT * FROM bo_table_defs WHERE id = ? AND tenant_id = ? LIMIT 1');
            $st->execute([$id, $tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($row)) {
            return null;
        }
        $def = json_decode((string) $row['def_json'], true);

        return [
            'id' => (int) $row['id'],
            'tenant_id' => (int) $row['tenant_id'],
            'owner_user_id' => (int) $row['owner_user_id'],
            'title' => (string) $row['title'],
            'source' => (string) $row['source'],
            'def' => is_array($def) ? $def : [],
            'shared' => (int) $row['shared'] === 1,
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @return list<array{id: int, title: string, source: string, shared: bool, mine: bool, updated_at: string}> */
    public function listReadable(int $tenantId, int $userId): array
    {
        if ($tenantId < 1 || $userId < 1) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT id, owner_user_id, title, source, shared, updated_at FROM bo_table_defs
                 WHERE tenant_id = ? AND (owner_user_id = ? OR shared = 1)
                 ORDER BY updated_at DESC, id DESC LIMIT 100'
            );
            $st->execute([$tenantId, $userId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'title' => (string) $r['title'],
            'source' => (string) $r['source'],
            'shared' => (int) $r['shared'] === 1,
            'mine' => (int) $r['owner_user_id'] === $userId,
            'updated_at' => (string) $r['updated_at'],
        ], $rows);
    }

    public function countOwned(int $tenantId, int $userId): int
    {
        $st = $this->pdo()->prepare('SELECT COUNT(*) FROM bo_table_defs WHERE tenant_id = ? AND owner_user_id = ?');
        $st->execute([$tenantId, $userId]);

        return (int) $st->fetchColumn();
    }

    /** @param array<string, mixed> $def */
    public function create(int $tenantId, int $userId, string $title, string $source, array $def, bool $shared): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO bo_table_defs (tenant_id, owner_user_id, title, source, def_json, shared, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $st->execute([$tenantId, $userId, self::cleanTitle($title), $source, self::encode($def), $shared ? 1 : 0]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $def */
    public function update(int $tenantId, int $id, int $ownerId, string $title, string $source, array $def, bool $shared): bool
    {
        $st = $this->pdo()->prepare(
            'UPDATE bo_table_defs SET title = ?, source = ?, def_json = ?, shared = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND owner_user_id = ?'
        );
        $st->execute([self::cleanTitle($title), $source, self::encode($def), $shared ? 1 : 0, $id, $tenantId, $ownerId]);

        return $st->rowCount() === 1;
    }

    public function delete(int $tenantId, int $id, int $ownerId): void
    {
        $st = $this->pdo()->prepare('DELETE FROM bo_table_defs WHERE id = ? AND tenant_id = ? AND owner_user_id = ?');
        $st->execute([$id, $tenantId, $ownerId]);
    }

    public static function cleanTitle(string $title): string
    {
        $t = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $title) ?? '';
        $t = mb_substr(trim($t), 0, 80, 'UTF-8');

        return $t !== '' ? $t : 'Tableau sans titre';
    }

    /** @param array<string, mixed> $def */
    private static function encode(array $def): string
    {
        return (string) json_encode($def, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
