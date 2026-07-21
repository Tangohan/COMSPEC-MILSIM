<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Avis lecteurs (note + commentaire court) sur les Documentations HTML, pendant de
 * TrainingLessonFeedbackRepository pour les leçons de formation.
 */
class TrainingFormationCustomPageFeedbackRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function findForPageUser(int $pageId, int $userId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM training_formation_custom_page_feedback WHERE page_id = ? AND user_id = ? LIMIT 1');
        $st->execute([$pageId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function upsert(int $tenantId, int $pageId, int $userId, int $rating, ?string $comment): bool
    {
        $rating = max(1, min(5, $rating));
        $comment = $comment !== null ? trim($comment) : null;
        $comment = ($comment !== null && $comment !== '') ? mb_substr($comment, 0, 2000) : null;

        $st = $this->pdo->prepare(
            'INSERT INTO training_formation_custom_page_feedback (tenant_id, page_id, user_id, rating, comment)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = NOW()'
        );

        return $st->execute([$tenantId, $pageId, $userId, $rating, $comment]);
    }

    /** @return list<array<string, mixed>> */
    public function listRecentForPage(int $pageId, int $tenantId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $st = $this->pdo->prepare(
            'SELECT f.*, u.display_name AS reader_name, u.email AS reader_email
             FROM training_formation_custom_page_feedback f
             INNER JOIN users u ON u.id = f.user_id
             WHERE f.page_id = ? AND f.tenant_id = ?
             ORDER BY COALESCE(f.updated_at, f.created_at) DESC
             LIMIT ' . $limit
        );
        $st->execute([$pageId, $tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{count:int,avg_rating:float} */
    public function aggregateForPage(int $pageId, int $tenantId): array
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) AS c, AVG(rating) AS avg_rating
             FROM training_formation_custom_page_feedback
             WHERE page_id = ? AND tenant_id = ?'
        );
        $st->execute([$pageId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'count' => (int) ($row['c'] ?? 0),
            'avg_rating' => round((float) ($row['avg_rating'] ?? 0), 2),
        ];
    }
}
