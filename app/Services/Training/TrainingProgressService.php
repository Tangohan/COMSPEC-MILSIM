<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingLessonRepository;
use App\Repositories\TrainingModuleRepository;
use App\Repositories\TrainingProgressRepository;
use App\Repositories\TrainingQuizRepository;

class TrainingProgressService
{
    public function __construct(
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingProgressRepository $progressRepository,
        private TrainingModuleRepository $moduleRepository,
        private TrainingLessonRepository $lessonRepository,
        private TrainingQuizRepository $quizRepository,
        private TrainingService $trainingService,
        private TrainingAuditService $auditService
    ) {}

    public function startEnrollment(int $enrollmentId, int $tenantId, int $userId): void
    {
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
        if (!$enrollment) {
            throw new \InvalidArgumentException('Enrollment not found.');
        }
        if ($enrollment['user_id'] != $userId) {
            throw new \InvalidArgumentException('Not your enrollment.');
        }
        $this->enrollmentRepository->update($enrollmentId, [
            'status' => 'in_progress',
            'started_at' => date('Y-m-d H:i:s'),
        ]);
        $lessonIds = $this->trainingService->getCourseLessonIds((int) $enrollment['course_id']);
        $this->progressRepository->initForEnrollment($enrollmentId, $lessonIds);
    }

    public function markLessonStarted(int $enrollmentId, int $lessonId, int $tenantId, int $userId, ?int $timeSpentSeconds = null): void
    {
        $this->ensureEnrollmentAccess($enrollmentId, $tenantId, $userId);
        $this->progressRepository->upsert($enrollmentId, $lessonId, [
            'status' => 'in_progress',
            'viewed_at' => date('Y-m-d H:i:s'),
            'time_spent_seconds' => $timeSpentSeconds ?? 0,
        ]);
    }

    public function markLessonCompleted(int $enrollmentId, int $lessonId, int $tenantId, int $userId, ?int $timeSpentSeconds = null): void
    {
        $this->ensureEnrollmentAccess($enrollmentId, $tenantId, $userId);
        $this->progressRepository->upsert($enrollmentId, $lessonId, [
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => date('Y-m-d H:i:s'),
            'viewed_at' => date('Y-m-d H:i:s'),
            'time_spent_seconds' => $timeSpentSeconds ?? 0,
        ]);
        $this->auditService->logLessonCompleted($tenantId, $userId, $enrollmentId, $lessonId);

        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
        if ($enrollment) {
            $courseProgress = $this->computeCourseProgress($enrollmentId);
            if ($courseProgress['completed']) {
                $this->enrollmentRepository->update($enrollmentId, [
                    'status' => 'completed',
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /** @return array{progress: list<array>, percent: float, completed: bool} */
    public function getProgressForEnrollment(int $enrollmentId): array
    {
        $progress = $this->progressRepository->listByEnrollmentId($enrollmentId);
        $total = count($progress);
        $completed = 0;
        foreach ($progress as $p) {
            if (($p['status'] ?? '') === 'completed') {
                $completed++;
            }
        }
        $percent = $total > 0 ? round(100.0 * $completed / $total, 2) : 0.0;
        $courseProgress = $this->computeCourseProgress($enrollmentId);
        return [
            'progress' => $progress,
            'percent' => $percent,
            'completed' => $courseProgress['completed'],
        ];
    }

    /** Un module est validé si toutes les leçons requises sont complétées et le quiz du module (s'il existe) est réussi. */
    public function isModuleValidated(int $enrollmentId, int $moduleId): bool
    {
        $lessons = $this->lessonRepository->listByModuleId($moduleId);
        foreach ($lessons as $l) {
            if ((int) ($l['is_required'] ?? 1) === 1) {
                $p = $this->progressRepository->findByEnrollmentAndLesson($enrollmentId, (int) $l['id']);
                if (!$p || ($p['status'] ?? '') !== 'completed') {
                    return false;
                }
            }
        }
        $quizzes = $this->quizRepository->listQuizzesByModuleId($moduleId);
        foreach ($quizzes as $q) {
            if ((int) ($q['is_final_exam'] ?? 0) === 0) {
                $attempts = $this->quizRepository->listAttemptsByEnrollmentAndQuiz($enrollmentId, (int) $q['id']);
                $passed = false;
                foreach ($attempts as $a) {
                    if ((int) ($a['passed'] ?? 0) === 1) {
                        $passed = true;
                        break;
                    }
                }
                if (!$passed) {
                    return false;
                }
            }
        }
        return true;
    }

    /** La formation est validée si tous les modules requis sont validés et le quiz final (s'il existe) est réussi. */
    public function computeCourseProgress(int $enrollmentId): array
    {
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, null);
        if (!$enrollment) {
            return ['completed' => false, 'percent' => 0.0];
        }
        $courseId = (int) $enrollment['course_id'];
        $modules = $this->moduleRepository->listByCourseId($courseId);
        $requiredModules = 0;
        $validatedRequired = 0;
        foreach ($modules as $mod) {
            if ((int) ($mod['is_required'] ?? 1) === 1) {
                $requiredModules++;
                if ($this->isModuleValidated($enrollmentId, (int) $mod['id'])) {
                    $validatedRequired++;
                }
            }
        }
        $hasFinalExam = false;
        $finalQuizPassed = true;
        foreach ($modules as $mod) {
            $quizzes = $this->quizRepository->listQuizzesByModuleId((int) $mod['id']);
            foreach ($quizzes as $q) {
                if ((int) ($q['is_final_exam'] ?? 0) === 1) {
                    $hasFinalExam = true;
                    $attempts = $this->quizRepository->listAttemptsByEnrollmentAndQuiz($enrollmentId, (int) $q['id']);
                    $passed = false;
                    foreach ($attempts as $a) {
                        if ((int) ($a['passed'] ?? 0) === 1) {
                            $passed = true;
                            break;
                        }
                    }
                    if (!$passed) {
                        $finalQuizPassed = false;
                    }
                }
            }
        }
        $completed = $requiredModules > 0 && $validatedRequired >= $requiredModules && (!$hasFinalExam || $finalQuizPassed);
        $percent = $this->trainingService->getGlobalProgress($enrollmentId);
        return ['completed' => $completed, 'percent' => $percent];
    }

    private function ensureEnrollmentAccess(int $enrollmentId, int $tenantId, int $userId): void
    {
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
        if (!$enrollment || (int) $enrollment['user_id'] !== $userId) {
            throw new \InvalidArgumentException('Enrollment not found or access denied.');
        }
        if (in_array($enrollment['status'], ['revoked', 'expired'], true)) {
            throw new \InvalidArgumentException('Enrollment no longer active.');
        }
    }
}
