<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\RoleRepository;
use App\Repositories\UnitRepository;
use App\Services\Admin\TenantRolePermissionPresetService;

/**
 * Page « Gestion des rôles et fonctions » : catalogue, graphe, liens vers affectations et presets.
 */
class RolesFunctionsAdminController
{
    public function __construct(
        private RoleRepository $roleRepository,
        private UnitRepository $unitRepository,
        private TenantRolePermissionPresetService $presetService
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(url('dashboard'));
        }

        $pdo = Database::getPdo();
        $roleDefinitions = [];
        $defRel = [];
        try {
            $roleDefinitions = $pdo->query('SELECT id, slug, name_fr, name_us, family, description, sort_order FROM role_definitions ORDER BY sort_order ASC, name_fr ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $defRel = $pdo->query(
                'SELECT rdr.relation_type, fd.slug AS from_slug, td.slug AS to_slug
                 FROM role_definition_relations rdr
                 INNER JOIN role_definitions fd ON fd.id = rdr.from_definition_id
                 INNER JOIN role_definitions td ON td.id = rdr.to_definition_id'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
        }

        $tenantRoles = $this->roleRepository->forTenantOrganization($tenantId);
        $roleRelations = [];
        try {
            $st = $pdo->prepare(
                'SELECT rr.id, rr.relation_type, rf.slug AS from_slug, rf.name AS from_name, rt.slug AS to_slug, rt.name AS to_name
                 FROM role_relations rr
                 INNER JOIN roles rf ON rf.id = rr.from_role_id
                 INNER JOIN roles rt ON rt.id = rr.to_role_id
                 WHERE rr.tenant_id = ?'
            );
            $st->execute([$tenantId]);
            $roleRelations = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
        }

        $units = $this->unitRepository->allForTenant($tenantId);
        $presets = $this->presetService->listPresetMeta();

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles_functions',
            'title' => 'Gestion des rôles et fonctions',
            'roleDefinitions' => $roleDefinitions,
            'definitionRelations' => $defRel,
            'tenantRoles' => $tenantRoles,
            'roleRelations' => $roleRelations,
            'units' => $units,
            'rolePresetMeta' => $presets,
        ]);
    }

    /**
     * Données graphe (nœuds + arêtes) pour visualisation JS.
     */
    public function graphJson(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::json(['error' => 'unauthorized'], 401);
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage')) {
            return Response::json(['error' => 'forbidden'], 403);
        }

        $pdo = Database::getPdo();
        $nodes = [];
        $edges = [];
        try {
            $st = $pdo->prepare(
                'SELECT rr.relation_type, rf.id AS from_id, rf.slug AS from_slug, rf.name AS from_name,
                        rt.id AS to_id, rt.slug AS to_slug, rt.name AS to_name
                 FROM role_relations rr
                 INNER JOIN roles rf ON rf.id = rr.from_role_id
                 INNER JOIN roles rt ON rt.id = rr.to_role_id
                 WHERE rr.tenant_id = ?'
            );
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $nodeMap = [];
            foreach ($rows as $r) {
                $fid = (int) ($r['from_id'] ?? 0);
                $tid = (int) ($r['to_id'] ?? 0);
                if ($fid > 0) {
                    $nodeMap[$fid] = ['id' => 'r' . $fid, 'label' => (string) ($r['from_name'] ?? ''), 'slug' => (string) ($r['from_slug'] ?? '')];
                }
                if ($tid > 0) {
                    $nodeMap[$tid] = ['id' => 'r' . $tid, 'label' => (string) ($r['to_name'] ?? ''), 'slug' => (string) ($r['to_slug'] ?? '')];
                }
                $edges[] = [
                    'from' => 'r' . $fid,
                    'to' => 'r' . $tid,
                    'type' => (string) ($r['relation_type'] ?? ''),
                ];
            }
            $nodes = array_values($nodeMap);
        } catch (\Throwable) {
        }

        return Response::json(['nodes' => $nodes, 'edges' => $edges]);
    }
}
