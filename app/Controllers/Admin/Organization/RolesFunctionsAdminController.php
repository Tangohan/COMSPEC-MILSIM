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
use PDO;

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
        $forbidden = $this->guardBackOfficeAccess();
        if ($forbidden instanceof Response) {
            return $forbidden;
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
                "SELECT rr.id, rr.relation_type, rf.slug AS from_slug, rf.name AS from_name, rt.slug AS to_slug, rt.name AS to_name
                 FROM role_relations rr
                 INNER JOIN roles rf ON rf.id = rr.from_role_id AND rf.tenant_id = rr.tenant_id AND rf.role_layer IN ('community','intra')
                 INNER JOIN roles rt ON rt.id = rr.to_role_id AND rt.tenant_id = rr.tenant_id AND rt.role_layer IN ('community','intra')
                 WHERE rr.tenant_id = ?"
            );
            $st->execute([$tenantId]);
            $roleRelations = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
        }

        $units = $this->unitRepository->allForTenant($tenantId);
        $presets = $this->presetService->listPresetMeta();

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles_functions',
            'title' => 'Cellule S1 — Doctrine des fonctions',
            'roleDefinitions' => $roleDefinitions,
            'definitionRelations' => $defRel,
            'tenantRoles' => $tenantRoles,
            'roleRelations' => $roleRelations,
            'units' => $units,
            'rolePresetMeta' => $presets,
        ]);
    }

    public function storeDefinition(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $forbidden = $this->guardBackOfficeAccess();
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $slug = mb_strtolower(trim((string) $request->input('slug')));
        $nameFr = trim((string) $request->input('name_fr'));
        $nameUs = trim((string) $request->input('name_us'));
        $family = trim((string) $request->input('family'));
        $description = trim((string) $request->input('description'));
        $sortOrder = (int) $request->input('sort_order', 0);
        if ($slug === '') {
            $slug = $this->slugify($nameFr !== '' ? $nameFr : $nameUs);
        }
        if ($slug === '' || $nameFr === '') {
            Session::flash('error', 'Le slug et le nom FR sont requis pour créer une fonction.');

            return Response::redirect(url('back-office/roles-functions'));
        }

        $pdo = Database::getPdo();
        try {
            $check = $pdo->prepare('SELECT id FROM role_definitions WHERE slug = ? LIMIT 1');
            $check->execute([$slug]);
            if ($check->fetchColumn()) {
                Session::flash('error', 'Le slug de fonction existe déjà.');

                return Response::redirect(url('back-office/roles-functions'));
            }

            $insert = $pdo->prepare(
                'INSERT INTO role_definitions (slug, name_fr, name_us, family, description, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $insert->execute([
                $slug,
                $nameFr,
                $nameUs !== '' ? $nameUs : $nameFr,
                $family !== '' ? $family : 'command',
                $description !== '' ? $description : null,
                $sortOrder,
            ]);

            Session::flash('success', 'Fonction ajoutée au référentiel S1.');
        } catch (\Throwable) {
            Session::flash('error', 'Impossible de créer cette fonction.');
        }

        return Response::redirect(url('back-office/roles-functions'));
    }

    public function storeRoleRelation(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $forbidden = $this->guardBackOfficeAccess();
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $fromRoleId = (int) $request->input('from_role_id', 0);
        $toRoleId = (int) $request->input('to_role_id', 0);
        $relationType = trim((string) $request->input('relation_type', 'reports_to'));
        if ($fromRoleId < 1 || $toRoleId < 1 || $fromRoleId === $toRoleId || $relationType === '') {
            Session::flash('error', 'Relation invalide (source, destination ou type).');

            return Response::redirect(url('back-office/roles-functions'));
        }

        $pdo = Database::getPdo();
        try {
            $checkRole = $pdo->prepare("SELECT id FROM roles WHERE id = ? AND tenant_id = ? AND role_layer IN ('community', 'intra') LIMIT 1");
            $checkRole->execute([$fromRoleId, $tenantId]);
            $fromExists = (bool) $checkRole->fetchColumn();
            $checkRole->execute([$toRoleId, $tenantId]);
            $toExists = (bool) $checkRole->fetchColumn();
            if (!$fromExists || !$toExists) {
                Session::flash('error', 'Un des rôles sélectionnés est introuvable.');

                return Response::redirect(url('back-office/roles-functions'));
            }

            $exists = $pdo->prepare('SELECT id FROM role_relations WHERE tenant_id = ? AND from_role_id = ? AND to_role_id = ? LIMIT 1');
            $exists->execute([$tenantId, $fromRoleId, $toRoleId]);
            if ($exists->fetchColumn()) {
                Session::flash('error', 'Cette relation existe déjà.');

                return Response::redirect(url('back-office/roles-functions'));
            }

            $insert = $pdo->prepare(
                'INSERT INTO role_relations (tenant_id, from_role_id, to_role_id, relation_type, created_at) VALUES (?, ?, ?, ?, NOW())'
            );
            $insert->execute([$tenantId, $fromRoleId, $toRoleId, $relationType]);

            Session::flash('success', 'Relation tactique ajoutée.');
        } catch (\Throwable) {
            Session::flash('error', 'Impossible de créer la relation.');
        }

        return Response::redirect(url('back-office/roles-functions'));
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
                "SELECT rr.relation_type, rf.id AS from_id, rf.slug AS from_slug, rf.name AS from_name,
                        rt.id AS to_id, rt.slug AS to_slug, rt.name AS to_name
                 FROM role_relations rr
                 INNER JOIN roles rf ON rf.id = rr.from_role_id AND rf.tenant_id = rr.tenant_id AND rf.role_layer IN ('community','intra')
                 INNER JOIN roles rt ON rt.id = rr.to_role_id AND rt.tenant_id = rr.tenant_id AND rt.role_layer IN ('community','intra')
                 WHERE rr.tenant_id = ?"
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

    private function guardBackOfficeAccess(): ?Response
    {
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(url('dashboard'));
        }

        return null;
    }

    private function slugify(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($value));

        return mb_strtolower(trim((string) $slug, '-'));
    }
}
