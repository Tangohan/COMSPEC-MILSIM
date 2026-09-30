<?php

declare(strict_types=1);

namespace App\Services\Advancement;

use App\Repositories\AdvancementRepository;
use App\Repositories\GradeRepository;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

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
    public const VIA_EXCEPTION = 'exception';

    public const DECISION_LISTED = 'inscrit';
    public const DECISION_NOT = 'non_inscrit';

    public function __construct(
        private AdvancementRepository $repository,
        private AdvancementEligibilityService $eligibility,
        private ?AdvancementNotifier $notifier = null,
        private ?AdvancementRankingService $ranking = null,
    ) {
        $this->notifier ??= new AdvancementNotifier();
        $this->ranking ??= new AdvancementRankingService();
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

        $passResult = null;
        $passId = (int) ($targetGrade['required_pass_id'] ?? 0);
        if ($passId > 0) {
            try {
                /** @var \App\Services\Personnel\Pass\PassService $passService */
                $passService = \App\Core\Container::get(\App\Services\Personnel\Pass\PassService::class);
                $eval = $passService->evaluatePassForUser($tenantId, $passId, $personnelId);
                $passResult = [
                    'eligible' => !empty($eval['eligible']),
                    'items' => $eval['items'] ?? [],
                    'pass_label' => trim((string) (($eval['pass']['label'] ?? '') ?: 'PASS')),
                ];
            } catch (Throwable) {
                $passResult = null;
            }
        }

        return $this->eligibility->evaluate([
            'current' => $current,
            'target' => $targetGrade,
            'qualification_award' => $award,
            'qualification_level_met' => $levelMet,
            'pass_result' => $passResult,
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
     * @param list<array{personnel_id:int, role:string}> $members
     */
    public function saveCommissionReview(int $tenantId, int $campaignId, array $rows, ?string $meetingDate = null, ?int $minutesDocumentId = null, array $members = [], int $actorId = 0): void
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
            $forced = !empty($row['exceptional_override']);
            $reason = trim((string) ($row['exceptional_reason'] ?? ''));
            if ($forced && $reason === '') {
                throw new RuntimeException('Un passage exceptionnel exige un motif pour ' . trim((string) ($existing['display_name'] ?? 'le candidat')) . '.');
            }
            $this->repository->updateCandidacyReview($id, [
                'preference_rank' => $row['preference_rank'] ?? '',
                'commission_opinion' => $opinion,
                'decision' => $decision,
                'decided_at' => $decision !== '' ? $today : null,
                'notes' => $row['notes'] ?? ($existing['notes'] ?? null),
                'mobility_requested' => !empty($row['mobility_requested']) || !empty($existing['mobility_requested']),
                'requested_billet_id' => $row['requested_billet_id'] ?? ($existing['requested_billet_id'] ?? null),
                'exceptional_override' => $forced,
                'exceptional_reason' => $reason,
                'exceptional_by' => $actorId > 0 ? $actorId : ($row['exceptional_by'] ?? null),
                'exceptional_at' => $forced ? date('Y-m-d H:i:s') : null,
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
            $forced = $this->ranker()->isForced($fresh);
            if (empty($fresh['is_eligible']) && !$forced) {
                $name = trim((string) ($row['display_name'] ?? $row['callsign'] ?? 'personnel'));
                throw new RuntimeException('Inscription impossible : ' . $name . ' n’est plus éligible (' . (string) ($fresh['eligibility_reason'] ?? 'critère non rempli') . '). Cochez le passage exceptionnel et saisissez un motif, ou retirez l’inscription.');
            }
            if ($forced) {
                $fresh['_via'] = self::VIA_EXCEPTION;
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
                    'obtained_via' => (string) ($row['_via'] ?? self::VIA_CHOICE),
                    'candidacy_id' => (int) $row['id'],
                    'created_by' => $actorId,
                ]);
                $promoted++;
            }
            $this->repository->updateCampaignStatus($campaignId, $tenantId, self::STATUS_PUBLISHED, $date);
        });

        $gradeLabel = trim((string) ($campaign['grade_label'] ?? 'grade visé'));
        foreach ($listed as $row) {
            $forced = ((string) ($row['_via'] ?? '')) === self::VIA_EXCEPTION;
            $this->notifier->notify(
                $tenantId,
                (int) $row['personnel_id'],
                $actorId,
                'Tableau d’avancement publié',
                $forced
                    ? 'Vous êtes inscrit exceptionnellement au tableau d’avancement au grade de ' . $gradeLabel . '. Motif : ' . trim((string) ($row['exceptional_reason'] ?? 'décision de commission')) . '.'
                    : 'Vous êtes inscrit au tableau d’avancement au grade de ' . $gradeLabel . ' (voie choix). Le poste demandé, s’il y en a un, n’est pas attribué par cette publication.'
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
     * @return array{
     *   current:?array<string, mixed>,
     *   history:list<array<string, mixed>>,
     *   offer:?array<string, mixed>,
     *   next:?array<string, mixed>,
     *   opinions:list<array<string, mixed>>
     * }
     */
    public function personnelPanel(int $tenantId, int $personnelId, ?DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $empty = ['current' => null, 'history' => [], 'offer' => null, 'next' => null, 'opinions' => []];
        if (!$this->repository->tablesReady()) {
            return $empty;
        }
        $this->syncHistoryFromFiche($tenantId, $personnelId, $today);
        $current = $this->withFicheDisplayLabel(
            $tenantId,
            $personnelId,
            $this->repository->activeGrade($tenantId, $personnelId)
        );
        $history = $this->repository->historyFor($tenantId, $personnelId);
        $offer = null;
        $next = null;
        if ($current !== null && empty($current['from_fiche']) && isset($current['rank_order'])) {
            $filiereId = isset($current['filiere_id']) && $current['filiere_id'] !== null && $current['filiere_id'] !== ''
                ? (int) $current['filiere_id']
                : null;
            $nextGrade = $this->repository->nextGradeAfter($tenantId, (int) $current['rank_order'], $filiereId);
            if ($nextGrade !== null) {
                $next = $this->buildNextGradeCard($tenantId, $personnelId, $nextGrade, $today);
                if (!empty($next['campaign_id'])) {
                    $offer = [
                        'campaign_id' => (int) $next['campaign_id'],
                        'grade_label' => (string) ($next['grade_label'] ?? ''),
                        'is_eligible' => !empty($next['choice_eligible']),
                        'eligibility_reason' => $next['choice_reason'] ?? null,
                        'months_in_grade' => $next['months_in_grade'] ?? 0,
                        'months_required' => $next['months_required'] ?? null,
                        'already_volunteered' => !empty($next['already_volunteered']),
                    ];
                }
            }
        }

        return [
            'current' => $current,
            'history' => $history,
            'offer' => $offer,
            'next' => $next,
            'opinions' => $this->commandOpinions($tenantId, $personnelId),
        ];
    }

    /**
     * @param array<string, mixed> $nextGrade
     * @return array<string, mixed>
     */
    private function buildNextGradeCard(int $tenantId, int $personnelId, array $nextGrade, DateTimeImmutable $today): array
    {
        $qualId = (int) ($nextGrade['required_qualification_id'] ?? 0);
        if ($qualId > 0 && trim((string) ($nextGrade['required_qualification_label'] ?? $nextGrade['required_qualification_name'] ?? '')) === '') {
            $names = $this->repository->qualificationNames($tenantId);
            $nextGrade['required_qualification_label'] = $names[$qualId] ?? 'requise';
        }
        $target = array_merge($nextGrade, [
            'grade_label' => $nextGrade['label'] ?? '',
        ]);
        $choiceEnabled = !empty($nextGrade['advancement_choice_enabled']);
        $seniorityEnabled = !empty($nextGrade['advancement_seniority_enabled']);
        $choice = $this->assessPersonnel($tenantId, $personnelId, $target, $today, AdvancementEligibilityService::PATH_CHOICE);
        $seniority = $this->assessPersonnel($tenantId, $personnelId, $target, $today, AdvancementEligibilityService::PATH_SENIORITY);
        $campaign = $this->repository->openCampaignForGrade($tenantId, (int) $nextGrade['id'], $today->format('Y-m-d'));
        $existing = $campaign !== null
            ? $this->repository->findCandidacyForPersonnel((int) $campaign['id'], $personnelId)
            : null;
        $dueOn = $choice['due_on'] ?? $seniority['due_on'] ?? null;
        $conditions = [];
        foreach (array_merge($seniority['conditions'] ?? [], $choice['conditions'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            if ($key === '' || $key === 'voie') {
                continue;
            }
            if (!isset($conditions[$key])) {
                $conditions[$key] = $row;
            }
        }
        if ($seniorityEnabled) {
            $conditions['voie_auto'] = [
                'key' => 'voie_auto',
                'label' => 'Avancement automatique',
                'met' => !empty($seniority['is_eligible']),
                'detail' => !empty($seniority['is_eligible'])
                    ? 'Les conditions sont réunies : le passage se fait à l’ancienneté, sans demande.'
                    : trim((string) ($seniority['eligibility_reason'] ?? 'Pas encore automatique.')),
            ];
        }
        if ($choiceEnabled) {
            $conditions['voie_choix'] = [
                'key' => 'voie_choix',
                'label' => 'Avancement au choix',
                'met' => !empty($choice['is_eligible']),
                'detail' => !empty($choice['is_eligible'])
                    ? 'Vous pouvez déposer une demande' . ($campaign !== null ? ' : une campagne est ouverte.' : '.')
                    : trim((string) ($choice['eligibility_reason'] ?? 'Les conditions au choix ne sont pas réunies.')),
            ];
        }

        return [
            'grade_id' => (int) $nextGrade['id'],
            'grade_label' => (string) ($nextGrade['label'] ?? ''),
            'grade_code' => (string) ($nextGrade['code'] ?? ''),
            'automatic' => $seniorityEnabled,
            'choice' => $choiceEnabled,
            'mode' => $this->nextMode($seniorityEnabled, $choiceEnabled),
            'due_on' => $dueOn,
            'months_in_grade' => (int) ($choice['months_in_grade'] ?? $seniority['months_in_grade'] ?? 0),
            'months_required' => $choice['months_required'] ?? $seniority['months_required'] ?? null,
            'choice_eligible' => !empty($choice['is_eligible']),
            'seniority_eligible' => !empty($seniority['is_eligible']),
            'choice_reason' => $choice['eligibility_reason'] ?? null,
            'seniority_reason' => $seniority['eligibility_reason'] ?? null,
            'conditions' => array_values($conditions),
            'campaign_id' => $campaign !== null ? (int) $campaign['id'] : null,
            'already_volunteered' => $existing !== null,
        ];
    }

    private function nextMode(bool $automatic, bool $choice): string
    {
        if ($automatic && $choice) {
            return 'both';
        }
        if ($automatic) {
            return 'automatic';
        }
        if ($choice) {
            return 'choice';
        }

        return 'none';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function commandOpinions(int $tenantId, int $personnelId): array
    {
        $out = [];
        foreach ($this->repository->listCandidaciesForPersonnel($tenantId, $personnelId) as $row) {
            $opinion = trim((string) ($row['commission_opinion'] ?? ''));
            $decision = trim((string) ($row['decision'] ?? ''));
            $out[] = [
                'campaign_id' => (int) ($row['campaign_id'] ?? 0),
                'year' => (int) ($row['year'] ?? 0),
                'grade_label' => (string) ($row['grade_label'] ?? ''),
                'campaign_status' => (string) ($row['campaign_status'] ?? ''),
                'commission_opinion' => $opinion,
                'opinion_label' => \App\Support\AdvancementCodes::opinionLabel($opinion !== '' ? $opinion : null),
                'decision' => $decision,
                'decision_label' => \App\Support\AdvancementCodes::decisionLabel($decision !== '' ? $decision : null),
                'volunteered_at' => (string) ($row['volunteered_at'] ?? ''),
                'notes' => trim((string) ($row['notes'] ?? '')),
                'is_eligible' => !empty($row['is_eligible']),
                'pending' => $opinion === '',
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function decorateCandidacies(int $tenantId, array $campaign, array $rows, ?DateTimeImmutable $today = null): array
    {
        foreach ($rows as &$row) {
            $result = $this->assessPersonnel($tenantId, (int) $row['personnel_id'], $campaign, $today, AdvancementEligibilityService::PATH_CHOICE);
            $row['months_in_grade'] = $result['months_in_grade'];
            $row['months_required'] = $result['months_required'];
            $row['due_on'] = $result['due_on'];
            $row['live_eligible'] = $result['is_eligible'];
            $row['live_reason'] = $result['eligibility_reason'];
            $row['is_forced'] = $this->ranker()->isForced($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $members
     * @return array{ranks: array<int, int>, detections: list<array{code:string, level:string, message:string}>}
     */
    public function autoRankCandidacies(int $tenantId, int $campaignId, array $members = []): array
    {
        $campaign = $this->requireCampaign($tenantId, $campaignId);
        $rows = $this->decorateCandidacies($tenantId, $campaign, $this->repository->listCandidacies($campaignId));
        $quota = isset($campaign['quota_slots']) && $campaign['quota_slots'] !== null && $campaign['quota_slots'] !== ''
            ? (int) $campaign['quota_slots']
            : null;
        $out = $this->ranker()->proposeCandidacyOrder($rows, $members, $quota);
        if ($out['ranks'] !== []) {
            $this->repository->updatePreferenceRanks($campaignId, $out['ranks']);
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $members
     * @return list<array{code:string, level:string, message:string}>
     */
    public function detectCandidacies(int $tenantId, array $campaign, array $rows, array $members = []): array
    {
        $quota = isset($campaign['quota_slots']) && $campaign['quota_slots'] !== null && $campaign['quota_slots'] !== ''
            ? (int) $campaign['quota_slots']
            : null;
        $proposed = $this->ranker()->proposeCandidacyOrder($rows, $members, $quota);

        return $this->ranker()->detectCandidacies($rows, $members, $quota, $proposed['ranks']);
    }

    /**
     * @return array{reordered:int, detections: list<array{code:string, level:string, message:string}>}
     */
    public function autoOrderGrades(int $tenantId): array
    {
        $grades = $this->repository->listGrades($tenantId, true);
        $proposal = $this->ranker()->proposeGradeOrder($grades);
        if ($proposal['order'] !== []) {
            $this->repository->reorderGrades($tenantId, $proposal['order']);
        }

        return ['reordered' => count($proposal['order']), 'detections' => $proposal['detections']];
    }

    /**
     * @return array{order: list<int>, detections: list<array{code:string, level:string, message:string}>}
     */
    public function detectGradeOrder(int $tenantId): array
    {
        return $this->ranker()->proposeGradeOrder($this->repository->listGrades($tenantId, true));
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

    private function ranker(): AdvancementRankingService
    {
        return $this->ranking ??= new AdvancementRankingService();
    }

    private function formatFr(string $iso): string
    {
        $ts = strtotime($iso);

        return $ts !== false ? date('d/m/Y', $ts) : $iso;
    }

    /**
     * Si la fiche a un grade (users.grade_id / titre) et qu’aucun historique n’existe,
     * on réutilise ces données pour ouvrir la première ligne d’avancement.
     */
    private function syncHistoryFromFiche(int $tenantId, int $personnelId, DateTimeImmutable $today): void
    {
        if ($this->repository->historyFor($tenantId, $personnelId) !== []) {
            return;
        }
        $fiche = $this->ficheGradeSnapshot($tenantId, $personnelId);
        if ($fiche === null) {
            return;
        }
        try {
            (new GradeScaleTemplateService($this->repository))->completeForTenant($tenantId);
        } catch (Throwable) {
        }
        $definition = $this->matchFicheToDefinition($tenantId, $fiche);
        if ($definition === null || !empty($definition['archived_at'])) {
            return;
        }
        $this->repository->insertHistory([
            'personnel_id' => $personnelId,
            'grade_id' => (int) $definition['id'],
            'obtained_at' => $fiche['obtained_at'] ?? $today->format('Y-m-d'),
            'obtained_via' => self::VIA_INITIAL,
            'created_by' => $personnelId,
        ]);
    }

    /**
     * @param array<string, mixed>|null $current
     * @return array<string, mixed>|null
     */
    private function withFicheDisplayLabel(int $tenantId, int $personnelId, ?array $current): ?array
    {
        $fiche = $this->ficheGradeSnapshot($tenantId, $personnelId);
        $title = trim((string) ($fiche['rank_display'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($fiche['label'] ?? ''));
        }
        if ($current !== null) {
            if ($title !== '') {
                $current['label'] = $title;
            }

            return $current;
        }
        if ($fiche === null || ($title === '' && trim((string) ($fiche['code'] ?? '')) === '')) {
            return null;
        }

        return [
            'label' => $title !== '' ? $title : (string) $fiche['code'],
            'code' => (string) ($fiche['code'] ?? ''),
            'obtained_at' => (string) ($fiche['obtained_at'] ?? date('Y-m-d')),
            'obtained_via' => self::VIA_INITIAL,
            'from_fiche' => true,
        ];
    }

    /**
     * @return array{grade_id:int, code:string, label:string, rank_display:string, obtained_at:string}|null
     */
    private function ficheGradeSnapshot(int $tenantId, int $personnelId): ?array
    {
        $pdo = $this->repository->pdo();
        $gradeId = 0;
        $obtainedAt = date('Y-m-d');
        try {
            $st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $st->execute([$personnelId]);
            $user = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
            $gradeId = (int) ($user['grade_id'] ?? 0);
            $created = substr((string) ($user['created_at'] ?? ''), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $created) === 1) {
                $obtainedAt = $created;
            }
        } catch (Throwable) {
        }
        try {
            $st = $pdo->prepare(
                'SELECT grade_id FROM user_community_profiles WHERE user_id = ? AND tenant_id = ? LIMIT 1'
            );
            $st->execute([$personnelId, $tenantId]);
            $communityGrade = (int) ($st->fetchColumn() ?: 0);
            if ($communityGrade > 0) {
                $gradeId = $communityGrade;
            }
        } catch (Throwable) {
        }
        $rankDisplay = '';
        try {
            $st = $pdo->prepare('SELECT rank_display FROM personnel_profiles WHERE user_id = ? LIMIT 1');
            $st->execute([$personnelId]);
            $rankDisplay = trim((string) ($st->fetchColumn() ?: ''));
        } catch (Throwable) {
        }
        $code = '';
        $label = '';
        if ($gradeId > 0) {
            $catalog = $this->catalogGradeById($pdo, $gradeId, $tenantId);
            if ($catalog !== null) {
                $code = strtoupper(trim((string) ($catalog['code'] ?? '')));
                $label = trim((string) ($catalog['label_long'] ?? $catalog['label_short'] ?? $catalog['label'] ?? ''));
            }
        }
        if ($gradeId < 1 && $rankDisplay === '' && $code === '' && $label === '') {
            return null;
        }

        return [
            'grade_id' => $gradeId,
            'code' => $code,
            'label' => $label,
            'rank_display' => $rankDisplay,
            'obtained_at' => $obtainedAt,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function catalogGradeById(\PDO $pdo, int $gradeId, int $tenantId): ?array
    {
        foreach (['grades', 'grades_referentiel'] as $table) {
            try {
                $st = $pdo->prepare(
                    'SELECT id, code, label_long, label_short FROM ' . $table . ' WHERE id = ? LIMIT 1'
                );
                $st->execute([$gradeId]);
                $row = $st->fetch(\PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return $row;
                }
            } catch (Throwable) {
            }
        }
        try {
            return (new GradeRepository())->findById($gradeId, $tenantId);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array{code?:string, label?:string, rank_display?:string} $fiche
     * @return array<string, mixed>|null
     */
    private function matchFicheToDefinition(int $tenantId, array $fiche): ?array
    {
        $code = strtoupper(trim((string) ($fiche['code'] ?? '')));
        if ($code !== '') {
            $byCode = $this->repository->findGradeByCode($tenantId, $code);
            if ($byCode !== null) {
                return $byCode;
            }
        }
        $needles = [];
        foreach ([$fiche['rank_display'] ?? '', $fiche['label'] ?? ''] as $raw) {
            $text = trim((string) $raw);
            if ($text !== '') {
                $needles[mb_strtolower($text)] = true;
            }
        }
        if ($needles === []) {
            return null;
        }
        foreach ($this->repository->listGrades($tenantId, false) as $grade) {
            foreach ([$grade['label'] ?? '', $grade['short_label'] ?? ''] as $raw) {
                $text = mb_strtolower(trim((string) $raw));
                if ($text !== '' && isset($needles[$text])) {
                    return $grade;
                }
            }
        }

        return null;
    }
}
