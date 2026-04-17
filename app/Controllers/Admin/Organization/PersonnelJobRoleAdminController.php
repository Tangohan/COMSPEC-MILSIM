<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PermissionRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\PersonnelJobRoleAssignmentsSettings;
use App\Support\MosInputValidator;
use App\Support\OrganizationRoleLabels;

class PersonnelJobRoleAdminController
{
    public function __construct(
        private PersonnelJobRoleRepository $jobRoleRepository,
        private PermissionRepository $permissionRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private UserRepository $userRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private TenantRepository $tenantRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!$this->canManageJobRoles()) {
            Session::flash('error', 'Vous n’avez pas les droits pour gérer le référentiel des emplois.');

            return Response::redirect(url('dashboard'));
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
        if (!$this->canManageJobRoles()) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
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
        if (!$this->canManageJobRoles()) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
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

        $mosCodeRaw = trim((string) $request->input('mos_code', ''));
        $mosTitleRaw = trim((string) $request->input('mos_specialty_title', ''));
        $mosCode = MosInputValidator::normalizeCode($mosCodeRaw !== '' ? $mosCodeRaw : null);
        $mosTitle = MosInputValidator::normalizeSpecialtyTitle($mosTitleRaw !== '' ? $mosTitleRaw : null);
        if ($mosCodeRaw !== '' && $mosCode === null) {
            Session::flash('error', 'Le code de spécialité de référence (format type 11B, 25U, 17C) est invalide.');

            return Response::redirect($id > 0 ? url('back-office/personnel-job-roles/roles/' . $id . '/edit') : url('back-office/personnel-job-roles/roles/create'));
        }
        if ($mosTitleRaw !== '' && $mosTitle === null) {
            Session::flash('error', 'L’intitulé officiel anglais est trop long ou invalide.');

            return Response::redirect($id > 0 ? url('back-office/personnel-job-roles/roles/' . $id . '/edit') : url('back-office/personnel-job-roles/roles/create'));
        }

        if ($id > 0) {
            $existing = $this->jobRoleRepository->findRoleById($id, $tenantId);
            if (!$existing) {
                Session::flash('error', 'Rôle introuvable.');

                return Response::redirect(url('back-office/personnel-job-roles'));
            }
            if (!empty($existing['is_system'])) {
                $mosCode = isset($existing['mos_code']) ? (is_string($existing['mos_code']) ? trim($existing['mos_code']) : null) : null;
                $mosCode = $mosCode !== '' ? $mosCode : null;
                $mosTitle = isset($existing['mos_specialty_title']) ? (is_string($existing['mos_specialty_title']) ? trim($existing['mos_specialty_title']) : null) : null;
                $mosTitle = $mosTitle !== '' ? $mosTitle : null;
            }
            $this->jobRoleRepository->updateRole($id, $tenantId, $categoryId, $name, $slug, $description, $sortOrder, $mosCode, $mosTitle);
            $this->jobRoleRepository->setPermissionsForRole($id, $permIds);
            Session::flash('success', 'Rôle métier enregistré.');
        } else {
            $newId = $this->jobRoleRepository->createRole($tenantId, $categoryId, $name, $slug, $description, $sortOrder, false, $mosCode, $mosTitle);
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
        if (!$this->canManageJobRoles()) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
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
        if (!$this->canManageJobRoles()) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
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
        if (!$this->canManageAssignments()) {
            Session::flash('error', 'Vous n’avez pas les droits pour attribuer les emplois.');

            return Response::redirect(url('dashboard'));
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
        $tenantSettings = $this->tenantRepository->getSettings($tenantId);
        $pjrAssignSettings = PersonnelJobRoleAssignmentsSettings::resolve($tenantSettings);
        $perPage = $pjrAssignSettings['assignments_page_size'];
        $total = $this->jobRoleRepository->countUsersForJobRoleAssignments($tenantId, $search, $filterJobRoleId, $onlyUnassigned);
        $rows = $this->jobRoleRepository->listUsersForJobRoleAssignments(
            $tenantId,
            $search,
            $filterJobRoleId,
            $onlyUnassigned,
            $perPage,
            ($page - 1) * $perPage
        );
        $userIds = array_values(array_filter(array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows), static fn (int $id): bool => $id > 0));
        $assignmentPivot = $this->jobRoleRepository->pivotTableExists()
            ? $this->jobRoleRepository->listPivotAssignmentsForUsers($tenantId, $userIds)
            : [];
        $community = is_array($tenantSettings['community'] ?? null) ? $tenantSettings['community'] : [];
        $tenantRow = $this->tenantRepository->findById($tenantId) ?: [];
        $orgRoleLabelMode = OrganizationRoleLabels::mode($community, $tenantRow);
        $jobRoleOptions = $this->jobRoleRepository->listRoleOptionsForSelect(
            $tenantId,
            $pjrAssignSettings['show_english_labels'],
            $pjrAssignSettings['show_category_in_role_picklist'],
            $orgRoleLabelMode
        );
        $jobRolePermissionCounts = $this->jobRoleRepository->permissionCountsForTenant($tenantId);
        $totalPages = max(1, (int) ceil($total / $perPage));

        return Response::view('layout.main', [
            'content' => 'admin.organization.personnel_job_roles.assignments',
            'title' => 'Attributions rôles métier',
            'assignmentRows' => $rows,
            'assignmentPivot' => $assignmentPivot,
            'jobRoleOptions' => $jobRoleOptions,
            'jobRolePermissionCounts' => $jobRolePermissionCounts,
            'pjrAssignSettings' => $pjrAssignSettings,
            'pivotEnabled' => $this->jobRoleRepository->pivotTableExists(),
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

    /**
     * Aperçu des autorisations liées aux emplois attribués (référentiel), fusionnées par membre.
     */
    public function memberJobRolePermissions(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::json(['ok' => false, 'message' => 'Session expirée.'], 401);
        }
        if (!$this->canManageAssignments()) {
            return Response::json(['ok' => false, 'message' => 'Permission refusée.'], 403);
        }
        if (!$this->jobRoleRepository->tablesExist() || !$this->jobRoleRepository->personnelProfilesHaveJobRoleColumns()) {
            return Response::json(['ok' => false, 'message' => 'Fonction indisponible.'], 503);
        }
        $userId = max(0, (int) $request->query('user_id', 0));
        if ($userId <= 0) {
            return Response::json(['ok' => false, 'message' => 'Membre invalide.'], 400);
        }
        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user) {
            return Response::json(['ok' => false, 'message' => 'Membre introuvable dans cette communauté.'], 404);
        }

        $permCounts = $this->jobRoleRepository->permissionCountsForTenant($tenantId);
        $assignedRoles = [];
        $seenRole = [];
        if ($this->jobRoleRepository->pivotTableExists()) {
            $pivot = $this->jobRoleRepository->listPivotAssignmentsForUsers($tenantId, [$userId]);
            foreach ($pivot[$userId] ?? [] as $row) {
                $rid = (int) ($row['personnel_job_role_id'] ?? 0);
                if ($rid <= 0 || isset($seenRole[$rid])) {
                    continue;
                }
                $seenRole[$rid] = true;
                $assignedRoles[] = [
                    'id' => $rid,
                    'name' => (string) ($row['role_name'] ?? ''),
                    'primary' => !empty($row['is_primary']),
                    'permission_count' => $permCounts[$rid] ?? 0,
                ];
            }
        }
        if ($assignedRoles === []) {
            $prof = $this->personnelProfileRepository->getByUserId($userId);
            $rid = (int) ($prof['personnel_job_role_id'] ?? 0);
            if ($rid > 0) {
                $role = $this->jobRoleRepository->findRoleById($rid, $tenantId);
                $assignedRoles[] = [
                    'id' => $rid,
                    'name' => (string) ($role['name'] ?? ''),
                    'primary' => true,
                    'permission_count' => $permCounts[$rid] ?? 0,
                ];
            }
        }

        $roleIds = array_values(array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $assignedRoles));
        $rawPerms = $this->jobRoleRepository->listDistinctPermissionsLinkedToJobRoles($tenantId, $roleIds);
        $permissions = [];
        foreach ($rawPerms as $p) {
            $mid = (string) ($p['module'] ?? '');
            $permissions[] = [
                'name' => (string) ($p['name'] ?? ''),
                'module' => $mid,
                'module_label' => self::permissionModuleLabelFr($mid),
            ];
        }

        return Response::json([
            'ok' => true,
            'member' => [
                'display_name' => (string) ($user['display_name'] ?? ''),
            ],
            'assigned_roles' => $assignedRoles,
            'permissions' => $permissions,
            'distinct_count' => count($permissions),
            'disclaimer' => 'Cette liste regroupe les autorisations associées aux emplois dans le référentiel (onglet « Permissions » de chaque emploi). Les accès réels sur le portail dépendent aussi du rôle communauté du membre et des réglages généraux.',
        ]);
    }

    private static function permissionModuleLabelFr(string $module): string
    {
        return match ($module) {
            'admin' => 'Administration',
            'dashboard' => 'Tableau de bord',
            'forum' => 'Forum',
            'documents' => 'Documents',
            'training' => 'Formations',
            'personnel' => 'Personnel',
            'organization' => 'Organisation',
            'interteam' => 'Inter-unités',
            'courrier' => 'Courrier',
            'atak' => 'Carte et terrain',
            'community' => 'Communauté',
            'messages' => 'Messagerie',
            'equipment' => 'Équipement',
            'pointage' => 'Présence',
            default => $module === '' ? 'Autre' : mb_convert_case(str_replace(['_', '-'], ' ', $module), MB_CASE_TITLE, 'UTF-8'),
        };
    }

    public function saveAssignmentsSettings(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel-job-roles/assignments'));
        }
        if (!$this->canManageAssignments()) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
        }
        $current = $this->tenantRepository->getSettings($tenantId);
        $patch = [
            'max_roles_per_member' => max(1, min(12, (int) $request->input('max_roles_per_member', 5))),
            'require_primary_when_multiple' => $request->input('require_primary_when_multiple') === '1',
            'assignments_page_size' => max(10, min(100, (int) $request->input('assignments_page_size', 30))),
            'show_english_labels' => $request->input('show_english_labels') === '1',
            'append_secondaries_to_primary_display' => $request->input('append_secondaries_to_primary_display') === '1',
            'show_category_in_role_picklist' => $request->input('show_category_in_role_picklist') === '1',
            'default_expand_role_rows' => max(1, min(12, (int) $request->input('default_expand_role_rows', 3))),
        ];
        $merged = PersonnelJobRoleAssignmentsSettings::mergePatch($current, $patch);
        $this->tenantRepository->updateSettings($tenantId, $merged);
        Session::flash('success', 'Paramètres d’attribution enregistrés pour votre organisation.');

        return Response::redirect(url('back-office/personnel-job-roles/assignments'));
    }

    public function saveAssignment(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel-job-roles/assignments'));
        }
        if (!$this->canManageAssignments()) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
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

        $tenantSettings = $this->tenantRepository->getSettings($tenantId);
        $assignCfg = PersonnelJobRoleAssignmentsSettings::resolve($tenantSettings);
        $roleSubLabelGlobal = trim((string) $request->input('role_sub_label'));

        $pivotResult = [
            'primary_job_role_id' => null,
            'primary_detail' => '',
            'primary_role_display' => '',
            'secondary_role_display' => '',
        ];

        if ($this->jobRoleRepository->pivotTableExists()) {
            $slotsIn = $request->input('slots');
            if (!is_array($slotsIn)) {
                $slotsIn = [];
            }
            $parsed = [];
            foreach ($slotsIn as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $rid = (int) ($s['role_id'] ?? 0);
                if ($rid <= 0) {
                    continue;
                }
                $parsed[] = [
                    'personnel_job_role_id' => $rid,
                    'role_detail' => trim((string) ($s['detail'] ?? '')),
                    'is_primary' => false,
                ];
            }
            $max = $assignCfg['max_roles_per_member'];
            if (count($parsed) > $max) {
                $parsed = array_slice($parsed, 0, $max);
            }
            $primaryIdx = (int) $request->input('primary_slot', 0);
            if ($parsed !== []) {
                if ($primaryIdx < 0 || $primaryIdx >= count($parsed)) {
                    $primaryIdx = 0;
                }
                foreach ($parsed as $i => &$p) {
                    $p['is_primary'] = ($i === $primaryIdx);
                }
                unset($p);
            }
            try {
                $pivotResult = $this->jobRoleRepository->replaceUserPivotJobRoles($tenantId, $userId, $parsed);
            } catch (\Throwable) {
                Session::flash('error', 'Enregistrement impossible (rôles métier).');

                return Response::redirect($this->assignmentsRedirectAfterSave($request));
            }

            $primaryBase = $pivotResult['primary_role_display'];
            if ($roleSubLabelGlobal !== '' && $primaryBase !== '') {
                $primaryBase .= ' — ' . $roleSubLabelGlobal;
            } elseif ($roleSubLabelGlobal !== '' && $primaryBase === '') {
                $primaryBase = $roleSubLabelGlobal;
            }

            $secondaryField = $pivotResult['secondary_role_display'];
            $primaryRoleStr = $primaryBase;
            if ($assignCfg['append_secondaries_to_primary_display'] && $secondaryField !== '') {
                $primaryRoleStr = $primaryBase !== '' ? $primaryBase . ' · ' . $secondaryField : $secondaryField;
            }
            $primaryRoleStr = $this->truncate100($primaryRoleStr);
            $secondaryFieldStored = $this->truncate100($secondaryField);

            $this->personnelProfileRepository->ensureRecord($userId);
            $this->personnelProfileRepository->update($userId, [
                'personnel_job_role_id' => $pivotResult['primary_job_role_id'],
                'role_sub_label' => $roleSubLabelGlobal !== '' ? $roleSubLabelGlobal : null,
                'primary_role' => $primaryRoleStr,
                'secondary_role' => $assignCfg['append_secondaries_to_primary_display'] ? null : ($secondaryFieldStored !== '' ? $secondaryFieldStored : null),
            ]);
        } else {
            $rawJr = $request->input('personnel_job_role_id');
            $jobRoleId = ($rawJr === null || $rawJr === '') ? null : (int) $rawJr;
            if ($jobRoleId !== null && $jobRoleId <= 0) {
                $jobRoleId = null;
            }
            $jrRow = null;
            if ($jobRoleId !== null) {
                $jrRow = $this->jobRoleRepository->findRoleById($jobRoleId, $tenantId);
                if (!$jrRow) {
                    Session::flash('error', 'Rôle métier invalide.');

                    return Response::redirect(url('back-office/personnel-job-roles/assignments'));
                }
            }
            $primaryRoleStr = $this->truncate100($this->buildPrimaryRoleString($jrRow, $roleSubLabelGlobal));

            $this->personnelProfileRepository->ensureRecord($userId);
            $this->personnelProfileRepository->update($userId, [
                'personnel_job_role_id' => $jobRoleId,
                'role_sub_label' => $roleSubLabelGlobal !== '' ? $roleSubLabelGlobal : null,
                'primary_role' => $primaryRoleStr,
            ]);
        }

        $profile = $this->personnelProfileRepository->getByUserId($userId) ?? [];
        $primaryUnitId = isset($profile['primary_unit_id']) ? (int) $profile['primary_unit_id'] : 0;
        $orbatRole = (string) ($profile['primary_role'] ?? '');
        if ($primaryUnitId > 0) {
            try {
                $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier($userId, $primaryUnitId, $orbatRole);
            } catch (\Throwable) {
                Session::flash('error', 'Rôle enregistré, mais la synchronisation ORBAT a échoué. Vérifiez l’unité principale du dossier.');

                return Response::redirect($this->assignmentsRedirectAfterSave($request));
            }
        }

        Session::flash('success', 'Rôles métier du dossier mis à jour pour ' . trim((string) ($user['display_name'] ?? 'le membre')) . '.');

        return Response::redirect($this->assignmentsRedirectAfterSave($request));
    }

    private function truncate100(string $s): string
    {
        if (function_exists('mb_strlen') && mb_strlen($s) > 100) {
            return mb_substr($s, 0, 100);
        }
        if (strlen($s) > 100) {
            return substr($s, 0, 100);
        }

        return $s;
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

    private function canManageJobRoles(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization')
            || $gate->allows('admin.roles.manage')
            || $gate->allows('personnel.assignments.manage');
    }

    private function canManageAssignments(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization')
            || $gate->allows('personnel.assignments.manage')
            || $gate->allows('admin.roles.manage');
    }
}
