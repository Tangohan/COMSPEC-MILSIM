<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumVoteRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** Mémoïsé pour la durée de la requête (appelé pour chaque message d’un sujet). */
    private static ?bool $tableExistsCache = null;

    public function tableExists(): bool
    {
        if (self::$tableExistsCache === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_post_votes' LIMIT 1");
            self::$tableExistsCache = (bool) $stmt?->fetchColumn();
        }

        return self::$tableExistsCache;
    }

    public function getUserVote(int $postId, int $userId): ?int
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT value FROM forum_post_votes WHERE post_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$postId, $userId]);
        $v = $stmt->fetchColumn();

        return $v === false ? null : (int) $v;
    }

    public function setVote(int $tenantId, int $postId, int $userId, int $value): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $value = $value >= 0 ? 1 : -1;
        $stmt = $this->pdo->prepare(
            'INSERT INTO forum_post_votes (tenant_id, post_id, user_id, value, created_at) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        $stmt->execute([$tenantId, $postId, $userId, $value]);
    }

    public function removeVote(int $postId, int $userId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM forum_post_votes WHERE post_id = ? AND user_id = ?');
        $stmt->execute([$postId, $userId]);
    }

    public function sumForPost(int $postId): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(value), 0) FROM forum_post_votes WHERE post_id = ?');
        $stmt->execute([$postId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Scores de plusieurs messages en une requête (page d’un sujet).
     *
     * @param list<int> $postIds
     * @return array<int, int> postId => somme des votes
     */
    public function sumForPosts(array $postIds): array
    {
        $postIds = array_values(array_filter(array_map('intval', $postIds), fn ($id) => $id > 0));
        if ($postIds === [] || !$this->tableExists()) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->pdo->prepare("SELECT post_id, COALESCE(SUM(value), 0) AS score FROM forum_post_votes WHERE post_id IN ($placeholders) GROUP BY post_id");
        $stmt->execute($postIds);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['post_id']] = (int) $row['score'];
        }

        return $out;
    }

    /**
     * Votes de l’utilisateur sur plusieurs messages en une requête.
     *
     * @param list<int> $postIds
     * @return array<int, int> postId => valeur (absent si pas de vote)
     */
    public function getUserVotesForPosts(int $userId, array $postIds): array
    {
        $postIds = array_values(array_filter(array_map('intval', $postIds), fn ($id) => $id > 0));
        if ($postIds === [] || !$this->tableExists()) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->pdo->prepare("SELECT post_id, value FROM forum_post_votes WHERE user_id = ? AND post_id IN ($placeholders)");
        $stmt->execute(array_merge([$userId], $postIds));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['post_id']] = (int) $row['value'];
        }

        return $out;
    }
}
