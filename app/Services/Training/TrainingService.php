<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingLessonRepository;
use App\Repositories\TrainingModuleRepository;
use App\Repositories\TrainingProgressRepository;
use App\Repositories\TrainingQuizRepository;

class TrainingService
{
    public function __construct(
        private TrainingCourseRepository $courseRepository,
        private TrainingModuleRepository $moduleRepository,
        private TrainingLessonRepository $lessonRepository,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingProgressRepository $progressRepository,
        private TrainingQuizRepository $quizRepository
    ) {}

    /** Catalogue : parcours publiés de la communauté + parcours plateforme Athena, avec statut d’inscription si userId fourni. */
    public function getCatalogue(int $tenantId, ?int $userId = null, ?string $category = null, ?string $search = null): array
    {
        $tenantCourses = $this->courseRepository->listForTenant($tenantId, 'published', $category, $search, true);
        $platformCourses = $this->courseRepository->listPublishedPlatform($category, $search);
        $byId = [];
        foreach ($platformCourses as $c) {
            $byId[(int) ($c['id'] ?? 0)] = $c;
        }
        foreach ($tenantCourses as $c) {
            $byId[(int) ($c['id'] ?? 0)] = $c;
        }
        $courses = array_values($byId);
        usort($courses, static fn (array $a, array $b): int => strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? '')));
        if ($userId === null) {
            return $courses;
        }
        foreach ($courses as $i => $course) {
            $enrollment = $this->enrollmentRepository->findByCourseAndUser((int) $course['id'], $userId);
            $courses[$i]['enrollment'] = $enrollment;
            $courses[$i]['progress_percent'] = $enrollment ? $this->getGlobalProgress($enrollment['id']) : 0;
        }

        return $courses;
    }

    public function getCourseBySlugOrId(int $tenantId, string $slugOrId): ?array
    {
        if (is_numeric($slugOrId)) {
            return $this->courseRepository->findByIdForViewer((int) $slugOrId, $tenantId);
        }

        return $this->courseRepository->findBySlug($slugOrId, $tenantId);
    }

    /** Formation avec structure complète : modules, leçons, quiz par module. */
    public function getCourseWithStructure(int $courseId, ?int $tenantId = null, bool $studioContext = false): ?array
    {
        $course = $studioContext && $tenantId !== null
            ? $this->courseRepository->findById($courseId, $tenantId)
            : ($tenantId !== null
                ? $this->courseRepository->findByIdForViewer($courseId, $tenantId)
                : $this->courseRepository->findById($courseId, null));
        if (!$course) {
            return null;
        }
        $modules = $this->moduleRepository->listByCourseId($courseId);
        foreach ($modules as $j => $mod) {
            $mid = (int) $mod['id'];
            $modules[$j]['lessons'] = $this->lessonRepository->listByModuleId($mid);
            $modules[$j]['quizzes'] = $this->quizRepository->listQuizzesByModuleId($mid);
        }
        $course['modules'] = $modules;
        return $course;
    }

    /** Liste des IDs de leçons du parcours (pour initialiser la progression). */
    public function getCourseLessonIds(int $courseId): array
    {
        $modules = $this->moduleRepository->listByCourseId($courseId);
        $ids = [];
        foreach ($modules as $mod) {
            $lessons = $this->lessonRepository->listByModuleId((int) $mod['id']);
            foreach ($lessons as $l) {
                $ids[] = (int) $l['id'];
            }
        }
        return $ids;
    }

    public function canAccessCourse(int $userId, int $courseId, int $tenantId): bool
    {
        $course = $this->courseRepository->findByIdForViewer($courseId, $tenantId);
        if (!$course || $course['visibility'] !== 'published') {
            return false;
        }
        $enrollment = $this->enrollmentRepository->findByCourseAndUser($courseId, $userId);
        if ($enrollment === null) {
            return false;
        }
        $st = (string) ($enrollment['status'] ?? '');

        return !in_array($st, ['revoked', 'expired', 'pending_approval', 'withdrawn'], true);
    }

    /**
     * Pourcentage global de progression : leçons obligatoires + quiz de module (hors examen final)
     * + examens finaux — aligné sur la logique de validation du parcours (pas seulement les leçons).
     */
    public function getGlobalProgress(int $enrollmentId): float
    {
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, null);
        if (!$enrollment) {
            return 0.0;
        }
        $courseId = (int) $enrollment['course_id'];
        $modules = $this->moduleRepository->listByCourseId($courseId);
        $progressRows = $this->progressRepository->listByEnrollmentId($enrollmentId);
        $byLesson = [];
        foreach ($progressRows as $p) {
            $byLesson[(int) $p['lesson_id']] = (string) ($p['status'] ?? '');
        }

        $total = 0;
        $done = 0;
        $finalExamQuizzes = [];

        foreach ($modules as $mod) {
            $moduleId = (int) $mod['id'];
            $moduleQuizzes = $this->quizRepository->listQuizzesByModuleId($moduleId);
            $required = (int) ($mod['is_required'] ?? 1) === 1;

            if ($required) {
                foreach ($this->lessonRepository->listByModuleId($moduleId) as $l) {
                    if ((int) ($l['is_required'] ?? 1) !== 1) {
                        continue;
                    }
                    ++$total;
                    $lid = (int) $l['id'];
                    if (($byLesson[$lid] ?? '') === 'completed') {
                        ++$done;
                    }
                }
            }

            foreach ($moduleQuizzes as $q) {
                if ((int) ($q['is_final_exam'] ?? 0) === 1) {
                    $finalExamQuizzes[] = $q;
                    continue;
                }
                if (!$required) {
                    continue;
                }
                ++$total;
                if ($this->quizHasPassedAttempt($enrollmentId, (int) $q['id'])) {
                    ++$done;
                }
            }
        }

        foreach ($finalExamQuizzes as $q) {
            ++$total;
            if ($this->quizHasPassedAttempt($enrollmentId, (int) $q['id'])) {
                ++$done;
            }
        }

        if ($total === 0) {
            return 0.0;
        }

        return round(100.0 * $done / $total, 2);
    }

    private function quizHasPassedAttempt(int $enrollmentId, int $quizId): bool
    {
        foreach ($this->quizRepository->listAttemptsByEnrollmentAndQuiz($enrollmentId, $quizId) as $a) {
            if ((int) ($a['passed'] ?? 0) === 1) {
                return true;
            }
        }

        return false;
    }
}
