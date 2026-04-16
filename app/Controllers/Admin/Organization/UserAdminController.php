<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EmailTokenRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\RoleRepository;
use App\Repositories\GradeCategoryRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PositionRepository;
use App\Repositories\RoleSetRepository;
use App\Services\Admin\ProfileCompletenessService;
use App\Services\Admin\AdminAuditService;
use App\Services\Email\EmailEvents;
use App\Services\Email\EmailTokenPurpose;
use App\Services\EmailService;
use App\Services\GradeValidationService;
use App\Services\Personnel\PersonnelCompletenessService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Services\Personnel\PersonnelOrgHistoryRecorder;
use App\Support\Audit\AuditFieldSnapshot;
use App\Support\OrganizationRoleLabels;

class UserAdminController
{
    private const SETUP_TOKEN_HOURS = 72;

    private const RESEND_VERIFICATION_COOLDOWN_SEC = 90;

    private function organizationRoleLabelModeForTenant(int $tenantId): string
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $tenant = $this->tenantRepository->findById($tenantId) ?: [];

        return OrganizationRoleLabels::mode($community, $tenant);
    }

    public function __construct(
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelExtrasRepository $personnelExtrasRepository,
        private RoleRepository $roleRepository,
        private GradeRepository $gradeRepository,
        private GradeCategoryRepository $gradeCategoryRepository,
        private ProfileCompletenessService $profileCompletenessService,
        private PersonnelCompletenessService $personnelCompletenessService,
        private AdminAuditService $adminAuditService,
        private GradeValidationService $gradeValidationService,
        private EmailService $emailService,
        private TenantRepository $tenantRepository,
        private PasswordResetRepository $passwordResetRepository,
        private UserNotificationPreferencesRepository $userNotificationPreferencesRepository,
        private EmailTokenRepository $emailTokenRepository,
        private PositionRepository $positionRepository,
        private RoleSetRepository $roleSetRepository,
        private IndicatorBlocklistService $indicatorBlocklist,
        private PersonnelOrgHistoryRecorder $personnelOrgHistoryRecorder,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $search = $this->queryString($request->query('search'));
        $status = $this->queryString($request->query('status'));
        if ($status === 'pending') {
            $status = 'pending_verification';
        }
        $roleId = $this->positiveIntOrNull($request->query('role_id'));
        $filterIncomplete = $request->query('filter_incomplete') === '1' || $request->query('filter_incomplete') === 'true';
        $filterNoUnit = $request->query('filter_no_unit') === '1' || $request->query('filter_no_unit') === 'true';
        $filterNoRole = $request->query('filter_no_role') === '1' || $request->query('filter_no_role') === 'true';
        $excludeServiceAccounts = true;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $statusFilter = ($status !== null && $status !== '') ? $status : null;

        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $forPlatformOperator = Gate::getInstance()->allows('admin.system');

        if ($filterIncomplete) {
            $allUsers = $this->userRepository->listForTenant($tenantId, $search, $statusFilter, $roleId, null, null, $excludeServiceAccounts);
            $completenessByUser = [];
            $personnelCompletenessByUser = [];
            foreach ($allUsers as $u) {
                $uid = (int) $u['id'];
                $up = $this->userProfileRepository->getByUserId($uid);
                $pp = $this->profileCompletenessService->getCompleteness($uid, $u, $up, null, $forPlatformOperator);
                $completenessByUser[$uid] = $pp;
                $personnelCompletenessByUser[$uid] = $this->buildPersonnelCompletenessForList($uid, $u, $tenantId, $forPlatformOperator);
            }
            $filtered = array_values(array_filter($allUsers, function ($u) use ($completenessByUser, $personnelCompletenessByUser) {
                $uid = (int) $u['id'];
                $comp = $completenessByUser[$uid] ?? ['score' => 100, 'sections_critiques' => []];
                $pComp = $personnelCompletenessByUser[$uid] ?? ['score' => 100, 'sections_critiques' => []];

                return $comp['score'] < 100 || !empty($comp['sections_critiques'])
                    || $pComp['score'] < 100 || !empty($pComp['sections_critiques']);
            }));
            $total = count($filtered);
            $users = array_slice($filtered, ($page - 1) * $perPage, $perPage);
            $ids = array_map(static fn ($u) => (int) $u['id'], $users);
            $completenessByUser = array_intersect_key($completenessByUser, array_flip($ids));
            $personnelCompletenessByUser = array_intersect_key($personnelCompletenessByUser, array_flip($ids));
        } else {
            $onlyNoUnit = $filterNoUnit ? true : null;
            $onlyNoRole = $filterNoRole ? true : null;
            $total = $this->userRepository->countListForTenant($tenantId, $search, $statusFilter, $roleId, $excludeServiceAccounts, $onlyNoUnit, $onlyNoRole);
            $users = $this->userRepository->listForTenant($tenantId, $search, $statusFilter, $roleId, $perPage, ($page - 1) * $perPage, $excludeServiceAccounts, $onlyNoUnit, $onlyNoRole);
            $completenessByUser = [];
            $personnelCompletenessByUser = [];
            foreach ($users as $u) {
                $uid = (int) $u['id'];
                $up = $this->userProfileRepository->getByUserId($uid);
                $completenessByUser[$uid] = $this->profileCompletenessService->getCompleteness($uid, $u, $up, null, $forPlatformOperator);
                $personnelCompletenessByUser[$uid] = $this->buildPersonnelCompletenessForList($uid, $u, $tenantId, $forPlatformOperator);
            }
        }

        $totalPages = max(1, (int) ceil($total / $perPage));

        return Response::view('layout.main', [
            'content' => 'admin.organization.users.index',
            'title' => 'Utilisateurs',
            'users' => $users,
            'roles' => $roles,
            'completenessByUser' => $completenessByUser,
            'personnelCompletenessByUser' => $personnelCompletenessByUser,
            'filters' => [
                'search' => $search,
                'status' => $status ?? '',
                'role_id' => $roleId,
                'filter_incomplete' => $filterIncomplete,
                'filter_no_unit' => $filterNoUnit,
                'filter_no_role' => $filterNoRole,
            ],
            'usersTotal' => $total,
            'usersPage' => $page,
            'usersPerPage' => $perPage,
            'usersTotalPages' => $totalPages,
        ]);
    }

    private function queryString(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return trim((string) $v);
    }

    private function positiveIntOrNull(mixed $v): ?int
    {
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            return Response::redirect(url('back-office/users'));
        }
        $userProfile = $this->userProfileRepository->getByUserId($id);
        $personnelProfile = $this->personnelProfileRepository->getByUserId($id);
        $forPlatformOperator = Gate::getInstance()->allows('admin.system');
        $completenessAccount = $this->profileCompletenessService->getCompleteness($id, $user, $userProfile, $personnelProfile, $forPlatformOperator);
        $isService = $this->userRepository->isServiceAccount($id);
        $extras = $this->personnelExtrasRepository->getByUserId($id);
        $civilProfile = $this->personnelExtrasRepository->getProfileByUserId($id);
        $completenessPersonnel = $isService
            ? null
            : $this->personnelCompletenessService->getScoreWithMissingLabelsForAudience($id, $user, $civilProfile, $extras, $tenantId, $forPlatformOperator);
        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $userRoleIds = $this->userRepository->listOrganizationRoleIdsForUser($id);
        if ($userRoleIds === [] && !empty($user['role_id'])) {
            $userRoleIds = [(int) $user['role_id']];
        }
        return Response::view('layout.main', [
            'content' => 'admin.organization.users.show',
            'title' => 'Fiche utilisateur',
            'user' => $user,
            'userRoleIds' => $userRoleIds,
            'userProfile' => $userProfile,
            'personnelProfile' => $personnelProfile,
            'completeness' => $completenessAccount,
            'completenessAccount' => $completenessAccount,
            'completenessPersonnel' => $completenessPersonnel,
            'isServiceAccount' => $isService,
            'roles' => $roles,
            'showPlatformDiagnostics' => $forPlatformOperator,
        ]);
    }

    /**
     * @return list<int>
     */
    private function parseRoleIdsFromRequest(Request $request): array
    {
        $raw = $request->input('role_ids', []);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $rid) {
            $r = (int) $rid;
            if ($r > 0) {
                $out[] = $r;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array{score: int, sections_critiques: list<string>, missing_labels?: list<string>}
     */
    private function buildPersonnelCompletenessForList(int $userId, array $userRow, int $tenantId, bool $forPlatformOperator): array
    {
        if ($this->userRepository->isServiceAccount($userId)) {
            return ['score' => 100, 'sections_critiques' => [], 'missing_labels' => []];
        }
        $extras = $this->personnelExtrasRepository->getByUserId($userId);
        $civil = $this->personnelExtrasRepository->getProfileByUserId($userId);

        return $this->personnelCompletenessService->getScoreWithMissingLabelsForAudience($userId, $userRow, $civil, $extras, $tenantId, $forPlatformOperator);
    }

    public function notifyProfileIncomplete(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('back-office/users'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/users'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');

            return Response::redirect(url('back-office/users'));
        }
        if ($this->userRepository->isServiceAccount($id)) {
            Session::flash('error', 'Les comptes techniques ne reçoivent pas de courriel de rappel.');

            return Response::redirect(url('back-office/users/' . $id));
        }
        $status = (string) ($user['status'] ?? '');
        if ($status !== 'active' && $status !== 'pending_verification') {
            Session::flash('error', 'Seuls les comptes actifs ou en attente peuvent recevoir ce rappel.');

            return Response::redirect(url('back-office/users/' . $id));
        }
        $tenantRow = $this->tenantRepository->findById($tenantId);
        $tenantName = $tenantRow ? (string) ($tenantRow['name'] ?? 'Athena') : 'Athena';
        $displayName = trim((string) ($user['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = (string) ($user['email'] ?? 'membre');
        }
        if (!$this->userNotificationPreferencesRepository->isEmailEventEnabled($id, EmailEvents::PROFILE_INCOMPLETE_REMINDER)) {
            Session::flash('error', 'Ce membre a désactivé les e-mails de rappel de profil dans ses préférences compte.');

            return Response::redirect(url('back-office/users/' . $id));
        }
        $editUrl = url('personnel/' . $id . '/edit');
        $ok = $this->emailService->sendProfileIncompleteReminder(
            (string) $user['email'],
            $displayName,
            $tenantName,
            $editUrl,
            $tenantId,
            ['target_user_id' => $id]
        );
        if ($ok) {
            Session::flash('success', 'Courriel de rappel envoyé.');
        } else {
            $base = 'Envoi impossible (vérifiez la configuration e-mail ou l’adresse du destinataire).';
            $detail = $this->emailService->getLastSendError();
            if ($detail !== null && $detail !== '') {
                $clean = preg_replace('/\s+/u', ' ', $detail) ?? $detail;
                if (function_exists('mb_substr')) {
                    $clean = mb_substr($clean, 0, 400);
                } else {
                    $clean = substr($clean, 0, 400);
                }
                $base .= ' Détail : ' . $clean;
            }
            Session::flash('error', $base);
        }

        return Response::redirect(url('back-office/users/' . $id));
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $roleMatrix = $this->roleRepository->organizationRolesPermissionMatrix($tenantId);
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $gradeCategories = $this->gradeCategoryRepository->listActive();
        return Response::view('layout.main', [
            'content' => 'admin.organization.users.create',
            'title' => 'Nouvel utilisateur',
            'roles' => $roles,
            'roleMatrix' => $roleMatrix,
            'grades' => $grades,
            'gradeCategories' => $gradeCategories,
            'organizationRoleLabelMode' => $this->organizationRoleLabelModeForTenant($tenantId),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');
        if (!$tenantId || !$actorUserId) {
            return Response::redirect(url('login'));
        }
        $email = trim((string) $request->input('email'));
        $displayName = trim((string) $request->input('display_name'));
        $callsign = trim((string) $request->input('callsign'));
        $roleIds = $this->parseRoleIdsFromRequest($request);
        foreach ($roleIds as $rid) {
            if (!$this->roleRepository->canAssignInTenantAdminContext($rid, $tenantId)) {
                Session::flash('error', 'Un rôle sélectionné ne peut pas être attribué depuis l’administration communauté.');

                return Response::redirect(url('back-office/organisation/structure?ouvrir=membre'));
            }
        }
        $primaryRoleId = $this->userRepository->peekPrimaryRoleIdForTenant($tenantId, $roleIds);
        $gradeId = $request->input('grade_id') ? (int) $request->input('grade_id') : null;
        $nationalityCode = trim((string) $request->input('nationality_code')) ?: null;
        $preferredGradeFormat = trim((string) $request->input('preferred_grade_format'));
        if (!in_array($preferredGradeFormat, ['classic', 'otan', 'hybrid'], true)) {
            $preferredGradeFormat = 'classic';
        }
        $professionalCategoryCode = trim((string) $request->input('professional_category_code')) ?: null;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Une adresse e-mail valide est requise.');
            return Response::redirect(url('back-office/organisation/structure?ouvrir=membre'));
        }
        if ($this->userRepository->emailExistsInTenant($tenantId, $email)) {
            Session::flash('error', 'Cet email est déjà utilisé.');
            return Response::redirect(url('back-office/organisation/structure?ouvrir=membre'));
        }

        $gate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
        if (!$gate->canAddMember($tenantId)) {
            Session::flash('error', 'Limite de membres du plan atteinte.');
            return Response::redirect(url('back-office/organisation/structure?ouvrir=membre'));
        }
        $passwordPlaceholder = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID);
        $userId = $this->userRepository->create($tenantId, [
            'email' => $email,
            'password_hash' => $passwordPlaceholder,
            'display_name' => $displayName ?: null,
            'callsign' => $callsign ?: null,
            'role_id' => $primaryRoleId,
            'grade_id' => $gradeId,
            'status' => 'pending_verification',
            'nationality_code' => $nationalityCode,
            'preferred_grade_format' => $preferredGradeFormat,
            'professional_category_code' => $professionalCategoryCode,
        ]);
        try {
            $this->userRepository->syncOrganizationRoles($userId, $tenantId, $roleIds, $actorUserId);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('back-office/organisation/structure?ouvrir=membre'));
        }

        $this->passwordResetRepository->deleteExpired();
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expires = new \DateTimeImmutable('+' . self::SETUP_TOKEN_HOURS . ' hours');
        $this->passwordResetRepository->create($userId, $tokenHash, $expires);

        $setupUrl = url('reset-password') . '?token=' . rawurlencode($rawToken);
        $tenantRow = $this->tenantRepository->findById($tenantId);
        $tenantName = $tenantRow ? (string) ($tenantRow['name'] ?? 'Communauté') : 'Communauté';
        $sent = false;
        if ($this->userNotificationPreferencesRepository->isEmailEventEnabled($userId, EmailEvents::TENANT_USER_SETUP)) {
            $sent = $this->emailService->sendTenantUserSetupInvite(
                $email,
                $setupUrl,
                self::SETUP_TOKEN_HOURS,
                $tenantName,
                $tenantId
            );
        }

        $this->adminAuditService->logUserCreated($tenantId, $actorUserId, $userId, $email);
        if ($sent) {
            Session::flash(
                'success',
                'Compte créé. Un e-mail a été envoyé à ' . $email . ' avec un lien pour définir le mot de passe (valide ' . self::SETUP_TOKEN_HOURS . ' h). Le compte sera actif après cette étape.'
            );
        } else {
            $msg = 'Compte créé, mais l’e-mail d’invitation n’a pas pu être envoyé. Vous pouvez utiliser « Mot de passe oublié » sur la page de connexion pour régénérer un lien, ou vérifier la configuration e-mail.';
            $detail = $this->emailService->getLastSendError();
            if ($detail !== null && $detail !== '') {
                $clean = preg_replace('/\s+/u', ' ', $detail) ?? $detail;
                $msg .= ' Détail : ' . (function_exists('mb_substr') ? mb_substr($clean, 0, 300) : substr($clean, 0, 300));
            }
            Session::flash('error', $msg);
        }

        return Response::redirect(url('back-office/users/' . $userId));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            return Response::redirect(url('back-office/users'));
        }
        $userProfile = $this->userProfileRepository->getByUserId($id);
        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $roleMatrix = $this->roleRepository->organizationRolesPermissionMatrix($tenantId);
        $selectedRoleIds = $this->userRepository->listOrganizationRoleIdsForUser($id);
        if ($selectedRoleIds === [] && !empty($user['role_id'])) {
            $selectedRoleIds = [(int) $user['role_id']];
        }
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $gradeCategories = $this->gradeCategoryRepository->listActive();
        $gradeValidationIssues = $this->gradeValidationService->validateUserProfile($user);
        $positions = $this->positionRepository->listForTenant($tenantId);
        $userActivePositions = $this->positionRepository->listActiveForUser($tenantId, $id);
        $roleSets = $this->roleSetRepository->listForTenant($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.organization.users.edit',
            'title' => 'Modifier l\'utilisateur',
            'user' => $user,
            'userProfile' => $userProfile,
            'isServiceAccount' => $this->userRepository->isServiceAccount($id),
            'roles' => $roles,
            'roleMatrix' => $roleMatrix,
            'selectedRoleIds' => $selectedRoleIds,
            'grades' => $grades,
            'gradeCategories' => $gradeCategories,
            'gradeValidationIssues' => $gradeValidationIssues,
            'positionsList' => $positions,
            'userActivePositions' => $userActivePositions,
            'roleSetsList' => $roleSets,
            'organizationRoleLabelMode' => $this->organizationRoleLabelModeForTenant($tenantId),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$actorUserId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            return Response::redirect(url('back-office/users'));
        }

        $rolesSynced = false;
        /** @var list<int>|null */
        $historyOldOrgRoleIds = null;
        /** @var list<int>|null */
        $historyNewOrgRoleIds = null;
        $data = [];
        if ($request->input('display_name') !== null) {
            $data['display_name'] = trim((string) $request->input('display_name'));
        }
        if ($request->input('callsign') !== null) {
            $data['callsign'] = trim((string) $request->input('callsign'));
        }
        if ($request->input('email') !== null) {
            $email = trim((string) $request->input('email'));
            if ($email !== '' && $this->userRepository->emailExistsInTenant($tenantId, $email, $id)) {
                Session::flash('error', 'Cet email est déjà utilisé.');
                return Response::redirect(url('back-office/users/' . $id . '/edit'));
            }
            $data['email'] = $email;
        }
        if ($request->input('user_roles_form') === '1') {
            $roleIds = $this->parseRoleIdsFromRequest($request);
            $oldRoleIds = $this->userRepository->listOrganizationRoleIdsForUser($id);
            if ($oldRoleIds === [] && !empty($user['role_id'])) {
                $oldRoleIds = [(int) $user['role_id']];
            }
            foreach ($roleIds as $rid) {
                if (!$this->roleRepository->canAssignInTenantAdminContext($rid, $tenantId)) {
                    Session::flash('error', 'Un rôle sélectionné ne peut pas être attribué depuis l’administration communauté.');

                    return Response::redirect(url('back-office/users/' . $id . '/edit'));
                }
            }
            $ownerRoleId = $this->roleRepository->getIdBySlug($tenantId, 'community_owner');
            if ($ownerRoleId !== null) {
                $hadOwner = in_array($ownerRoleId, $oldRoleIds, true);
                $hasOwnerNew = in_array($ownerRoleId, $roleIds, true);
                if ($hadOwner && !$hasOwnerNew) {
                    $count = $this->userRepository->countUsersWithRole($ownerRoleId);
                    if ($count <= 1) {
                        Session::flash('error', 'Impossible de retirer le rôle propriétaire communauté au dernier titulaire.');

                        return Response::redirect(url('back-office/users/' . $id . '/edit'));
                    }
                }
            }
            try {
                $this->userRepository->syncOrganizationRoles($id, $tenantId, $roleIds, $actorUserId);
            } catch (\InvalidArgumentException $e) {
                Session::flash('error', $e->getMessage());

                return Response::redirect(url('back-office/users/' . $id . '/edit'));
            }
            $this->adminAuditService->logRoleAssigned(
                $tenantId,
                $actorUserId,
                $id,
                $oldRoleIds !== [] ? implode(',', $oldRoleIds) : null,
                $roleIds !== [] ? implode(',', $roleIds) : null
            );
            $rolesSynced = true;
            $historyOldOrgRoleIds = $oldRoleIds;
            $userAfterRoles = $this->userRepository->findById($id, $tenantId) ?? [];
            $historyNewOrgRoleIds = $this->userRepository->listOrganizationRoleIdsForUser($id);
            if ($historyNewOrgRoleIds === [] && !empty($userAfterRoles['role_id'])) {
                $historyNewOrgRoleIds = [(int) $userAfterRoles['role_id']];
            }
        }
        if ($request->input('grade_id') !== null) {
            $data['grade_id'] = $request->input('grade_id') ? (int) $request->input('grade_id') : null;
        }
        if ($request->input('status') !== null) {
            $st = trim((string) $request->input('status'));
            if ($st === 'pending') {
                $st = 'pending_verification';
            }
            if (in_array($st, ['active', 'inactive', 'pending_verification'], true)) {
                $data['status'] = $st;
            }
        }
        if ($request->input('nationality_code') !== null) {
            $v = trim((string) $request->input('nationality_code'));
            $data['nationality_code'] = $v !== '' ? $v : null;
        }
        if ($request->input('preferred_grade_format') !== null) {
            $v = trim((string) $request->input('preferred_grade_format'));
            $data['preferred_grade_format'] = in_array($v, ['classic', 'otan', 'hybrid'], true) ? $v : 'classic';
        }
        if ($request->input('professional_category_code') !== null) {
            $v = trim((string) $request->input('professional_category_code'));
            $data['professional_category_code'] = $v !== '' ? $v : null;
        }
        $password = $request->input('password');
        if ($password !== null && $password !== '') {
            if (strlen($password) >= 6) {
                $data['password_hash'] = password_hash($password, PASSWORD_ARGON2ID);
            }
        }

        $updatedUser = array_merge($user, $data);
        $gradeValidationIssues = $this->gradeValidationService->validateUserProfile($updatedUser);
        if ($this->gradeValidationService->hasErrors($gradeValidationIssues)) {
            foreach ($gradeValidationIssues as $i) {
                if (($i['type'] ?? '') === 'error') {
                    Session::flash('error', $i['message']);
                    return Response::redirect(url('back-office/users/' . $id . '/edit'));
                }
            }
        }

        $actorLabel = trim((string) Session::get('display_name'));

        if ($rolesSynced && $historyOldOrgRoleIds !== null && $historyNewOrgRoleIds !== null) {
            $this->personnelOrgHistoryRecorder->recordOrganizationRolesChange(
                $tenantId,
                $id,
                $actorUserId,
                $actorLabel,
                $historyOldOrgRoleIds,
                $historyNewOrgRoleIds
            );
        }

        if (!empty($data)) {
            $auditKeys = ['email', 'grade_id', 'status', 'nationality_code', 'preferred_grade_format', 'professional_category_code', 'display_name', 'callsign', 'profile_slug'];
            $passwordWillChange = isset($data['password_hash']);
            $beforeAugmented = array_merge($user, ['connexion_mot_de_passe' => false]);
            $keys = $auditKeys;
            if ($passwordWillChange) {
                $keys[] = 'connexion_mot_de_passe';
            }
            $this->userRepository->update($id, $tenantId, $data);
            $afterUser = $this->userRepository->findById($id, $tenantId) ?? [];
            $afterAugmented = array_merge($afterUser, ['connexion_mot_de_passe' => $passwordWillChange]);
            [$o, $n] = AuditFieldSnapshot::diffOnly($beforeAugmented, $afterAugmented, $keys);
            [$os, $ns] = AuditFieldSnapshot::encodePair($o, $n);
            $this->adminAuditService->logUserUpdated($tenantId, $actorUserId, $id, $os, $ns);
        }

        if ($rolesSynced || !empty($data)) {
            $final = $this->userRepository->findById($id, $tenantId) ?? [];
            if ($final !== []) {
                $this->personnelOrgHistoryRecorder->recordUserTableDiff($tenantId, $user, $final, $actorUserId, $actorLabel);
            }
        }

        if (!empty($data)) {
            Session::flash('success', 'Utilisateur mis à jour.');
        } elseif ($rolesSynced) {
            Session::flash('success', 'Rôles mis à jour.');
        }

        return Response::redirect(url('back-office/users/' . $id));
    }

    public function assignPosition(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$actorUserId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/users/' . $id . '/edit'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');

            return Response::redirect(url('back-office/users'));
        }
        $positionId = (int) $request->input('position_id', 0);
        $startsAt = trim((string) $request->input('starts_at', ''));
        $endsAt = trim((string) $request->input('ends_at', ''));
        if ($positionId < 1 || $startsAt === '') {
            Session::flash('error', 'Choisissez un poste et une date de début.');

            return Response::redirect(url('back-office/users/' . $id . '/edit'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startsAt)) {
            Session::flash('error', 'Date de début invalide.');

            return Response::redirect(url('back-office/users/' . $id . '/edit'));
        }
        if ($endsAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsAt)) {
            Session::flash('error', 'Date de fin invalide.');

            return Response::redirect(url('back-office/users/' . $id . '/edit'));
        }
        $ok = $this->positionRepository->assignUser($tenantId, $id, $positionId, $startsAt, $endsAt !== '' ? $endsAt : null, $actorUserId);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Affectation de poste enregistrée.' : 'Impossible d’enregistrer l’affectation.');

        return Response::redirect(url('back-office/users/' . $id . '/edit'));
    }

    public function applyRoleSet(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$actorUserId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/users/' . $id . '/edit'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');

            return Response::redirect(url('back-office/users'));
        }
        $setId = (int) $request->input('role_set_id', 0);
        if ($setId < 1) {
            Session::flash('error', 'Choisissez un jeu de rôles.');

            return Response::redirect(url('back-office/users/' . $id . '/edit'));
        }
        $extraIds = $this->roleSetRepository->roleIdsForSet($tenantId, $setId);
        if ($extraIds === []) {
            Session::flash('error', 'Ce jeu ne contient aucun rôle utilisable.');

            return Response::redirect(url('back-office/users/' . $id . '/edit'));
        }
        $current = $this->userRepository->listOrganizationRoleIdsForUser($id);
        if ($current === [] && !empty($user['role_id'])) {
            $current = [(int) $user['role_id']];
        }
        foreach ($extraIds as $rid) {
            if (!$this->roleRepository->canAssignInTenantAdminContext($rid, $tenantId)) {
                Session::flash('error', 'Un rôle du jeu ne peut pas être attribué depuis l’administration communauté.');

                return Response::redirect(url('back-office/users/' . $id . '/edit'));
            }
        }
        $merged = array_values(array_unique(array_merge($current, $extraIds)));
        try {
            $this->userRepository->syncOrganizationRoles($id, $tenantId, $merged, $actorUserId);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('back-office/users/' . $id . '/edit'));
        }
        Session::flash('success', 'Rôles du jeu appliqués (sans retirer les rôles déjà présents).');

        return Response::redirect(url('back-office/users/' . $id . '/edit'));
    }

    public function deactivate(Request $request, array $params = []): Response
    {
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/users'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$actorUserId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            return Response::redirect(url('back-office/users'));
        }
        $ownerRoleId = $this->roleRepository->getIdBySlug($tenantId, 'community_owner');
        if ($ownerRoleId !== null && $this->userRepository->userHasTenantRole($id, $ownerRoleId)) {
            $count = $this->userRepository->countUsersWithRole($ownerRoleId);
            if ($count <= 1) {
                Session::flash('error', 'Impossible de désactiver le dernier propriétaire communauté.');

                return Response::redirect(url('back-office/users/' . $id));
            }
        }
        $this->userRepository->update($id, $tenantId, ['status' => 'inactive']);
        $this->adminAuditService->logUserDeactivated($tenantId, $actorUserId, $id);
        $successMsg = 'Utilisateur désactivé.';
        if ($request->input('block_email_rejoin') === '1') {
            $memberEmail = strtolower(trim((string) ($user['email'] ?? '')));
            if ($memberEmail !== '' && filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    $this->indicatorBlocklist->addEmailBlock(
                        $actorUserId,
                        'tenant',
                        $tenantId,
                        $memberEmail,
                        'Suite au retrait d’accès à la communauté',
                        null,
                        null
                    );
                    $successMsg .= ' L’adresse e-mail ne pourra plus servir à rejoindre cette communauté.';
                } catch (\Throwable) {
                    Session::flash('warning', 'Accès retiré, mais la consigne sur l’adresse e-mail n’a pas pu être enregistrée. Ajoutez-la depuis la page Modération si besoin.');
                }
            }
        }
        Session::flash('success', $successMsg);

        return Response::redirect(url('back-office/users'));
    }

    public function resendVerificationEmail(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('back-office/users'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/users'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $returnList = $request->input('_return') === 'list';
        $backUsers = url('back-office/users');
        $backUser = url('back-office/users/' . $id);
        if (!$tenantId || !$id) {
            return Response::redirect($backUsers);
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');

            return Response::redirect($backUsers);
        }
        if ($this->userRepository->isServiceAccount($id)) {
            Session::flash('error', 'Cette action ne s’applique pas aux comptes techniques.');

            return Response::redirect($returnList ? $backUsers : $backUser);
        }
        if (($user['status'] ?? '') !== 'pending_verification') {
            Session::flash('error', 'Le renvoi du lien de confirmation n’est utile que pour un compte en attente de vérification de l’e-mail.');

            return Response::redirect($returnList ? $backUsers : $backUser);
        }
        $last = $this->emailTokenRepository->getLatestTokenCreatedAtForUserPurpose($id, EmailTokenPurpose::REGISTER_CONFIRM);
        if ($last !== null && (time() - $last->getTimestamp()) < self::RESEND_VERIFICATION_COOLDOWN_SEC) {
            $wait = self::RESEND_VERIFICATION_COOLDOWN_SEC - (time() - $last->getTimestamp());
            Session::flash(
                'error',
                'Un e-mail a déjà été envoyé récemment. Veuillez patienter encore environ ' . max(1, $wait) . ' seconde(s) avant de renvoyer.'
            );

            return Response::redirect($returnList ? $backUsers : $backUser);
        }
        $tenantRow = $this->tenantRepository->findById($tenantId);
        $tenantName = (string) ($tenantRow['name'] ?? 'Communauté');
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            Session::flash('error', 'Ce compte n’a pas d’adresse e-mail : impossible d’envoyer le lien.');

            return Response::redirect($returnList ? $backUsers : $backUser);
        }
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expires = new \DateTimeImmutable('+15 minutes');
        $verifyUrl = url('verify-email') . '?token=' . rawurlencode($rawToken);
        $ok = $this->emailService->sendUserRegisterConfirmation(
            $email,
            (string) ($user['display_name'] ?? 'Membre'),
            $tenantName,
            $verifyUrl,
            15,
            $tenantId
        );
        if (!$ok) {
            Session::flash(
                'error',
                'L’e-mail n’a pas pu être envoyé. Vérifiez la configuration d’envoi des courriels ou réessayez plus tard.'
            );

            return Response::redirect($returnList ? $backUsers : $backUser);
        }
        $this->emailTokenRepository->deletePendingForUserPurpose($id, EmailTokenPurpose::REGISTER_CONFIRM);
        $this->emailTokenRepository->create(
            $tenantId,
            $id,
            EmailTokenPurpose::REGISTER_CONFIRM,
            $tokenHash,
            bin2hex(random_bytes(16)),
            $expires
        );
        $notice = \email_file_mailer_notice();
        if ($notice !== '') {
            Session::flash('warning', $notice);
        }
        Session::flash(
            'success',
            'Un nouveau lien de confirmation a été envoyé à l’adresse du compte. Demandez au membre de vérifier sa boîte e-mail (et les courriers indésirables).'
        );

        return Response::redirect($returnList ? $backUsers : $backUser);
    }
}
