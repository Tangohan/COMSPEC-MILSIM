<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\RolePermissionMatrixRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\Rbac\RolePermissionMatrixCatalog;
use App\Services\Rbac\RolePermissionMatrixService;
use InvalidArgumentException;

final class RolePermissionMatrixController
{
    public function __construct(
        private ?RolePermissionMatrixRepository $matrix = null,
        private ?RolePermissionMatrixService $service = null,
        private ?RoleRepository $roles = null,
        private ?UserRepository $users = null,
    ) {
        $this->matrix ??= new RolePermissionMatrixRepository();
        $this->service ??= new RolePermissionMatrixService($this->matrix);
        $this->roles ??= new RoleRepository();
        $this->users ??= new UserRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $forbidden = $this->guard();
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'scope' => trim((string) $request->query('scope', '')),
            'level' => trim((string) $request->query('level', '')),
            'active' => trim((string) $request->query('active', '')),
        ];
        $data = $this->matrix->listMatrix($tenantId, $filters);
        $members = $this->users->listForTenant($tenantId, null, 'active', null, 200, 0);

        return Response::view('layout.main', [
            'content' => 'admin.roles_permissions.index',
            'title' => 'Rôles & permissions',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Système',
            'boPageTitle' => 'Rôles & permissions',
            'boPageKicker' => 'SYSTÈME · ACCÈS',
            'boPageSubtitle' => 'Matrice des rôles applicatifs : périmètre d’accès, actions autorisées et titulaires.',
            'boPageAction' => 'Créer un rôle',
            'boPageActionUrl' => url('back-office/roles'),
            'boPageQuick' => [
                ['label' => 'Rôles', 'href' => url('back-office/roles')],
                ['label' => 'Permissions', 'href' => url('back-office/roles-permissions')],
                ['label' => 'Titulaires', 'href' => url('back-office/users')],
            ],
            'matrixRows' => $data['rows'],
            'matrixStats' => $data['stats'],
            'matrixFilters' => $filters,
            'moduleLabels' => RolePermissionMatrixCatalog::moduleLabelsFr(),
            'accessLevelLabels' => RolePermissionMatrixCatalog::accessLevelLabelsFr(),
            'csrfToken' => Csrf::token(),
            'assignableMembers' => $members,
        ]);
    }

    public function assign(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if ($tenantId < 1 || $actorId < 1) {
            return Response::redirect(url('login'));
        }
        $forbidden = $this->guard();
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/roles-permissions'));
        }

        $roleId = (int) $request->input('role_id', 0);
        $userId = (int) $request->input('user_id', 0);
        $role = $this->roles->findById($roleId, $tenantId);
        $user = $this->users->findById($userId, $tenantId);
        if (!$role || !$user || !$this->roles->canAssignInTenantAdminContext($roleId, $tenantId)) {
            Session::flash('error', 'Le membre ou le rôle sélectionné est invalide.');
            return Response::redirect(url('back-office/roles-permissions'));
        }

        try {
            $added = $this->users->addOrganizationRoleIfMissing($userId, $tenantId, $roleId, $actorId);
            Session::flash('success', $added ? 'Rôle attribué au membre.' : 'Ce membre possède déjà ce rôle.');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/roles-permissions') . '#role-' . $roleId);
    }

    public function save(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $forbidden = $this->guard();
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/roles-permissions'));
        }

        $roleId = (int) $request->input('role_id', 0);
        $modules = [];
        foreach (RolePermissionMatrixCatalog::moduleKeys() as $moduleKey) {
            $modules[$moduleKey] = (string) $request->input('module_' . $moduleKey, RolePermissionMatrixCatalog::LEVEL_NONE);
        }

        $result = $this->service->saveRoleMatrix($tenantId, $roleId, [
            'code' => $request->input('code'),
            'level' => $request->input('level'),
            'is_active' => $request->input('is_active') ? true : false,
            'can_delete' => $request->input('can_delete') ? true : false,
            'can_export' => $request->input('can_export') ? true : false,
            'mark_reviewed' => $request->input('mark_reviewed') ? true : false,
            'modules' => $modules,
        ]);

        if (!($result['ok'] ?? false)) {
            Session::flash('error', (string) ($result['error'] ?? 'Enregistrement impossible.'));
        } else {
            Session::flash('success', 'Matrice mise à jour pour ce rôle.');
        }

        return Response::redirect(url('back-office/roles-permissions'));
    }

    public function markReviewed(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $forbidden = $this->guard();
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/roles-permissions'));
        }

        $count = $this->matrix->markAllReviewed($tenantId);
        Session::flash('success', $count > 0
            ? 'Revue d’accès enregistrée pour l’ensemble des rôles.'
            : 'Aucun rôle à mettre à jour.');

        return Response::redirect(url('back-office/roles-permissions'));
    }

    public function export(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = $this->guard();
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'scope' => trim((string) $request->query('scope', '')),
            'level' => trim((string) $request->query('level', '')),
            'active' => trim((string) $request->query('active', '')),
        ];
        $data = $this->matrix->listMatrix($tenantId, $filters);
        $csv = $this->matrix->exportCsv($data['rows']);

        $response = new Response();
        $response->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="roles-permissions-' . gmdate('Ymd-His') . '.csv"')
            ->header('Cache-Control', 'no-store')
            ->setBody("\xEF\xBB\xBF" . $csv);

        return $response;
    }

    private function guard(): ?Response
    {
        $gate = Gate::getInstance();
        if ($gate->allows('admin.roles.manage') || $gate->allows('admin.permissions.manage')
            || $gate->allows('admin.organization') || $gate->allows('admin.access')) {
            return null;
        }
        Session::flash('error', 'Vous n’avez pas les droits pour gérer les rôles et permissions.');

        return Response::redirect(url('back-office'));
    }
}
