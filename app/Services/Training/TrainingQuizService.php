<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingModuleRepository;
use App\Repositories\TrainingQuizRepository;

class TrainingQuizService
{
    public function __construct(
        private TrainingQuizRepository $quizRepository,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingModuleRepository $moduleRepository,
        private TrainingAuditService $auditService
    ) {}

    /** Démarre une tentative. Vérifie max_attempts et qu'aucune tentative in_progress n'existe. */
    public function startAttempt(int $quizId, int $enrollmentId, int $tenantId, int $userId): array
    {
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
        if (!$enrollment || (int) $enrollment['user_id'] !== $userId) {
            throw new \InvalidArgumentException('Enrollment not found or access denied.');
        }
        if (in_array((string) ($enrollment['status'] ?? ''), ['revoked', 'expired', 'pending_approval'], true)) {
            throw new \InvalidArgumentException('Accès au quiz indisponible pour cette inscription.');
        }
        $quiz = $this->quizRepository->findQuizById($quizId);
        if (!$quiz) {
            throw new \InvalidArgumentException('Quiz not found.');
        }
        $module = $this->moduleRepository->findById((int) $quiz['module_id']);
        if (!$module || (int) $module['course_id'] !== (int) $enrollment['course_id']) {
            throw new \InvalidArgumentException('Quiz does not belong to this course.');
        }

        $inProgress = $this->quizRepository->getInProgressAttempt($enrollmentId, $quizId);
        if ($inProgress) {
            return $inProgress;
        }
        $attempts = $this->quizRepository->listAttemptsByEnrollmentAndQuiz($enrollmentId, $quizId);
        $submitted = 0;
        foreach ($attempts as $a) {
            if (in_array($a['status'], ['submitted', 'graded'], true)) {
                $submitted++;
            }
        }
        $maxAttempts = (int) ($quiz['max_attempts'] ?? 3);
        if ($submitted >= $maxAttempts) {
            throw new \RuntimeException('Maximum attempts reached for this quiz.');
        }
        $attemptId = $this->quizRepository->createAttempt($quizId, $enrollmentId);
        return $this->quizRepository->findAttemptById($attemptId);
    }

    /** Soumet la tentative et corrige (auto pour choix / vrai-faux). */
    public function submitAttempt(int $attemptId, array $responses, int $tenantId, int $userId): array
    {
        $attempt = $this->quizRepository->findAttemptById($attemptId);
        if (!$attempt) {
            throw new \InvalidArgumentException('Attempt not found.');
        }
        if ($attempt['status'] !== 'in_progress') {
            throw new \RuntimeException('Attempt already submitted.');
        }
        $enrollment = $this->enrollmentRepository->findById((int) $attempt['enrollment_id'], $tenantId);
        if (!$enrollment || (int) $enrollment['user_id'] !== $userId) {
            throw new \InvalidArgumentException('Access denied.');
        }
        if (in_array((string) ($enrollment['status'] ?? ''), ['revoked', 'expired', 'pending_approval'], true)) {
            throw new \InvalidArgumentException('Accès au quiz indisponible pour cette inscription.');
        }
        $quiz = $this->quizRepository->findQuizById((int) $attempt['quiz_id']);
        if (!$quiz) {
            throw new \InvalidArgumentException('Quiz not found.');
        }
        if ($quiz['time_limit_minutes']) {
            $start = strtotime($attempt['started_at']);
            if ($start + (int) $quiz['time_limit_minutes'] * 60 < time()) {
                $this->quizRepository->updateAttempt($attemptId, ['status' => 'expired']);
                throw new \RuntimeException('Time limit exceeded.');
            }
        }

        $questions = $this->quizRepository->listQuestionsByQuizId((int) $attempt['quiz_id'], false);
        foreach ($questions as $q) {
            $qid = (int) $q['id'];
            $answerData = $responses[$qid] ?? null;
            $answerId = null;
            $responseText = null;
            if (is_array($answerData)) {
                $answerId = isset($answerData['answer_id']) ? (int) $answerData['answer_id'] : null;
                $responseText = $answerData['text'] ?? null;
                if (isset($answerData['answer_ids'])) {
                    $responseText = json_encode($answerData['answer_ids']);
                }
            } elseif (is_numeric($answerData)) {
                $answerId = (int) $answerData;
            } elseif (is_string($answerData)) {
                $responseText = $answerData;
            }

            $isCorrect = null;
            $pointsAwarded = 0.0;
            $type = $q['question_type'] ?? 'single_choice';
            if (in_array($type, ['single_choice', 'true_false'], true) && $answerId !== null) {
                $answers = $this->quizRepository->listAnswersByQuestionId($qid);
                foreach ($answers as $a) {
                    if ((int) $a['id'] === $answerId) {
                        $isCorrect = (int) ($a['is_correct'] ?? 0) === 1;
                        $pointsAwarded = $isCorrect ? (float) ($q['points'] ?? 1) : 0;
                        break;
                    }
                }
            } elseif ($type === 'multiple_choice' && $responseText !== null) {
                $selectedIds = json_decode($responseText, true);
                if (is_array($selectedIds)) {
                    $answers = $this->quizRepository->listAnswersByQuestionId($qid);
                    $correctIds = [];
                    foreach ($answers as $a) {
                        if ((int) ($a['is_correct'] ?? 0) === 1) {
                            $correctIds[] = (int) $a['id'];
                        }
                    }
                    sort($correctIds);
                    sort($selectedIds);
                    $isCorrect = $correctIds === $selectedIds ? 1 : 0;
                    $pointsAwarded = $isCorrect ? (float) ($q['points'] ?? 1) : 0;
                }
            }

            $this->quizRepository->createResponse($attemptId, $qid, $answerId, $responseText, $isCorrect, $pointsAwarded);
        }
        $this->gradeAttempt($attemptId);
        $attempt = $this->quizRepository->findAttemptById($attemptId);
        $this->auditService->logQuizAttemptSubmitted($tenantId, $userId, $attemptId, [
            'score' => $attempt['score'],
            'passed' => $attempt['passed'],
        ]);
        return $attempt;
    }

    public function gradeAttempt(int $attemptId): void
    {
        $attempt = $this->quizRepository->findAttemptById($attemptId);
        if (!$attempt) {
            return;
        }
        $quiz = $this->quizRepository->findQuizById((int) $attempt['quiz_id']);
        $responses = $this->quizRepository->listResponsesByAttemptId($attemptId);
        $totalPoints = 0.0;
        $awardedPoints = 0.0;
        foreach ($responses as $r) {
            $totalPoints += (float) ($r['question_points'] ?? 1);
            $awardedPoints += (float) ($r['points_awarded'] ?? 0);
        }
        $score = $totalPoints > 0 ? round(100.0 * $awardedPoints / $totalPoints, 2) : 0;
        $passingScore = (float) ($quiz['passing_score'] ?? 80);
        $passed = $score >= $passingScore ? 1 : 0;
        $this->quizRepository->updateAttempt($attemptId, [
            'submitted_at' => date('Y-m-d H:i:s'),
            'score' => $score,
            'passed' => $passed,
            'status' => 'graded',
        ]);
    }

    public function getAttempt(int $attemptId, int $tenantId, int $userId): ?array
    {
        $attempt = $this->quizRepository->findAttemptById($attemptId);
        if (!$attempt) {
            return null;
        }
        $enrollment = $this->enrollmentRepository->findById((int) $attempt['enrollment_id'], $tenantId);
        if (!$enrollment || (int) $enrollment['user_id'] !== $userId) {
            return null;
        }
        $attempt['responses'] = $this->quizRepository->listResponsesByAttemptId($attemptId);
        $attempt['quiz'] = $this->quizRepository->findQuizById((int) $attempt['quiz_id']);
        return $attempt;
    }

    /** Vérifie si la tentative est expirée (time limit). */
    public function isAttemptExpired(int $attemptId): bool
    {
        $attempt = $this->quizRepository->findAttemptById($attemptId);
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            return true;
        }
        $quiz = $this->quizRepository->findQuizById((int) $attempt['quiz_id']);
        if (!$quiz || !$quiz['time_limit_minutes']) {
            return false;
        }
        $start = strtotime($attempt['started_at']);
        return $start + (int) $quiz['time_limit_minutes'] * 60 < time();
    }
}
