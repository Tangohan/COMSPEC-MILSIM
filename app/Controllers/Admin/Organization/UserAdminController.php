<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RoleRepository;
use App\Repositories\GradeCategoryRepository;
use App\Repositories\GradeRepository;
use App\Services\Admin\ProfileCompletenessService;
use App\Services\Admin\AdminAuditService;
use App\Services\GradeValidationService;

class UserAdminController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private RoleRepository $roleRepository,
        private GradeRepository $gradeRepository,
        private GradeCategoryRepository $gradeCategoryRepository,
        private ProfileCompletenessService $profileCompletenessService,
        private AdminAuditService $adminAuditService,
        private GradeValidationService $gradeValidationService
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $search = $request->input('search');
        $status = $request->input('status');
        $roleId = $request->input('role_id') ? (int) $request->input('role_id') : null;
        $filterIncomplete = $request->input('filter_incomplete') === '1' || $request->input('filter_incomplete') === 'true';
        $users = $this->userRepository->listForTenant($tenantId, $search, $status !== '' ? $status : null, $roleId);
        $roles = $this->roleRepository->allForTenant($tenantId);
        $completenessByUser = [];
        foreach ($users as $u) {
            $up = $this->userProfileRepository->getByUserId((int) $u['id']);
            $pp = $this->profileCompletenessService->getCompleteness((int) $u['id'], $u, $up, null);
            $completenessByUser[(int) $u['id']] = $pp;
        }
        if ($filterIncomplete) {
            $users = array_filter($users, function ($u) use ($completenessByUser) {
                $comp = $completenessByUser[(int) $u['id']] ?? ['score' => 100, 'sections_critiques' => []];
                return $comp['score'] < 100 || !empty($comp['sections_critiques']);
            });
        }
        return Response::view('layout.main', [
            'content' => 'admin.organization.users.index',
            'title' => 'Utilisateurs',
            'users' => $users,
            'roles' => $roles,
            'completenessByUser' => $completenessByUser,
            'filters' => ['search' => $search, 'status' => $status, 'role_id' => $roleId, 'filter_incomplete' => $filterIncomplete],
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('admin/organization/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            return Response::redirect(url('admin/organization/users'));
        }
        $userProfile = $this->userProfileRepository->getByUserId($id);
        $personnelProfile = $this->personnelProfileRepository->getByUserId($id);
        $completeness = $this->profileCompletenessService->getCompleteness($id, $user, $userProfile, $personnelProfile);
        $roles = $this->roleRepository->allForTenant($tenantId);
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $gradeCategories = $this->gradeCategoryRepository->listActive();
        return Response::view('layout.main', [
            'content' => 'admin.organization.users.show',
            'title' => 'Fiche utilisateur',
            'user' => $user,
            'userProfile' => $userProfile,
            'completeness' => $completeness,
            'roles' => $roles,
            'grades' => $grades,
            'gradeCategories' => $gradeCategories,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $roles = $this->roleRepository->allForTenant($tenantId);
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $gradeCategories = $this->gradeCategoryRepository->listActive();
        return Response::view('layout.main', [
            'content' => 'admin.organization.users.create',
            'title' => 'Nouvel utilisateur',
            'roles' => $roles,
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
        $password = $request->input('password');
        $displayName = trim((string) $request->input('display_name'));
        $callsign = trim((string) $request->input('callsign'));
        $roleId = $request->input('role_id') ? (int) $request->input('role_id') : null;
        $gradeId = $request->input('grade_id') ? (int) $request->input('grade_id') : null;
        $status = trim((string) ($request->input('status') ?: 'pending'));
        $nationalityCode = trim((string) $request->input('nationality_code')) ?: null;
        $preferredGradeFormat = trim((string) $request->input('preferred_grade_format'));
        if (!in_array($preferredGradeFormat, ['classic', 'otan', 'hybrid'], true)) {
            $preferredGradeFormat = 'classic';
        }
        $professionalCategoryCode = trim((string) $request->input('professional_category_code')) ?: null;

        if ($email === '' || $password === '' || strlen($password) < 6) {
            Session::flash('error', 'Email et mot de passe (min. 6 caractères) requis.');
            return Response::redirect(url('admin/organization/users/create'));
        }
        if ($this->userRepository->emailExistsInTenant($tenantId, $email)) {
            Session::flash('error', 'Cet email est déjà utilisé.');
            return Response::redirect(url('admin/organization/users/create'));
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $userId = $this->userRepository->create($tenantId, [
            'email' => $email,
            'password_hash' => $passwordHash,
            'display_name' => $displayName ?: null,
            'callsign' => $callsign ?: null,
            'role_id' => $roleId,
            'grade_id' => $gradeId,
            'status' => $status ?: 'pending',
            'nationality_code' => $nationalityCode,
            'preferred_grade_format' => $preferredGradeFormat,
            'professional_category_code' => $professionalCategoryCode,
        ]);

        $this->adminAuditService->logUserCreated($tenantId, $actorUserId, $userId, $email);
        Session::flash('success', 'Utilisateur créé.');
        return Response::redirect(url('admin/organization/users/' . $userId));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('admin/organization/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            return Response::redirect(url('admin/organization/users'));
        }
        $userProfile = $this->userProfileRepository->getByUserId($id);
        $roles = $this->roleRepository->allForTenant($tenantId);
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $gradeCategories = $this->gradeCategoryRepository->listActive();
        $gradeValidationIssues = $this->gradeValidationService->validateUserProfile($user);
        return Response::view('layout.main', [
            'content' => 'admin.organization.users.edit',
            'title' => 'Modifier l\'utilisateur',
            'user' => $user,
            'userProfile' => $userProfile,
            'roles' => $roles,
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
            return Response::redirect(url('admin/organization/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            return Response::redirect(url('admin/organization/users'));
        }

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
                return Response::redirect(url('admin/organization/users/' . $id . '/edit'));
            }
            $data['email'] = $email;
        }
        if ($request->input('role_id') !== null) {
            $newRoleId = $request->input('role_id') ? (int) $request->input('role_id') : null;
            $oldRoleId = $user['role_id'] !== null ? (int) $user['role_id'] : null;
            $superAdminRoleId = $this->roleRepository->getIdBySlug($tenantId, 'super_admin');
            if ($superAdminRoleId !== null && $oldRoleId === $superAdminRoleId && $newRoleId !== $superAdminRoleId) {
                $count = $this->userRepository->countUsersWithRole($superAdminRoleId);
                if ($count <= 1) {
                    Session::flash('error', 'Impossible de retirer le rôle super-administrateur au dernier super-admin.');
                    return Response::redirect(url('admin/organization/users/' . $id . '/edit'));
                }
            }
            $data['role_id'] = $newRoleId;
            $this->adminAuditService->logRoleAssigned($tenantId, $actorUserId, $id, $oldRoleId !== null ? (string) $oldRoleId : null, $newRoleId !== null ? (string) $newRoleId : null);
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
                    return Response::redirect(url('admin/organization/users/' . $id . '/edit'));
                }
            }
        }

        if (!empty($data)) {
            $this->userRepository->update($id, $tenantId, $data);
            $this->adminAuditService->logUserUpdated($tenantId, $actorUserId, $id);
            Session::flash('success', 'Utilisateur mis à jour.');
        }

        return Response::redirect(url('admin/organization/users/' . $id));
    }

    public function deactivate(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$actorUserId || !$id) {
            return Response::redirect(url('admin/organization/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            return Response::redirect(url('admin/organization/users'));
        }
        $superAdminRoleId = $this->roleRepository->getIdBySlug($tenantId, 'super_admin');
        if ($superAdminRoleId !== null && (int) ($user['role_id'] ?? 0) === $superAdminRoleId) {
            $count = $this->userRepository->countUsersWithRole($superAdminRoleId);
            if ($count <= 1) {
                Session::flash('error', 'Impossible de désactiver le dernier super-administrateur.');
                return Response::redirect(url('admin/organization/users/' . $id));
            }
        }
        $this->userRepository->update($id, $tenantId, ['status' => 'inactive']);
        $this->adminAuditService->logUserDeactivated($tenantId, $actorUserId, $id);
        Session::flash('success', 'Utilisateur désactivé.');
        return Response::redirect(url('admin/organization/users'));
    }
}
