<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\CommunityMediaDetails;
use PDO;

final class CommunityMediaRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tablesExist(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_media_items' LIMIT 1"
            );

            return (bool) $st?->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function likesTableExists(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_media_likes' LIMIT 1"
            );

            return (bool) $st?->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listCollections(int $tenantId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM community_media_items i WHERE i.collection_id = c.id AND i.tenant_id = c.tenant_id) AS items_count
             FROM community_media_collections c
             WHERE c.tenant_id = ?
             ORDER BY c.sort_order ASC, c.id DESC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listPublicCollections(int $tenantId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM community_media_collections
             WHERE tenant_id = ? AND is_public = 1
             ORDER BY sort_order ASC, id DESC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findCollection(int $id, int $tenantId): ?array
    {
        if (!$this->tablesExist()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM community_media_collections WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function createCollection(int $tenantId, array $data, ?int $createdBy): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO community_media_collections (tenant_id, title, description, is_public, sort_order, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            trim((string) ($data['title'] ?? '')),
            ($data['description'] ?? null) !== null && trim((string) $data['description']) !== ''
                ? trim((string) $data['description'])
                : null,
            !empty($data['is_public']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
            $createdBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateCollection(int $id, int $tenantId, array $data): void
    {
        $st = $this->pdo->prepare(
            'UPDATE community_media_collections
             SET title = ?, description = ?, is_public = ?, sort_order = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );
        $st->execute([
            trim((string) ($data['title'] ?? '')),
            ($data['description'] ?? null) !== null && trim((string) $data['description']) !== ''
                ? trim((string) $data['description'])
                : null,
            !empty($data['is_public']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
            $id,
            $tenantId,
        ]);
    }

    public function deleteCollection(int $id, int $tenantId): void
    {
        $st = $this->pdo->prepare(
            'UPDATE community_media_items SET collection_id = NULL WHERE collection_id = ? AND tenant_id = ?'
        );
        $st->execute([$id, $tenantId]);
        $del = $this->pdo->prepare('DELETE FROM community_media_collections WHERE id = ? AND tenant_id = ?');
        $del->execute([$id, $tenantId]);
    }

    /** @return list<array<string, mixed>> */
    public function listItems(int $tenantId, ?int $collectionId = null): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        if ($collectionId !== null) {
            $st = $this->pdo->prepare(
                'SELECT * FROM community_media_items
                 WHERE tenant_id = ? AND collection_id = ?
                 ORDER BY sort_order ASC, id DESC'
            );
            $st->execute([$tenantId, $collectionId]);
        } else {
            $st = $this->pdo->prepare(
                'SELECT * FROM community_media_items
                 WHERE tenant_id = ?
                 ORDER BY sort_order ASC, id DESC'
            );
            $st->execute([$tenantId]);
        }

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listPublicPageItems(int $tenantId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM community_media_items
             WHERE tenant_id = ?
               AND show_on_public_page = 1
               AND status = ?
             ORDER BY is_hero DESC, sort_order ASC, id DESC'
        );
        $st->execute([$tenantId, CommunityMediaDetails::STATUS_PUBLISHED]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Fil vertical « Reels » : vidéos courtes d’abord, puis images, puis vidéos longues.
     *
     * @return list<array<string, mixed>>
     */
    public function listReelsFeedItems(int $tenantId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM community_media_items
             WHERE tenant_id = ?
               AND show_on_public_page = 1
               AND status = ?
             ORDER BY
               CASE media_kind
                 WHEN ? THEN 0
                 WHEN ? THEN 1
                 ELSE 2
               END ASC,
               is_hero DESC,
               sort_order ASC,
               id DESC'
        );
        $st->execute([
            $tenantId,
            CommunityMediaDetails::STATUS_PUBLISHED,
            CommunityMediaDetails::KIND_SHORT_VIDEO,
            CommunityMediaDetails::KIND_IMAGE,
        ]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Médias publics enrichis avec compteurs et état « j’aime » (si table présente).
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public function attachLikeState(array $items, ?int $userId = null): array
    {
        if ($items === []) {
            return [];
        }
        $ids = [];
        foreach ($items as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $counts = $this->countLikesByItemIds($ids);
        $liked = $userId !== null && $userId > 0
            ? $this->likedItemIdsForUser($userId, $ids)
            : [];

        foreach ($items as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $row['likes_count'] = (int) ($counts[$id] ?? 0);
            $row['liked_by_viewer'] = isset($liked[$id]);
        }
        unset($row);

        return $items;
    }

    /** @return array<string, mixed>|null */
    public function findPublishedPublicItem(int $id, int $tenantId): ?array
    {
        if (!$this->tablesExist() || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM community_media_items
             WHERE id = ? AND tenant_id = ?
               AND show_on_public_page = 1
               AND status = ?
             LIMIT 1'
        );
        $st->execute([$id, $tenantId, CommunityMediaDetails::STATUS_PUBLISHED]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param list<int> $itemIds
     * @return array<int, int> media_item_id => count
     */
    public function countLikesByItemIds(array $itemIds): array
    {
        if (!$this->likesTableExists() || $itemIds === []) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare(
            "SELECT media_item_id, COUNT(*) AS c
             FROM community_media_likes
             WHERE media_item_id IN ({$placeholders})
             GROUP BY media_item_id"
        );
        $st->execute($ids);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $row['media_item_id']] = (int) $row['c'];
        }

        return $out;
    }

    public function countLikes(int $mediaItemId): int
    {
        if (!$this->likesTableExists() || $mediaItemId < 1) {
            return 0;
        }
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM community_media_likes WHERE media_item_id = ?');
        $st->execute([$mediaItemId]);

        return (int) $st->fetchColumn();
    }

    /**
     * @param list<int> $itemIds
     * @return array<int, true> media_item_id => true
     */
    public function likedItemIdsForUser(int $userId, array $itemIds): array
    {
        if (!$this->likesTableExists() || $userId < 1 || $itemIds === []) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$userId], $ids);
        $st = $this->pdo->prepare(
            "SELECT media_item_id FROM community_media_likes
             WHERE user_id = ? AND media_item_id IN ({$placeholders})"
        );
        $st->execute($params);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $row['media_item_id']] = true;
        }

        return $out;
    }

    public function isLiked(int $userId, int $mediaItemId): bool
    {
        if (!$this->likesTableExists() || $userId < 1 || $mediaItemId < 1) {
            return false;
        }
        $st = $this->pdo->prepare(
            'SELECT 1 FROM community_media_likes WHERE user_id = ? AND media_item_id = ? LIMIT 1'
        );
        $st->execute([$userId, $mediaItemId]);

        return (bool) $st->fetchColumn();
    }

    public function setLike(int $tenantId, int $userId, int $mediaItemId, bool $on): void
    {
        if (!$this->likesTableExists() || $tenantId < 1 || $userId < 1 || $mediaItemId < 1) {
            return;
        }
        if ($on) {
            $st = $this->pdo->prepare(
                'INSERT IGNORE INTO community_media_likes (tenant_id, media_item_id, user_id) VALUES (?, ?, ?)'
            );
            $st->execute([$tenantId, $mediaItemId, $userId]);
        } else {
            $st = $this->pdo->prepare(
                'DELETE FROM community_media_likes WHERE user_id = ? AND media_item_id = ?'
            );
            $st->execute([$userId, $mediaItemId]);
        }
    }

    /** @return array<string, mixed>|null */
    public function findItem(int $id, int $tenantId): ?array
    {
        if (!$this->tablesExist()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM community_media_items WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function createItem(int $tenantId, array $data, ?int $createdBy): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO community_media_items (
                tenant_id, collection_id, media_kind, title, caption, storage_path, external_url,
                mime_type, file_size, duration_seconds, width, height, blur_mode, blur_regions_json,
                show_on_public_page, is_hero, sort_order, status, created_by
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
             )'
        );
        $st->execute([
            $tenantId,
            isset($data['collection_id']) && (int) $data['collection_id'] > 0 ? (int) $data['collection_id'] : null,
            (string) ($data['media_kind'] ?? CommunityMediaDetails::KIND_IMAGE),
            trim((string) ($data['title'] ?? '')),
            ($data['caption'] ?? null) !== null && trim((string) $data['caption']) !== ''
                ? trim((string) $data['caption'])
                : null,
            $data['storage_path'] ?? null,
            $data['external_url'] ?? null,
            $data['mime_type'] ?? null,
            isset($data['file_size']) ? (int) $data['file_size'] : null,
            isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            isset($data['width']) ? (int) $data['width'] : null,
            isset($data['height']) ? (int) $data['height'] : null,
            (string) ($data['blur_mode'] ?? CommunityMediaDetails::BLUR_NONE),
            $data['blur_regions_json'] ?? null,
            !empty($data['show_on_public_page']) ? 1 : 0,
            !empty($data['is_hero']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
            (string) ($data['status'] ?? CommunityMediaDetails::STATUS_DRAFT),
            $createdBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateItem(int $id, int $tenantId, array $data): void
    {
        $existing = $this->findItem($id, $tenantId);
        if ($existing === null) {
            return;
        }
        $merged = array_merge($existing, $data);
        $st = $this->pdo->prepare(
            'UPDATE community_media_items SET
                collection_id = ?, media_kind = ?, title = ?, caption = ?, storage_path = ?, external_url = ?,
                mime_type = ?, file_size = ?, duration_seconds = ?, width = ?, height = ?,
                blur_mode = ?, blur_regions_json = ?, show_on_public_page = ?, is_hero = ?,
                sort_order = ?, status = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );
        $st->execute([
            isset($merged['collection_id']) && (int) $merged['collection_id'] > 0 ? (int) $merged['collection_id'] : null,
            (string) ($merged['media_kind'] ?? CommunityMediaDetails::KIND_IMAGE),
            trim((string) ($merged['title'] ?? '')),
            ($merged['caption'] ?? null) !== null && trim((string) $merged['caption']) !== ''
                ? trim((string) $merged['caption'])
                : null,
            $merged['storage_path'] ?? null,
            $merged['external_url'] ?? null,
            $merged['mime_type'] ?? null,
            isset($merged['file_size']) ? (int) $merged['file_size'] : null,
            isset($merged['duration_seconds']) ? (int) $merged['duration_seconds'] : null,
            isset($merged['width']) ? (int) $merged['width'] : null,
            isset($merged['height']) ? (int) $merged['height'] : null,
            (string) ($merged['blur_mode'] ?? CommunityMediaDetails::BLUR_NONE),
            $merged['blur_regions_json'] ?? null,
            !empty($merged['show_on_public_page']) ? 1 : 0,
            !empty($merged['is_hero']) ? 1 : 0,
            (int) ($merged['sort_order'] ?? 0),
            (string) ($merged['status'] ?? CommunityMediaDetails::STATUS_DRAFT),
            $id,
            $tenantId,
        ]);
    }

    public function deleteItem(int $id, int $tenantId): ?string
    {
        $item = $this->findItem($id, $tenantId);
        if ($item === null) {
            return null;
        }
        $path = isset($item['storage_path']) ? (string) $item['storage_path'] : null;
        if ($this->likesTableExists()) {
            $delLikes = $this->pdo->prepare(
                'DELETE FROM community_media_likes WHERE media_item_id = ? AND tenant_id = ?'
            );
            $delLikes->execute([$id, $tenantId]);
        }
        $st = $this->pdo->prepare('DELETE FROM community_media_items WHERE id = ? AND tenant_id = ?');
        $st->execute([$id, $tenantId]);

        return $path;
    }
}
