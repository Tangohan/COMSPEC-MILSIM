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
}

