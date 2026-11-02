<?php

declare(strict_types=1);

namespace App\Services\Advancement;

use App\Repositories\AdvancementProposalRepository;
use App\Repositories\AdvancementRepository;
use App\Repositories\TenantRepository;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Propositions automatiques d’une campagne (ou d’un grade) : rassemble les données réelles de chaque membre
 * du grade précédent, les passe au moteur déterministe et suit les propositions retenues ou écartées.
 * Retenir une proposition crée la candidature (avec la justification) ; rien n’est publié sans la commission.
 */
final class AdvancementProposalService
{
    /** @var array<int, array<string, mixed>> */
    private array $context = [];

    public function __construct(
        private AdvancementRepository $repository,
        private AdvancementWorkflowService $workflow,
        private AdvancementProposalRepository $signals,
        private ?AdvancementProposalEngine $engine = null,
        private ?TenantRepository $tenants = null,
    ) {
        $this->engine ??= new AdvancementProposalEngine();
    }

    /** @return array<string, int> */
    public function settings(int $tenantId): array
    {
        try {
            $raw = ($this->tenants ??= new TenantRepository())->getSettings($tenantId);
        } catch (Throwable) {
            $raw = [];
        }

        return AdvancementProposalEngine::settings($raw);
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, int>
     */
    public function saveSettings(int $tenantId, array $raw): array
    {
        $clean = AdvancementProposalEngine::sanitize($raw);
        ($this->tenants ??= new TenantRepository())->mergeSettings($tenantId, [AdvancementProposalEngine::SETTINGS_KEY => $clean]);
        unset($this->context[$tenantId]);

        return $clean;
    }

    /**
     * Membres du grade précédent le grade visé, évalués et classés.
     *
     * @param array<string, mixed> $target grade (listGrades) ou campagne (findCampaign)
     * @return array{rows:list<array<string, mixed>>, counts:array<string, int>, previous_label:string}
     */
    public function forTarget(int $tenantId, array $target, ?int $campaignId = null, ?DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $ctx = $this->context($tenantId, $today);
        $settings = $ctx['settings'];
        $rankOrder = (int) ($target['grade_rank_order'] ?? $target['rank_order'] ?? 0);
        $filiere = array_key_exists('grade_filiere_id', $target) ? $target['grade_filiere_id'] : ($target['filiere_id'] ?? null);
        $filiere = $filiere === null || $filiere === '' ? null : (int) $filiere;

        $candidates = [];
        $decisions = [];
        if ($campaignId !== null && $campaignId > 0) {
            foreach ($this->repository->listCandidacies($campaignId) as $row) {
                $candidates[(int) $row['personnel_id']] = true;
            }
            $decisions = $this->signals->listDecisions($tenantId, $campaignId);
        }

        $population = [];
        $previousLabel = '';
        foreach ($ctx['histories'] as $history) {
            $pid = (int) ($history['personnel_id'] ?? 0);
            if (!isset($ctx['members'][$pid]) || (int) ($history['rank_order'] ?? 0) !== $rankOrder - 1) {
                continue;
            }
            $hf = $history['filiere_id'] ?? null;
            $hf = $hf === null || $hf === '' ? null : (int) $hf;
            if ($hf !== $filiere) {
                continue;
            }
            $population[$pid] = $history;
            if ($previousLabel === '') {
                $previousLabel = trim((string) ($history['label'] ?? ''));
            }
        }

        $rows = [];
        $counts = [
            'population' => 0,
            AdvancementProposalEngine::STATUS_PROPOSE => 0,
            AdvancementProposalEngine::STATUS_REVIEW => 0,
            AdvancementProposalEngine::STATUS_BLOCKED => 0,
            AdvancementProposalEngine::STATUS_INELIGIBLE => 0,
            'near' => 0,
            'candidates' => 0,
            'dismissed' => 0,
            'pending' => 0,
        ];
        foreach ($population as $pid => $history) {
            $member = $ctx['members'][$pid];
            try {
                $eligibility = $this->workflow->assessPersonnel($tenantId, $pid, $target, $today, AdvancementEligibilityService::PATH_CHOICE);
            } catch (Throwable) {
                continue;
            }
            $proposal = $this->engine->evaluate($settings, $this->facts($ctx, $pid, $eligibility, $today), $today);
            $decision = $decisions[$pid] ?? null;
            $isCandidate = isset($candidates[$pid]);
            $rows[] = [
                'personnel_id' => $pid,
                'name' => $this->name($member),
                'callsign' => $member['callsign'],
                'current_label' => trim((string) ($history['label'] ?? '')),
                'obtained_at' => (string) ($history['obtained_at'] ?? ''),
                'months_in_grade' => (int) ($eligibility['months_in_grade'] ?? 0),
                'eligibility' => $eligibility,
                'proposal' => $proposal,
                'is_candidate' => $isCandidate,
                'decision' => $decision,
            ];
            $counts['population']++;
            $counts[$proposal['status']]++;
            if (!empty($proposal['near'])) {
                $counts['near']++;
            }
            if ($isCandidate) {
                $counts['candidates']++;
            } elseif (($decision['decision'] ?? '') === AdvancementProposalRepository::DECISION_DISMISSED) {
                $counts['dismissed']++;
            } elseif ($proposal['status'] === AdvancementProposalEngine::STATUS_PROPOSE) {
                $counts['pending']++;
            }
        }

        return ['rows' => $this->engine->rank($rows), 'counts' => $counts, 'previous_label' => $previousLabel];
    }

    /**
     * Ce que détecte le système pour chaque grade ouvert au choix (page d’accueil de l’avancement).
     *
     * @param list<array<string, mixed>> $campaigns
     * @return list<array<string, mixed>>
     */
    public function overview(int $tenantId, array $campaigns, ?DateTimeImmutable $today = null): array
    {
        $running = [];
        foreach ($campaigns as $campaign) {
            $status = (string) ($campaign['status'] ?? '');
            if (!in_array($status, [AdvancementWorkflowService::STATUS_PUBLISHED, AdvancementWorkflowService::STATUS_ARCHIVED], true)) {
                $running[(int) ($campaign['grade_id'] ?? 0)] ??= $campaign;
            }
        }
        $out = [];
        foreach ($this->repository->listGrades($tenantId, false) as $grade) {
            if (empty($grade['advancement_choice_enabled'])) {
                continue;
            }
            $result = $this->forTarget($tenantId, $grade, null, $today);
            $out[] = [
                'grade' => $grade,
                'previous_label' => $result['previous_label'],
                'counts' => $result['counts'],
                'campaign' => $running[(int) $grade['id']] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Sources de données réellement disponibles dans la communauté (affichées au gestionnaire).
     *
     * @return list<array{key:string, label:string, measured:bool, detail:string}>
     */
    public function sources(int $tenantId, ?DateTimeImmutable $today = null): array
    {
        $ctx = $this->context($tenantId, ($today ?? new DateTimeImmutable('today'))->setTime(0, 0));
        $window = (int) $ctx['settings']['window_days'];
        $graded = [];
        foreach ($ctx['histories'] as $history) {
            $pid = (int) ($history['personnel_id'] ?? 0);
            if (isset($ctx['members'][$pid])) {
                $graded[$pid] = true;
            }
        }
        $ungraded = count($ctx['members']) - count($graded);
        $sessions = $ctx['sessions'];
        $events = $ctx['events'];

        return [
            [
                'key' => 'grade_time',
                'label' => 'Temps dans le grade',
                'measured' => $graded !== [],
                'detail' => $ungraded > 0
                    ? count($graded) . ' membre(s) avec un grade enregistré, ' . $ungraded . ' sans grade dans l’historique (non pris en compte)'
                    : count($graded) . ' membre(s) avec un grade enregistré',
            ],
            [
                'key' => 'seniority',
                'label' => 'Ancienneté dans la communauté',
                'measured' => true,
                'detail' => 'Date d’engagement de la fiche, sinon date de création du compte',
            ],
            [
                'key' => 'presences',
                'label' => 'Présences',
                'measured' => !empty($sessions['measured']) || !empty($events['measured']),
                'detail' => (!empty($sessions['measured']) || !empty($events['measured']))
                    ? 'Sessions Arma pointées et événements pointés, sur ' . $window . ' jours'
                    : 'Aucune session Arma ni événement pointé sur ' . $window . ' jours : critère non compté',
            ],
            [
                'key' => 'hours',
                'label' => 'Heures de jeu',
                'measured' => !empty($sessions['hours_measured']),
                'detail' => !empty($sessions['hours_measured'])
                    ? 'Temps validé des sessions Arma, sur ' . $window . ' jours'
                    : 'Aucun temps de jeu validé dans les sessions Arma : critère non compté',
            ],
            [
                'key' => 'activity',
                'label' => 'Activité récente',
                'measured' => (bool) $ctx['activity_measured'],
                'detail' => $ctx['activity_measured'] ? (string) $ctx['activity_basis'] : 'Aucune trace d’activité disponible : critère non compté',
            ],
            [
                'key' => 'conduct',
                'label' => 'Conduite',
                'measured' => $ctx['sanctions'] !== null,
                'detail' => $ctx['sanctions'] !== null ? 'Mesures de la page Restrictions membres' : 'Module des restrictions membres indisponible : critère non compté',
            ],
            [
                'key' => 'absences',
                'label' => 'Absences déclarées',
                'measured' => $ctx['absences'] !== null,
                'detail' => $ctx['absences'] !== null ? 'Une absence en cours suspend le critère d’inactivité' : 'Absences non disponibles',
            ],
        ];
    }

    public function accept(int $tenantId, int $campaignId, int $personnelId, string $justification, int $actorId, ?DateTimeImmutable $today = null): void
    {
        $campaign = $this->requireCampaign($tenantId, $campaignId);
        $row = $this->rowFor($tenantId, $campaign, $personnelId, $today);
        if ($row === null) {
            throw new RuntimeException('Ce membre ne fait pas partie des propositions de la campagne.');
        }
        if (!empty($row['is_candidate'])) {
            throw new RuntimeException('Ce membre est déjà candidat dans cette campagne.');
        }
        $justification = trim($justification) !== '' ? trim($justification) : (string) $row['proposal']['justification'];
        $this->workflow->createCandidacy($tenantId, $campaignId, $personnelId, $actorId, ['notes' => mb_substr($justification, 0, 2000)], $today);
        if ($this->signals->decisionsReady()) {
            $this->signals->saveDecision($tenantId, $campaignId, $personnelId, AdvancementProposalRepository::DECISION_KEPT, (int) $row['proposal']['score'], $justification, $actorId);
        }
    }

    /**
     * Retient toutes les propositions « Proposé » encore en attente.
     *
     * @return array{accepted:int, errors:list<string>}
     */
    public function acceptAll(int $tenantId, int $campaignId, int $actorId, ?DateTimeImmutable $today = null): array
    {
        $campaign = $this->requireCampaign($tenantId, $campaignId);
        $result = $this->forTarget($tenantId, $campaign, $campaignId, $today);
        $accepted = 0;
        $errors = [];
        foreach ($result['rows'] as $row) {
            if ($row['proposal']['status'] !== AdvancementProposalEngine::STATUS_PROPOSE || !empty($row['is_candidate']) || $row['decision'] !== null) {
                continue;
            }
            try {
                $this->workflow->createCandidacy($tenantId, $campaignId, (int) $row['personnel_id'], $actorId, ['notes' => (string) $row['proposal']['justification']], $today);
                if ($this->signals->decisionsReady()) {
                    $this->signals->saveDecision($tenantId, $campaignId, (int) $row['personnel_id'], AdvancementProposalRepository::DECISION_KEPT, (int) $row['proposal']['score'], (string) $row['proposal']['justification'], $actorId);
                }
                $accepted++;
            } catch (Throwable $e) {
                $errors[] = $row['name'] . ' : ' . $e->getMessage();
            }
        }

        return ['accepted' => $accepted, 'errors' => $errors];
    }

    public function dismiss(int $tenantId, int $campaignId, int $personnelId, string $reason, int $actorId, ?DateTimeImmutable $today = null): void
    {
        if (!$this->signals->decisionsReady()) {
            throw new RuntimeException('Écarter une proposition arrive avec la prochaine mise à jour du site.');
        }
        $campaign = $this->requireCampaign($tenantId, $campaignId);
        $row = $this->rowFor($tenantId, $campaign, $personnelId, $today);
        if ($row === null) {
            throw new RuntimeException('Ce membre ne fait pas partie des propositions de la campagne.');
        }
        $this->signals->saveDecision($tenantId, $campaignId, $personnelId, AdvancementProposalRepository::DECISION_DISMISSED, (int) $row['proposal']['score'], trim($reason), $actorId);
    }

    public function restore(int $tenantId, int $campaignId, int $personnelId): void
    {
        $this->requireCampaign($tenantId, $campaignId);
        if ($this->signals->decisionsReady()) {
            $this->signals->deleteDecision($tenantId, $campaignId, $personnelId);
        }
    }

    /**
     * @param array<string, mixed> $ctx
     * @param array<string, mixed> $eligibility
     * @return array<string, mixed>
     */
    private function facts(array $ctx, int $pid, array $eligibility, DateTimeImmutable $today): array
    {
        $settings = $ctx['settings'];
        $windowStart = $today->modify('-' . (int) $settings['window_days'] . ' days')->format('Y-m-d');
        $member = $ctx['members'][$pid];
        $session = $ctx['sessions']['by_user'][$pid] ?? null;
        $event = $ctx['events']['by_user'][$pid] ?? null;

        $presences = null;
        if (!empty($ctx['sessions']['measured']) || !empty($ctx['events']['measured'])) {
            $days = array_merge(array_keys($session['days'] ?? []), array_keys($event['days'] ?? []));
            $presences = count(array_filter(array_unique($days), static fn (string $d): bool => strcmp($d, $windowStart) >= 0));
        }
        $hours = null;
        if (!empty($ctx['sessions']['hours_measured'])) {
            $seconds = 0;
            foreach ($session['seconds_by_day'] ?? [] as $day => $s) {
                if (strcmp((string) $day, $windowStart) >= 0) {
                    $seconds += (int) $s;
                }
            }
            $hours = round($seconds / 3600, 2);
        }

        $last = null;
        $source = '';
        if ($ctx['game_activity']) {
            foreach ([
                [$session['last'] ?? null, 'session Arma'],
                [$event['last'] ?? null, 'événement pointé'],
                [$ctx['reports'][$pid] ?? null, 'temps de jeu remonté par le mod'],
            ] as [$day, $label]) {
                if ($day !== null && ($last === null || strcmp((string) $day, $last) > 0)) {
                    $last = (string) $day;
                    $source = $label;
                }
            }
        } elseif ($ctx['activity_measured'] && $member['last_login_at'] !== null) {
            $last = $member['last_login_at'];
            $source = 'connexion au site';
        }

        $seniority = null;
        if ($member['joined_on'] !== null) {
            try {
                $seniority = (new AdvancementEligibilityService())->monthsCompleted(new DateTimeImmutable($member['joined_on']), $today);
            } catch (Throwable) {
                $seniority = null;
            }
        }

        return [
            'eligibility' => $eligibility,
            'seniority_months' => $seniority,
            'presences' => $presences,
            'hours' => $hours,
            'last_activity' => $last,
            'last_activity_source' => $source,
            'activity_measured' => $ctx['activity_measured'],
            'sanctions' => $ctx['sanctions'] !== null ? ($ctx['sanctions'][$pid] ?? []) : null,
            'absent_until' => $ctx['absences'][$pid] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function context(int $tenantId, DateTimeImmutable $today): array
    {
        $key = $today->format('Y-m-d');
        if (isset($this->context[$tenantId]) && $this->context[$tenantId]['day'] === $key) {
            return $this->context[$tenantId];
        }
        $settings = $this->settings($tenantId);
        $span = max((int) $settings['window_days'], (int) $settings['inactivity_days']) + 1;
        $since = $today->modify('-' . $span . ' days')->format('Y-m-d');
        $members = $this->signals->activeMembers($tenantId);
        $sessions = $this->signals->sessionSignals($tenantId, $since) ?? ['measured' => false, 'hours_measured' => false, 'by_user' => []];
        $events = $this->signals->eventSignals($tenantId, $since, $key) ?? ['measured' => false, 'by_user' => []];
        $reports = $this->signals->lastGameReports($tenantId) ?? [];
        $gameActivity = !empty($sessions['measured']) || !empty($events['measured']) || $reports !== [];
        $logins = false;
        foreach ($members as $member) {
            if ($member['last_login_at'] !== null) {
                $logins = true;
                break;
            }
        }
        $basis = $gameActivity
            ? 'Dernière session Arma, dernier événement pointé ou dernier temps de jeu remonté par le mod'
            : 'Dernière connexion au site (aucune donnée de jeu dans la communauté)';
        try {
            $histories = $this->repository->listActiveHistories($tenantId);
        } catch (Throwable) {
            $histories = [];
        }
        usort($histories, static fn (array $a, array $b): int => strcmp((string) ($a['obtained_at'] ?? ''), (string) ($b['obtained_at'] ?? '')));
        $latest = [];
        foreach ($histories as $history) {
            $latest[(int) ($history['personnel_id'] ?? 0)] = $history;
        }

        return $this->context[$tenantId] = [
            'day' => $key,
            'settings' => $settings,
            'members' => $members,
            'histories' => array_values($latest),
            'sessions' => $sessions,
            'events' => $events,
            'reports' => $reports,
            'game_activity' => $gameActivity,
            'activity_measured' => $gameActivity || $logins,
            'activity_basis' => $basis,
            'sanctions' => $this->signals->sanctions($tenantId),
            'absences' => $this->signals->activeAbsences($tenantId, $key),
        ];
    }

    /**
     * @param array<string, mixed> $campaign
     * @return array<string, mixed>|null
     */
    private function rowFor(int $tenantId, array $campaign, int $personnelId, ?DateTimeImmutable $today): ?array
    {
        foreach ($this->forTarget($tenantId, $campaign, (int) $campaign['id'], $today)['rows'] as $row) {
            if ((int) $row['personnel_id'] === $personnelId) {
                return $row;
            }
        }

        return null;
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

    /** @param array<string, mixed> $member */
    private function name(array $member): string
    {
        foreach (['display_name', 'callsign', 'email'] as $key) {
            $value = trim((string) ($member[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Membre';
    }
}
