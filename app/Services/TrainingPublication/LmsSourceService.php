<?php

declare(strict_types=1);

namespace App\Services\TrainingPublication;

use App\Services\Training\TrainingService;

class LmsSourceService
{
    public function __construct(private TrainingService $trainingService) {}

    public function normalizedCourse(int $courseId, int $tenantId): array
    {
        $course = $this->trainingService->getCourseWithStructure($courseId, $tenantId);
        if (!$course) {
            throw new \RuntimeException('Formation introuvable.', 404);
        }

        $chapters = [];
        foreach (($course['modules'] ?? []) as $module) {
            if (!(bool) ($module['is_publishable'] ?? true)) {
                continue;
            }
            $lessons = [];
            foreach (($module['lessons'] ?? []) as $lesson) {
                if (!(bool) ($lesson['is_publishable'] ?? true)) {
                    continue;
                }
                $lessons[] = [
                    'id' => (int) ($lesson['id'] ?? 0),
                    'title' => (string) ($lesson['title'] ?? ''),
                    'objectives' => (array) ($lesson['objectives'] ?? []),
                ];
            }
            $chapters[] = [
                'id' => (int) ($module['id'] ?? 0),
                'title' => (string) ($module['title'] ?? ''),
                'lessons' => $lessons,
            ];
        }

        return [
            'chapters' => $chapters,
            'objectives' => (array) ($course['objectives'] ?? []),
            'metadata' => [
                'title' => (string) ($course['title'] ?? ''),
                'code' => (string) ($course['code'] ?? ''),
                'duration' => (int) ($course['estimated_duration_minutes'] ?? 0),
            ],
        ];
    }
}
