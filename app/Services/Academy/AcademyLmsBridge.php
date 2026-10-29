<?php

declare(strict_types=1);

namespace App\Services\Academy;

use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingRepository;
use App\Services\Training\TrainingService;

/** Read the existing LMS as the source of truth; never copy its enrolments or grading. */
final class AcademyLmsBridge
{
    public function __construct(private TrainingService $training, private TrainingEnrollmentRepository $enrollments, private ?TrainingRepository $legacy = null) {}

    public function load(int $tenantId, int $userId): array
    {
        if ($tenantId < 1 || $userId < 1) {
            return ['legacyCourses' => [], 'legacyEnrollments' => []];
        }
        // Includes tenant/platform visibility rules, hidden courses and tenant overrides.
        $courses = $this->training->getCatalogue($tenantId, $userId);
        if ($this->legacy !== null) {
            $known = array_fill_keys(array_column($courses, 'slug'), true);
            foreach ($this->legacy->listPublishedForTenant($tenantId) as $module) {
                $slug = (string) ($module['slug'] ?? '');
                if ($slug === '' || isset($known[$slug])) continue;
                $module['short_description'] = $module['short_description'] ?? $module['summary'] ?? '';
                $module['estimated_minutes'] = $module['estimated_minutes'] ?? $module['duration_minutes'] ?? 0;
                $module['source'] = 'legacy';
                $courses[] = $module;
                $known[$slug] = true;
            }
        }
        $enrollments = [];
        foreach ($this->enrollments->listByUserId($userId, $tenantId) as $enrollment) {
            if (in_array($enrollment['status'] ?? '', ['withdrawn', 'revoked', 'expired'], true)) {
                continue;
            }
            $enrollment['progress_percent'] = ($enrollment['status'] ?? '') === 'pending_approval'
                ? 0 : (int) round($this->training->getGlobalProgress((int) $enrollment['id']));
            $enrollments[] = $enrollment;
        }
        return ['legacyCourses' => $courses, 'legacyEnrollments' => $enrollments];
    }
}
