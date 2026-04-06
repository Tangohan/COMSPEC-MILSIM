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

    /** Catalogue : formations publiées pour le tenant, avec statut d'enrollment si userId fourni. */
    public function getCatalogue(int $tenantId, ?int $userId = null, ?string $category = null, ?string $search = null): array
    {
        $courses = $this->courseRepository->listForTenant($tenantId, 'published', $category, $search);
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
            return $this->courseRepository->findById((int) $slugOrId, $tenantId);
        }
        return $this->courseRepository->findBySlug($slugOrId, $tenantId);
    }

    /** Formation avec structure complète : modules, leçons, quiz par module. */
    public function getCourseWithStructure(int $courseId, ?int $tenantId = null): ?array
    {
        $course = $this->courseRepository->findById($courseId, $tenantId);
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
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course || $course['visibility'] !== 'published') {
            return false;
        }
        $enrollment = $this->enrollmentRepository->findByCourseAndUser($courseId, $userId);
        if ($enrollment === null) {
            return false;
        }
        $st = (string) ($enrollment['status'] ?? '');

        return !in_array($st, ['revoked', 'expired', 'pending_approval'], true);
    }

    /** Pourcentage global de progression (leçons complétées / total). */
    public function getGlobalProgress(int $enrollmentId): float
    {
        $progressRows = $this->progressRepository->listByEnrollmentId($enrollmentId);
        $total = count($progressRows);
        if ($total === 0) {
            return 0.0;
        }
        $completed = 0;
        foreach ($progressRows as $p) {
            if (($p['status'] ?? '') === 'completed') {
                $completed++;
            }
        }
        return round(100.0 * $completed / $total, 2);
    }
}
