<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Security\AccessControlService;
use PDO;

final class AccessControlApiController
{
    private PDO $pdo;

    public function __construct(private AccessControlService $acs, private UserRepository $users)
    {
        $this->pdo = Database::getPdo();
    }

    public function roles(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($request->isGet()) {
            $st = $this->pdo->prepare('SELECT id, name, slug, level, is_system FROM roles WHERE tenant_id = ? ORDER BY name');
            $st->execute([$tenantId]);

            return Response::json(['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        $st = $this->pdo->prepare('INSERT INTO roles (tenant_id, name, slug, level, is_system, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
        $st->execute([$tenantId, (string) $request->input('name'), (string) $request->input('slug'), (int) $request->input('level', 0)]);

        return Response::json(['ok' => true, 'id' => (int) $this->pdo->lastInsertId()]);
    }

    public function permissions(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($request->isGet()) {
            $st = $this->pdo->prepare('SELECT id, code, label, category FROM permissions WHERE tenant_id = ? OR tenant_id IS NULL ORDER BY code');
            $st->execute([$tenantId]);

            return Response::json(['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        $st = $this->pdo->prepare('INSERT INTO permissions (tenant_id, code, slug, label, name, category, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $code = (string) $request->input('code');
        $label = (string) $request->input('label', $code);
        $st->execute([$tenantId, $code, $code, $label, $label, (string) $request->input('category', 'module')]);

        return Response::json(['ok' => true, 'id' => (int) $this->pdo->lastInsertId()]);
    }

    public function rolePermissions(Request $request, array $params = []): Response
    {
        $roleId = (int) $request->input('role_id');
        $permissionId = (int) $request->input('permission_id');
        $allowed = $request->input('allowed') ? 1 : 0;

        $this->pdo->prepare('DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?')->execute([$roleId, $permissionId]);
        $this->pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, allowed) VALUES (?, ?, ?)')->execute([$roleId, $permissionId, $allowed]);

        return Response::json(['ok' => true]);
    }

    public function rules(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($request->isGet()) {
            $st = $this->pdo->prepare('SELECT * FROM access_rules WHERE tenant_id = ? ORDER BY priority DESC, id DESC');
            $st->execute([$tenantId]);

            return Response::json(['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        $st = $this->pdo->prepare('INSERT INTO access_rules (tenant_id, name, description, target_type, target_id, condition_type, condition_value, effect, priority, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $st->execute([
            $tenantId,
            (string) $request->input('name'),
            (string) $request->input('description', ''),
            strtoupper((string) $request->input('target_type', 'ROLE')),
            (int) $request->input('target_id', 0),
            strtoupper((string) $request->input('condition_type', 'STATUS')),
            (string) $request->input('condition_value', '{}'),
            strtoupper((string) $request->input('effect', 'ALLOW')),
            (int) $request->input('priority', 100),
            $request->input('is_active') ? 1 : 0,
        ]);

        return Response::json(['ok' => true, 'id' => (int) $this->pdo->lastInsertId()]);
    }

    public function scopes(Request $request, array $params = []): Response
    {
        if ($request->isGet()) {
            $ruleId = (int) $request->query('rule_id', 0);
            $st = $this->pdo->prepare('SELECT * FROM access_scopes WHERE rule_id = ?');
            $st->execute([$ruleId]);

            return Response::json(['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        $st = $this->pdo->prepare('INSERT INTO access_scopes (rule_id, scope_type, scope_identifier, action) VALUES (?, ?, ?, ?)');
        $st->execute([(int) $request->input('rule_id'), strtoupper((string) $request->input('scope_type', 'MODULE')), (string) $request->input('scope_identifier'), strtoupper((string) $request->input('action', 'READ'))]);

        return Response::json(['ok' => true, 'id' => (int) $this->pdo->lastInsertId()]);
    }

    public function simulation(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $user = $this->users->findById((int) $request->input('user_id', 0), $tenantId);
        if (!$user) {
            return Response::json(['ok' => false, 'error' => 'user_not_found'], 404);
        }

        return Response::json([
            'ok' => true,
            'data' => $this->acs->decision($user, (string) $request->input('resource', 'documents'), strtoupper((string) $request->input('action', 'READ'))),
        ]);
    }
}
