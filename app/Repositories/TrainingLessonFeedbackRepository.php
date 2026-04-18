<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Feedback post-leçon standardisé (difficulté, clarté, utilité) côté apprenant.
 */
class TrainingLessonFeedbackRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasTable(): bool
    {
        $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_lesson_feedback' LIMIT 1");

        return $st && (bool) $st->fetchColumn();
    }

    public function findForEnrollmentLessonUser(int $enrollmentId, int $lessonId, int $userId): ?array
    {
        if (!$this->hasTable()) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM training_lesson_feedback WHERE enrollment_id = ? AND lesson_id = ? AND user_id = ? LIMIT 1'
        );
        $st->execute([$enrollmentId, $lessonId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function upsert(
        int $tenantId,
        int $courseId,
        int $moduleId,
        int $lessonId,
        int $enrollmentId,
        int $userId,
        int $difficulty,
        int $clarity,
        int $utility,
        ?string $comment
    ): bool {
        if (!$this->hasTable()) {
            return false;
        }
        $difficulty = max(1, min(5, $difficulty));
        $clarity = max(1, min(5, $clarity));
        $utility = max(1, min(5, $utility));
        $comment = $comment !== null ? trim($comment) : null;
        if ($comment !== null && $comment !== '') {
            $comment = mb_substr($comment, 0, 2000);
        } else {
            $comment = null;
        }

        $st = $this->pdo->prepare(
            'INSERT INTO training_lesson_feedback
             (tenant_id, course_id, module_id, lesson_id, enrollment_id, user_id, difficulty_rating, clarity_rating, utility_rating, comment)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 difficulty_rating = VALUES(difficulty_rating),
                 clarity_rating = VALUES(clarity_rating),
                 utility_rating = VALUES(utility_rating),
                 comment = VALUES(comment),
                 updated_at = NOW()'
        );

        return $st->execute([
            $tenantId,
            $courseId,
            $moduleId,
            $lessonId,
            $enrollmentId,
            $userId,
            $difficulty,
            $clarity,
            $utility,
            $comment,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listRecentForTenant(int $tenantId, ?int $courseId = null, int $limit = 150): array
    {
        if (!$this->hasTable()) {
            return [];
        }
        $limit = max(1, min(400, $limit));
        $sql = 'SELECT f.*,
                       c.title AS course_title,
                       m.title AS module_title,
                       l.title AS lesson_title,
                       u.display_name AS learner_name,
                       u.email AS learner_email
                FROM training_lesson_feedback f
                INNER JOIN training_courses c ON c.id = f.course_id
                INNER JOIN training_modules m ON m.id = f.module_id
                INNER JOIN training_lessons l ON l.id = f.lesson_id
                INNER JOIN users u ON u.id = f.user_id
                WHERE f.tenant_id = ?';
        $params = [$tenantId];
        if ($courseId !== null && $courseId > 0) {
            $sql .= ' AND f.course_id = ?';
            $params[] = $courseId;
        }
        $sql .= ' ORDER BY COALESCE(f.updated_at, f.created_at) DESC LIMIT ' . $limit;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{count:int,avg_difficulty:float,avg_clarity:float,avg_utility:float} */
    public function aggregateForTenant(int $tenantId, ?int $courseId = null): array
    {
        if (!$this->hasTable()) {
            return ['count' => 0, 'avg_difficulty' => 0.0, 'avg_clarity' => 0.0, 'avg_utility' => 0.0];
        }
        $sql = 'SELECT COUNT(*) AS c,
                       AVG(difficulty_rating) AS avg_difficulty,
                       AVG(clarity_rating) AS avg_clarity,
                       AVG(utility_rating) AS avg_utility
                FROM training_lesson_feedback
                WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($courseId !== null && $courseId > 0) {
            $sql .= ' AND course_id = ?';
            $params[] = $courseId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'count' => (int) ($row['c'] ?? 0),
            'avg_difficulty' => round((float) ($row['avg_difficulty'] ?? 0), 2),
            'avg_clarity' => round((float) ($row['avg_clarity'] ?? 0), 2),
            'avg_utility' => round((float) ($row['avg_utility'] ?? 0), 2),
        ];
    }
}
