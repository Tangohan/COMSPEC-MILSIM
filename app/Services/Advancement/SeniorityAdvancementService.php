<?php

declare(strict_types=1);

namespace App\Services\Advancement;

use App\Repositories\AdvancementRepository;
use App\Repositories\ForumNotificationRepository;
use DateTimeImmutable;

final class SeniorityAdvancementService
{
    public function __construct(
        private AdvancementRepository $repository,
        private AdvancementEligibilityService $eligibility,
        private ForumNotificationRepository $notifications,
    ) {}

    /** @return array{evaluated: int, promoted: int, skipped: int} */
    public function run(): array
    {
        $stats = ['evaluated' => 0, 'promoted' => 0, 'skipped' => 0];
        foreach ($this->repository->activeGradeHolders() as $holder) {
            $stats['evaluated']++;
            $tenantId = (int) $holder['tenant_id'];
            $personnelId = (int) $holder['personnel_id'];
            $next = $this->repository->nextGrade($tenantId, $holder);
            if ($next === null || empty($next['advancement_seniority_enabled'])) {
                $stats['skipped']++;
                continue;
            }
            $result = $this->eligibility->evaluate($tenantId, $personnelId, (int) $next['id']);
            if (empty($result['is_eligible'])) {
                $stats['skipped']++;
                continue;
            }
            $effectiveAt = (string) ($result['details']['eligible_at'] ?? (new DateTimeImmutable('today'))->format('Y-m-d'));
            $this->repository->assignGrade(
                $tenantId,
                $personnelId,
                (int) $next['id'],
                $effectiveAt,
                'seniority',
                null,
                null
            );
            $stats['promoted']++;
            try {
                $this->notifications->create($tenantId, $personnelId, 'advancement_seniority', [
                    'title' => 'Avancement à l’ancienneté',
                    'message' => 'Vous accédez au grade de ' . (string) $next['label'] . '.',
                    'href' => function_exists('url') ? url('back-office/ma-situation/avancement') : '',
                ]);
            } catch (\Throwable) {
            }
        }

        return $stats;
    }
}
