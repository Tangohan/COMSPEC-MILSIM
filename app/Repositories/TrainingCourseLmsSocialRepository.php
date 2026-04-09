<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/** Favoris, avis, questions, commentaires, créneaux — tables optionnelles (migration engagement). */
class TrainingCourseLmsSocialRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasTable(string $table): bool
    {
        $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $this->pdo->quote($table) . ' LIMIT 1');

        return $st && (bool) $st->fetchColumn();
    }

    public function isFavorite(int $userId, int $courseId): bool
    {
        if (!$this->hasTable('training_course_favorites')) {
            return false;
        }
        $st = $this->pdo->prepare('SELECT 1 FROM training_course_favorites WHERE user_id = ? AND course_id = ? LIMIT 1');
        $st->execute([$userId, $courseId]);

        return (bool) $st->fetchColumn();
    }

    public function setFavorite(int $tenantId, int $userId, int $courseId, bool $on): void
    {
        if (!$this->hasTable('training_course_favorites')) {
            return;
        }
        if ($on) {
            $st = $this->pdo->prepare(
                'INSERT IGNORE INTO training_course_favorites (tenant_id, user_id, course_id) VALUES (?, ?, ?)'
            );
            $st->execute([$tenantId, $userId, $courseId]);
        } else {
            $st = $this->pdo->prepare('DELETE FROM training_course_favorites WHERE user_id = ? AND course_id = ?');
            $st->execute([$userId, $courseId]);
        }
    }

    public function isLiked(int $userId, int $courseId): bool
    {
        if (!$this->hasTable('training_course_likes')) {
            return false;
        }
        $st = $this->pdo->prepare('SELECT 1 FROM training_course_likes WHERE user_id = ? AND course_id = ? LIMIT 1');
        $st->execute([$userId, $courseId]);

        return (bool) $st->fetchColumn();
    }

    public function setLike(int $tenantId, int $userId, int $courseId, bool $on): void
    {
        if (!$this->hasTable('training_course_likes')) {
            return;
        }
        if ($on) {
            $st = $this->pdo->prepare(
                'INSERT IGNORE INTO training_course_likes (tenant_id, user_id, course_id) VALUES (?, ?, ?)'
            );
            $st->execute([$tenantId, $userId, $courseId]);
        } else {
            $st = $this->pdo->prepare('DELETE FROM training_course_likes WHERE user_id = ? AND course_id = ?');
            $st->execute([$userId, $courseId]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function listPublishedReviews(int $courseId, int $limit = 50): array
    {
        if (!$this->hasTable('training_course_reviews')) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $st = $this->pdo->prepare(
            'SELECT r.*, u.display_name, u.callsign
             FROM training_course_reviews r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.course_id = ? AND r.status = ?
             ORDER BY r.created_at DESC
             LIMIT ' . $limit
        );
        $st->execute([$courseId, 'published']);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findUserReview(int $userId, int $courseId, string $kind = 'rating'): ?array
    {
        if (!$this->hasTable('training_course_reviews')) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM training_course_reviews WHERE user_id = ? AND course_id = ? AND kind = ? LIMIT 1'
        );
        $st->execute([$userId, $courseId, $kind]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function upsertReview(
        int $tenantId,
        int $userId,
        int $courseId,
        int $rating,
        ?string $title,
        ?string $body,
        string $kind = 'rating'
    ): void {
        if (!$this->hasTable('training_course_reviews')) {
            return;
        }
        $rating = max(1, min(5, $rating));
        $kind = $kind === 'review' ? 'review' : 'rating';
        $st = $this->pdo->prepare(
            'INSERT INTO training_course_reviews (tenant_id, course_id, user_id, rating, title, body, kind, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), title = VALUES(title), body = VALUES(body), updated_at = NOW()'
        );
        $st->execute([$tenantId, $courseId, $userId, $rating, $title, $body, $kind, 'published']);
    }

    /** @return list<array<string, mixed>> */
    public function listQuestionsForCourse(int $courseId, int $limit = 100): array
    {
        if (!$this->hasTable('training_course_questions')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $st = $this->pdo->prepare(
            'SELECT q.*, u.display_name AS author_name, a.display_name AS staff_name
             FROM training_course_questions q
             INNER JOIN users u ON u.id = q.user_id
             LEFT JOIN users a ON a.id = q.answered_by
             WHERE q.course_id = ? AND q.status != ?
             ORDER BY q.created_at DESC
             LIMIT ' . $limit
        );
        $st->execute([$courseId, 'hidden']);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addQuestion(int $tenantId, int $userId, int $courseId, string $text): int
    {
        if (!$this->hasTable('training_course_questions')) {
            return 0;
        }
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO training_course_questions (tenant_id, course_id, user_id, question_text, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$tenantId, $courseId, $userId, $text, 'open']);

        return (int) $this->pdo->lastInsertId();
    }

    public function answerQuestion(int $questionId, int $tenantId, int $courseId, int $staffUserId, string $answer): bool
    {
        if (!$this->hasTable('training_course_questions')) {
            return false;
        }
        $answer = trim($answer);
        if ($answer === '') {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE training_course_questions SET answer_text = ?, answered_by = ?, answered_at = NOW(), status = ?
             WHERE id = ? AND tenant_id = ? AND course_id = ?'
        );
        $st->execute([$answer, $staffUserId, 'answered', $questionId, $tenantId, $courseId]);

        return $st->rowCount() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function listCommentsForCourse(int $courseId, int $limit = 80): array
    {
        if (!$this->hasTable('training_course_comments')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $st = $this->pdo->prepare(
            'SELECT c.*, u.display_name, u.callsign
             FROM training_course_comments c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.course_id = ? AND c.status = ? AND c.parent_id IS NULL
             ORDER BY c.created_at DESC
             LIMIT ' . $limit
        );
        $st->execute([$courseId, 'visible']);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addComment(int $tenantId, int $userId, int $courseId, string $body, ?int $parentId = null): int
    {
        if (!$this->hasTable('training_course_comments')) {
            return 0;
        }
        $body = trim($body);
        if ($body === '') {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO training_course_comments (tenant_id, course_id, user_id, parent_id, body, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$tenantId, $courseId, $userId, $parentId, $body, 'visible']);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listSessionsForCourse(int $courseId): array
    {
        if (!$this->hasTable('training_course_sessions')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM training_course_sessions WHERE course_id = ? ORDER BY starts_at ASC'
        );
        $st->execute([$courseId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createSession(int $tenantId, int $courseId, array $data): int
    {
        if (!$this->hasTable('training_course_sessions')) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO training_course_sessions (tenant_id, course_id, starts_at, ends_at, label, location, max_seats, instructor_user_id, audio_briefing_url, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $courseId,
            $data['starts_at'],
            $data['ends_at'],
            $data['label'] ?? null,
            $data['location'] ?? null,
            isset($data['max_seats']) ? (int) $data['max_seats'] : null,
            isset($data['instructor_user_id']) ? (int) $data['instructor_user_id'] : null,
            $data['audio_briefing_url'] ?? null,
            $data['notes'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteSession(int $id, int $tenantId, int $courseId): bool
    {
        if (!$this->hasTable('training_course_sessions')) {
            return false;
        }
        $st = $this->pdo->prepare('DELETE FROM training_course_sessions WHERE id = ? AND tenant_id = ? AND course_id = ?');
        $st->execute([$id, $tenantId, $courseId]);

        return $st->rowCount() > 0;
    }

    public function averageRating(int $courseId): ?float
    {
        if (!$this->hasTable('training_course_reviews')) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT AVG(rating) FROM training_course_reviews WHERE course_id = ? AND status = ? AND kind = ?'
        );
        $st->execute([$courseId, 'published', 'rating']);
        $v = $st->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }

        return round((float) $v, 2);
    }
}
