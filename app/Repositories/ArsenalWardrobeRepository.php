<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\EquipmentCoverStorage;
use App\Support\SilentSchemaMigration;
use PDO;

final class ArsenalWardrobeRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tablesReady(): bool
    {
        try {
            SilentSchemaMigration::run(base_path('bootstrap/arsenal_wardrobe_migration.php'), $this->pdo);
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'arsenal_wardrobes' LIMIT 1"
            );

            return (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    public static function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? 'wardrobe';
        $slug = trim($slug, '-');

        return $slug !== '' ? substr($slug, 0, 120) : 'wardrobe';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listWardrobesForUser(int $tenantId, int $userId, ?int $collectionId = null): array
    {
        $sql = 'SELECT w.*, c.name AS collection_name, c.slug AS collection_slug
                FROM arsenal_wardrobes w
                LEFT JOIN arsenal_equipment_collections c ON c.id = w.collection_id
                WHERE w.tenant_id = ? AND w.user_id = ?';
        $params = [$tenantId, $userId];
        if ($collectionId !== null && $collectionId > 0) {
            $sql .= ' AND w.collection_id = ?';
            $params[] = $collectionId;
        }
        $sql .= ' ORDER BY w.is_favorite DESC, w.name ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return array_map([$this, 'mapWardrobe'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Tenues de toute la communauté (même locataire), avec le nom du membre.
     *
     * @return list<array<string, mixed>>
     */
    public function listAccessibleWardrobes(int $tenantId, int $userId): array
    {
        $st = $this->pdo->prepare(
            'SELECT w.id, w.tenant_id, w.user_id, w.steam_uid, w.collection_id, w.name, w.slug,
                    w.source, w.payload_format, w.payload_sha256, CHAR_LENGTH(w.payload_text) AS payload_bytes,
                    w.notes, w.cover_image_path, w.is_favorite, w.last_synced_at, w.created_at, w.updated_at,
                    c.name AS collection_name, c.slug AS collection_slug, c.visibility AS collection_visibility,
                    COALESCE(NULLIF(TRIM(u.callsign), \'\'), NULLIF(TRIM(u.display_name), \'\'), \'Membre\') AS owner_label
             FROM arsenal_wardrobes w
             LEFT JOIN arsenal_equipment_collections c ON c.id = w.collection_id
             LEFT JOIN users u ON u.id = w.user_id
             WHERE w.tenant_id = ?
             ORDER BY w.is_favorite DESC, w.name ASC, w.id ASC'
        );
        $st->execute([$tenantId]);

        $rows = array_map([$this, 'mapWardrobe'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
        foreach ($rows as &$row) {
            $row['mine'] = (int) ($row['user_id'] ?? 0) === $userId;
        }
        unset($row);

        return $rows;
    }

    public function findWardrobe(int $tenantId, int $id, ?int $userId = null): ?array
    {
        $sql = 'SELECT w.*, c.name AS collection_name, c.slug AS collection_slug
                FROM arsenal_wardrobes w
                LEFT JOIN arsenal_equipment_collections c ON c.id = w.collection_id
                WHERE w.tenant_id = ? AND w.id = ?';
        $params = [$tenantId, $id];
        if ($userId !== null) {
            $sql .= ' AND w.user_id = ?';
            $params[] = $userId;
        }
        $st = $this->pdo->prepare($sql . ' LIMIT 1');
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapWardrobe($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upsertWardrobe(int $tenantId, int $userId, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = 'Sans nom';
        }
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = self::slugify($name);
        } else {
            $slug = self::slugify($slug);
        }
        $payload = (string) ($data['payload_text'] ?? $data['payload'] ?? '');
        if ($payload === '') {
            throw new \InvalidArgumentException('payload_required');
        }
        if (strlen($payload) > 512000) {
            throw new \InvalidArgumentException('payload_too_large');
        }
        $hash = hash('sha256', $payload);
        $collectionId = isset($data['collection_id']) ? (int) $data['collection_id'] : 0;
        if ($collectionId < 1) {
            $collectionId = null;
        }
        $source = substr(trim((string) ($data['source'] ?? 'ace_arsenal')), 0, 40) ?: 'ace_arsenal';
        $format = substr(trim((string) ($data['payload_format'] ?? 'arma_loadout_str')), 0, 24) ?: 'arma_loadout_str';
        $notes = trim((string) ($data['notes'] ?? ''));
        $notes = $notes !== '' ? substr($notes, 0, 255) : null;
        $favorite = !empty($data['is_favorite']) ? 1 : 0;
        $steam = trim((string) ($data['steam_uid'] ?? ''));
        $steam = $steam !== '' ? substr($steam, 0, 32) : null;

        $existing = $this->findBySlug($tenantId, $userId, $slug);
        if ($existing !== null) {
            $st = $this->pdo->prepare(
                'UPDATE arsenal_wardrobes SET
                    name = ?, collection_id = ?, source = ?, payload_format = ?,
                    payload_text = ?, payload_sha256 = ?, notes = ?, is_favorite = ?,
                    steam_uid = COALESCE(?, steam_uid), last_synced_at = NOW(), updated_at = NOW()
                 WHERE id = ? AND tenant_id = ? AND user_id = ?'
            );
            $st->execute([
                $name,
                $collectionId,
                $source,
                $format,
                $payload,
                $hash,
                $notes,
                $favorite,
                $steam,
                (int) $existing['id'],
                $tenantId,
                $userId,
            ]);

            return $this->findWardrobe($tenantId, (int) $existing['id'], $userId) ?? $existing;
        }

        $st = $this->pdo->prepare(
            'INSERT INTO arsenal_wardrobes
                (tenant_id, user_id, steam_uid, collection_id, name, slug, source, payload_format,
                 payload_text, payload_sha256, notes, is_favorite, last_synced_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())'
        );
        $st->execute([
            $tenantId,
            $userId,
            $steam,
            $collectionId,
            $name,
            $slug,
            $source,
            $format,
            $payload,
            $hash,
            $notes,
            $favorite,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return $this->findWardrobe($tenantId, $id, $userId) ?? [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array{saved:int, wardrobes:list<array<string, mixed>>}
     */
    public function upsertMany(int $tenantId, int $userId, array $items): array
    {
        $saved = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            try {
                $saved[] = $this->upsertWardrobe($tenantId, $userId, $item);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return ['saved' => count($saved), 'wardrobes' => $saved];
    }

    public function deleteWardrobe(int $tenantId, int $userId, int $id): bool
    {
        $st = $this->pdo->prepare(
            'DELETE FROM arsenal_wardrobes WHERE tenant_id = ? AND user_id = ? AND id = ?'
        );
        $st->execute([$tenantId, $userId, $id]);

        return $st->rowCount() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCollections(int $tenantId, int $userId): array
    {
        $st = $this->pdo->prepare(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM arsenal_wardrobes w WHERE w.collection_id = c.id) AS wardrobe_count
             FROM arsenal_equipment_collections c
             WHERE c.tenant_id = ?
               AND (c.owner_user_id = ? OR c.owner_user_id IS NULL OR c.visibility IN (\'unit\', \'tenant\'))
             ORDER BY c.sort_order ASC, c.name ASC'
        );
        $st->execute([$tenantId, $userId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row) use ($userId): array {
            $tags = $row['tags_json'] ?? null;
            if (is_string($tags) && $tags !== '') {
                $decoded = json_decode($tags, true);
                $row['tags'] = is_array($decoded) ? $decoded : [];
            } else {
                $row['tags'] = [];
            }
            unset($row['tags_json']);
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['wardrobe_count'] = (int) ($row['wardrobe_count'] ?? 0);
            $row['cover_url'] = EquipmentCoverStorage::publicUrl(
                isset($row['cover_image_path']) ? (string) $row['cover_image_path'] : null
            );
            $row['mine'] = (int) ($row['owner_user_id'] ?? 0) === $userId;

            return $row;
        }, $rows);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upsertCollection(int $tenantId, int $userId, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $slug = trim((string) ($data['slug'] ?? ''));
        $slug = $slug !== '' ? self::slugify($slug) : self::slugify($name);
        $description = trim((string) ($data['description'] ?? ''));
        $description = $description !== '' ? substr($description, 0, 500) : null;
        $visibility = strtolower(trim((string) ($data['visibility'] ?? 'personal')));
        if (!in_array($visibility, ['personal', 'unit', 'tenant'], true)) {
            $visibility = 'personal';
        }
        $tags = $data['tags'] ?? [];
        if (!is_array($tags)) {
            $tags = [];
        }
        $tagsJson = json_encode(array_values(array_map('strval', $tags)), JSON_UNESCAPED_UNICODE);
        $sort = (int) ($data['sort_order'] ?? 0);
        $id = (int) ($data['id'] ?? 0);

        if ($id > 0) {
            $st = $this->pdo->prepare(
                'UPDATE arsenal_equipment_collections
                 SET name = ?, slug = ?, description = ?, visibility = ?, tags_json = ?, sort_order = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ? AND (owner_user_id = ? OR owner_user_id IS NULL)'
            );
            $st->execute([$name, $slug, $description, $visibility, $tagsJson, $sort, $id, $tenantId, $userId]);
        } else {
            $st = $this->pdo->prepare(
                'INSERT INTO arsenal_equipment_collections
                    (tenant_id, owner_user_id, name, slug, description, visibility, tags_json, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    visibility = VALUES(visibility),
                    tags_json = VALUES(tags_json),
                    sort_order = VALUES(sort_order),
                    updated_at = NOW()'
            );
            $st->execute([$tenantId, $userId, $name, $slug, $description, $visibility, $tagsJson, $sort]);
            $id = (int) $this->pdo->lastInsertId();
            if ($id < 1) {
                $found = $this->findCollectionBySlug($tenantId, $slug);
                $id = (int) ($found['id'] ?? 0);
            }
        }

        $wardrobeIds = $data['wardrobe_ids'] ?? null;
        if (is_array($wardrobeIds)) {
            $this->setCollectionWardrobes($tenantId, $userId, $id, $wardrobeIds);
        }

        return $this->findCollection($tenantId, $id) ?? [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
        ];
    }

    public function deleteCollection(int $tenantId, int $userId, int $id): bool
    {
        $st = $this->pdo->prepare(
            'DELETE FROM arsenal_equipment_collections
             WHERE tenant_id = ? AND id = ? AND (owner_user_id = ? OR owner_user_id IS NULL)'
        );
        $st->execute([$tenantId, $id, $userId]);

        return $st->rowCount() > 0;
    }

    public function findCollection(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM arsenal_equipment_collections WHERE tenant_id = ? AND id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $tags = $row['tags_json'] ?? null;
        if (is_string($tags) && $tags !== '') {
            $decoded = json_decode($tags, true);
            $row['tags'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['tags'] = [];
        }
        unset($row['tags_json']);
        $row['id'] = (int) $row['id'];
        $row['cover_url'] = EquipmentCoverStorage::publicUrl(
            isset($row['cover_image_path']) ? (string) $row['cover_image_path'] : null
        );

        return $row;
    }

    public function findCollectionBySlug(int $tenantId, string $slug): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM arsenal_equipment_collections WHERE tenant_id = ? AND slug = ? LIMIT 1'
        );
        $st->execute([$tenantId, $slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param list<int|string> $wardrobeIds
     */
    public function setCollectionWardrobes(int $tenantId, int $userId, int $collectionId, array $wardrobeIds): void
    {
        $ids = [];
        foreach ($wardrobeIds as $wid) {
            $wid = (int) $wid;
            if ($wid > 0) {
                $ids[$wid] = $wid;
            }
        }
        $ids = array_values($ids);

        $clearSql = 'UPDATE arsenal_wardrobes SET collection_id = NULL, updated_at = NOW()
                     WHERE tenant_id = ? AND user_id = ? AND collection_id = ?';
        $clearParams = [$tenantId, $userId, $collectionId];
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $clearSql .= ' AND id NOT IN (' . $placeholders . ')';
            $clearParams = array_merge($clearParams, $ids);
        }
        $this->pdo->prepare($clearSql)->execute($clearParams);

        $this->pdo->prepare(
            'DELETE cw FROM arsenal_collection_wardrobes cw
             INNER JOIN arsenal_wardrobes w ON w.id = cw.wardrobe_id
             WHERE cw.collection_id = ? AND w.tenant_id = ? AND w.user_id = ?'
        )->execute([$collectionId, $tenantId, $userId]);

        if ($ids === []) {
            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO arsenal_collection_wardrobes (collection_id, wardrobe_id, sort_order)
             SELECT ?, w.id, ? FROM arsenal_wardrobes w
             WHERE w.tenant_id = ? AND w.user_id = ? AND w.id = ?'
        );
        $assign = $this->pdo->prepare(
            'UPDATE arsenal_wardrobes SET collection_id = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND user_id = ?'
        );
        foreach ($ids as $order => $wid) {
            $ins->execute([$collectionId, $order, $tenantId, $userId, $wid]);
            $assign->execute([$collectionId, $wid, $tenantId, $userId]);
        }
    }

    public function setCollectionCover(int $tenantId, int $userId, int $id, ?string $path): bool
    {
        $current = $this->findCollection($tenantId, $id);
        if ($current === null) {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE arsenal_equipment_collections
             SET cover_image_path = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND (owner_user_id = ? OR owner_user_id IS NULL)'
        );
        $st->execute([$path, $id, $tenantId, $userId]);
        if ($st->rowCount() > 0 && $path !== ($current['cover_image_path'] ?? null)) {
            EquipmentCoverStorage::delete(isset($current['cover_image_path']) ? (string) $current['cover_image_path'] : null);
        }

        return $st->rowCount() > 0;
    }

    public function setWardrobeCover(int $tenantId, int $userId, int $id, ?string $path): bool
    {
        $current = $this->findWardrobe($tenantId, $id, $userId);
        if ($current === null) {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE arsenal_wardrobes SET cover_image_path = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND user_id = ?'
        );
        $st->execute([$path, $id, $tenantId, $userId]);
        if ($st->rowCount() > 0) {
            EquipmentCoverStorage::delete(isset($current['cover_image_path']) ? (string) $current['cover_image_path'] : null);
        }

        return $st->rowCount() > 0;
    }

    public function assignWardrobeCollection(int $tenantId, int $userId, int $wardrobeId, ?int $collectionId): bool
    {
        if ($collectionId !== null && $collectionId > 0) {
            if ($this->findCollection($tenantId, $collectionId) === null) {
                return false;
            }
        } else {
            $collectionId = null;
        }
        $st = $this->pdo->prepare(
            'UPDATE arsenal_wardrobes SET collection_id = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND user_id = ?'
        );
        $st->execute([$collectionId, $wardrobeId, $tenantId, $userId]);
        if ($st->rowCount() < 1) {
            return false;
        }
        $this->pdo->prepare('DELETE FROM arsenal_collection_wardrobes WHERE wardrobe_id = ?')
            ->execute([$wardrobeId]);
        if ($collectionId !== null) {
            $this->pdo->prepare(
                'INSERT INTO arsenal_collection_wardrobes (collection_id, wardrobe_id, sort_order)
                 VALUES (?, ?, 0)'
            )->execute([$collectionId, $wardrobeId]);
        }

        return true;
    }

    public function updateWardrobeNotes(int $tenantId, int $userId, int $id, ?string $notes): bool
    {
        $notes = $notes !== null && $notes !== '' ? substr($notes, 0, 255) : null;
        $st = $this->pdo->prepare(
            'UPDATE arsenal_wardrobes SET notes = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND user_id = ?'
        );
        $st->execute([$notes, $id, $tenantId, $userId]);

        return $st->rowCount() > 0;
    }

    private function findBySlug(int $tenantId, int $userId, string $slug): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM arsenal_wardrobes WHERE tenant_id = ? AND user_id = ? AND slug = ? LIMIT 1'
        );
        $st->execute([$tenantId, $userId, $slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapWardrobe($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapWardrobe(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['user_id'] = (int) ($row['user_id'] ?? 0);
        $row['tenant_id'] = (int) ($row['tenant_id'] ?? 0);
        $row['collection_id'] = isset($row['collection_id']) && $row['collection_id'] !== null
            ? (int) $row['collection_id']
            : null;
        $row['is_favorite'] = !empty($row['is_favorite']);
        $row['payload_bytes'] = isset($row['payload_bytes'])
            ? (int) $row['payload_bytes']
            : (isset($row['payload_text']) ? strlen((string) $row['payload_text']) : 0);
        $row['owner_label'] = trim((string) ($row['owner_label'] ?? ''));
        $row['mine'] = !empty($row['mine']);
        $row['cover_url'] = EquipmentCoverStorage::publicUrl(
            isset($row['cover_image_path']) ? (string) $row['cover_image_path'] : null
        );

        return $row;
    }
}
