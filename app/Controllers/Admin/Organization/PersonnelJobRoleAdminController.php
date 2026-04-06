<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PermissionRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\UserRepository;

class PersonnelJobRoleAdminController
{
    public function __construct(
        private PersonnelJobRoleRepository $jobRoleRepository,
        private PermissionRepository $permissionRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private UserRepository $userRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!$this->jobRoleRepository->tablesExist()) {
            return (new Response())->setStatusCode(503)->setBody('Migration rôles métier non appliquée. Exécutez les migrations.');
        }
        $categories = $this->jobRoleRepository->listCategories($tenantId);
        $roles = $this->jobRoleRepository->listRolesWithCategory($tenantId);
        $permCounts = $this->jobRoleRepository->permissionCountsForTenant($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.organization.personnel_job_roles.index',
            'title' => 'Rôles métier (dossier)',
            'categories' => $categories,
            'roles' => $roles,
            'permCounts' => $permCounts,
            'activeTab' => 'referentiel',
            'personnelProfilesJobRoleReady' => $this->jobRoleRepository->personnelProfilesHaveJobRoleColumns(),
        ]);
    }

    public function createRole(Request $request, array $params = []): Response
    {
        return $this->editRole($request, ['id' => '0']);
    }

    public function editRole(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!$this->jobRoleRepository->tablesExist()) {
            return (new Response())->setStatusCode(503)->setBody('Migration rôles métier non appliquée.');
        }
        $id = (int) ($params['id'] ?? 0);
        $role = $id > 0 ? $this->jobRoleRepository->findRoleById($id, $tenantId) : null;
        if ($id > 0 && !$role) {
            Session::flash('error', 'Rôle introuvable.');

            return Response::redirect(url('back-office/personnel-job-roles'));
        }
        $categories = $this->jobRoleRepository->listCategories($tenantId);
        $catOptions = $this->buildCategoryOptions($categories);
        $permissions = $this->permissionRepository->allForTenant($tenantId);
        $selectedPerm = $role ? $this->jobRoleRepository->getPermissionIdsForRole($id) : [];

        return Response::view('layout.main', [
            'content' => 'admin.organization.personnel_job_roles.role_form',
            'title' => $role ? 'Modifier le rôle métier' : 'Nouveau rôle métier',
            'role' => $role,
            'categories' => $categories,
            'catOptions' => $catOptions,
            'permissions' => $permissions,
            'selectedPerm' => $selectedPerm,
            'activeTab' => 'referentiel',
        ]);
    }

    public function saveRole(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel-job-roles'));
        }
        $id = (int) $request->input('id', 0);
        $categoryId = (int) $request->input('category_id', 0);
        $name = trim((string) $request->input('name'));
        $slug = trim((string) $request->input('slug'));
        $description = trim((string) $request->input('description')) ?: null;
        $sortOrder = (int) $request->input('sort_order', 0);
        $permRaw = $request->input('permissions', []);
        $permIds = is_array($permRaw) ? array_map('intval', $permRaw) : [];
        $allowedPermIds = array_map(static fn ($p) => (int) $p['id'], $this->permissionRepository->allForTenant($tenantId));
        $permIds = array_values(array_intersect($permIds, $allowedPermIds));

        if ($name === '' || $slug === '' || $categoryId <= 0) {
            Session::flash('error', 'Nom, identifiant et catégorie sont requis.');

            return Response::redirect(url('back-office/personnel-job-roles'));
        }
        if (!$this->jobRoleRepository->findCategoryById($categoryId, $tenantId)) {
            Session::flash('error', 'Catégorie invalide.');

            return Response::redirect(url('back-office/personnel-job-roles'));
        }

        if ($id > 0) {
            $existing = $this->jobRoleRepository->findRoleById($id, $tenantId);
            if (!$existing) {
                Session::flash('error', 'Rôle introuvable.');

                return Response::redirect(url('back-office/personnel-job-roles'));
            }
            $this->jobRoleRepository->updateRole($id, $tenantId, $categoryId, $name, $slug, $description, $sortOrder);
            $this->jobRoleRepository->setPermissionsForRole($id, $permIds);
            Session::flash('success', 'Rôle métier enregistré.');
        } else {
            $newId = $this->jobRoleRepository->createRole($tenantId, $categoryId, $name, $slug, $description, $sortOrder, false);
            $this->jobRoleRepository->setPermissionsForRole($newId, $permIds);
            Session::flash('success', 'Rôle métier créé.');
        }

        return Response::redirect(url('back-office/personnel-job-roles'));
    }

    public function deleteRole(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel-job-roles'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::redirect(url('back-office/personnel-job-roles'));
        }
        if ($this->jobRoleRepository->deleteRole($id, $tenantId)) {
            Session::flash('success', 'Rôle supprimé.');
        } else {
            Session::flash('error', 'Suppression impossible (rôle système ou introuvable).');
        }

        return Response::redirect(url('back-office/personnel-job-roles'));
    }

    public function saveCategory(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel-job-roles'));
        }
        $id = (int) $request->input('category_id', 0);
        $parentRaw = $request->input('parent_id');
        $parentId = ($parentRaw === null || $parentRaw === '') ? null : (int) $parentRaw;
        if ($parentId !== null && $parentId <= 0) {
            $parentId = null;
        }
        $name = trim((string) $request->input('category_name'));
        $slug = trim((string) $request->input('category_slug'));
        $sortOrder = (int) $request->input('category_sort_order', 0);
        if ($name === '') {
            Session::flash('error', 'Le nom de la catégorie est requis.');

            return Response::redirect(url('back-office/personnel-job-roles'));
        }
        if ($slug === '') {
            $slug = $this->slugifyCategory($name);
        }
        if ($id > 0) {
            $existing = $this->jobRoleRepository->findCategoryById($id, $tenantId);
            if (!$existing) {
                Session::flash('error', 'Catégorie introuvable.');

                return Response::redirect(url('back-office/personnel-job-roles'));
            }
            if ($parentId === $id) {
                Session::flash('error', 'Une catégorie ne peut pas être son propre parent.');

                return Response::redirect(url('back-office/personnel-job-roles'));
            }
            $this->jobRoleRepository->updateCategory($id, $tenantId, $parentId, $name, $slug, $sortOrder);
            Session::flash('success', 'Catégorie enregistrée.');
        } else {
            $this->jobRoleRepository->createCategory($tenantId, $parentId, $name, $slug, $sortOrder);
            Session::flash('success', 'Catégorie créée.');
        }

        return Response::redirect(url('back-office/personnel-job-roles'));
    }

    public function deleteCategory(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel-job-roles'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::redirect(url('back-office/personnel-job-roles'));
        }
        if ($this->jobRoleRepository->deleteCategory($id, $tenantId)) {
            Session::flash('success', 'Catégorie supprimée.');
        } else {
            Session::flash('error', 'Suppression impossible : sous-catégories ou rôles encore rattachés.');
        }

        return Response::redirect(url('back-office/personnel-job-roles'));
    }

    public function assignments(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!$this->jobRoleRepository->tablesExist() || !$this->jobRoleRepository->personnelProfilesHaveJobRoleColumns()) {
            return (new Response())->setStatusCode(503)->setBody('Migration rôles métier non appliquée. Exécutez les migrations.');
        }
        $search = trim((string) $request->query('search', ''));
        $search = $search !== '' ? $search : null;
        $filterJobRoleId = (int) $request->query('job_role_id', 0);
        $filterJobRoleId = $filterJobRoleId > 0 ? $filterJobRoleId : null;
        $onlyUnassigned = $request->query('unassigned') === '1' || $request->query('unassigned') === 'true';
        if ($onlyUnassigned) {
            $filterJobRoleId = null;
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 30;
        $total = $this->jobRoleRepository->countUsersForJobRoleAssignments($tenantId, $search, $filterJobRoleId, $onlyUnassigned);
        $rows = $this->jobRoleRepository->listUsersForJobRoleAssignments(
            $tenantId,
            $search,
            $filterJobRoleId,
            $onlyUnassigned,
            $perPage,
            ($page - 1) * $perPage
        );
        $jobRoleOptions = $this->jobRoleRepository->listRoleOptionsForSelect($tenantId);
        $totalPages = max(1, (int) ceil($total / $perPage));

        return Response::view('layout.main', [
            'content' => 'admin.organization.personnel_job_roles.assignments',
            'title' => 'Attributions rôles métier',
            'assignmentRows' => $rows,
            'jobRoleOptions' => $jobRoleOptions,
            'filters' => [
                'search' => $search ?? '',
                'job_role_id' => $filterJobRoleId ?? 0,
                'unassigned' => $onlyUnassigned,
            ],
            'assignmentsTotal' => $total,
            'assignmentsPage' => $page,
            'assignmentsPerPage' => $perPage,
            'assignmentsTotalPages' => $totalPages,
            'activeTab' => 'assignments',
        ]);
    }

    public function saveAssignment(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel-job-roles/assignments'));
        }
        if (!$this->jobRoleRepository->tablesExist() || !$this->jobRoleRepository->personnelProfilesHaveJobRoleColumns()) {
            Session::flash('error', 'Migration non appliquée.');

            return Response::redirect(url('back-office/personnel-job-roles/assignments'));
        }
        $userId = (int) $request->input('user_id', 0);
        if ($userId <= 0) {
            Session::flash('error', 'Utilisateur invalide.');

            return Response::redirect(url('back-office/personnel-job-roles/assignments'));
        }
        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable dans cette communauté.');

            return Response::redirect(url('back-office/personnel-job-roles/assignments'));
        }
        $rawJr = $request->input('personnel_job_role_id');
        $jobRoleId = ($rawJr === null || $rawJr === '') ? null : (int) $rawJr;
        if ($jobRoleId !== null && $jobRoleId <= 0) {
            $jobRoleId = null;
        }
        $roleSubLabel = trim((string) $request->input('role_sub_label'));
        $jrRow = null;
        if ($jobRoleId !== null) {
            $jrRow = $this->jobRoleRepository->findRoleById($jobRoleId, $tenantId);
            if (!$jrRow) {
                Session::flash('error', 'Rôle métier invalide.');

                return Response::redirect(url('back-office/personnel-job-roles/assignments'));
            }
        }
        $primaryRoleStr = $this->buildPrimaryRoleString($jrRow, $roleSubLabel);
        if (function_exists('mb_strlen') && mb_strlen($primaryRoleStr) > 100) {
            $primaryRoleStr = mb_substr($primaryRoleStr, 0, 100);
        } elseif (strlen($primaryRoleStr) > 100) {
            $primaryRoleStr = substr($primaryRoleStr, 0, 100);
        }

        $this->personnelProfileRepository->ensureRecord($userId);
        $this->personnelProfileRepository->update($userId, [
            'personnel_job_role_id' => $jobRoleId,
            'role_sub_label' => $roleSubLabel !== '' ? $roleSubLabel : null,
            'primary_role' => $primaryRoleStr,
        ]);

        $profile = $this->personnelProfileRepository->getByUserId($userId) ?? [];
        $primaryUnitId = isset($profile['primary_unit_id']) ? (int) $profile['primary_unit_id'] : 0;
        if ($primaryUnitId > 0) {
            try {
                $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier($userId, $primaryUnitId, $primaryRoleStr);
            } catch (\Throwable) {
                Session::flash('error', 'Rôle enregistré, mais la synchronisation ORBAT a échoué. Vérifiez l’unité principale du dossier.');

                return Response::redirect($this->assignmentsRedirectAfterSave($request));
            }
        }

        Session::flash('success', 'Rôle métier dossier mis à jour pour ' . trim((string) ($user['display_name'] ?? 'le membre')) . '.');

        return Response::redirect($this->assignmentsRedirectAfterSave($request));
    }

    private function assignmentsRedirectAfterSave(Request $request): string
    {
        $rq = trim((string) $request->input('return_query', ''));
        if ($rq !== '') {
            parse_str($rq, $parsed);
            if (is_array($parsed)) {
                $allowed = array_intersect_key($parsed, array_flip(['search', 'job_role_id', 'unassigned', 'page']));
                $qs = http_build_query(array_filter($allowed, static fn ($v) => $v !== null && $v !== ''));
                if ($qs !== '') {
                    return url('back-office/personnel-job-roles/assignments') . '?' . $qs;
                }
            }
        }
        $q = $request->queryParams();
        $keep = array_intersect_key($q, array_flip(['search', 'job_role_id', 'unassigned', 'page']));
        $qs = http_build_query(array_filter($keep, static fn ($v) => $v !== null && $v !== ''));

        return $qs !== '' ? url('back-office/personnel-job-roles/assignments') . '?' . $qs : url('back-office/personnel-job-roles/assignments');
    }

    /**
     * @param array<string, mixed>|null $jobRow ligne personnel_job_roles ou null
     */
    private function buildPrimaryRoleString(?array $jobRow, string $roleSubLabel): string
    {
        $roleSubLabel = trim($roleSubLabel);
        if ($jobRow !== null) {
            $n = trim((string) ($jobRow['name'] ?? ''));

            return $roleSubLabel !== '' ? $n . ' — ' . $roleSubLabel : $n;
        }

        return $roleSubLabel;
    }

    /**
     * @param list<array<string, mixed>> $categories
     * @return list<array{id: int, label: string}>
     */
    private function buildCategoryOptions(array $categories): array
    {
        $byId = [];
        foreach ($categories as $c) {
            $byId[(int) $c['id']] = $c;
        }
        $path = function (array $c) use (&$byId): string {
            $parts = [];
            $cur = $c;
            $g = 0;
            while ($cur && $g++ < 12) {
                array_unshift($parts, (string) $cur['name']);
                $pid = isset($cur['parent_id']) ? (int) $cur['parent_id'] : 0;
                $cur = $pid > 0 && isset($byId[$pid]) ? $byId[$pid] : null;
            }

            return implode(' › ', $parts);
        };
        $out = [];
        foreach ($categories as $c) {
            $out[] = ['id' => (int) $c['id'], 'label' => $path($c)];
        }

        return $out;
    }

    private function slugifyCategory(string $name): string
    {
        $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($s === false) {
            $s = $name;
        }
        $s = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $s) ?? '');
        $s = trim($s, '-');

        return $s !== '' ? $s : 'categorie';
    }
}
