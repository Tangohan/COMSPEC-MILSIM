<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumPostReactionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_post_reactions' LIMIT 1");

        return (bool) $stmt?->fetchColumn();
    }

    public function getUserReactionKey(int $postId, int $userId): ?string
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT reaction_key FROM forum_post_reactions WHERE post_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$postId, $userId]);
        $v = $stmt->fetchColumn();

        return $v !== false ? (string) $v : null;
    }

    /**
     * @return array<string, int>
     */
    public function countByKeysForPost(int $postId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT reaction_key, COUNT(*) AS c FROM forum_post_reactions WHERE post_id = ? GROUP BY reaction_key');
        $stmt->execute([$postId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['reaction_key']] = (int) $row['c'];
        }

        return $out;
    }

    /**
     * @param list<int> $postIds
     * @return array<int, array<string, int>>
     */
    public function countByKeysForPosts(array $postIds): array
    {
        if (!$this->tableExists() || $postIds === []) {
            return [];
        }
        $postIds = array_values(array_filter(array_map('intval', $postIds), fn ($id) => $id > 0));
        if ($postIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT post_id, reaction_key, COUNT(*) AS c FROM forum_post_reactions WHERE post_id IN ($placeholders) GROUP BY post_id, reaction_key"
        );
        $stmt->execute($postIds);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pid = (int) $row['post_id'];
            if (!isset($map[$pid])) {
                $map[$pid] = [];
            }
            $map[$pid][(string) $row['reaction_key']] = (int) $row['c'];
        }

        return $map;
    }

    /**
     * @param list<int> $postIds
     * @return array<int, string|null> postId => reaction_key
     */
    public function getUserReactionsForPosts(int $userId, array $postIds): array
    {
        if (!$this->tableExists() || $postIds === []) {
            return [];
        }
        $postIds = array_values(array_filter(array_map('intval', $postIds), fn ($id) => $id > 0));
        if ($postIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $sql = "SELECT post_id, reaction_key FROM forum_post_reactions WHERE user_id = ? AND post_id IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$userId], $postIds));
        $out = [];
        foreach ($postIds as $pid) {
            $out[$pid] = null;
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['post_id']] = (string) $row['reaction_key'];
        }

        return $out;
    }

    public function setReaction(int $tenantId, int $postId, int $userId, string $reactionKey): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $reactionKey = preg_replace('/[^a-z0-9_]/', '', strtolower($reactionKey)) ?? '';
        if ($reactionKey === '') {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO forum_post_reactions (tenant_id, post_id, user_id, reaction_key, created_at) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE reaction_key = VALUES(reaction_key), created_at = NOW()'
        );
        $stmt->execute([$tenantId, $postId, $userId, $reactionKey]);
    }

    public function removeReaction(int $postId, int $userId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM forum_post_reactions WHERE post_id = ? AND user_id = ?');
        $stmt->execute([$postId, $userId]);
    }
}
