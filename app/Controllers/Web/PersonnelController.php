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
use App\Core\Gate;

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
        private UserProfileDisplaySettingsRepository $displaySettingsRepository
    ) {}

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
        $extras = $this->personnelExtrasRepository->getByUserId((int) $target['id']);
        $profile = $this->personnelExtrasRepository->getProfileByUserId((int) $target['id']);
        $personnelProfile = $this->personnelProfileRepository->getByUserId((int) $target['id']);
        $assignments = $this->personnelAssignmentRepository->listActiveForUserResolved((int) $target['id']);
        $primaryAssignment = $assignments[0] ?? null;
        $commander = null;
        if (!empty($primaryAssignment['commander_user_id'])) {
            $commander = $this->userRepository->findById((int) $primaryAssignment['commander_user_id'], (int) $tenantId);
        }
        $qualifications = $this->personnelQualificationRepository->listForUser((int) $target['id']);
        $serviceHistory = $this->personnelServiceHistoryRepository->listForUser((int) $target['id']);
        $trainingCertificates = $this->trainingCertificateRepository->listByUserId((int) $target['id'], (int) $tenantId);
        $completeness = $this->completenessService->getScore((int) $target['id'], $target, $profile, $extras);

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
        $adminDataByPanel = $this->adminDataRepository->getAllForUser((int) $target['id']);

        $currentUser = $this->authService->user();
        $currentUserId = $currentUser ? (int) $currentUser['id'] : 0;
        $isSelf = $currentUserId === (int) $target['id'];
        $canStaffEdit = $this->canStaffEditPersonnel();
        $canStaffView = $this->canStaffViewPersonnel();
        $canSensitive = $this->canViewSensitivePersonnel();
        $isForumMod = function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator();
        $canEditNotes = $isSelf || $canStaffEdit;
        $canEditProfile = $isSelf || $canStaffEdit;
        $canViewCivil = $isSelf || $canStaffView || $canSensitive || $isForumMod;
        $canViewCommandNotes = $isSelf || $canStaffEdit;
        $displaySettings = $this->displaySettingsRepository->getOrDefaults((int) $target['id']);
        $showEmailInContact = $isSelf || $canStaffView || $canSensitive || $isForumMod || (int) ($displaySettings['fiche_show_email_to_others'] ?? 0) === 1;
        $showMatriculePublic = $isSelf || $canStaffView || $canSensitive || $isForumMod || (int) ($displaySettings['fiche_show_matricule_to_others'] ?? 1) === 1;

        return Response::view('layout.main', [
            'content' => 'personnel.file',
            'title' => 'Fiche personnel',
            'targetUser' => $target,
            'personnelExtras' => $extras,
            'personnelProfile' => $personnelProfile,
            'userProfile' => $profile,
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
        $redirect = $isSelf ? url('personnel/me') : url('personnel/' . $this->personPathSegment($target));
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
        $tree = $this->unitRepository->getTree((int) $tenantId);
        return Response::view('layout.main', [
            'content' => 'personnel.orbat',
            'title' => 'ORBAT',
            'unitsTree' => $tree,
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
        $personnelProfile = $this->personnelProfileRepository->getByUserId((int) $target['id']);
        $displaySettings = $this->displaySettingsRepository->getOrDefaults((int) $target['id']);
        $units = $this->unitRepository->allForTenant($tenantId);
        return Response::view('layout.main', [
            'content' => 'personnel.edit',
            'title' => 'Éditer le dossier',
            'targetUser' => $target,
            'personnelProfile' => $personnelProfile,
            'displaySettings' => $displaySettings,
            'units' => $units,
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
        $data = [
            'character_name' => trim((string) $request->input('character_name')),
            'callsign' => trim((string) $request->input('callsign')),
            'primary_role' => trim((string) $request->input('primary_role')),
            'secondary_role' => trim((string) $request->input('secondary_role')),
            'primary_unit_id' => $request->input('primary_unit_id') ? (int) $request->input('primary_unit_id') : null,
            'clearance_level' => trim((string) $request->input('clearance_level')),
            'enlistment_date' => trim((string) $request->input('enlistment_date')) ?: null,
            'equipment_class' => trim((string) $request->input('equipment_class')),
            'kit_assigned' => trim((string) $request->input('kit_assigned')),
            'radio_assigned' => trim((string) $request->input('radio_assigned')),
            'vehicle_authorized' => trim((string) $request->input('vehicle_authorized')),
            'weapon_specialty' => trim((string) $request->input('weapon_specialty')),
            'deployable' => (int) $request->input('deployable', 1) ? 1 : 0,
        ];
        if ($isSelf || $canStaffEdit) {
            $notes = trim((string) $request->input('command_notes'));
            $data['command_notes'] = $notes;
            $this->personnelExtrasRepository->updateAdminNotes((int) $target['id'], $notes);
        }
        $this->personnelProfileRepository->update((int) $target['id'], $data);
        if ($isSelf || $canStaffEdit) {
            $mode = trim((string) $request->input('forum_label_mode')) ?: 'display_name';
            if (!in_array($mode, ['display_name', 'callsign', 'character_name', 'forum_alias'], true)) {
                $mode = 'display_name';
            }
            $this->displaySettingsRepository->upsert((int) $target['id'], [
                'forum_alias' => trim((string) $request->input('forum_alias')) ?: null,
                'forum_label_mode' => $mode,
                'show_matricule_forum' => $request->input('show_matricule_forum') ? 1 : 0,
                'show_grade_forum' => $request->input('show_grade_forum') ? 1 : 0,
                'show_unit_forum' => $request->input('show_unit_forum') ? 1 : 0,
                'show_bio_forum' => $request->input('show_bio_forum') ? 1 : 0,
                'fiche_show_email_to_others' => $request->input('fiche_show_email_to_others') ? 1 : 0,
                'fiche_show_matricule_to_others' => $request->input('fiche_show_matricule_to_others') ? 1 : 0,
                'public_roster_opt_in' => $request->input('public_roster_opt_in') ? 1 : 0,
            ]);
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
