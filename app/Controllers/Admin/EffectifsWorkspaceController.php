<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ElevationRequestRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Admin\AdminAuditService;
use App\Services\Effectifs\EffectifsStaffAlertService;
use App\Support\EffectifsLmsAccess;

/**
 * Bureau LMS de pilotage des effectifs (outil RH) — shell type mes-formations / recrutement.
 */
class EffectifsWorkspaceController
{
    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private UnitRepository $unitRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private AdminAuditService $adminAuditService,
        private EffectifsStaffAlertService $effectifsStaffAlertService,
        private TenantRepository $tenantRepository,
        private ?ElevationRequestRepository $elevationRequestRepository = null,
    ) {
        $this->elevationRequestRepository ??= new ElevationRequestRepository();
    }

    public function roster(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');

        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        if ($status === 'pending') {
            $status = 'pending_verification';
        }
        $roleId = max(0, (int) $request->query('role_id', 0));
        $onlyNoUnit = $request->query('sans_affectation') === '1';
        $onlyNoRole = $request->query('sans_role') === '1';
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 80;

        $statusFilter = $status !== '' ? $status : null;
        $roleFilter = $roleId > 0 ? $roleId : null;
        $onlyWithoutUnit = $onlyNoUnit ? true : null;
        $onlyWithoutRole = $onlyNoRole ? true : null;
        $searchFilter = $search !== '' ? $search : null;

        $total = $this->userRepository->countListForTenant(
            $tenantId,
            $searchFilter,
            $statusFilter,
            $roleFilter,
            true,
            $onlyWithoutUnit,
            $onlyWithoutRole
        );
        $baseUsers = $this->userRepository->listForTenant(
            $tenantId,
            $searchFilter,
            $statusFilter,
            $roleFilter,
            $perPage,
            ($page - 1) * $perPage,
            true,
            $onlyWithoutUnit,
            $onlyWithoutRole
        );
        $rows = $this->enrichRosterRows($tenantId, $baseUsers);

        $counts = $this->rosterCounts($tenantId);
        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $units = $this->unitRepository->allForTenant($tenantId);
        $gate = Gate::getInstance();
        $communityName = $this->communityNameForTenant($tenantId);
        $elevationRecipients = $this->effectifsStaffAlertService->listElevationRecipients(
            $tenantId,
            (int) Session::get('user_id')
        );

        return $this->shell('admin.effectifs_workspace.roster', [
            'title' => 'Tableur des effectifs',
            'effectifsNav' => 'roster',
            'rosterRows' => $rows,
            'rosterTotal' => $total,
            'rosterPage' => $page,
            'rosterPerPage' => $perPage,
            'rosterTotalPages' => max(1, (int) ceil($total / $perPage)),
            'rosterFilters' => [
                'q' => $search,
                'status' => $status,
                'role_id' => $roleId,
                'sans_affectation' => $onlyNoUnit,
                'sans_role' => $onlyNoRole,
            ],
            'rosterCounts' => $counts,
            'orgRoles' => $roles,
            'orgUnits' => $units,
            'communityName' => $communityName,
            'elevationRecipientsCount' => count($elevationRecipients),
            'canEditProfiles' => EffectifsLmsAccess::canEditProfiles($gate),
            'canManageStatus' => EffectifsLmsAccess::canManageStatus($gate),
            'canManageAssignments' => EffectifsLmsAccess::canManageAssignments($gate),
            'canManageRoles' => EffectifsLmsAccess::canManageRoles($gate),
            'canManageGrades' => EffectifsLmsAccess::canManageGrades($gate),
            'canRequestElevation' => EffectifsLmsAccess::canRequestElevation($gate) && $elevationRecipients !== [],
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function member(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }

        $enriched = $this->enrichRosterRows($tenantId, [$user]);
        $row = $enriched[0] ?? $user;
        $assignments = $this->personnelAssignmentRepository->listActiveForUserResolved($id);
        $personnelProfile = $this->personnelProfileRepository->getByUserId($id);
        $roleIds = $this->userRepository->listOrganizationRoleIdsForUser($id);
        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $roleNames = [];
        foreach ($roles as $r) {
            if (in_array((int) ($r['id'] ?? 0), $roleIds, true)) {
                $roleNames[] = (string) ($r['name'] ?? '');
            }
        }
        $jobRoles = [];
        if ($this->personnelJobRoleRepository->tablesExist()) {
            $byUser = $this->personnelJobRoleRepository->listPivotAssignmentsForUsers($tenantId, [$id]);
            $jobRoles = $byUser[$id] ?? [];
        }
        $gate = Gate::getInstance();
        $units = $this->unitRepository->allForTenant($tenantId);
        $elevationRecipients = $this->effectifsStaffAlertService->listElevationRecipients(
            $tenantId,
            (int) Session::get('user_id')
        );

        return $this->shell('admin.effectifs_workspace.member', [
            'title' => 'Fiche membre',
            'effectifsNav' => 'roster',
            'member' => $row,
            'memberAssignments' => $assignments,
            'memberPersonnelProfile' => $personnelProfile,
            'memberRoleNames' => $roleNames,
            'memberJobRoles' => $jobRoles,
            'orgRoles' => $roles,
            'orgUnits' => $units,
            'communityName' => $this->communityNameForTenant($tenantId),
            'canEditProfiles' => EffectifsLmsAccess::canEditProfiles($gate),
            'canManageStatus' => EffectifsLmsAccess::canManageStatus($gate),
            'canManageAssignments' => EffectifsLmsAccess::canManageAssignments($gate),
            'canManageRoles' => EffectifsLmsAccess::canManageRoles($gate),
            'canManageGrades' => EffectifsLmsAccess::canManageGrades($gate),
            'canRequestElevation' => EffectifsLmsAccess::canRequestElevation($gate) && $elevationRecipients !== [],
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function quickStatus(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageStatus($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier le statut des comptes.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $status = trim((string) $request->input('status', ''));
        $allowed = ['active', 'inactive', 'pending_verification'];
        if ($id < 1 || !in_array($status, $allowed, true)) {
            Session::flash('error', 'Action impossible : statut non reconnu.');

            return Response::redirect(effectifs_workspace_url());
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $before = (string) ($user['status'] ?? '');
        $this->userRepository->update($id, $tenantId, ['status' => $status]);
        $actorId = (int) Session::get('user_id');
        $this->adminAuditService->logUserUpdated(
            $tenantId,
            $actorId,
            $id,
            'status:' . $before,
            'status:' . $status
        );
        $label = match ($status) {
            'active' => 'Compte actif',
            'inactive' => 'Compte inactif',
            'pending_verification' => 'En attente de vérification de l’e-mail',
            default => $status,
        };
        Session::flash('success', 'Statut mis à jour : ' . $label . '.');

        return Response::redirect(effectifs_workspace_url('membres/' . $id));
    }

    public function quickAssignment(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier les affectations.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->redirectBackToRoster($request));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $unitId = (int) $request->input('unit_id', 0);
        if ($id < 1) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }

        $unitName = '';
        if ($unitId > 0) {
            $unit = $this->unitRepository->findById($unitId, $tenantId);
            if (!$unit) {
                Session::flash('error', 'Unité introuvable dans cette communauté.');

                return Response::redirect($this->redirectBackToRoster($request));
            }
            $unitName = trim((string) ($unit['name'] ?? ''));
        }

        try {
            $this->personnelProfileRepository->ensureRecord($id);
            $this->personnelProfileRepository->update($id, [
                'primary_unit_id' => $unitId > 0 ? $unitId : null,
            ]);
            $roleName = trim((string) ($user['display_name'] ?? ''));
            $profile = $this->personnelProfileRepository->getByUserId($id);
            if ($profile) {
                $fromProfile = trim((string) ($profile['primary_role'] ?? ''));
                if ($fromProfile !== '') {
                    $roleName = $fromProfile;
                }
            }
            $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier(
                $id,
                $unitId > 0 ? $unitId : null,
                $roleName !== '' ? $roleName : 'Membre'
            );
            $actorId = (int) Session::get('user_id');
            $this->adminAuditService->logUserUpdated(
                $tenantId,
                $actorId,
                $id,
                'affectation',
                $unitId > 0 ? ('unit:' . $unitId) : 'unit:none'
            );
        } catch (\Throwable) {
            Session::flash('error', 'Impossible d’enregistrer l’affectation. Réessayez ou ouvrez le dossier personnel.');

            return Response::redirect($this->redirectBackToRoster($request));
        }

        Session::flash(
            'success',
            $unitId > 0
                ? ('Affectation enregistrée' . ($unitName !== '' ? ' : ' . $unitName : '') . '.')
                : 'Affectation retirée.'
        );

        $returnMember = $request->input('return_to') === 'member';

        return Response::redirect(
            $returnMember
                ? effectifs_workspace_url('membres/' . $id)
                : $this->redirectBackToRoster($request)
        );
    }

    public function requestElevation(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canRequestElevation($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à demander une élévation.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->redirectBackToRoster($request));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $kind = trim((string) $request->input('elevation_kind', 'general'));
        $note = trim((string) $request->input('elevation_note', ''));
        if ($id < 1) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }

        $result = $this->effectifsStaffAlertService->requestElevation(
            $tenantId,
            (int) Session::get('user_id'),
            $user,
            $kind,
            $note
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        $returnMember = $request->input('return_to') === 'member';

        return Response::redirect(
            $returnMember
                ? effectifs_workspace_url('membres/' . $id)
                : $this->redirectBackToRoster($request)
        );
    }

    public function roles(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $gate = Gate::getInstance();

        return $this->shell('admin.effectifs_workspace.roles', [
            'title' => 'Rôles',
            'effectifsNav' => 'roles',
            'orgRoles' => $roles,
            'canManageRoles' => EffectifsLmsAccess::canManageRoles($gate),
            'rosterCounts' => $this->rosterCounts($tenantId),
        ]);
    }

    public function droits(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $gate = Gate::getInstance();

        return $this->shell('admin.effectifs_workspace.droits', [
            'title' => 'Droits d’accès',
            'effectifsNav' => 'droits',
            'canManageRoles' => EffectifsLmsAccess::canManageRoles($gate),
            'canAccessManagement' => $gate->allows('admin.organization')
                || $gate->allows('admin.access')
                || $gate->allows('admin.access.manage'),
            'rosterCounts' => $this->rosterCounts($tenantId),
        ]);
    }

    public function fonctions(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $jobRoles = [];
        if ($this->personnelJobRoleRepository->tablesExist()) {
            $jobRoles = $this->personnelJobRoleRepository->listRolesWithCategory($tenantId);
        }
        $gate = Gate::getInstance();

        return $this->shell('admin.effectifs_workspace.fonctions', [
            'title' => 'Fonctions',
            'effectifsNav' => 'fonctions',
            'jobRoles' => $jobRoles,
            'canManageAssignments' => EffectifsLmsAccess::canManageAssignments($gate),
            'rosterCounts' => $this->rosterCounts($tenantId),
        ]);
    }

    public function affectations(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $units = $this->unitRepository->allForTenant($tenantId);
        $withoutUnit = $this->userRepository->countListForTenant($tenantId, null, 'active', null, true, true, null);
        $gate = Gate::getInstance();

        return $this->shell('admin.effectifs_workspace.affectations', [
            'title' => 'Affectations',
            'effectifsNav' => 'affectations',
            'units' => $units,
            'membersWithoutUnit' => $withoutUnit,
            'canManageAssignments' => EffectifsLmsAccess::canManageAssignments($gate),
            'rosterCounts' => $this->rosterCounts($tenantId),
        ]);
    }

    public function elevationRequests(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageRoles($gate) && !EffectifsLmsAccess::canManageStatus($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à traiter les demandes d’élévation.');

            return Response::redirect(effectifs_workspace_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $showAll = $request->query('all') === '1';
        $requests = $showAll
            ? $this->elevationRequestRepository->listRecentForTenant($tenantId, 300)
            : $this->elevationRequestRepository->listOpenForTenant($tenantId, 300);

        return $this->shell('admin.effectifs_workspace.elevation_requests', [
            'title' => 'Demandes d’élévation',
            'effectifsNav' => 'elevations',
            'elevationRequests' => $requests,
            'elevationShowAll' => $showAll,
            'elevationKindLabels' => EffectifsStaffAlertService::ELEVATION_KIND_LABELS,
        ]);
    }

    public function updateElevationRequestStatus(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageRoles($gate) && !EffectifsLmsAccess::canManageStatus($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à traiter les demandes d’élévation.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/ressources/effectifs/elevations'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $status = trim((string) $request->input('status', ''));
        $note = trim((string) $request->input('resolution_note', ''));
        if (mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }
        $existing = $id > 0 ? $this->elevationRequestRepository->findByIdForTenant($id, $tenantId) : null;
        if (!$existing) {
            Session::flash('error', 'Demande introuvable.');

            return Response::redirect(url('back-office/ressources/effectifs/elevations'));
        }
        $ok = $this->elevationRequestRepository->updateStatus(
            $id,
            $tenantId,
            $status,
            (int) Session::get('user_id'),
            $note
        );
        Session::flash(
            $ok ? 'success' : 'error',
            $ok ? 'Le statut de la demande a été mis à jour.' : 'Statut invalide ou mise à jour impossible.'
        );

        return Response::redirect(url('back-office/ressources/effectifs/elevations'));
    }

    private function denyUnlessAccess(): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        if (!EffectifsLmsAccess::allows(Gate::getInstance())) {
            Session::flash('error', 'Accès réservé au pilotage des effectifs.');

            return Response::redirect(url('dashboard'));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function shell(string $content, array $extra): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $counts = $extra['rosterCounts'] ?? $this->rosterCounts($tenantId);
        $elevationOpen = 0;
        try {
            $elevationOpen = $this->elevationRequestRepository->countOpenForTenant($tenantId);
        } catch (\Throwable) {
            $elevationOpen = 0;
        }

        return Response::view('layout.effectifs_lms', array_merge([
            'content' => $content,
            'showPortalFooter' => false,
            'rosterCounts' => $counts,
            'elevationOpenCount' => $elevationOpen,
            'viewerName' => (string) (Session::get('display_name') ?? Session::get('email') ?? ''),
        ], $extra));
    }

    /**
     * @return array{total: int, active: int, inactive: int, pending: int, no_unit: int, no_role: int}
     */
    private function rosterCounts(int $tenantId): array
    {
        return [
            'total' => $this->userRepository->countListForTenant($tenantId, null, null, null, true),
            'active' => $this->userRepository->countListForTenant($tenantId, null, 'active', null, true),
            'inactive' => $this->userRepository->countListForTenant($tenantId, null, 'inactive', null, true),
            'pending' => $this->userRepository->countListForTenant($tenantId, null, 'pending_verification', null, true),
            'no_unit' => $this->userRepository->countListForTenant($tenantId, null, null, null, true, true, null),
            'no_role' => $this->userRepository->countListForTenant($tenantId, null, null, null, true, null, true),
        ];
    }

    private function communityNameForTenant(int $tenantId): string
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return 'Communauté';
        }
        $name = function_exists('community_display_name')
            ? community_display_name($tenant)
            : (string) ($tenant['name'] ?? '');

        return $name !== '' ? $name : 'Communauté';
    }

    private function redirectBackToRoster(Request $request): string
    {
        $returnUrl = trim((string) $request->input('return_url', ''));
        if ($returnUrl !== '' && str_starts_with($returnUrl, effectifs_workspace_url())) {
            return $returnUrl;
        }

        return effectifs_workspace_url();
    }

    /**
     * Enrichit les lignes utilisateurs avec grade, unité, communauté, fonction métier.
     *
     * @param list<array<string, mixed>> $users
     * @return list<array<string, mixed>>
     */
    private function enrichRosterRows(int $tenantId, array $users): array
    {
        if ($users === []) {
            return [];
        }
        $ids = array_map(static fn (array $u): int => (int) ($u['id'] ?? 0), $users);
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        $richById = [];
        if ($ids !== []) {
            foreach ($this->userRepository->listEffectifsRosterByIds($tenantId, $ids) as $rich) {
                $richById[(int) ($rich['id'] ?? 0)] = $rich;
            }
        }
        $communityFallback = $this->communityNameForTenant($tenantId);
        $out = [];
        foreach ($users as $u) {
            $id = (int) ($u['id'] ?? 0);
            $rich = $richById[$id] ?? [];
            $out[] = array_merge($u, [
                'grade_short' => $rich['grade_short'] ?? null,
                'grade_long' => $rich['grade_long'] ?? null,
                'unit_name' => $rich['unit_name'] ?? null,
                'unit_code' => $rich['unit_code'] ?? null,
                'unit_id' => isset($rich['unit_id']) ? (int) $rich['unit_id'] : null,
                'community_name' => trim((string) ($rich['community_name'] ?? '')) !== ''
                    ? (string) $rich['community_name']
                    : $communityFallback,
                'personnel_job_role_name' => $rich['personnel_job_role_name'] ?? null,
                'primary_role' => $rich['primary_role'] ?? null,
                'role_sub_label' => $rich['role_sub_label'] ?? null,
                'character_name' => $rich['character_name'] ?? null,
                'matricule_internal' => $rich['matricule_internal'] ?? null,
                'roles_display' => $u['roles_display'] ?? ($u['role_name'] ?? null),
            ]);
        }

        return $out;
    }
}
