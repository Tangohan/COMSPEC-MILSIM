<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\AdvancementCampaignRepository;
use App\Repositories\AdvancementCandidacyRepository;
use App\Repositories\GradeDefinitionRepository;
use App\Repositories\PersonnelCareerEventRepository;
use App\Repositories\PersonnelGradeHistoryRepository;
use App\Repositories\TenantMessageRepository;
use App\Repositories\UserRepository;
use App\Support\AdvancementCodes;
use DateTimeImmutable;
use RuntimeException;

final class AdvancementPublicationService
{
    public function __construct(
        private AdvancementCampaignRepository $campaigns,
        private AdvancementCandidacyRepository $candidacies,
        private PersonnelGradeHistoryRepository $history,
        private GradeDefinitionRepository $grades,
        private PersonnelCareerEventRepository $careerEvents,
        private UserRepository $users,
        private ?TenantMessageRepository $messages = null,
    ) {
        $this->messages ??= new TenantMessageRepository();
    }

    /**
     * @return array{promoted: int, notified: int}
     */
    public function publish(int $tenantId, int $campaignId, int $actorUserId, DateTimeImmutable $now): array
    {
        $campaign = $this->campaigns->find($tenantId, $campaignId);
        if ($campaign === null) {
            throw new RuntimeException('Campagne introuvable.');
        }
        $status = (string) ($campaign['status'] ?? '');
        if ($status === AdvancementCodes::CAMPAIGN_PUBLISHED) {
            throw new RuntimeException('Le tableau d’avancement est déjà publié. Toute correction passe par une nouvelle ligne d’historique.');
        }
        if (!in_array($status, [AdvancementCodes::CAMPAIGN_IN_COMMISSION, AdvancementCodes::CAMPAIGN_CLOSED], true)) {
            throw new RuntimeException('La publication n’est possible qu’après passage en commission.');
        }

        $rows = $this->candidacies->listForCampaign($campaignId);
        $inscribed = array_values(array_filter(
            $rows,
            static fn (array $r): bool => (string) ($r['decision'] ?? '') === AdvancementCodes::DECISION_INSCRIBED
        ));
        $quota = isset($campaign['quota_slots']) && $campaign['quota_slots'] !== null
            ? (int) $campaign['quota_slots']
            : 0;
        if ($quota > 0 && count($inscribed) > $quota) {
            throw new RuntimeException(
                'Le nombre d’inscrits (' . count($inscribed) . ') dépasse le quota (' . $quota . ').'
            );
        }

        $gradeId = (int) ($campaign['grade_id'] ?? 0);
        $grade = $this->grades->find($tenantId, $gradeId);
        $obtainedAt = $now->format('Y-m-d');
        $promoted = 0;
        $notified = 0;
        foreach ($inscribed as $row) {
            $personnelId = (int) ($row['personnel_id'] ?? 0);
            if ($personnelId < 1) {
                continue;
            }
            $this->history->append($tenantId, $personnelId, $gradeId, [
                'obtained_at' => $obtainedAt,
                'obtained_via' => AdvancementCodes::VIA_CHOICE,
                'candidacy_id' => (int) ($row['id'] ?? 0),
                'created_by' => $actorUserId,
            ]);
            $this->syncLegacyGrade($tenantId, $personnelId, $grade);
            $this->careerEvents->record($tenantId, $personnelId, 'grade_advancement_choice', $actorUserId, [
                'grade_id' => $gradeId,
                'campaign_id' => $campaignId,
                'via' => AdvancementCodes::VIA_CHOICE,
            ]);
            $this->notify(
                $tenantId,
                $actorUserId,
                $personnelId,
                'Avancement au grade de ' . trim((string) ($grade['label'] ?? 'grade')),
                'Vous êtes inscrit au tableau d’avancement. Votre grade est désormais : '
                    . trim((string) ($grade['label'] ?? '')) . '.'
            );
            $promoted++;
            $notified++;
        }

        $this->campaigns->setStatus(
            $tenantId,
            $campaignId,
            AdvancementCodes::CAMPAIGN_PUBLISHED,
            $obtainedAt
        );

        return ['promoted' => $promoted, 'notified' => $notified];
    }

    /** @param array<string, mixed>|null $grade */
    private function syncLegacyGrade(int $tenantId, int $personnelId, ?array $grade): void
    {
        $legacyId = (int) ($grade['source_catalog_grade_id'] ?? 0);
        if ($legacyId < 1) {
            return;
        }
        try {
            $this->users->update($personnelId, $tenantId, ['grade_id' => $legacyId]);
        } catch (\Throwable) {
        }
    }

    private function notify(int $tenantId, int $fromUserId, int $toUserId, string $subject, string $body): void
    {
        try {
            $threadId = $this->messages->createThread($tenantId, $fromUserId, $subject, [$toUserId]);
            $this->messages->addMessage($threadId, $fromUserId, $body);
        } catch (\Throwable) {
        }
    }
}
