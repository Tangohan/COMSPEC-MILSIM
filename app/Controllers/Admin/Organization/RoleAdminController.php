<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Services\Admin\RolePermissionService;
use App\Services\Admin\TenantRolePermissionPresetService;
use App\Services\Rbac\RoleCoherenceValidator;

class RoleAdminController
{
    public function __construct(
        private RolePermissionService $rolePermissionService,
        private PermissionRepository $permissionRepository,
        private RoleRepository $roleRepository,
        private TenantRolePermissionPresetService $presetService
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $layer = trim((string) $request->query('layer', ''));
        $roles = match ($layer) {
            'community' => $this->rolePermissionService->listOrganizationRolesByLayer($tenantId, 'community'),
            'intra' => $this->rolePermissionService->listOrganizationRolesByLayer($tenantId, 'intra'),
            default => $this->rolePermissionService->listOrganizationRoles($tenantId),
        };

        $tierFilter = trim((string) $request->query('tier', ''));
        $validTiers = ['authority', 'function', 'specialty', 'status'];
        if ($tierFilter !== '' && !in_array($tierFilter, $validTiers, true)) {
            $tierFilter = '';
        }
        if ($tierFilter !== '') {
            $roles = array_values(array_filter(
                $roles,
                static function (array $r) use ($tierFilter): bool {
                    $t = (string) ($r['semantic_tier'] ?? 'function');

                    return $t === $tierFilter;
                }
            ));
        }

        $permissionCounts = [];
        foreach ($roles as $r) {
            $permissionCounts[(int) $r['id']] = count($this->rolePermissionService->getPermissionIdsForRole((int) $r['id']));
        }

        $roleViewSections = $this->buildRoleViewSections($roles);

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles.index',
            'title' => 'Rôles communauté',
            'roles' => $roles,
            'permissionCounts' => $permissionCounts,
            'roleLayerFilter' => $layer,
            'roleTierFilter' => $tierFilter,
            'roleViewSections' => $roleViewSections,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $roles
     *
     * @return list<array{kind: string, title: string, category?: string, subcategory?: string, roles: list<array<string, mixed>>}>
     */
    private function buildRoleViewSections(array $roles): array
    {
        $ungroupedIntraKey = "\0other_intra\0";
        $community = [];
        /** @var array<string, list<array<string, mixed>>> $intraBuckets */
        $intraBuckets = [];
        foreach ($roles as $r) {
            if (($r['role_layer'] ?? '') === 'community') {
                $community[] = $r;
                continue;
            }
            $cat = trim((string) ($r['category'] ?? ''));
            $sub = trim((string) ($r['subcategory'] ?? ''));
            if ($cat === '' && $sub === '') {
                $intraBuckets[$ungroupedIntraKey][] = $r;
            } else {
                $k = $cat . "\n" . $sub;
                $intraBuckets[$k][] = $r;
            }
        }

        $sections = [];
        if ($community !== []) {
            $sections[] = ['kind' => 'community', 'title' => 'Gouvernance de la communauté', 'roles' => $community];
        }
        foreach ($intraBuckets as $k => $list) {
            if ($k === $ungroupedIntraKey) {
                $sections[] = ['kind' => 'flat', 'title' => 'Autres rôles opérationnels', 'roles' => $list];

                continue;
            }
            $parts = explode("\n", $k, 2);
            $sections[] = [
                'kind' => 'group',
                'title' => ($parts[0] ?? '') . ' — ' . ($parts[1] ?? ''),
                'category' => $parts[0] ?? '',
                'subcategory' => $parts[1] ?? '',
                'roles' => $list,
            ];
        }

        return $sections;
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/roles'));
        }
        $roles = $this->rolePermissionService->listOrganizationRoles($tenantId);
        $role = null;
        foreach ($roles as $r) {
            if ((int) $r['id'] === $id) {
                $role = $r;
                break;
            }
        }
        if (!$role) {
            Session::flash('error', 'Rôle introuvable.');

            return Response::redirect(url('back-office/roles'));
        }
        $permissionIds = $this->rolePermissionService->getPermissionIdsForRole($id);
        $allPermissions = $this->permissionRepository->allForTenant($tenantId);
        $rolePermissions = array_filter($allPermissions, fn ($p) => in_array((int) $p['id'], $permissionIds, true));

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles.show',
            'title' => 'Détail rôle',
            'role' => $role,
            'rolePermissions' => $rolePermissions,
        ]);
    }

    /**
     * Profils prédéfinis : applique un jeu complet de permissions à un rôle communauté / intra.
     */
    public function presets(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Vous n’avez pas la permission de gérer les profils de droits.');

            return Response::redirect(url('dashboard'));
        }

        $roles = $this->rolePermissionService->listOrganizationRoles($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles.presets',
            'title' => 'Profils de permissions',
            'presetMeta' => $this->presetService->listPresetMeta(),
            'roles' => $roles,
        ]);
    }

    public function presetsApply(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/roles/presets'));
        }

        $presetId = trim((string) $request->input('preset_id'));
        $roleId = (int) $request->input('role_id');
        $allowedIds = array_column($this->presetService->listPresetMeta(), 'id');
        if ($presetId === '' || !in_array($presetId, $allowedIds, true)) {
            Session::flash('error', 'Profil invalide.');

            return Response::redirect(url('back-office/roles/presets'));
        }
        if ($roleId < 1) {
            Session::flash('error', 'Choisissez un rôle.');

            return Response::redirect(url('back-office/roles/presets'));
        }

        $role = $this->roleRepository->findById($roleId, $tenantId);
        if (!$role) {
            Session::flash('error', 'Rôle introuvable pour cette communauté.');

            return Response::redirect(url('back-office/roles/presets'));
        }
        if ($this->rolePermissionService->isRoleLocked($roleId)) {
            Session::flash('error', 'Ce rôle est verrouillé : les permissions ne peuvent pas être remplacées par un profil.');

            return Response::redirect(url('back-office/roles/presets'));
        }

        $ids = $this->presetService->getPermissionIdsForPreset($tenantId, $presetId);
        if ($ids === []) {
            Session::flash('error', 'Aucune permission correspondante dans cette communauté (migrations ou catalogue à jour ?).');

            return Response::redirect(url('back-office/roles/presets'));
        }

        try {
            $this->rolePermissionService->setPermissionsForOrganizationTenantRole($tenantId, $roleId, $ids);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('back-office/roles/presets'));
        }
        Session::flash('success', 'Permissions du rôle « ' . (string) ($role['name'] ?? '') . ' » mises à jour selon le profil sélectionné (' . count($ids) . ' droits).');

        return Response::redirect(url('back-office/roles/' . $roleId));
    }

    /**
     * Intitulé, description et apparence du badge (hors permissions et slug).
     */
    public function editPresentation(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/roles'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Vous n’avez pas la permission de modifier les rôles.');

            return Response::redirect(url('dashboard'));
        }
        $role = $this->roleRepository->findById($id, $tenantId);
        if (!$role || !in_array((string) ($role['role_layer'] ?? ''), ['community', 'intra'], true)) {
            Session::flash('error', 'Rôle introuvable ou hors périmètre organisation.');

            return Response::redirect(url('back-office/roles'));
        }
        $badge = [];
        $rawBadge = $role['badge_style'] ?? null;
        if (is_string($rawBadge) && $rawBadge !== '') {
            $decoded = json_decode($rawBadge, true);
            $badge = is_array($decoded) ? $decoded : [];
        } elseif (is_array($rawBadge)) {
            $badge = $rawBadge;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles.edit_presentation',
            'title' => 'Présentation du rôle',
            'role' => $role,
            'badgeStyle' => $badge,
        ]);
    }

    public function updatePresentation(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/roles'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/roles/' . $id . '/edit-presentation'));
        }
        $role = $this->roleRepository->findById($id, $tenantId);
        if (!$role || !in_array((string) ($role['role_layer'] ?? ''), ['community', 'intra'], true)) {
            Session::flash('error', 'Rôle invalide.');

            return Response::redirect(url('back-office/roles'));
        }
        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));
        $ok = $this->roleRepository->updateOrganizationRolePresentation($tenantId, $id, $name, $description);
        if (!$ok) {
            Session::flash('error', 'Enregistrement impossible (intitulé vide ou rôle introuvable).');

            return Response::redirect(url('back-office/roles/' . $id . '/edit-presentation'));
        }

        $allowedColors = ['slate', 'blue', 'indigo', 'emerald', 'amber', 'red', 'purple'];
        $allowedIcons = ['shield', 'star', 'user', 'flag', 'briefcase', 'award', 'megaphone', 'none'];
        $allowedVariants = ['soft', 'solid', 'outline'];
        $c = trim((string) $request->input('badge_color', ''));
        $ic = trim((string) $request->input('badge_icon', ''));
        $v = trim((string) $request->input('badge_variant', ''));
        $style = [];
        if ($c !== '' && in_array($c, $allowedColors, true)) {
            $style['color'] = $c;
        }
        if ($ic !== '' && in_array($ic, $allowedIcons, true) && $ic !== 'none') {
            $style['icon'] = $ic;
        }
        if ($v !== '' && in_array($v, $allowedVariants, true)) {
            $style['variant'] = $v;
        }
        $this->roleRepository->updateOrganizationRoleBadgeStyle($tenantId, $id, $style === [] ? null : $style);

        $msg = 'Présentation du rôle mise à jour.';
        $tier = (string) ($role['semantic_tier'] ?? 'function');
        if ($tier === 'authority') {
            $pdo = Database::getPdo();
            if (!RoleCoherenceValidator::authorityRoleHasPermissions($pdo, $id)) {
                $msg .= ' Pensez à associer au moins une habilitation à ce rôle d’autorité (depuis la fiche du rôle).';
            }
        }
        Session::flash('success', $msg);

        return Response::redirect(url('back-office/roles/' . $id));
    }
}
