<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\RolePermissionMatrixRepository;
use App\Services\Rbac\RolePermissionMatrixCatalog;
use App\Services\Rbac\RolePermissionMatrixService;

final class RolePermissionMatrixApiController
{
    public function __construct(
        private ?RolePermissionMatrixRepository $matrix = null,
        private ?RolePermissionMatrixService $service = null,
    ) {
        $this->matrix ??= new RolePermissionMatrixRepository();
        $this->service ??= new RolePermissionMatrixService($this->matrix);
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        if (!$this->canManage()) {
            return Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'scope' => trim((string) $request->query('scope', '')),
            'level' => trim((string) $request->query('level', '')),
            'active' => trim((string) $request->query('active', '')),
        ];
        $data = $this->matrix->listMatrix($tenantId, $filters);

        return Response::json([
            'ok' => true,
            'rows' => $data['rows'],
            'stats' => $data['stats'],
            'modules' => RolePermissionMatrixCatalog::moduleLabelsFr(),
            'access_levels' => RolePermissionMatrixCatalog::accessLevelLabelsFr(),
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        if (!$this->canManage()) {
            return Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }
        if (!$this->csrfOk($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée.'], 419);
        }

        $body = $this->body();
        $roleId = (int) ($params['id'] ?? $body['role_id'] ?? $request->input('role_id', 0));
        $result = $this->service->saveRoleMatrix($tenantId, $roleId, $body + [
            'code' => $request->input('code', $body['code'] ?? null),
            'level' => $request->input('level', $body['level'] ?? null),
            'is_active' => $request->input('is_active', $body['is_active'] ?? null),
            'can_delete' => $request->input('can_delete', $body['can_delete'] ?? null),
            'can_export' => $request->input('can_export', $body['can_export'] ?? null),
            'mark_reviewed' => $request->input('mark_reviewed', $body['mark_reviewed'] ?? null),
            'modules' => $body['modules'] ?? null,
        ]);

        if (!($result['ok'] ?? false)) {
            return Response::json(['ok' => false, 'error' => (string) ($result['error'] ?? 'Enregistrement impossible.')], 422);
        }

        return Response::json(['ok' => true]);
    }

    public function export(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        if (!$this->canManage()) {
            return Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'scope' => trim((string) $request->query('scope', '')),
            'level' => trim((string) $request->query('level', '')),
            'active' => trim((string) $request->query('active', '')),
        ];
        $data = $this->matrix->listMatrix($tenantId, $filters);

        return Response::json([
            'ok' => true,
            'generated_at' => gmdate('c'),
            'rows' => $data['rows'],
            'stats' => $data['stats'],
        ]);
    }

    private function canManage(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.roles.manage') || $gate->allows('admin.permissions.manage')
            || $gate->allows('admin.organization') || $gate->allows('admin.access');
    }

    private function csrfOk(Request $request): bool
    {
        $body = $this->body();
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf_token') ?? ($body['_csrf_token'] ?? ''));

        return Csrf::validate($token);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
