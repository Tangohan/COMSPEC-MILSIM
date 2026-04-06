<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TenantRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingLessonRepository;
use App\Repositories\TrainingModuleRepository;
use App\Repositories\TrainingProgressRepository;
use App\Repositories\TrainingQuizRepository;
use App\Repositories\UserRepository;
use App\Services\EmailService;

class TrainingProgressService
{
    public function __construct(
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingProgressRepository $progressRepository,
        private TrainingModuleRepository $moduleRepository,
        private TrainingLessonRepository $lessonRepository,
        private TrainingQuizRepository $quizRepository,
        private TrainingService $trainingService,
        private TrainingAuditService $auditService,
        private EmailService $emailService,
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private TrainingCourseRepository $courseRepository,
        private TrainingStaffAlertService $staffAlertService
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
        if (($enrollment['status'] ?? '') !== 'assigned') {
            throw new \InvalidArgumentException('Enrollment cannot be started in current state.');
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
            $wasAlreadyCompleted = (($enrollment['status'] ?? '') === 'completed');
            $courseProgress = $this->computeCourseProgress($enrollmentId);
            if ($courseProgress['completed'] && !$wasAlreadyCompleted) {
                $this->enrollmentRepository->update($enrollmentId, [
                    'status' => 'completed',
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
                $this->notifyCourseCompleted($tenantId, $userId, (int) $enrollment['course_id'], $enrollmentId);
            }
        }
    }

    /** Félicitations par e-mail (échec d’envoi ignoré). */
    private function notifyCourseCompleted(int $tenantId, int $userId, int $courseId, int $enrollmentId): void
    {
        try {
            $course = $this->courseRepository->findById($courseId, $tenantId);
            if (!$course) {
                return;
            }
            $user = $this->userRepository->findById($userId, $tenantId);
            if (!$user) {
                return;
            }
            $email = trim((string) ($user['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $tenant = $this->tenantRepository->findById($tenantId);
            $tenantName = 'Communauté';
            if ($tenant) {
                $tenantName = function_exists('community_display_name')
                    ? community_display_name($tenant)
                    : (string) ($tenant['name'] ?? 'Communauté');
            }
            $display = trim((string) ($user['display_name'] ?? ''));
            if ($display === '') {
                $display = trim((string) ($user['callsign'] ?? ''));
            }
            if ($display === '') {
                $display = $email;
            }
            $slug = trim((string) ($course['slug'] ?? ''));
            $courseUrl = $slug !== '' ? \url('formations/' . rawurlencode($slug)) : \url('formations/mes-formations');
            $this->emailService->sendTrainingCourseCompleted(
                $email,
                $display,
                $tenantName,
                (string) ($course['title'] ?? 'Formation'),
                $courseUrl,
                (int) ($course['is_certifying'] ?? 0) === 1,
                $tenantId
            );
        } catch (\Throwable) {
        }
        try {
            $this->staffAlertService->recordCourseCompletedByLearner($tenantId, $userId, $enrollmentId, $courseId);
        } catch (\Throwable) {
        }
    }

    /**
     * Synthèse d’un module pour la page bilan apprenant.
     *
     * @return array{
     *   module: array<string, mixed>,
     *   lessons: list<array<string, mixed>>,
     *   quizzes: list<array<string, mixed>>,
     *   module_validated: bool,
     *   course_completed: bool,
     *   gaps: list<string>
     * }|null
     */
    public function getModuleBilan(int $enrollmentId, int $moduleId, int $tenantId, int $userId): ?array
    {
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
        if (!$enrollment || (int) $enrollment['user_id'] !== $userId) {
            return null;
        }
        if (in_array((string) ($enrollment['status'] ?? ''), ['revoked', 'expired'], true)) {
            return null;
        }
        $courseId = (int) $enrollment['course_id'];
        $module = $this->moduleRepository->findById($moduleId);
        if (!$module || (int) ($module['course_id'] ?? 0) !== $courseId) {
            return null;
        }

        $lessonsOut = [];
        $lessons = $this->lessonRepository->listByModuleId($moduleId);
        foreach ($lessons as $l) {
            $lid = (int) ($l['id'] ?? 0);
            $p = $this->progressRepository->findByEnrollmentAndLesson($enrollmentId, $lid);
            $st = (string) ($p['status'] ?? 'not_started');
            $lessonsOut[] = [
                'lesson' => $l,
                'status' => $st,
                'status_label' => $this->lessonStatusLabelFr($st),
                'title' => (string) ($l['title'] ?? ''),
                'is_required' => (int) ($l['is_required'] ?? 1) === 1,
            ];
        }

        $quizzesOut = [];
        foreach ($this->quizRepository->listQuizzesByModuleId($moduleId) as $q) {
            if ((int) ($q['is_final_exam'] ?? 0) === 1) {
                continue;
            }
            $qid = (int) ($q['id'] ?? 0);
            $attempts = $this->quizRepository->listAttemptsByEnrollmentAndQuiz($enrollmentId, $qid);
            $best = null;
            $passed = false;
            $submitted = 0;
            foreach ($attempts as $a) {
                if (in_array((string) ($a['status'] ?? ''), ['submitted', 'graded'], true)) {
                    $submitted++;
                }
                if ((int) ($a['passed'] ?? 0) === 1) {
                    $passed = true;
                }
                if (isset($a['score']) && $a['score'] !== null && $a['score'] !== '') {
                    $sc = (float) $a['score'];
                    $best = $best === null ? $sc : max($best, $sc);
                }
            }
            $quizzesOut[] = [
                'quiz' => $q,
                'attempts_count' => count($attempts),
                'submitted_attempts' => $submitted,
                'best_score' => $best,
                'passed' => $passed,
                'passing_score' => (float) ($q['passing_score'] ?? 80),
                'title' => (string) ($q['title'] ?? 'Quiz'),
            ];
        }

        $moduleValidated = $this->isModuleValidated($enrollmentId, $moduleId);
        $courseState = $this->computeCourseProgress($enrollmentId);

        $gaps = [];
        foreach ($lessonsOut as $row) {
            if (!$row['is_required']) {
                continue;
            }
            if (($row['status'] ?? '') !== 'completed') {
                $gaps[] = 'Leçon « ' . $row['title'] . ' » : ' . ($row['status_label'] ?? 'non validée');
            }
        }
        foreach ($quizzesOut as $qz) {
            if ($qz['passed']) {
                continue;
            }
            $t = $qz['title'];
            $thr = $qz['passing_score'];
            if ($qz['attempts_count'] === 0) {
                $gaps[] = 'Évaluation « ' . $t . ' » : aucune tentative terminée (réussite attendue : au moins ' . round($thr, 1) . ' %).';
            } elseif ($qz['best_score'] !== null) {
                $gaps[] = 'Évaluation « ' . $t . ' » : meilleur résultat ' . round($qz['best_score'], 1) . ' % pour un seuil de ' . round($thr, 1) . ' %.';
            } else {
                $gaps[] = 'Évaluation « ' . $t . ' » : à valider (seuil ' . round($thr, 1) . ' %).';
            }
        }

        return [
            'module' => $module,
            'lessons' => $lessonsOut,
            'quizzes' => $quizzesOut,
            'module_validated' => $moduleValidated,
            'course_completed' => (bool) ($courseState['completed'] ?? false),
            'gaps' => $gaps,
        ];
    }

    private function lessonStatusLabelFr(string $status): string
    {
        return match ($status) {
            'completed' => 'Validée',
            'in_progress' => 'En cours',
            'skipped' => 'Ignorée',
            default => 'Pas encore validée',
        };
    }

    /** @return array{progress: list<array>, percent: float, completed: bool} */
    public function getProgressForEnrollment(int $enrollmentId): array
    {
        $progress = $this->progressRepository->listByEnrollmentId($enrollmentId);
        $percent = $this->trainingService->getGlobalProgress($enrollmentId);
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
        if (in_array($enrollment['status'], ['revoked', 'expired', 'pending_approval'], true)) {
            throw new \InvalidArgumentException('Enrollment no longer active.');
        }
    }
}
