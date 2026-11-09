<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\BackOffice\Home\SheetData;
use PDO;

/**
 * Tableaux de l'accueil du back-office (bo_sheets). Toute lecture et écriture filtre sur la communauté ; l'écriture
 * passe par un verrou optimiste (colonne version) pour détecter deux modifications simultanées.
 */
class BoSheetRepository
{
    public const MAX_PER_OWNER = 20;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @return array{id: int, tenant_id: int, owner_user_id: int, title: string, data: array{cols: list<array{name: string}>, rows: list<list<string>>}, shared: bool, version: int, updated_at: string}|null
     */
    public function find(int $tenantId, int $id): ?array
    {
        if ($tenantId < 1 || $id < 1) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM bo_sheets WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::hydrate($row) : null;
    }

    /**
     * Tableaux lisibles : les siens et ceux partagés dans la communauté (sans les données).
     *
     * @return list<array{id: int, title: string, shared: bool, mine: bool, updated_at: string}>
     */
    public function listReadable(int $tenantId, int $userId): array
    {
        if ($tenantId < 1 || $userId < 1) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT id, owner_user_id, title, shared, updated_at FROM bo_sheets
                 WHERE tenant_id = ? AND (owner_user_id = ? OR shared = 1)
                 ORDER BY updated_at DESC, id DESC LIMIT 100'
            );
            $st->execute([$tenantId, $userId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'title' => (string) $r['title'],
                'shared' => (int) $r['shared'] === 1,
                'mine' => (int) $r['owner_user_id'] === $userId,
                'updated_at' => (string) $r['updated_at'],
            ];
        }

        return $out;
    }

    public function countOwned(int $tenantId, int $userId): int
    {
        $st = $this->pdo()->prepare('SELECT COUNT(*) FROM bo_sheets WHERE tenant_id = ? AND owner_user_id = ?');
        $st->execute([$tenantId, $userId]);

        return (int) $st->fetchColumn();
    }

    /** @param array{cols: list<array{name: string}>, rows: list<list<string>>} $data */
    public function create(int $tenantId, int $userId, string $title, array $data): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO bo_sheets (tenant_id, owner_user_id, title, data_json, shared, version, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, 1, NOW(), NOW())'
        );
        $st->execute([$tenantId, $userId, SheetData::cleanTitle($title), self::encode($data)]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Écrit les données si personne n'a écrit depuis la version lue. Faux : quelqu'un est passé avant.
     *
     * @param array{cols: list<array{name: string}>, rows: list<list<string>>} $data
     */
    public function saveData(int $tenantId, int $id, int $ownerId, array $data, int $expectedVersion): bool
    {
        $st = $this->pdo()->prepare(
            'UPDATE bo_sheets SET data_json = ?, version = version + 1, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND owner_user_id = ? AND version = ?'
        );
        $st->execute([self::encode($data), $id, $tenantId, $ownerId, $expectedVersion]);

        return $st->rowCount() === 1;
    }

    public function saveMeta(int $tenantId, int $id, int $ownerId, string $title, bool $shared): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE bo_sheets SET title = ?, shared = ?, version = version + 1, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND owner_user_id = ?'
        );
        $st->execute([SheetData::cleanTitle($title), $shared ? 1 : 0, $id, $tenantId, $ownerId]);
    }

    public function delete(int $tenantId, int $id, int $ownerId): void
    {
        $st = $this->pdo()->prepare('DELETE FROM bo_sheets WHERE id = ? AND tenant_id = ? AND owner_user_id = ?');
        $st->execute([$id, $tenantId, $ownerId]);
    }

    /** @param array{cols: list<array{name: string}>, rows: list<list<string>>} $data */
    private static function encode(array $data): string
    {
        return (string) json_encode(SheetData::sanitize($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, tenant_id: int, owner_user_id: int, title: string, data: array{cols: list<array{name: string}>, rows: list<list<string>>}, shared: bool, version: int, updated_at: string}
     */
    private static function hydrate(array $row): array
    {
        $raw = json_decode((string) ($row['data_json'] ?? ''), true);

        return [
            'id' => (int) $row['id'],
            'tenant_id' => (int) $row['tenant_id'],
            'owner_user_id' => (int) $row['owner_user_id'],
            'title' => (string) $row['title'],
            'data' => SheetData::sanitize($raw),
            'shared' => (int) $row['shared'] === 1,
            'version' => (int) $row['version'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
}
