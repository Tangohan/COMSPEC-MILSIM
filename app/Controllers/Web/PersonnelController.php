<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
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
use App\Core\Csrf;
use App\Services\Personnel\MatriculeService;
use App\Services\Personnel\PersonnelCompletenessService;
use App\Repositories\UserProfileDisplaySettingsRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Core\Gate;
use App\Support\OrbatRosterPayload;

class PersonnelController
{
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
        private MatriculeService $matriculeService,
        private PersonnelCompletenessService $completenessService,
        private UserProfileDisplaySettingsRepository $displaySettingsRepository,
        private UserProfileRepository $userProfileRepository,
        private EnlistmentRepository $enlistmentRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private RoleRepository $roleRepository,
        private TenantRepository $tenantRepository
    ) {}

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

    /** Redirection /personnel → fiche de l’utilisateur connecté. */
    public function personnelIndex(Request $request, array $params = []): Response
    {
        return Response::redirect(url('personnel/me'));
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
        $primaryAssignment = $assignments[0] ?? null;
        $primaryUnitFallbackName = null;
        if ($primaryAssignment === null && !empty($personnelProfile['primary_unit_id'])) {
            $urow = $this->unitRepository->findById((int) $personnelProfile['primary_unit_id'], (int) $tenantId);
            if ($urow) {
                $primaryUnitFallbackName = (string) ($urow['name'] ?? '');
            }
        }

        $commander = null;
        if (!empty($primaryAssignment['commander_user_id'])) {
            $commander = $this->userRepository->findById((int) $primaryAssignment['commander_user_id'], (int) $tenantId);
        }
        $qualifications = $this->personnelQualificationRepository->listForUser($uid);
        $serviceHistory = $this->personnelServiceHistoryRepository->listForUser($uid);
        $trainingCertificates = $this->trainingCertificateRepository->listByUserId($uid, (int) $tenantId);
        $completeness = $this->completenessService->getScore($uid, $target, $mergedProfileForScore, $extras, (int) $tenantId);

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
        $isForumMod = function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator();
        $canEditNotes = $isSelf || $canStaffEdit;
        $canEditProfile = $isSelf || $canStaffEdit;
        $canViewCivil = $isSelf || $canStaffView || $canSensitive || $isForumMod;
        $canViewCommandNotes = $isSelf || $canStaffEdit;
        $displaySettings = $this->displaySettingsRepository->getOrDefaults((int) $target['id']);
        $hidePersonalInfo = (int) ($displaySettings['hide_personal_info'] ?? 0) === 1;
        $viewerPrivilegedForPersonal = $isSelf || $canStaffView || $canStaffEdit || $isForumMod;
        $redactPersonalPresentation = $hidePersonalInfo && !$viewerPrivilegedForPersonal;
        $canViewCivilSection = $canViewCivil && !$redactPersonalPresentation;
        $showEmailInContact = !$redactPersonalPresentation && ($isSelf || $canStaffView || $canSensitive || $isForumMod || (int) ($displaySettings['fiche_show_email_to_others'] ?? 0) === 1);
        $showMatriculePublic = $isSelf || $canStaffView || $canSensitive || $isForumMod || (int) ($displaySettings['fiche_show_matricule_to_others'] ?? 1) === 1;

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
            'primaryAssignment' => $primaryAssignment,
            'commander' => $commander,
            'qualifications' => $qualifications,
            'serviceHistory' => $serviceHistory,
            'trainingCertificates' => $trainingCertificates,
            'completeness' => $completeness,
            'adminPanels' => $adminPanels,
            'adminDataByPanel' => $adminDataByPanel,
            'canEditNotes' => $canEditNotes,
            'canEditProfile' => $canEditProfile,
            'canViewCivil' => $canViewCivil,
            'canViewCivilSection' => $canViewCivilSection,
            'redactPersonalPresentation' => $redactPersonalPresentation,
            'canViewCommandNotes' => $canViewCommandNotes,
            'displaySettings' => $displaySettings,
            'showEmailInContact' => $showEmailInContact,
            'showMatriculePublic' => $showMatriculePublic,
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
        $rosterData = OrbatRosterPayload::buildForTenant($this->unitRepository, $tid);
        $gate = Gate::getInstance();
        $orbatCanManage = $gate->allows('admin.organization') || $gate->allows('admin.access');
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
        $userProfile = $this->userProfileRepository->getByUserId($uid);
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

        $forumOrgRoleChoices = [];
        foreach ($this->userRepository->listOrganizationRoleIdsForUser($uid) as $rid) {
            $rrow = $this->roleRepository->findById($rid, $tenantId);
            if ($rrow !== null) {
                $forumOrgRoleChoices[] = [
                    'id' => $rid,
                    'name' => trim((string) ($rrow['name'] ?? '')),
                ];
            }
        }
        usort($forumOrgRoleChoices, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $memberCanChooseDisplayRole = !empty($community['member_can_choose_display_role']);

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
                'first_name' => trim((string) $request->input('civil_first_name')),
                'last_name' => trim((string) $request->input('civil_last_name')),
                'bio' => trim((string) $request->input('civil_bio')) ?: null,
                'phone' => trim((string) $request->input('civil_phone')) ?: null,
                'timezone' => trim((string) $request->input('civil_timezone')) ?: null,
                'language' => trim((string) $request->input('civil_language')) ?: null,
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
            if ($forumVisibleRoleId !== null && !$this->userRepository->userHasTenantRole((int) $target['id'], $forumVisibleRoleId)) {
                Session::flash('error', 'Le rôle affiché sur le forum doit être l’un de vos rôles communauté pour ce compte.');

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
