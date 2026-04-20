<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Security\AccessControlService;
use PDO;

final class AccessManagementController
{
    private PDO $pdo;

    public function __construct(private AccessControlService $accessControlService, private UserRepository $users)
    {
        $this->pdo = Database::getPdo();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.access_management.index',
            'title' => 'Gestion des accès',
            'roles' => $this->listRoles($tenantId),
            'permissions' => $this->listPermissions($tenantId),
            'rules' => $this->listRules($tenantId),
            'logs' => $this->listLogs($tenantId),
            'users' => $this->listUsers($tenantId),
            'activeTab' => (string) $request->query('tab', 'roles'),
        ]);
    }

    public function saveRole(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/access-management?tab=roles'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $name = trim((string) $request->input('name'));
        $slug = trim((string) $request->input('slug'));
        $level = (int) $request->input('level', 0);
        if ($tenantId < 1 || $name === '' || $slug === '') {
            Session::flash('error', 'Rôle invalide.');

            return Response::redirect(url('back-office/access-management?tab=roles'));
        }
        $stmt = $this->pdo->prepare('INSERT INTO roles (tenant_id, name, slug, level, is_system, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
        $stmt->execute([$tenantId, $name, $slug, $level]);
        $this->auditRuleChange($tenantId, (int) Session::get('user_id'), 'role.created', ['name' => $name, 'slug' => $slug]);
        Session::flash('success', 'Rôle enregistré.');

        return Response::redirect(url('back-office/access-management?tab=roles'));
    }

    public function saveRule(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::redirect(url('back-office/access-management?tab=rules'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $name = trim((string) $request->input('name'));
        $targetType = strtoupper(trim((string) $request->input('target_type', 'ROLE')));
        $targetId = (int) $request->input('target_id', 0);
        $conditionType = strtoupper(trim((string) $request->input('condition_type', 'STATUS')));
        $effect = strtoupper(trim((string) $request->input('effect', 'ALLOW')));
        $priority = (int) $request->input('priority', 100);
        $scopeType = strtoupper(trim((string) $request->input('scope_type', 'MODULE')));
        $scopeIdentifier = trim((string) $request->input('scope_identifier', '*'));
        $action = strtoupper(trim((string) $request->input('action', 'READ')));

        $conditionValue = [
            'days' => (int) $request->input('days', 0),
            'module_id' => (int) $request->input('module_id', 0),
            'unit_id' => (int) $request->input('unit_id', 0),
            'accepted' => array_values(array_filter(array_map('trim', explode(',', (string) $request->input('statuses', 'active'))))),
            'field' => trim((string) $request->input('approval_field', 'access_manually_approved')),
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO access_rules (tenant_id, name, description, target_type, target_id, condition_type, condition_value, effect, priority, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $tenantId,
            $name,
            trim((string) $request->input('description', '')),
            $targetType,
            $targetId,
            $conditionType,
            json_encode($conditionValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $effect,
            $priority,
            $request->input('is_active') ? 1 : 0,
        ]);
        $ruleId = (int) $this->pdo->lastInsertId();

        $scopeStmt = $this->pdo->prepare('INSERT INTO access_scopes (rule_id, scope_type, scope_identifier, action) VALUES (?, ?, ?, ?)');
        $scopeStmt->execute([$ruleId, $scopeType, $scopeIdentifier, $action]);

        $this->auditRuleChange($tenantId, (int) Session::get('user_id'), 'rule.created', ['rule_id' => $ruleId, 'name' => $name]);
        Session::flash('success', 'Règle enregistrée.');

        return Response::redirect(url('back-office/access-management?tab=rules'));
    }

    public function simulate(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) $request->query('user_id', $request->input('user_id', 0));
        $resource = trim((string) $request->query('resource', $request->input('resource', 'documents')));
        $action = strtoupper(trim((string) $request->query('action', $request->input('action', 'READ'))));

        $user = $this->users->findById($userId, $tenantId);
        if (!$user) {
            return Response::json(['ok' => false, 'error' => 'Utilisateur introuvable.'], 404);
        }

        $decision = $this->accessControlService->decision($user, $resource, $action);

        return Response::json(['ok' => true, 'decision' => $decision]);
    }

    /** @return list<array<string,mixed>> */
    private function listRoles(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug, level, is_system FROM roles WHERE tenant_id = ? ORDER BY level DESC, name ASC');
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    private function listPermissions(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, code, label, category FROM permissions WHERE tenant_id = ? OR tenant_id IS NULL ORDER BY category ASC, code ASC');
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    private function listRules(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM access_rules WHERE tenant_id = ? ORDER BY priority DESC, id DESC');
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    private function listLogs(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM access_logs WHERE tenant_id = ? ORDER BY id DESC LIMIT 50');
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    private function listUsers(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, status, display_name, callsign
             FROM users
             WHERE tenant_id = ?
             ORDER BY LOWER(COALESCE(NULLIF(TRIM(display_name), \'\'), NULLIF(TRIM(callsign), \'\'), email)) ASC
             LIMIT 200'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function auditRuleChange(int $tenantId, int $userId, string $resource, array $context): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO access_logs (tenant_id, user_id, resource, action, decision, reason, context_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$tenantId, $userId, $resource, 'WRITE', 'ALLOW', 'policy_change', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
}
