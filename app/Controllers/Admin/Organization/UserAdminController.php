<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
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
use App\Services\Admin\ProfileCompletenessService;
use App\Services\Admin\AdminAuditService;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Services\GradeValidationService;
use App\Services\Personnel\PersonnelCompletenessService;

class UserAdminController
{
    private const SETUP_TOKEN_HOURS = 72;

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
        private UserNotificationPreferencesRepository $userNotificationPreferencesRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $search = $this->queryString($request->query('search'));
        $status = $this->queryString($request->query('status'));
        $roleId = $this->positiveIntOrNull($request->query('role_id'));
        $filterIncomplete = $request->query('filter_incomplete') === '1' || $request->query('filter_incomplete') === 'true';
        $filterNoUnit = $request->query('filter_no_unit') === '1' || $request->query('filter_no_unit') === 'true';
        $filterNoRole = $request->query('filter_no_role') === '1' || $request->query('filter_no_role') === 'true';
        $showTechnicalAccounts = $request->query('show_technical') === '1' || $request->query('show_technical') === 'true';
        $excludeServiceAccounts = ! $showTechnicalAccounts;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $statusFilter = ($status !== null && $status !== '') ? $status : null;

        $roles = $this->roleRepository->forTenantOrganization($tenantId);

        if ($filterIncomplete) {
            $allUsers = $this->userRepository->listForTenant($tenantId, $search, $statusFilter, $roleId, null, null, $excludeServiceAccounts);
            $completenessByUser = [];
            $personnelCompletenessByUser = [];
            foreach ($allUsers as $u) {
                $uid = (int) $u['id'];
                $up = $this->userProfileRepository->getByUserId($uid);
                $pp = $this->profileCompletenessService->getCompleteness($uid, $u, $up, null);
                $completenessByUser[$uid] = $pp;
                $personnelCompletenessByUser[$uid] = $this->buildPersonnelCompletenessForList($uid, $u, $tenantId);
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
                $completenessByUser[$uid] = $this->profileCompletenessService->getCompleteness($uid, $u, $up, null);
                $personnelCompletenessByUser[$uid] = $this->buildPersonnelCompletenessForList($uid, $u, $tenantId);
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
                'show_technical' => $showTechnicalAccounts,
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
        $completenessAccount = $this->profileCompletenessService->getCompleteness($id, $user, $userProfile, $personnelProfile);
        $isService = $this->userRepository->isServiceAccount($id);
        $extras = $this->personnelExtrasRepository->getByUserId($id);
        $civilProfile = $this->personnelExtrasRepository->getProfileByUserId($id);
        $completenessPersonnel = $isService
            ? null
            : $this->personnelCompletenessService->getScoreWithMissingLabels($id, $user, $civilProfile, $extras, $tenantId);
        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $userRoleIds = $this->userRepository->listOrganizationRoleIdsForUser($id);
        if ($userRoleIds === [] && !empty($user['role_id'])) {
            $userRoleIds = [(int) $user['role_id']];
        }
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $gradeCategories = $this->gradeCategoryRepository->listActive();
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
            'grades' => $grades,
            'gradeCategories' => $gradeCategories,
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
    private function buildPersonnelCompletenessForList(int $userId, array $userRow, int $tenantId): array
    {
        if ($this->userRepository->isServiceAccount($userId)) {
            return ['score' => 100, 'sections_critiques' => [], 'missing_labels' => []];
        }
        $extras = $this->personnelExtrasRepository->getByUserId($userId);
        $civil = $this->personnelExtrasRepository->getProfileByUserId($userId);

        return $this->personnelCompletenessService->getScoreWithMissingLabels($userId, $userRow, $civil, $extras, $tenantId);
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

                return Response::redirect(url('back-office/users/create'));
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
            return Response::redirect(url('back-office/users/create'));
        }
        if ($this->userRepository->emailExistsInTenant($tenantId, $email)) {
            Session::flash('error', 'Cet email est déjà utilisé.');
            return Response::redirect(url('back-office/users/create'));
        }

        $gate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
        if (!$gate->canAddMember($tenantId)) {
            Session::flash('error', 'Limite de membres du plan atteinte.');
            return Response::redirect(url('back-office/users/create'));
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
        $this->userRepository->syncOrganizationRoles($userId, $tenantId, $roleIds);

        $this->passwordResetRepository->deleteExpired();
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expires = new \DateTimeImmutable('+' . self::SETUP_TOKEN_HOURS . ' hours');
        $this->passwordResetRepository->create($userId, $tokenHash, $expires);

        $setupUrl = url('reset-password') . '?token=' . rawurlencode($rawToken);
        $tenantRow = $this->tenantRepository->findById($tenantId);
        $tenantName = $tenantRow ? (string) ($tenantRow['name'] ?? 'Communauté') : 'Communauté';
        $sent = $this->emailService->sendTenantUserSetupInvite(
            $email,
            $setupUrl,
            self::SETUP_TOKEN_HOURS,
            $tenantName,
            $tenantId
        );

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
            $this->userRepository->syncOrganizationRoles($id, $tenantId, $roleIds);
            $this->adminAuditService->logRoleAssigned(
                $tenantId,
                $actorUserId,
                $id,
                $oldRoleIds !== [] ? implode(',', $oldRoleIds) : null,
                $roleIds !== [] ? implode(',', $roleIds) : null
            );
            $rolesSynced = true;
        }
        if ($request->input('grade_id') !== null) {
            $data['grade_id'] = $request->input('grade_id') ? (int) $request->input('grade_id') : null;
        }
        if ($request->input('status') !== null) {
            $data['status'] = trim((string) $request->input('status'));
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

        if (!empty($data)) {
            $this->userRepository->update($id, $tenantId, $data);
            $this->adminAuditService->logUserUpdated($tenantId, $actorUserId, $id);
            Session::flash('success', 'Utilisateur mis à jour.');
        } elseif ($rolesSynced) {
            Session::flash('success', 'Rôles mis à jour.');
        }

        return Response::redirect(url('back-office/users/' . $id));
    }

    public function deactivate(Request $request, array $params = []): Response
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
        Session::flash('success', 'Utilisateur désactivé.');
        return Response::redirect(url('back-office/users'));
    }
}
