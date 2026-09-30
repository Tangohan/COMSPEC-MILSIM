<?php

declare(strict_types=1);

namespace App\Services\Advancement;

use App\Repositories\AdvancementRepository;
use DateTimeImmutable;
use RuntimeException;

/**
 * Campagnes au choix et avancement automatique à l'ancienneté.
 * Une publication fige les décisions : les grades attribués ne sont jamais réécrits.
 */
final class AdvancementWorkflowService
{
    public const STATUS_OPEN = 'ouverte';
    public const STATUS_CLOSED = 'cloturee';
    public const STATUS_COMMISSION = 'en_commission';
    public const STATUS_PUBLISHED = 'publiee';
    public const STATUS_ARCHIVED = 'archivee';

    public const VIA_INITIAL = 'initial';
    public const VIA_SENIORITY = 'anciennete';
    public const VIA_CHOICE = 'choix';

    public const DECISION_LISTED = 'inscrit';
    public const DECISION_NOT = 'non_inscrit';

    public function __construct(
        private AdvancementRepository $repository,
        private AdvancementEligibilityService $eligibility,
        private ?AdvancementNotifier $notifier = null,
    ) {
        $this->notifier ??= new AdvancementNotifier();
    }

    /**
     * @return array{is_eligible:bool, eligibility_reason:?string, months_in_grade:int, months_required:?int, due_on:?string}
     */
    public function assessPersonnel(int $tenantId, int $personnelId, array $targetGrade, ?DateTimeImmutable $today = null, string $pathway = AdvancementEligibilityService::PATH_CHOICE): array
    {
        $targetGrade = $this->targetShape($targetGrade);
        $current = $this->repository->activeGrade($tenantId, $personnelId);
        $award = null;
        $levelMet = true;
        $qualId = (int) ($targetGrade['required_qualification_id'] ?? 0);
        if ($qualId > 0) {
            $award = $this->repository->latestAward($tenantId, $personnelId, $qualId);
            $requiredLevel = (int) ($targetGrade['required_qualification_level_id'] ?? 0);
            $levelMet = $requiredLevel < 1 || $this->repository->qualificationLevelMet(
                (int) ($award['qualification_level_id'] ?? 0),
                $requiredLevel
            );
            if ($targetGrade['required_qualification_label'] ?? '' === '') {
                $names = $this->repository->qualificationNames($tenantId);
                $targetGrade['required_qualification_label'] = $names[$qualId] ?? 'requise';
            }
        }

        return $this->eligibility->evaluate([
            'current' => $current,
            'target' => $targetGrade,
            'qualification_award' => $award,
            'qualification_level_met' => $levelMet,
        ], $today, $pathway);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function createCandidacy(int $tenantId, int $campaignId, int $personnelId, int $actorId, array $extra = [], ?DateTimeImmutable $today = null): int
    {
        $today = $today ?? new DateTimeImmutable('today');
        $campaign = $this->requireCampaign($tenantId, $campaignId);
        if ((string) $campaign['status'] !== self::STATUS_OPEN) {
            throw new RuntimeException('Les candidatures sont closes pour cette campagne.');
        }
        $day = $today->format('Y-m-d');
        if ($day < (string) $campaign['opens_at'] || $day > (string) $campaign['closes_at']) {
            throw new RuntimeException('La fenêtre de candidature n’est pas ouverte.');
        }
        if ($this->repository->findCandidacyForPersonnel($campaignId, $personnelId) !== null) {
            throw new RuntimeException('Une candidature existe déjà pour ce personnel.');
        }
        $result = $this->assessPersonnel($tenantId, $personnelId, $campaign, $today, AdvancementEligibilityService::PATH_CHOICE);

        return $this->repository->insertCandidacy([
            'campaign_id' => $campaignId,
            'personnel_id' => $personnelId,
            'volunteered_at' => $today->format('Y-m-d H:i:s'),
            'is_eligible' => $result['is_eligible'],
            'eligibility_reason' => $result['eligibility_reason'],
            'mobility_requested' => !empty($extra['mobility_requested']),
            'requested_billet_id' => $extra['requested_billet_id'] ?? null,
            'notes' => $extra['notes'] ?? null,
            'created_by' => $actorId,
        ]);
    }

    public function recheckCampaign(int $tenantId, int $campaignId, ?DateTimeImmutable $today = null): int
    {
        $campaign = $this->requireCampaign($tenantId, $campaignId);
        if ((string) $campaign['status'] === self::STATUS_PUBLISHED) {
            throw new RuntimeException('Une campagne publiée ne se recalcule pas : les grades attribués restent figés.');
        }
        $updated = 0;
        foreach ($this->repository->listCandidacies($campaignId) as $row) {
            $result = $this->assessPersonnel($tenantId, (int) $row['personnel_id'], $campaign, $today, AdvancementEligibilityService::PATH_CHOICE);
            $this->repository->updateCandidacyEligibility((int) $row['id'], $result['is_eligible'], $result['eligibility_reason']);
            $updated++;
        }

        return $updated;
    }

    /**
     * @param list<array{personnel_id:int, role:string}> $members
     */
    public function openCommission(int $tenantId, int $campaignId, ?string $meetingDate, ?int $minutesDocumentId, array $members): void
    {
        $campaign = $this->requireCampaign($tenantId, $campaignId);
        $status = (string) $campaign['status'];
        if (!in_array($status, [self::STATUS_OPEN, self::STATUS_CLOSED], true)) {
            throw new RuntimeException('Seule une campagne ouverte ou clôturée peut passer en commission.');
        }
        $this->repository->updateCampaignStatus($campaignId, $tenantId, self::STATUS_COMMISSION);
        $this->repository->saveCommission($campaignId, $meetingDate, $minutesDocumentId, $members);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function saveCommissionReview(int $tenantId, int $campaignId, array $rows, ?string $meetingDate = null, ?int $minutesDocumentId = null, array $members = []): void
    {
        $campaign = $this->requireCampaign($tenantId, $campaignId);
        if ((string) $campaign['status'] !== self::STATUS_COMMISSION) {
            throw new RuntimeException('Les avis ne se saisissent qu’en commission.');
        }
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $existing = $id > 0 ? $this->repository->findCandidacy($id) : null;
            if ($existing === null || (int) $existing['campaign_id'] !== $campaignId) {
                continue;
            }
            $decision = (string) ($row['decision'] ?? '');
            if (!in_array($decision, ['', self::DECISION_LISTED, self::DECISION_NOT], true)) {
                $decision = '';
            }
            $opinion = (string) ($row['commission_opinion'] ?? '');
            if (!in_array($opinion, ['', 'propose', 'non_propose'], true)) {
                $opinion = '';
            }
            $this->repository->updateCandidacyReview($id, [
                'preference_rank' => $row['preference_rank'] ?? '',
                'commission_opinion' => $opinion,
                'decision' => $decision,
                'decided_at' => $decision !== '' ? $today : null,
                'notes' => $row['notes'] ?? ($existing['notes'] ?? null),
                'mobility_requested' => !empty($row['mobility_requested']) || !empty($existing['mobility_requested']),
                'requested_billet_id' => $row['requested_billet_id'] ?? ($existing['requested_billet_id'] ?? null),
            ]);
        }
        if ($meetingDate !== null || $minutesDocumentId !== null || $members !== []) {
            $this->repository->saveCommission($campaignId, $meetingDate, $minutesDocumentId, $members);
        }
    }

    /**
     * Publication irréversible : crée les lignes d'historique, ne réécrit jamais une ligne déjà publiée.
     *
     * @return array{promoted:int, skipped:int}
     */
    public function publish(int $tenantId, int $campaignId, int $actorId, ?DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $campaign = $this->requireCampaign($tenantId, $campaignId);
        $this->assertPublishable($campaign);
        $this->recheckCampaign($tenantId, $campaignId, $today);
        $rows = $this->repository->listCandidacies($campaignId);
        $listed = [];
        foreach ($rows as $row) {
            if ((string) ($row['decision'] ?? '') !== self::DECISION_LISTED) {
                continue;
            }
            $fresh = $this->repository->findCandidacy((int) $row['id']) ?? $row;
            if (empty($fresh['is_eligible'])) {
                $name = trim((string) ($row['display_name'] ?? $row['callsign'] ?? 'personnel'));
                throw new RuntimeException('Inscription impossible : ' . $name . ' n’est plus éligible (' . (string) ($fresh['eligibility_reason'] ?? 'critère non rempli') . ').');
            }
            $listed[] = $fresh;
        }
        $quota = isset($campaign['quota_slots']) && $campaign['quota_slots'] !== null && $campaign['quota_slots'] !== ''
            ? (int) $campaign['quota_slots']
            : null;
        if ($quota !== null && $quota >= 0 && count($listed) > $quota) {
            throw new RuntimeException('Le nombre d’inscrits (' . count($listed) . ') dépasse le quota (' . $quota . ').');
        }

        $promoted = 0;
        $date = $today->format('Y-m-d');
        $this->repository->transaction(function () use ($listed, $campaign, $tenantId, $campaignId, $actorId, $date, &$promoted): void {
            foreach ($listed as $row) {
                $personnelId = (int) $row['personnel_id'];
                $active = $this->repository->activeGrade($tenantId, $personnelId);
                if ($active !== null) {
                    $this->repository->closeHistory((int) $active['id'], $date);
                }
                $this->repository->insertHistory([
                    'personnel_id' => $personnelId,
                    'grade_id' => (int) $campaign['grade_id'],
                    'obtained_at' => $date,
                    'obtained_via' => self::VIA_CHOICE,
                    'candidacy_id' => (int) $row['id'],
                    'created_by' => $actorId,
                ]);
                $promoted++;
            }
            $this->repository->updateCampaignStatus($campaignId, $tenantId, self::STATUS_PUBLISHED, $date);
        });

        $gradeLabel = trim((string) ($campaign['grade_label'] ?? 'grade visé'));
        foreach ($listed as $row) {
            $this->notifier->notify(
                $tenantId,
                (int) $row['personnel_id'],
                $actorId,
                'Tableau d’avancement publié',
                'Vous êtes inscrit au tableau d’avancement au grade de ' . $gradeLabel . ' (voie choix). Le poste demandé, s’il y en a un, n’est pas attribué par cette publication.'
            );
        }

        return ['promoted' => $promoted, 'skipped' => count($rows) - $promoted];
    }

    /**
     * @param array<string, mixed> $campaign
     */
    public function assertPublishable(array $campaign): void
    {
        $status = (string) ($campaign['status'] ?? '');
        if ($status === self::STATUS_PUBLISHED || !empty($campaign['published_at'])) {
            throw new RuntimeException('Le tableau d’avancement est déjà publié. Toute correction passe par une nouvelle ligne d’historique, jamais par une modification de la ligne publiée.');
        }
        if ($status !== self::STATUS_COMMISSION) {
            throw new RuntimeException('La publication n’est possible qu’après la commission.');
        }
    }

    public function closeCampaign(int $tenantId, int $campaignId): void
    {
        $campaign = $this->requireCampaign($tenantId, $campaignId);
        if ((string) $campaign['status'] !== self::STATUS_OPEN) {
            throw new RuntimeException('Seule une campagne ouverte peut être clôturée.');
        }
        $this->repository->updateCampaignStatus($campaignId, $tenantId, self::STATUS_CLOSED);
    }

    public function archiveCampaign(int $tenantId, int $campaignId): void
    {
        $campaign = $this->requireCampaign($tenantId, $campaignId);
        if ((string) $campaign['status'] === self::STATUS_ARCHIVED) {
            return;
        }
        $this->repository->updateCampaignStatus($campaignId, $tenantId, self::STATUS_ARCHIVED);
    }

    public function assignInitialGrade(int $tenantId, int $personnelId, int $gradeId, string $obtainedAt, int $actorId): int
    {
        $grade = $this->repository->findGrade($gradeId, $tenantId);
        if ($grade === null || !empty($grade['archived_at'])) {
            throw new RuntimeException('Grade introuvable pour cette communauté.');
        }
        if ($this->repository->historyFor($tenantId, $personnelId) !== []) {
            throw new RuntimeException('Un historique de grade existe déjà. Une correction se fait par une nouvelle ligne, pas en écrasant la première.');
        }
        $date = substr($obtainedAt, 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Date d’obtention invalide.');
        }

        return $this->repository->insertHistory([
            'personnel_id' => $personnelId,
            'grade_id' => $gradeId,
            'obtained_at' => $date,
            'obtained_via' => self::VIA_INITIAL,
            'created_by' => $actorId,
        ]);
    }

    /**
     * @return array{tenants:int, promoted:int}
     */
    public function applySeniorityAll(?DateTimeImmutable $today = null): array
    {
        $totals = ['tenants' => 0, 'promoted' => 0];
        if (!$this->repository->tablesReady()) {
            return $totals;
        }
        foreach ($this->repository->listTenantIds() as $tenantId) {
            if ($tenantId < 1) {
                continue;
            }
            $out = $this->applySeniorityForTenant($tenantId, $today);
            $totals['tenants']++;
            $totals['promoted'] += (int) $out['promoted'];
        }

        return $totals;
    }

    /**
     * @return array{promoted:int}
     */
    public function applySeniorityForTenant(int $tenantId, ?DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $promoted = 0;
        if (!$this->repository->tablesReady()) {
            return ['promoted' => 0];
        }
        $actorId = $this->repository->firstStaffUserId($tenantId);
        $seen = [];
        foreach ($this->repository->listActiveHistories($tenantId) as $active) {
            $personnelId = (int) $active['personnel_id'];
            if (isset($seen[$personnelId])) {
                continue;
            }
            $seen[$personnelId] = true;
            $steps = 0;
            while ($steps < 8) {
                $current = $this->repository->activeGrade($tenantId, $personnelId);
                if ($current === null) {
                    break;
                }
                $filiereId = isset($current['filiere_id']) && $current['filiere_id'] !== null && $current['filiere_id'] !== ''
                    ? (int) $current['filiere_id']
                    : null;
                $next = $this->repository->nextGradeAfter($tenantId, (int) $current['rank_order'], $filiereId);
                if ($next === null || empty($next['advancement_seniority_enabled'])) {
                    break;
                }
                $months = $next['min_time_in_previous_grade_months'] ?? null;
                if ($months === null || (int) $months <= 0) {
                    break;
                }
                $result = $this->assessPersonnel($tenantId, $personnelId, $next, $today, AdvancementEligibilityService::PATH_SENIORITY);
                if (!$result['is_eligible'] || empty($result['due_on']) || $result['due_on'] > $today->format('Y-m-d')) {
                    break;
                }
                $due = (string) $result['due_on'];
                $this->repository->transaction(function () use ($current, $personnelId, $next, $due, $actorId): void {
                    $this->repository->closeHistory((int) $current['id'], $due);
                    $this->repository->insertHistory([
                        'personnel_id' => $personnelId,
                        'grade_id' => (int) $next['id'],
                        'obtained_at' => $due,
                        'obtained_via' => self::VIA_SENIORITY,
                        'created_by' => $actorId > 0 ? $actorId : null,
                    ]);
                });
                $label = trim((string) ($next['label'] ?? 'grade suivant'));
                $this->notifier->notify(
                    $tenantId,
                    $personnelId,
                    $actorId,
                    'Avancement à l’ancienneté',
                    'Votre grade de ' . $label . ' est acquis à l’ancienneté au ' . $this->formatFr($due) . '.'
                );
                $promoted++;
                $steps++;
            }
        }

        return ['promoted' => $promoted];
    }

    /**
     * @return array{current:?array<string, mixed>, history:list<array<string, mixed>>, offer:?array<string, mixed>}
     */
    public function personnelPanel(int $tenantId, int $personnelId, ?DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $current = null;
        $history = [];
        $offer = null;
        if (!$this->repository->tablesReady()) {
            return ['current' => null, 'history' => [], 'offer' => null];
        }
        $current = $this->repository->activeGrade($tenantId, $personnelId);
        $history = $this->repository->historyFor($tenantId, $personnelId);
        if ($current !== null) {
            $filiereId = isset($current['filiere_id']) && $current['filiere_id'] !== null && $current['filiere_id'] !== ''
                ? (int) $current['filiere_id']
                : null;
            $next = $this->repository->nextGradeAfter($tenantId, (int) $current['rank_order'], $filiereId);
            if ($next !== null && !empty($next['advancement_choice_enabled'])) {
                $campaign = $this->repository->openCampaignForGrade($tenantId, (int) $next['id'], $today->format('Y-m-d'));
                if ($campaign !== null) {
                    $target = array_merge($next, [
                        'grade_label' => $next['label'] ?? '',
                    ]);
                    $result = $this->assessPersonnel($tenantId, $personnelId, $target, $today, AdvancementEligibilityService::PATH_CHOICE);
                    $existing = $this->repository->findCandidacyForPersonnel((int) $campaign['id'], $personnelId);
                    $offer = [
                        'campaign_id' => (int) $campaign['id'],
                        'grade_label' => (string) ($next['label'] ?? ''),
                        'is_eligible' => $result['is_eligible'],
                        'eligibility_reason' => $result['eligibility_reason'],
                        'months_in_grade' => $result['months_in_grade'],
                        'months_required' => $result['months_required'],
                        'already_volunteered' => $existing !== null,
                    ];
                }
            }
        }

        return ['current' => $current, 'history' => $history, 'offer' => $offer];
    }

    /**
     * Une campagne joint le grade visé sous d'autres alias : on les ramène au vocabulaire du calcul.
     *
     * @param array<string, mixed> $grade
     * @return array<string, mixed>
     */
    private function targetShape(array $grade): array
    {
        if (!isset($grade['rank_order']) && isset($grade['grade_rank_order'])) {
            $grade['rank_order'] = $grade['grade_rank_order'];
        }
        if (($grade['label'] ?? '') === '' && isset($grade['grade_label'])) {
            $grade['label'] = $grade['grade_label'];
        }
        if (array_key_exists('grade_filiere_id', $grade)) {
            $grade['filiere_id'] = $grade['grade_filiere_id'];
        }

        return $grade;
    }

    /** @return array<string, mixed> */
    private function requireCampaign(int $tenantId, int $campaignId): array
    {
        $campaign = $this->repository->findCampaign($campaignId, $tenantId);
        if ($campaign === null) {
            throw new RuntimeException('Campagne introuvable.');
        }

        return $campaign;
    }

    private function formatFr(string $iso): string
    {
        $ts = strtotime($iso);

        return $ts !== false ? date('d/m/Y', $ts) : $iso;
    }
}
