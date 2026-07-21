<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingQuizRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    // ---------- Quizzes ----------
    public function findQuizById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_quizzes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listQuizzesByModuleId(int $moduleId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_quizzes WHERE module_id = ? ORDER BY id ASC');
        $stmt->execute([$moduleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Quiz de plusieurs modules en une seule requête (évite le N+1 module par module).
     *
     * @param list<int> $moduleIds
     * @return list<array<string, mixed>>
     */
    public function listQuizzesByModuleIds(array $moduleIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $moduleIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM training_quizzes WHERE module_id IN ($placeholders) ORDER BY module_id ASC, id ASC"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createQuiz(int $moduleId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_quizzes (module_id, title, description, passing_score, max_attempts, time_limit_minutes, randomize_questions, is_final_exam)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $moduleId,
            $data['title'],
            $data['description'] ?? null,
            (float) ($data['passing_score'] ?? 80),
            (int) ($data['max_attempts'] ?? 3),
            isset($data['time_limit_minutes']) ? (int) $data['time_limit_minutes'] : null,
            (int) ($data['randomize_questions'] ?? 0),
            (int) ($data['is_final_exam'] ?? 0),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateQuiz(int $id, array $data): void
    {
        $allowed = ['title', 'description', 'passing_score', 'max_attempts', 'time_limit_minutes', 'randomize_questions', 'is_final_exam'];
        $fields = [];
        $params = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $fields[] = "`$k` = ?";
                $params[] = $data[$k];
            }
        }
        if ($fields === []) {
            return;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE training_quizzes SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function deleteQuiz(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM training_quizzes WHERE id = ?');
        $stmt->execute([$id]);
    }

    // ---------- Questions ----------
    /** @return list<array<string, mixed>> */
    public function listQuestionsByQuizId(int $quizId, bool $randomize = false): array
    {
        $sql = 'SELECT * FROM training_quiz_questions WHERE quiz_id = ? ORDER BY ' . ($randomize ? 'RAND()' : 'position ASC, id ASC');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$quizId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findQuestionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_quiz_questions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createQuestion(int $quizId, array $data): int
    {
        $pos = $data['position'] ?? $this->getMaxQuestionPosition($quizId) + 1;
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_quiz_questions (quiz_id, question_type, question_text, explanation, points, position) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $quizId,
            $data['question_type'],
            $data['question_text'],
            $data['explanation'] ?? null,
            (float) ($data['points'] ?? 1),
            $pos,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function getMaxQuestionPosition(int $quizId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(position), 0) FROM training_quiz_questions WHERE quiz_id = ?');
        $stmt->execute([$quizId]);
        return (int) $stmt->fetchColumn();
    }

    public function updateQuestion(int $id, array $data): void
    {
        $allowed = ['question_type', 'question_text', 'explanation', 'points', 'position'];
        $fields = [];
        $params = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $fields[] = "`$k` = ?";
                $params[] = $data[$k];
            }
        }
        if ($fields === []) {
            return;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE training_quiz_questions SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function deleteQuestion(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM training_quiz_questions WHERE id = ?');
        $stmt->execute([$id]);
    }

    // ---------- Answers ----------
    /** @return list<array<string, mixed>> */
    public function listAnswersByQuestionId(int $questionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_quiz_answers WHERE question_id = ? ORDER BY position ASC, id ASC');
        $stmt->execute([$questionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createAnswer(int $questionId, array $data): int
    {
        $pos = $data['position'] ?? $this->getMaxAnswerPosition($questionId) + 1;
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_quiz_answers (question_id, answer_text, is_correct, position) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $questionId,
            $data['answer_text'],
            (int) ($data['is_correct'] ?? 0),
            $pos,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function getMaxAnswerPosition(int $questionId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(position), 0) FROM training_quiz_answers WHERE question_id = ?');
        $stmt->execute([$questionId]);
        return (int) $stmt->fetchColumn();
    }

    public function updateAnswer(int $id, array $data): void
    {
        $allowed = ['answer_text', 'is_correct', 'position'];
        $fields = [];
        $params = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $fields[] = "`$k` = ?";
                $params[] = $data[$k];
            }
        }
        if ($fields === []) {
            return;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE training_quiz_answers SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function deleteAnswer(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM training_quiz_answers WHERE id = ?');
        $stmt->execute([$id]);
    }

    // ---------- Attempts ----------
    public function findAttemptById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_quiz_attempts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listAttemptsByEnrollmentAndQuiz(int $enrollmentId, int $quizId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM training_quiz_attempts WHERE enrollment_id = ? AND quiz_id = ? ORDER BY started_at DESC'
        );
        $stmt->execute([$enrollmentId, $quizId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tentatives d’évaluation envoyées (soumises ou notées), groupées par quiz, pour plusieurs inscriptions.
     *
     * @param list<int> $enrollmentIds
     * @return array<int, list<array{quiz_id: int, quiz_title: string, submitted_attempts: int, best_score: ?float, passed_any: int}>>
     */
    public function summarizeSubmittedAttemptsForEnrollments(array $enrollmentIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn ($v) => (int) $v, $enrollmentIds), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT a.enrollment_id AS enrollment_id, a.quiz_id AS quiz_id, q.title AS quiz_title,
                SUM(CASE WHEN a.status IN (\'submitted\', \'graded\') THEN 1 ELSE 0 END) AS submitted_attempts,
                MAX(CASE WHEN a.status IN (\'submitted\', \'graded\') AND a.score IS NOT NULL THEN a.score END) AS best_score,
                MAX(CASE WHEN a.passed = 1 THEN 1 ELSE 0 END) AS passed_any
                FROM training_quiz_attempts a
                INNER JOIN training_quizzes q ON q.id = a.quiz_id
                WHERE a.enrollment_id IN (' . $placeholders . ')
                GROUP BY a.enrollment_id, a.quiz_id, q.title
                ORDER BY a.enrollment_id ASC, q.id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $eid = (int) ($row['enrollment_id'] ?? 0);
            if ($eid < 1) {
                continue;
            }
            $best = $row['best_score'] ?? null;
            $out[$eid][] = [
                'quiz_id' => (int) ($row['quiz_id'] ?? 0),
                'quiz_title' => (string) ($row['quiz_title'] ?? ''),
                'submitted_attempts' => (int) ($row['submitted_attempts'] ?? 0),
                'best_score' => $best !== null && $best !== '' ? (float) $best : null,
                'passed_any' => (int) ($row['passed_any'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{quiz_id: int, quiz_title: string, submitted_attempts: int, best_score: ?float, passed_any: int}>
     */
    public function summarizeSubmittedAttemptsForEnrollment(int $enrollmentId): array
    {
        return $this->summarizeSubmittedAttemptsForEnrollments([$enrollmentId])[$enrollmentId] ?? [];
    }

    public function getInProgressAttempt(int $enrollmentId, int $quizId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM training_quiz_attempts WHERE enrollment_id = ? AND quiz_id = ? AND status = 'in_progress' LIMIT 1"
        );
        $stmt->execute([$enrollmentId, $quizId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createAttempt(int $quizId, int $enrollmentId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_quiz_attempts (quiz_id, enrollment_id, status) VALUES (?, ?, ?)'
        );
        $stmt->execute([$quizId, $enrollmentId, 'in_progress']);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateAttempt(int $id, array $data): void
    {
        $allowed = ['submitted_at', 'score', 'passed', 'status'];
        $fields = [];
        $params = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $fields[] = "`$k` = ?";
                $params[] = $data[$k];
            }
        }
        if ($fields === []) {
            return;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE training_quiz_attempts SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    // ---------- Responses ----------
    /** @return list<array<string, mixed>> */
    public function listResponsesByAttemptId(int $attemptId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, q.question_type, q.question_text, q.points AS question_points
             FROM training_quiz_responses r
             JOIN training_quiz_questions q ON q.id = r.question_id
             WHERE r.attempt_id = ? ORDER BY r.id ASC'
        );
        $stmt->execute([$attemptId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createResponse(int $attemptId, int $questionId, ?int $answerId, ?string $responseText, ?int $isCorrect, ?float $pointsAwarded): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_quiz_responses (attempt_id, question_id, answer_id, response_text, is_correct, points_awarded) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$attemptId, $questionId, $answerId, $responseText, $isCorrect, $pointsAwarded ?? 0]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateResponsePoints(int $id, ?int $isCorrect, ?float $pointsAwarded): void
    {
        $stmt = $this->pdo->prepare('UPDATE training_quiz_responses SET is_correct = ?, points_awarded = ? WHERE id = ?');
        $stmt->execute([$isCorrect, $pointsAwarded ?? 0, $id]);
    }
}
