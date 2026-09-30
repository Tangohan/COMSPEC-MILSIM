<?php

declare(strict_types=1);

namespace App\Services\Advancement;

use App\Repositories\AdvancementRepository;
use App\Repositories\ForumNotificationRepository;
use DateTimeImmutable;
use RuntimeException;

final class AdvancementService
{
    public function __construct(
        private AdvancementRepository $repository,
        private AdvancementEligibilityService $eligibility,
        private ForumNotificationRepository $notifications,
    ) {}

    public function apply(int $tenantId, int $campaignId, int $personnelId, array $data, ?int $actorId): int
    {
        $campaign = $this->repository->findCampaign($tenantId, $campaignId);
        if ($campaign === null || $campaign['status'] !== 'open') {
            throw new RuntimeException('Cette campagne n’accepte pas de candidature.');
        }
        $today = new DateTimeImmutable('today');
        if ($today < new DateTimeImmutable((string) $campaign['opens_at'])
            || $today > new DateTimeImmutable((string) $campaign['closes_at'])
        ) {
            throw new RuntimeException('La fenêtre de candidature est fermée.');
        }
        $result = $this->eligibility->evaluate($tenantId, $personnelId, (int) $campaign['grade_id'], $today);

        return $this->repository->createCandidacy($campaignId, $personnelId, $result, $data, $actorId);
    }

    public function recheck(int $tenantId, int $candidacyId): array
    {
        $candidacy = $this->repository->findCandidacy($tenantId, $candidacyId);
        if ($candidacy === null) {
            throw new RuntimeException('Candidature introuvable.');
        }
        $result = $this->eligibility->evaluate(
            $tenantId,
            (int) $candidacy['personnel_id'],
            (int) $candidacy['grade_id']
        );
        $this->repository->updateEligibility($tenantId, $candidacyId, $result);

        return $result;
    }

    public function publish(int $tenantId, int $campaignId, ?int $actorId): int
    {
        foreach ($this->repository->listCandidacies($tenantId, $campaignId) as $candidate) {
            $this->recheck($tenantId, (int) $candidate['id']);
        }
        $count = $this->repository->publishCampaign($tenantId, $campaignId, $actorId);
        foreach ($this->repository->listCandidacies($tenantId, $campaignId) as $candidate) {
            if (($candidate['decision'] ?? null) !== 'registered' || empty($candidate['is_eligible'])) {
                continue;
            }
            try {
                $this->notifications->create($tenantId, (int) $candidate['personnel_id'], 'advancement_published', [
                    'title' => 'Votre avancement a été publié',
                    'href' => function_exists('url') ? url('back-office/ma-situation/avancement') : '',
                ]);
            } catch (\Throwable) {
                // Une notification ne doit jamais annuler une publication déjà atomique.
            }
        }

        return $count;
    }
}
