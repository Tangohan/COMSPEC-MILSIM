<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Request;
use App\Services\Moderation\ModerationRestrictionResolver;
use App\Services\Moderation\ModerationStatusPresenter;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\AuthService;
use App\Repositories\UserRepository;
use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelServiceHistoryRepository;
use App\Repositories\UnitRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAdminPanelRepository;
use App\Repositories\PersonnelAdminDataRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Services\Training\TrainingService;
use App\Core\Csrf;
use App\Services\Personnel\MatriculeService;
use App\Services\Personnel\PersonnelCompletenessService;
use App\Repositories\UserProfileDisplaySettingsRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserLegalIdentityRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PlanningEntryRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\ArmaPlaytimeRepository;
use App\Repositories\PersonnelOrgHistoryRepository;
use App\Repositories\PersonnelRoleplayTimelineRepository;
use App\Services\Personnel\SenioritySummaryService;
use App\Services\Steam\SteamWebApiService;
use App\Core\Gate;
use App\Support\OrbatRosterPayload;

class PersonnelController
{
    /** @return array{enabled: bool, optional: bool, stages: list<string>, recruitment_tracks: list<string>, eligibility: array<string,mixed>} */
    private function roleplayFollowupConfig(int $tenantId): array
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $cfg = is_array($community['roleplay_followup'] ?? null) ? $community['roleplay_followup'] : [];
        $stages = [];
        foreach (($cfg['stages'] ?? []) as $s) {
            $v = trim((string) $s);
            if ($v !== '') {
                $stages[] = $v;
            }
        }
        if ($stages === []) {
            $stages = ['Pré-qualification', 'Tutorat', 'Validation', 'Intégration active'];
        }
        $tracks = [];
        foreach (($cfg['recruitment_tracks'] ?? []) as $s) {
            $v = trim((string) $s);
            if ($v !== '') {
                $tracks[] = $v;
            }
        }

        return [
            'enabled' => !empty($cfg['enabled']),
            'optional' => !empty($cfg['optional']),
            'stages' => array_values(array_unique($stages)),
            'recruitment_tracks' => array_values(array_unique($tracks)),
            'eligibility' => is_array($cfg['eligibility'] ?? null) ? $cfg['eligibility'] : [],
        ];
    }

    /** @return array{eligible: bool, checks: list<array{label: string, ok: bool}>} */
    private function roleplayFollowupEligibility(array $cfg, int $completenessScore, ?int $readinessScore, array $personnelProfile): array
    {
        $rules = is_array($cfg['eligibility'] ?? null) ? $cfg['eligibility'] : [];
        $checks = [];
        $checks[] = [
            'label' => 'Complétude dossier ≥ ' . max(0, min(100, (int) ($rules['min_completeness'] ?? 50))) . '%',
            'ok' => $completenessScore >= max(0, min(100, (int) ($rules['min_completeness'] ?? 50))),
        ];
        $checks[] = [
            'label' => 'Disponibilité ≥ ' . max(0, min(100, (int) ($rules['min_readiness'] ?? 30))) . '%',
            'ok' => ($readinessScore ?? 0) >= max(0, min(100, (int) ($rules['min_readiness'] ?? 30))),
        ];
        if (!empty($rules['require_unit'])) {
            $checks[] = ['label' => 'Affectation unité renseignée', 'ok' => !empty($personnelProfile['primary_unit_id'])];
        }
        if (!empty($rules['require_callsign'])) {
            $checks[] = ['label' => 'Callsign renseigné', 'ok' => trim((string) ($personnelProfile['callsign'] ?? '')) !== ''];
        }
        if (!empty($rules['require_tutor'])) {
            $checks[] = ['label' => 'Tuteur affecté', 'ok' => (int) ($personnelProfile['rp_tutor_user_id'] ?? 0) > 0];
        }
        $eligible = true;
        foreach ($checks as $c) {
            if (empty($c['ok'])) {
                $eligible = false;
                break;
            }
        }

        return ['eligible' => $eligible, 'checks' => $checks];
    }

    /** Résout /personnel/{id} (numérique) ou /personnel/{slug} (profile_slug). */
    private function resolvePersonnelTarget(string $raw, int $tenantId, ?int $fallbackUserId = null): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return $fallbackUserId !== null ? $this->userRepository->findById($fallbackUserId, $tenantId) : null;
        }
        if (ctype_digit($raw)) {
            return $this->userRepository->findById((int) $raw, $tenantId);
        }

        return $this->userRepository->findByProfileSlug($tenantId, $raw);
    }

    /** Segment d’URL pour les redirections (slug préféré). */
    private function personPathSegment(array $userRow): string
    {
        $slug = trim((string) ($userRow['profile_slug'] ?? ''));

        return $slug !== '' ? $slug : (string) ($userRow['id'] ?? '');
    }

    /**
     * Prénom/nom : `user_profiles`, sinon dernière candidature d’enrôlement, sinon découpage prudent du nom d’affichage.
     *
     * @return array{first_name: string, last_name: string, source: ?string}
     */
    private function resolveCivilIdentity(?array $userProfile, array $targetUser, ?array $enlistment): array
    {
        $up = $userProfile ?? [];
        $fn = trim((string) ($up['first_name'] ?? ''));
        $ln = trim((string) ($up['last_name'] ?? ''));
        $source = ($fn !== '' || $ln !== '') ? 'profile' : null;

        if ($fn === '' && $ln === '' && $enlistment !== null) {
            $fn = trim((string) ($enlistment['first_name'] ?? ''));
            $ln = trim((string) ($enlistment['last_name'] ?? ''));
            if ($fn !== '' || $ln !== '') {
                $source = 'enlistment';
            }
        }

        if ($fn === '' && $ln === '') {
            $dn = trim((string) ($targetUser['display_name'] ?? ''));
            if ($dn !== '') {
                $parts = preg_split('/\s+/u', $dn, 2, PREG_SPLIT_NO_EMPTY);
                if ($parts !== false && $parts !== []) {
                    $fn = $parts[0];
                    $ln = isset($parts[1]) ? trim($parts[1]) : '';
                    $source = 'display_name';
                }
            }
        }

        return ['first_name' => $fn, 'last_name' => $ln, 'source' => $source];
    }

    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private PersonnelExtrasRepository $personnelExtrasRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelQualificationRepository $personnelQualificationRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private PersonnelServiceHistoryRepository $personnelServiceHistoryRepository,
        private UnitRepository $unitRepository,
        private GradeRepository $gradeRepository,
        private PersonnelAdminPanelRepository $adminPanelRepository,
        private PersonnelAdminDataRepository $adminDataRepository,
        private TrainingCertificateRepository $trainingCertificateRepository,
        private TrainingEnrollmentRepository $trainingEnrollmentRepository,
        private TrainingService $trainingService,
        private MatriculeService $matriculeService,
        private PersonnelCompletenessService $completenessService,
        private UserProfileDisplaySettingsRepository $displaySettingsRepository,
        private UserProfileRepository $userProfileRepository,
        private UserLegalIdentityRepository $userLegalIdentityRepository,
        private EnlistmentRepository $enlistmentRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private PlanningEntryRepository $planningEntryRepository,
        private RoleRepository $roleRepository,
        private TenantRepository $tenantRepository,
        private SenioritySummaryService $senioritySummaryService,
        private ArmaPlaytimeRepository $armaPlaytimeRepository,
        private SteamWebApiService $steamWebApiService,
        private PersonnelOrgHistoryRepository $personnelOrgHistoryRepository,
        private PersonnelRoleplayTimelineRepository $personnelRoleplayTimelineRepository,
    ) {}

    private function formatArmaPlaytimeFrench(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'Pas encore de temps enregistré';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($h > 0) {
            return $m > 0 ? "{$h} h {$m} min" : "{$h} h";
        }
        if ($m > 0) {
            return "{$m} min";
        }

        return 'Moins d’une minute';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadDossierPresets(): array
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'personnel_dossier_presets.php';
        if (!is_file($path)) {
            return [];
        }
        $data = require $path;

        return is_array($data) ? $data : [];
    }

    public function tutorials(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        if (!$user || !$tenantId) {
            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'content' => 'personnel.tutorials',
            'title' => 'Tutoriels — dossier personnel',
        ]);
    }

    public function personnelIndex(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        if (!$user || $tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->canStaffViewPersonnel()) {
            return Response::redirect(url('personnel/me'));
        }

        $query = trim((string) $request->query('q', ''));
        $results = $query !== ''
            ? $this->userRepository->searchForPortal($tenantId, $query, 120)
            : $this->userRepository->listForPersonnelDirectory($tenantId, 120);

        return Response::view('layout.main', [
            'content' => 'personnel.directory',
            'title' => 'Annuaire des profils',
            'query' => $query,
            'results' => $results,
        ]);
    }

    public function me(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        return $this->show($request, ['id' => (string) $user['id']]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $raw = (string) ($params['id'] ?? '');
        $target = $this->resolvePersonnelTarget($raw, (int) $tenantId);
        if (!$target) {
            return (new Response())->setStatusCode(404)->setBody('Utilisateur non trouvé.');
        }

        $currentUser = $this->authService->user();
        $currentUserId = $currentUser ? (int) $currentUser['id'] : 0;
        $isSelf = $currentUserId === (int) $target['id'];
        $uid = (int) $target['id'];

        if ($isSelf) {
            $this->personnelProfileRepository->ensureRecord($uid);
            $this->personnelExtrasRepository->ensureRecord($uid);
            $this->userProfileRepository->ensureRow($uid);
        }

        $extras = $this->personnelExtrasRepository->getByUserId($uid) ?? [];
        $profile = $this->personnelExtrasRepository->getProfileByUserId($uid);
        $personnelProfile = $this->personnelProfileRepository->getByUserId($uid);
        $latestEnlistment = $this->enlistmentRepository->findLatestBySubmitter((int) $tenantId, $uid);
        $civilIdentity = $this->resolveCivilIdentity($profile, $target, $latestEnlistment);
        $civilSourceLabel = match ($civilIdentity['source'] ?? null) {
            'profile' => 'Profil compte (préférences)',
            'enlistment' => 'Candidature d’enrôlement',
            'display_name' => 'Nom d’affichage (découpage)',
            default => '',
        };

        $mergedProfileForScore = $profile !== null ? $profile : [];
        if ($civilIdentity['first_name'] !== '') {
            $mergedProfileForScore['first_name'] = $civilIdentity['first_name'];
        }
        if ($civilIdentity['last_name'] !== '') {
            $mergedProfileForScore['last_name'] = $civilIdentity['last_name'];
        }

        $assignments = $this->personnelAssignmentRepository->listActiveForUserResolved($uid);
        $assignments = $this->personnelAssignmentRepository->enrichAssignmentHistoryWithDurations($assignments);
        $primaryAssignment = $assignments[0] ?? null;
        $primaryUnitFallbackName = null;
        if ($primaryAssignment === null && !empty($personnelProfile['primary_unit_id'])) {
            $urow = $this->unitRepository->findById((int) $personnelProfile['primary_unit_id'], (int) $tenantId);
            if ($urow) {
                $primaryUnitFallbackName = (string) ($urow['name'] ?? '');
            }
        }

        $personnelAssignmentHistory = [];
        $personnelAssignmentHistoryUnitTotals = [];
        if ($this->personnelAssignmentRepository->personnelAssignmentsTableExists()) {
            $histRaw = $this->personnelAssignmentRepository->listAssignmentHistoryForTenantUser((int) $tenantId, $uid, 120);
            $personnelAssignmentHistory = $this->personnelAssignmentRepository->enrichAssignmentHistoryWithDurations($histRaw);
            $personnelAssignmentHistoryUnitTotals = $this->personnelAssignmentRepository->sumDurationDaysByUnit($personnelAssignmentHistory);
        }

        $commander = null;
        if (!empty($primaryAssignment['commander_user_id'])) {
            $commander = $this->userRepository->findById((int) $primaryAssignment['commander_user_id'], (int) $tenantId);
        }

        $commanderLabelsById = [];
        $seenCommanderIds = [];
        foreach (array_merge($assignments, $personnelAssignmentHistory) as $a) {
            $cid = (int) ($a['commander_user_id'] ?? 0);
            if ($cid < 1 || isset($seenCommanderIds[$cid])) {
                continue;
            }
            $seenCommanderIds[$cid] = true;
            $cu = $this->userRepository->findById($cid, (int) $tenantId);
            if ($cu) {
                $dn = trim((string) ($cu['display_name'] ?? ''));
                $cs = trim((string) ($cu['callsign'] ?? ''));
                $em = trim((string) ($cu['email'] ?? ''));
                $commanderLabelsById[$cid] = $dn !== '' ? $dn : ($cs !== '' ? $cs : $em);
            } else {
                $commanderLabelsById[$cid] = '—';
            }
        }

        $personnelJobRoleAssignments = [];
        if ($this->personnelJobRoleRepository->tablesExist() && $this->personnelJobRoleRepository->pivotTableExists()) {
            $pivotMap = $this->personnelJobRoleRepository->listPivotAssignmentsForUsers((int) $tenantId, [$uid]);
            $personnelJobRoleAssignments = $pivotMap[$uid] ?? [];
        }

        $qualifications = $this->personnelQualificationRepository->listForUser($uid);
        $serviceHistory = $this->personnelServiceHistoryRepository->listForUser($uid);
        $trainingCertificates = $this->trainingCertificateRepository->listByUserId($uid, (int) $tenantId);
        $lmsEnrollmentsForPersonnel = [];
        foreach ($this->trainingEnrollmentRepository->listByUserId($uid, (int) $tenantId) as $enr) {
            $st = (string) ($enr['status'] ?? '');
            $enr['progress_percent'] = $st === 'pending_approval'
                ? 0.0
                : $this->trainingService->getGlobalProgress((int) $enr['id']);
            $lmsEnrollmentsForPersonnel[] = $enr;
        }
        usort($lmsEnrollmentsForPersonnel, static function (array $a, array $b): int {
            $rank = static function (array $x): int {
                return match ($x['status'] ?? '') {
                    'in_progress' => 0,
                    'pending_approval' => 1,
                    'assigned' => 2,
                    'completed' => 3,
                    'failed' => 4,
                    'withdrawn' => 5,
                    'revoked' => 6,
                    'expired' => 7,
                    default => 9,
                };
            };
            $ra = $rank($a);
            $rb = $rank($b);
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcmp((string) ($b['assigned_at'] ?? ''), (string) ($a['assigned_at'] ?? ''));
        });
        $completeness = $this->completenessService->getScore($uid, $target, $mergedProfileForScore, $extras, (int) $tenantId);
        $roleplayFollowupConfig = $this->roleplayFollowupConfig((int) $tenantId);
        $roleplayEligibility = $this->roleplayFollowupEligibility(
            $roleplayFollowupConfig,
            (int) ($completeness['score'] ?? 0),
            isset($personnelProfile['readiness_score']) ? (int) $personnelProfile['readiness_score'] : null,
            is_array($personnelProfile) ? $personnelProfile : []
        );
        $rpTutorLabel = null;
        $rpTutorId = (int) ($personnelProfile['rp_tutor_user_id'] ?? 0);
        if ($rpTutorId > 0) {
            $rpTutor = $this->userRepository->findById($rpTutorId, (int) $tenantId);
            if ($rpTutor) {
                $rpTutorLabel = trim((string) ($rpTutor['display_name'] ?? '')) ?: (trim((string) ($rpTutor['callsign'] ?? '')) ?: trim((string) ($rpTutor['email'] ?? '')));
            }
        }
        $grades = $this->gradeRepository->listForTenant((int) $tenantId);
        $grade = null;
        if (!empty($target['grade_id'])) {
            foreach ($grades as $g) {
                if ((int) $g['id'] === (int) $target['grade_id']) {
                    $grade = $g;
                    break;
                }
            }
        }
        $adminPanels = $this->adminPanelRepository->listForTenant((int) $tenantId);
        $adminDataByPanel = $this->adminDataRepository->getAllForUser($uid);

        $rpDossierNeedsAttention = $isSelf && trim((string) ($personnelProfile['character_name'] ?? '')) === '';
        $canStaffEdit = $this->canStaffEditPersonnel();
        $canStaffView = $this->canStaffViewPersonnel();
        $canSensitive = $this->canViewSensitivePersonnel();
        $roleplayTimelineEvents = [];
        if ($roleplayFollowupConfig['enabled'] && ($isSelf || $canStaffView || $canStaffEdit) && $this->personnelRoleplayTimelineRepository->tableExists()) {
            $roleplayTimelineEvents = $this->personnelRoleplayTimelineRepository->listForUser((int) $tenantId, $uid, 80);
        }
        $isForumMod = function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator();
        /** Lecture identité civile, état civil, dossier recrutement détaillé : titulaire + staff / RH habilités (pas les autres membres). */
        $privatePersonnelIdentity = $isSelf || $canStaffView || $canStaffEdit || $canSensitive;
        $canEditNotes = $isSelf || $canStaffEdit;
        $canEditProfile = $isSelf || $canStaffEdit;
        $canViewCivil = $privatePersonnelIdentity;
        $canViewCommandNotes = $isSelf || $canStaffEdit;
        $displaySettings = $this->displaySettingsRepository->getOrDefaults((int) $target['id']);
        $hidePersonalInfo = (int) ($displaySettings['hide_personal_info'] ?? 0) === 1;
        $viewerPrivilegedForPersonal = $isSelf || $canStaffView || $canStaffEdit || $isForumMod;
        $redactPersonalPresentation = $hidePersonalInfo && !$viewerPrivilegedForPersonal;
        $canViewCivilSection = $canViewCivil && !$redactPersonalPresentation;
        $showEmailInContact = !$redactPersonalPresentation && $privatePersonnelIdentity;
        $showMatriculePublic = $isSelf || $canStaffView || $canSensitive || $isForumMod || (int) ($displaySettings['fiche_show_matricule_to_others'] ?? 1) === 1;
        if (!$privatePersonnelIdentity) {
            $adminPanels = array_values(array_filter($adminPanels, static function (array $p): bool {
                return strtolower(trim((string) ($p['slug'] ?? ''))) !== 'etat-civil';
            }));
        }

        /** @var ModerationRestrictionResolver $modResolver */
        $modResolver = Container::get(ModerationRestrictionResolver::class);
        $modSet = $modResolver->getActiveSet((int) $tenantId, $uid);
        $personnelModerationStaffLines = $canStaffView ? ModerationStatusPresenter::linesForStaff($modSet) : [];
        $personnelModerationMemberBrief = ($isSelf && !$redactPersonalPresentation) ? ModerationStatusPresenter::briefForMember($modSet) : null;

        $personnelPlanningEntries = [];
        if (($isSelf || $canStaffView) && $this->planningEntryRepository->isOperationalBoardSchemaReady()) {
            $personnelPlanningEntries = $this->planningEntryRepository->listActiveEntriesForAssignedUser((int) $tenantId, $uid, 15);
        }
        $gateInst = Gate::getInstance();
        $canViewOperationalBoardLink = $gateInst->allows('admin.organization')
            || $gateInst->allows('admin.access')
            || $gateInst->allows('site.support');

        $senioritySummary = ($isSelf || $canStaffView)
            ? $this->senioritySummaryService->personnelSenioritySummary((int) $tenantId, $uid)
            : ['global' => null, 'detail' => []];
        $seniorityGlobal = $senioritySummary['global'] ?? null;
        $seniorityDetailLines = is_array($senioritySummary['detail'] ?? null) ? $senioritySummary['detail'] : [];

        $steamIdResolved = trim((string) ($target['steam_id'] ?? ''));
        $steamIdResolved = $steamIdResolved !== '' ? $steamIdResolved : null;

        $steamProfileSyncOffered = $this->steamWebApiService->isConfigured()
            && $steamIdResolved !== null
            && $canEditProfile;

        $armaPlaytime = null;
        if ($isSelf || $canStaffView) {
            $ready = $this->armaPlaytimeRepository->schemaReady();
            $armaPlaytime = [
                'show_steam_hint_self' => $isSelf && $steamIdResolved === null,
                'no_steam_staff' => !$isSelf && $steamIdResolved === null,
                'schema_ready' => $ready,
                'hours_label' => null,
                'last_sync_label' => null,
            ];
            if ($steamIdResolved !== null && $ready) {
                $ptRow = $this->armaPlaytimeRepository->getSummaryForUser((int) $tenantId, $uid);
                $ptSecs = $ptRow !== null ? (int) ($ptRow['total_seconds'] ?? 0) : 0;
                $armaPlaytime['hours_label'] = $this->formatArmaPlaytimeFrench($ptSecs);
                if ($ptRow && !empty($ptRow['last_report_at'])) {
                    $tsPt = strtotime((string) $ptRow['last_report_at']);
                    if ($tsPt) {
                        $armaPlaytime['last_sync_label'] = date('d/m/Y \à H:i', $tsPt);
                    }
                }
            }
        }

        $communityRoleLabel = null;
        $roleId = (int) ($target['role_id'] ?? 0);
        if ($roleId > 0) {
            $roleRow = $this->roleRepository->findById($roleId, (int) $tenantId)
                ?? $this->roleRepository->findById($roleId, null);
            if ($roleRow) {
                $n = trim((string) ($roleRow['name'] ?? ''));
                $communityRoleLabel = $n !== '' ? $n : null;
            }
        }

        $personnelOrgHistory = [];
        $personnelOrgHistorySection = ($isSelf || $canStaffView) && $this->personnelOrgHistoryRepository->schemaReady();
        if ($personnelOrgHistorySection) {
            $histRows = $this->personnelOrgHistoryRepository->listForUser((int) $tenantId, $uid, 25);
            foreach ($histRows as &$hRow) {
                $hRow['actor_label'] = null;
                $aid = isset($hRow['actor_user_id']) ? (int) $hRow['actor_user_id'] : 0;
                if ($aid > 0) {
                    $au = $this->userRepository->findById($aid, (int) $tenantId);
                    if ($au) {
                        $dn = trim((string) ($au['display_name'] ?? ''));
                        $cs = trim((string) ($au['callsign'] ?? ''));
                        $hRow['actor_label'] = $dn !== '' ? $dn : ($cs !== '' ? $cs : 'Référent');
                    }
                }
            }
            unset($hRow);
            $personnelOrgHistory = $histRows;
        }

        $qualificationIssuerLabels = [];
        $issuerIds = [];
        foreach ($qualifications as $q) {
            $ib = (int) ($q['issued_by'] ?? 0);
            if ($ib > 0) {
                $issuerIds[$ib] = true;
            }
        }
        foreach (array_keys($issuerIds) as $issuerUserId) {
            $iu = $this->userRepository->findById($issuerUserId, (int) $tenantId);
            if ($iu) {
                $dn = trim((string) ($iu['display_name'] ?? ''));
                $cs = trim((string) ($iu['callsign'] ?? ''));
                $em = trim((string) ($iu['email'] ?? ''));
                $qualificationIssuerLabels[$issuerUserId] = $dn !== '' ? $dn : ($cs !== '' ? $cs : $em);
            } else {
                $qualificationIssuerLabels[$issuerUserId] = 'Référent inconnu';
            }
        }

        return Response::view('layout.main', [
            'content' => 'personnel.file',
            'title' => 'Fiche personnel',
            'targetUser' => $target,
            'personnelExtras' => $extras,
            'personnelProfile' => $personnelProfile,
            'userProfile' => $profile,
            'civilIdentity' => $civilIdentity,
            'civilSourceLabel' => $civilSourceLabel,
            'latestEnlistment' => $latestEnlistment,
            'primaryUnitFallbackName' => $primaryUnitFallbackName,
            'rpDossierNeedsAttention' => $rpDossierNeedsAttention,
            'grade' => $grade,
            'grades' => $grades,
            'assignments' => $assignments,
            'personnelAssignmentHistory' => $personnelAssignmentHistory,
            'personnelAssignmentHistoryUnitTotals' => $personnelAssignmentHistoryUnitTotals,
            'primaryAssignment' => $primaryAssignment,
            'commander' => $commander,
            'commanderLabelsById' => $commanderLabelsById,
            'personnelJobRoleAssignments' => $personnelJobRoleAssignments,
            'personnelPlanningEntries' => $personnelPlanningEntries,
            'canViewOperationalBoardLink' => $canViewOperationalBoardLink,
            'qualifications' => $qualifications,
            'serviceHistory' => $serviceHistory,
            'trainingCertificates' => $trainingCertificates,
            'lmsEnrollmentsForPersonnel' => $lmsEnrollmentsForPersonnel,
            'completeness' => $completeness,
            'adminPanels' => $adminPanels,
            'adminDataByPanel' => $adminDataByPanel,
            'canEditNotes' => $canEditNotes,
            'canEditProfile' => $canEditProfile,
            'canViewCivil' => $canViewCivil,
            'canViewCivilSection' => $canViewCivilSection,
            'privatePersonnelIdentity' => $privatePersonnelIdentity,
            'redactPersonalPresentation' => $redactPersonalPresentation,
            'canViewCommandNotes' => $canViewCommandNotes,
            'displaySettings' => $displaySettings,
            'showEmailInContact' => $showEmailInContact,
            'showMatriculePublic' => $showMatriculePublic,
            'personnelModerationStaffLines' => $personnelModerationStaffLines,
            'personnelModerationMemberBrief' => $personnelModerationMemberBrief,
            'seniorityGlobal' => is_array($seniorityGlobal) ? $seniorityGlobal : null,
            'seniorityDetailLines' => $seniorityDetailLines,
            'armaPlaytime' => $armaPlaytime,
            'steamProfileSyncOffered' => $steamProfileSyncOffered,
            'personnelOrgHistory' => $personnelOrgHistory,
            'personnelOrgHistorySection' => $personnelOrgHistorySection,
            'personnelIsSelf' => $isSelf,
            'communityRoleLabel' => $communityRoleLabel,
            'qualificationIssuerLabels' => $qualificationIssuerLabels,
            'roleplayFollowupConfig' => $roleplayFollowupConfig,
            'roleplayEligibility' => $roleplayEligibility,
            'rpTutorLabel' => $rpTutorLabel,
            'roleplayTimelineEvents' => $roleplayTimelineEvents,
        ]);
    }

    public function generateMatricule(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $raw = (string) ($params['id'] ?? '');
        $target = $this->resolvePersonnelTarget($raw, $tenantId);
        if (!$target) {
            return (new Response())->setStatusCode(404)->setBody('Utilisateur non trouvé.');
        }
        $currentUserId = (int) Session::get('user_id');
        $isSelf = ($currentUserId === (int) $target['id']);
        $gate = Gate::getInstance();
        if (!$isSelf && !$this->canStaffEditPersonnel() && !$gate->allows('personnel.grades.manage')) {
            return (new Response())->setStatusCode(403)->setBody('Non autorisé.');
        }
        $this->matriculeService->assignNextForUser((int) $target['id'], $tenantId);
        Session::flash('success', 'Matricule attribué.');
        $returnTo = trim((string) ($request->input('return_to') ?? ''));
        if ($returnTo === 'edit') {
            $redirect = url('personnel/' . $this->personPathSegment($target) . '/edit');
        } else {
            $redirect = $isSelf ? url('personnel/me') : url('personnel/' . $this->personPathSegment($target));
        }
        return Response::redirect($redirect);
    }

    public function updateNotes(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $raw = (string) ($params['id'] ?? '');
        $target = $this->resolvePersonnelTarget($raw, $tenantId);
        if (!$target) {
            return (new Response())->setStatusCode(404)->setBody('Utilisateur non trouvé.');
        }
        $currentUserId = (int) Session::get('user_id');
        $isSelf = ($currentUserId === (int) $target['id']);
        if (!$isSelf && !$this->canStaffEditPersonnel()) {
            return (new Response())->setStatusCode(403)->setBody('Non autorisé.');
        }
        $notes = trim((string) ($request->input('admin_notes') ?? ''));
        $this->personnelExtrasRepository->updateAdminNotes((int) $target['id'], $notes);
        $this->personnelProfileRepository->updateCommandNotes((int) $target['id'], $notes);
        $redirect = $isSelf ? url('personnel/me') : url('personnel/' . $this->personPathSegment($target));
        return Response::redirect($redirect);
    }

    public function orbat(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tid = (int) $tenantId;
        $gate = Gate::getInstance();
        if (!$gate->allows('organization.orbat.view')) {
            Session::flash('error', 'Vous n’avez pas accès à l’organigramme des unités.');

            return Response::redirect(url('dashboard'));
        }
        $orbatCanManage = $gate->allows('admin.organization') || $gate->allows('admin.access')
            || $gate->allows('organization.orbat.manage');
        $viewerId = (int) Session::get('user_id');
        $rosterData = OrbatRosterPayload::buildForTenant(
            $this->unitRepository,
            $tid,
            $viewerId,
            $orbatCanManage
        );
        $orbatCommanderOptions = [];
        if ($orbatCanManage) {
            foreach ($this->userRepository->allForTenant($tid) as $u) {
                if (($u['status'] ?? '') !== 'active') {
                    continue;
                }
                $id = (int) ($u['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $dn = trim((string) ($u['display_name'] ?? ''));
                $cs = trim((string) ($u['callsign'] ?? ''));
                $em = trim((string) ($u['email'] ?? ''));
                $label = $dn !== '' ? $dn : ($cs !== '' ? $cs : $em);
                if ($label === '') {
                    $label = 'Compte #' . $id;
                }
                $orbatCommanderOptions[] = ['id' => $id, 'label' => $label];
            }
        }

        return Response::view('layout.main', [
            'content' => 'personnel.orbat',
            'title' => 'ORBAT',
            'orbatRosterData' => $rosterData,
            'orbatCanManage' => $orbatCanManage,
            'orbatCommanderOptions' => $orbatCommanderOptions,
            'orbatCsrfToken' => Csrf::token(),
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $currentUser = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$currentUser) {
            return Response::redirect(url('login'));
        }
        $raw = (string) ($params['id'] ?? '');
        $target = $this->resolvePersonnelTarget($raw, $tenantId, (int) $currentUser['id']);
        if (!$target) {
            return (new Response())->setStatusCode(404)->setBody('Utilisateur non trouvé.');
        }
        $currentUserId = (int) $currentUser['id'];
        $isSelf = ($currentUserId === (int) $target['id']);
        if (!$isSelf && !$this->canStaffEditPersonnel()) {
            return (new Response())->setStatusCode(403)->setBody('Non autorisé.');
        }
        $uid = (int) $target['id'];
        $personnelProfile = $this->personnelProfileRepository->getByUserId($uid);
        $displaySettings = $this->displaySettingsRepository->getOrDefaults($uid);
        $units = $this->unitRepository->allForTenant($tenantId);
        $userProfile = $this->userProfileRepository->getByUserId($uid) ?? [];
        $legalIdentity = $this->userLegalIdentityRepository->getByUserId($uid) ?? [];
        if ($legalIdentity !== []) {
            $userProfile['first_name'] = $legalIdentity['first_name'] ?? ($userProfile['first_name'] ?? '');
            $userProfile['last_name'] = $legalIdentity['last_name'] ?? ($userProfile['last_name'] ?? '');
            $userProfile['phone'] = $legalIdentity['phone'] ?? ($userProfile['phone'] ?? '');
            $userProfile['birth_date'] = $legalIdentity['birth_date'] ?? ($userProfile['birth_date'] ?? '');
            $userProfile['nationality'] = $legalIdentity['nationality'] ?? ($userProfile['nationality'] ?? '');
        }
        $personnelExtras = $this->personnelExtrasRepository->getByUserId($uid) ?? [];
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $currentGrade = null;
        if (!empty($target['grade_id'])) {
            foreach ($grades as $g) {
                if ((int) $g['id'] === (int) $target['grade_id']) {
                    $currentGrade = $g;
                    break;
                }
            }
        }
        $completeness = $this->completenessService->getScoreWithMissingLabels($uid, $target, $userProfile, $personnelExtras, $tenantId);
        $matricule = $personnelProfile['matricule_internal'] ?? $personnelExtras['service_number'] ?? null;
        $personnelAssignments = $this->personnelAssignmentRepository->listActiveForUserResolved($uid);
        $dossierPresets = $this->loadDossierPresets();
        $jobRolesEnabled = $this->personnelJobRoleRepository->tablesExist();
        $jobRoleOptions = $jobRolesEnabled ? $this->personnelJobRoleRepository->listRoleOptionsForSelect($tenantId) : [];
        $jobRoleSlugToId = [];
        if ($jobRolesEnabled) {
            foreach ($this->personnelJobRoleRepository->listRolesWithCategory($tenantId) as $jr) {
                $slug = trim((string) ($jr['slug'] ?? ''));
                if ($slug !== '') {
                    $jobRoleSlugToId[$slug] = (int) $jr['id'];
                }
            }
        }

        $forumQuickMode = trim((string) $request->query('forum_mode', ''));
        if (!in_array($forumQuickMode, ['display_name', 'callsign', 'character_name', 'forum_alias'], true)) {
            $forumQuickMode = '';
        }
        $forumFocus = trim((string) $request->query('forum_focus', ''));
        if (!in_array($forumFocus, ['label', 'hide_level'], true)) {
            $forumFocus = '';
        }
        $forumPreHideLevel = (string) $request->query('forum_hide_level', '') === '1';

        $targetEmail = strtolower(trim((string) ($target['email'] ?? '')));
        $siteRoleRepo = Container::get(\App\Repositories\SiteRoleAssignmentRepository::class);
        $forumOrgRoleChoices = function_exists('forum_build_visible_role_choices')
            ? forum_build_visible_role_choices(
                $uid,
                $tenantId,
                $targetEmail,
                $this->userRepository,
                $this->roleRepository,
                $siteRoleRepo
            )
            : [];

        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $memberCanChooseDisplayRole = !empty($community['member_can_choose_display_role']);
        $roleplayFollowupConfig = $this->roleplayFollowupConfig($tenantId);
        $rpTutorChoices = [];
        $roleplayEventTypes = ['entretien', 'medical', 'rotation', 'formation', 'recrutement', 'tutorat', 'administratif'];
        if ($roleplayFollowupConfig['enabled'] && ($isSelf || $this->canStaffEditPersonnel())) {
            foreach ($this->userRepository->allForTenant($tenantId) as $u) {
                if (($u['status'] ?? 'active') !== 'active') {
                    continue;
                }
                $id = (int) ($u['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $label = trim((string) ($u['display_name'] ?? '')) ?: (trim((string) ($u['callsign'] ?? '')) ?: trim((string) ($u['email'] ?? '')));
                if ($label === '') {
                    $label = 'Compte #' . $id;
                }
                $rpTutorChoices[] = ['id' => $id, 'label' => $label];
            }
        }

        return Response::view('layout.main', [
            'content' => 'personnel.edit',
            'title' => 'Éditer le dossier',
            'targetUser' => $target,
            'personnelProfile' => $personnelProfile,
            'displaySettings' => $displaySettings,
            'forumQuickMode' => $forumQuickMode,
            'forumFocus' => $forumFocus,
            'forumPreHideLevel' => $forumPreHideLevel,
            'units' => $units,
            'userProfile' => $userProfile,
            'grades' => $grades,
            'currentGrade' => $currentGrade,
            'completeness' => $completeness,
            'matriculeDisplay' => $matricule,
            'personnelAssignments' => $personnelAssignments,
            'dossierPresets' => $dossierPresets,
            'jobRolesEnabled' => $jobRolesEnabled,
            'jobRoleOptions' => $jobRoleOptions,
            'jobRoleSlugToId' => $jobRoleSlugToId,
            'forumOrgRoleChoices' => $forumOrgRoleChoices,
            'memberCanChooseDisplayRole' => $memberCanChooseDisplayRole,
            'roleplayFollowupConfig' => $roleplayFollowupConfig,
            'rpTutorChoices' => $rpTutorChoices,
            'roleplayEventTypes' => $roleplayEventTypes,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $currentUser = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$currentUser) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('personnel/me'));
        }
        $raw = (string) ($params['id'] ?? '');
        $target = $this->resolvePersonnelTarget($raw, $tenantId, (int) $currentUser['id']);
        if (!$target) {
            return (new Response())->setStatusCode(404)->setBody('Utilisateur non trouvé.');
        }
        $currentUserId = (int) $currentUser['id'];
        $isSelf = ($currentUserId === (int) $target['id']);
        $canStaffEdit = $this->canStaffEditPersonnel();
        if (!$isSelf && !$canStaffEdit) {
            return (new Response())->setStatusCode(403)->setBody('Non autorisé.');
        }
        $clearanceReview = trim((string) $request->input('clearance_reviewed_at'));
        $readinessRaw = $request->input('readiness_score');
        $readinessScore = ($readinessRaw === null || $readinessRaw === '') ? null : max(0, min(100, (int) $readinessRaw));
        $roleplayFollowupConfig = $this->roleplayFollowupConfig($tenantId);
        $existingProfile = $this->personnelProfileRepository->getByUserId((int) $target['id']) ?? [];

        $primaryUnitIdRaw = $request->input('primary_unit_id');
        $primaryUnitId = $primaryUnitIdRaw ? (int) $primaryUnitIdRaw : null;
        if ($primaryUnitId !== null && $primaryUnitId > 0) {
            $unitRow = $this->unitRepository->findById($primaryUnitId, $tenantId);
            if (!$unitRow) {
                Session::flash('error', 'Unité sélectionnée introuvable pour cette communauté.');
                return Response::redirect(url('personnel/' . $this->personPathSegment($target) . '/edit'));
            }
        } else {
            $primaryUnitId = null;
        }

        $jobRolesEnabled = $this->personnelJobRoleRepository->tablesExist();
        $primaryRoleStr = '';
        $jobRoleId = null;
        $roleSubLabel = trim((string) $request->input('role_sub_label'));
        if ($jobRolesEnabled) {
            $rawJr = $request->input('personnel_job_role_id');
            $jobRoleId = ($rawJr === null || $rawJr === '') ? null : (int) $rawJr;
            if ($jobRoleId !== null && $jobRoleId <= 0) {
                $jobRoleId = null;
            }
            $jrRow = null;
            if ($jobRoleId !== null) {
                $jrRow = $this->personnelJobRoleRepository->findRoleById($jobRoleId, $tenantId);
                if (!$jrRow) {
                    Session::flash('error', 'Rôle métier invalide pour cette communauté.');

                    return Response::redirect(url('personnel/' . $this->personPathSegment($target) . '/edit'));
                }
            }
            if ($jrRow !== null) {
                $n = trim((string) ($jrRow['name'] ?? ''));
                $primaryRoleStr = $roleSubLabel !== '' ? $n . ' — ' . $roleSubLabel : $n;
            } else {
                $primaryRoleStr = $roleSubLabel;
            }
            if (function_exists('mb_strlen') && mb_strlen($primaryRoleStr) > 100) {
                $primaryRoleStr = mb_substr($primaryRoleStr, 0, 100);
            } elseif (strlen($primaryRoleStr) > 100) {
                $primaryRoleStr = substr($primaryRoleStr, 0, 100);
            }
        } else {
            $primaryRoleStr = trim((string) $request->input('primary_role'));
        }

        $data = [
            'character_name' => trim((string) $request->input('character_name')),
            'callsign' => trim((string) $request->input('callsign')),
            'primary_role' => $primaryRoleStr,
            'secondary_role' => trim((string) $request->input('secondary_role')),
            'primary_unit_id' => $primaryUnitId,
            'clearance_level' => trim((string) $request->input('clearance_level')),
            'clearance_reviewed_at' => $clearanceReview !== '' ? $clearanceReview : null,
            'readiness_score' => $readinessScore !== null ? $readinessScore : 0,
            'enlistment_date' => trim((string) $request->input('enlistment_date')) ?: null,
            'equipment_class' => trim((string) $request->input('equipment_class')),
            'kit_assigned' => trim((string) $request->input('kit_assigned')),
            'radio_assigned' => trim((string) $request->input('radio_assigned')),
            'vehicle_authorized' => trim((string) $request->input('vehicle_authorized')),
            'weapon_specialty' => trim((string) $request->input('weapon_specialty')),
            'deployable' => (int) $request->input('deployable', 1) ? 1 : 0,
            'rank_display' => trim((string) $request->input('rank_display')) ?: null,
            'rank_display_override' => trim((string) $request->input('rank_display_override')) ?: null,
            'motto' => trim((string) $request->input('motto')) ?: null,
            'languages' => trim((string) $request->input('languages')) ?: null,
            'nationality' => trim((string) $request->input('nationality_rp')) ?: null,
            'blood_type' => trim((string) $request->input('blood_type')) ?: null,
        ];
        if ($roleplayFollowupConfig['enabled']) {
            $stage = trim((string) $request->input('rp_followup_stage'));
            if ($stage !== '' && !in_array($stage, $roleplayFollowupConfig['stages'], true)) {
                $stage = '';
            }
            $stream = trim((string) $request->input('rp_recruitment_stream'));
            if ($stream !== '' && !in_array($stream, $roleplayFollowupConfig['recruitment_tracks'], true)) {
                $stream = '';
            }
            $progressRaw = $request->input('rp_followup_progress');
            $progress = ($progressRaw === null || $progressRaw === '') ? null : max(0, min(100, (int) $progressRaw));
            $tutorRaw = $request->input('rp_tutor_user_id');
            $tutorId = ($tutorRaw === null || $tutorRaw === '') ? null : (int) $tutorRaw;
            if ($tutorId !== null && $tutorId > 0) {
                $tu = $this->userRepository->findById($tutorId, $tenantId);
                if (!$tu) {
                    $tutorId = null;
                }
            } else {
                $tutorId = null;
            }
            $elig = $this->roleplayFollowupEligibility(
                $roleplayFollowupConfig,
                (int) ($this->completenessService->getScore((int) $target['id'], $target, $this->userProfileRepository->getByUserId((int) $target['id']) ?? [], $this->personnelExtrasRepository->getByUserId((int) $target['id']) ?? [], $tenantId)['score'] ?? 0),
                $readinessScore,
                ['primary_unit_id' => $primaryUnitId, 'callsign' => trim((string) $request->input('callsign')), 'rp_tutor_user_id' => $tutorId]
            );
            $snap = [
                'at' => date('c'),
                'eligible' => $elig['eligible'],
                'checks' => $elig['checks'],
            ];
            $data['rp_followup_stage'] = $stage !== '' ? $stage : null;
            $data['rp_followup_status'] = trim((string) $request->input('rp_followup_status')) ?: null;
            $data['rp_followup_progress'] = $progress;
            $data['rp_tutor_user_id'] = $tutorId;
            $data['rp_recruitment_stream'] = $stream !== '' ? $stream : null;
            $data['rp_next_interview_date'] = trim((string) $request->input('rp_next_interview_date')) ?: null;
            $data['rp_medical_due_date'] = trim((string) $request->input('rp_medical_due_date')) ?: null;
            $data['rp_service_rotation_date'] = trim((string) $request->input('rp_service_rotation_date')) ?: null;
            $data['rp_followup_notes'] = trim((string) $request->input('rp_followup_notes')) ?: null;
            $data['rp_eligibility_snapshot_json'] = json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($jobRolesEnabled) {
            $data['personnel_job_role_id'] = $jobRoleId;
            $data['role_sub_label'] = $roleSubLabel !== '' ? $roleSubLabel : null;
        }
        if ($isSelf || $canStaffEdit) {
            $notes = trim((string) $request->input('command_notes'));
            $data['command_notes'] = $notes;
            $this->personnelExtrasRepository->updateAdminNotes((int) $target['id'], $notes);
        }
        $this->personnelProfileRepository->update((int) $target['id'], $data);
        if ($roleplayFollowupConfig['enabled']) {
            $actorId = (int) Session::get('user_id');
            $newStage = (string) ($data['rp_followup_stage'] ?? '');
            $oldStage = trim((string) ($existingProfile['rp_followup_stage'] ?? ''));
            if ($newStage !== '' && $newStage !== $oldStage) {
                $this->personnelRoleplayTimelineRepository->addEvent(
                    $tenantId,
                    (int) $target['id'],
                    'stage',
                    'Changement d’étape RP',
                    'Nouvelle étape : ' . $newStage,
                    date('Y-m-d'),
                    null,
                    'completed',
                    null,
                    $actorId > 0 ? $actorId : null
                );
            }
            $newTutorId = isset($data['rp_tutor_user_id']) ? (int) $data['rp_tutor_user_id'] : 0;
            $oldTutorId = (int) ($existingProfile['rp_tutor_user_id'] ?? 0);
            if ($newTutorId > 0 && $newTutorId !== $oldTutorId) {
                $tu = $this->userRepository->findById($newTutorId, $tenantId);
                $tuLabel = $tu ? (trim((string) ($tu['display_name'] ?? '')) ?: trim((string) ($tu['callsign'] ?? ''))) : ('#' . $newTutorId);
                $this->personnelRoleplayTimelineRepository->addEvent(
                    $tenantId,
                    (int) $target['id'],
                    'tutorat',
                    'Affectation tuteur',
                    'Tuteur assigné : ' . $tuLabel,
                    date('Y-m-d'),
                    null,
                    'completed',
                    null,
                    $actorId > 0 ? $actorId : null
                );
            }
            $dateFieldMap = [
                'rp_next_interview_date' => ['entretien', 'Planification entretien individuel'],
                'rp_medical_due_date' => ['medical', 'Planification visite médicale'],
                'rp_service_rotation_date' => ['rotation', 'Planification rotation de service'],
            ];
            foreach ($dateFieldMap as $key => [$type, $title]) {
                $old = trim((string) ($existingProfile[$key] ?? ''));
                $new = trim((string) ($data[$key] ?? ''));
                if ($new !== '' && $new !== $old) {
                    $this->personnelRoleplayTimelineRepository->addEvent(
                        $tenantId,
                        (int) $target['id'],
                        $type,
                        $title,
                        null,
                        date('Y-m-d'),
                        $new,
                        'planned',
                        null,
                        $actorId > 0 ? $actorId : null
                    );
                }
            }
            $manualTitle = trim((string) $request->input('rp_timeline_title'));
            if ($manualTitle !== '') {
                $manualType = trim((string) $request->input('rp_timeline_type'));
                if ($manualType === '') {
                    $manualType = 'administratif';
                }
                $manualStatus = trim((string) $request->input('rp_timeline_status'));
                if ($manualStatus === '') {
                    $manualStatus = 'planned';
                }
                $manualDeltaRaw = $request->input('rp_timeline_progress_delta');
                $manualDelta = ($manualDeltaRaw === null || $manualDeltaRaw === '') ? null : (int) $manualDeltaRaw;
                $this->personnelRoleplayTimelineRepository->addEvent(
                    $tenantId,
                    (int) $target['id'],
                    $manualType,
                    $manualTitle,
                    trim((string) $request->input('rp_timeline_detail')) ?: null,
                    trim((string) $request->input('rp_timeline_event_date')) ?: date('Y-m-d'),
                    trim((string) $request->input('rp_timeline_due_date')) ?: null,
                    $manualStatus,
                    $manualDelta,
                    $actorId > 0 ? $actorId : null
                );
            }
        }

        if ($jobRolesEnabled && $this->personnelJobRoleRepository->pivotTableExists()) {
            try {
                if ($jobRoleId !== null && $jobRoleId > 0) {
                    $this->personnelJobRoleRepository->replaceUserPivotJobRoles($tenantId, (int) $target['id'], [[
                        'personnel_job_role_id' => $jobRoleId,
                        'role_detail' => $roleSubLabel,
                        'is_primary' => true,
                    ]]);
                } else {
                    $this->personnelJobRoleRepository->replaceUserPivotJobRoles($tenantId, (int) $target['id'], []);
                }
            } catch (\Throwable) {
            }
        }

        $assignmentRole = $primaryRoleStr;
        try {
            $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier((int) $target['id'], $primaryUnitId, $assignmentRole);
        } catch (\Throwable) {
            Session::flash('error', 'Le dossier a été enregistré, mais la synchronisation ORBAT / affectation a échoué. Réessayez ou contactez un administrateur.');
            return Response::redirect(url('personnel/' . $this->personPathSegment($target) . '/edit'));
        }

        if ($isSelf) {
            $this->userProfileRepository->ensureRow((int) $target['id']);
            $this->userProfileRepository->upsert((int) $target['id'], [
                'bio' => trim((string) $request->input('civil_bio')) ?: null,
                'timezone' => trim((string) $request->input('civil_timezone')) ?: null,
                'language' => trim((string) $request->input('civil_language')) ?: null,
            ]);
            $this->userLegalIdentityRepository->upsert((int) $target['id'], $tenantId, [
                'first_name' => trim((string) $request->input('civil_first_name')),
                'last_name' => trim((string) $request->input('civil_last_name')),
                'phone' => trim((string) $request->input('civil_phone')) ?: null,
                'nationality' => trim((string) $request->input('civil_nationality')) ?: null,
                'birth_date' => trim((string) $request->input('civil_birth_date')) ?: null,
            ]);
        }

        if ($isSelf || $canStaffEdit) {
            $mode = trim((string) $request->input('forum_label_mode')) ?: 'display_name';
            if (!in_array($mode, ['display_name', 'callsign', 'character_name', 'forum_alias'], true)) {
                $mode = 'display_name';
            }
            $displayUpsert = [
                'forum_alias' => trim((string) $request->input('forum_alias')) ?: null,
                'forum_label_mode' => $mode,
                'show_matricule_forum' => $request->input('show_matricule_forum') ? 1 : 0,
                'show_grade_forum' => $request->input('show_grade_forum') ? 1 : 0,
                'show_unit_forum' => $request->input('show_unit_forum') ? 1 : 0,
                'show_bio_forum' => $request->input('show_bio_forum') ? 1 : 0,
                'hide_forum_level' => $request->input('hide_forum_level') ? 1 : 0,
                'fiche_show_email_to_others' => $request->input('fiche_show_email_to_others') ? 1 : 0,
                'fiche_show_matricule_to_others' => $request->input('fiche_show_matricule_to_others') ? 1 : 0,
                'public_roster_opt_in' => $request->input('public_roster_opt_in') ? 1 : 0,
            ];
            if ($isSelf) {
                $displayUpsert['hide_personal_info'] = $request->input('hide_personal_info') ? 1 : 0;
            }
            $forumVisibleRoleRaw = $request->input('forum_visible_role_id');
            $forumVisibleRoleId = ($forumVisibleRoleRaw === null || $forumVisibleRoleRaw === '') ? null : (int) $forumVisibleRoleRaw;
            if ($forumVisibleRoleId !== null && $forumVisibleRoleId <= 0) {
                $forumVisibleRoleId = null;
            }
            $targetMail = strtolower(trim((string) ($target['email'] ?? '')));
            $siteRoleRepo = Container::get(\App\Repositories\SiteRoleAssignmentRepository::class);
            if ($forumVisibleRoleId !== null && !forum_user_may_set_visible_role_id(
                (int) $target['id'],
                $targetMail,
                $forumVisibleRoleId,
                $this->userRepository,
                $siteRoleRepo
            )) {
                Session::flash('error', 'Le rôle affiché sur le forum doit correspondre à un rôle réellement attribué à ce compte (communauté ou plateforme).');

                return Response::redirect(url('personnel/' . $this->personPathSegment($target) . '/edit'));
            }
            $displayUpsert['forum_visible_role_id'] = $forumVisibleRoleId;
            $this->displaySettingsRepository->upsert((int) $target['id'], $displayUpsert);
        }
        if ($isSelf) {
            $settings = $this->tenantRepository->getSettings($tenantId);
            $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
            if (!empty($community['member_can_choose_display_role'])) {
                $prefRaw = $request->input('preferred_display_role_id');
                $prefId = ($prefRaw === null || $prefRaw === '' || $prefRaw === '0') ? null : (int) $prefRaw;
                if ($prefId !== null && $prefId > 0 && !$this->userRepository->userHasTenantRole((int) $target['id'], $prefId)) {
                    Session::flash('error', 'Le rôle choisi doit faire partie de vos rôles dans cette communauté.');

                    return Response::redirect(url('personnel/' . $this->personPathSegment($target) . '/edit'));
                }
                $this->userRepository->setPreferredDisplayRoleId((int) $target['id'], $tenantId, $prefId);
            }
        }
        Session::flash('success', 'Dossier mis à jour.');
        $redirect = $isSelf ? url('personnel/me') : url('personnel/' . $this->personPathSegment($target));
        return Response::redirect($redirect);
    }

    public function syncSteamProfile(Request $request, array $params = []): Response
    {
        $currentUser = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$currentUser) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('personnel/me'));
        }
        $raw = (string) ($params['id'] ?? '');
        $target = $this->resolvePersonnelTarget($raw, $tenantId);
        if (!$target) {
            return (new Response())->setStatusCode(404)->setBody('Utilisateur non trouvé.');
        }
        $currentUserId = (int) $currentUser['id'];
        $isSelf = $currentUserId === (int) $target['id'];
        if (!$isSelf && !$this->canStaffEditPersonnel()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('personnel/' . $this->personPathSegment($target)));
        }
        if (!$this->steamWebApiService->isConfigured()) {
            Session::flash('error', 'L’import depuis Steam n’est pas configuré sur ce serveur.');

            return Response::redirect(url('personnel/' . $this->personPathSegment($target)));
        }
        $steamId = trim((string) ($target['steam_id'] ?? ''));
        if ($steamId === '') {
            Session::flash('error', 'Aucun identifiant Steam n’est renseigné sur ce dossier. Indiquez-le dans les préférences du compte du membre, puis réessayez.');

            return Response::redirect(url('personnel/' . $this->personPathSegment($target)));
        }
        $applyName = $request->input('apply_steam_display_name') === '1';
        $summary = $this->steamWebApiService->fetchPublicPlayer($steamId);
        if ($summary === null) {
            Session::flash('error', 'Impossible de récupérer le profil public pour cet identifiant. Vérifiez l’identifiant ou réessayez plus tard.');

            return Response::redirect(url('personnel/' . $this->personPathSegment($target)));
        }
        $patch = [];
        if ($summary['avatar_url'] !== '') {
            $patch['avatar_url'] = function_exists('mb_substr')
                ? mb_substr($summary['avatar_url'], 0, 500)
                : substr($summary['avatar_url'], 0, 500);
        }
        if ($applyName && $summary['personaname'] !== '') {
            $patch['display_name'] = function_exists('mb_substr')
                ? mb_substr($summary['personaname'], 0, 100)
                : substr($summary['personaname'], 0, 100);
        }
        if ($patch === []) {
            Session::flash('error', 'Aucune donnée exploitable n’a été renvoyée pour ce profil.');

            return Response::redirect(url('personnel/' . $this->personPathSegment($target)));
        }
        $targetUid = (int) $target['id'];
        $this->userRepository->update($targetUid, $tenantId, $patch);
        if ($isSelf) {
            $fresh = $this->userRepository->findById($targetUid, $tenantId);
            if ($fresh) {
                Session::set('display_name', (string) ($fresh['display_name'] ?? ''));
                Session::set('callsign', (string) ($fresh['callsign'] ?? ''));
            }
        }
        Session::flash(
            'success',
            $applyName
                ? 'Photo et nom d’affichage mis à jour depuis le profil public Steam.'
                : 'Photo du compte mise à jour depuis le profil public Steam.'
        );

        return Response::redirect(url('personnel/' . $this->personPathSegment($target)));
    }

    private function canStaffViewPersonnel(): bool
    {
        return Gate::getInstance()->allows('personnel.profile.view');
    }

    private function canStaffEditPersonnel(): bool
    {
        return Gate::getInstance()->allows('personnel.profile.update');
    }

    private function canViewSensitivePersonnel(): bool
    {
        return Gate::getInstance()->allows('personnel.sensitive.view');
    }
}
