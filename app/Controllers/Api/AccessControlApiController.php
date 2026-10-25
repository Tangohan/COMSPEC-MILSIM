<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Authorization\SystemReservedPermissions;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Security\AccessControlService;
use PDO;

final class AccessControlApiController
{
    /** Valeurs reconnues par AccessControlService (cibles, évaluateurs de condition, effets). */
    private const RULE_TARGET_TYPES = ['ROLE', 'USER'];
    private const RULE_CONDITION_TYPES = ['STATUS', 'UNIT', 'DAYS_SINCE_CREATION', 'MODULE_VALIDATED', 'MANUAL_APPROVAL'];
    private const RULE_EFFECTS = ['ALLOW', 'DENY'];

    private PDO $pdo;

    public function __construct(private AccessControlService $acs, private UserRepository $users)
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * Écritures d’habilitations : réservées au gestionnaire de la communauté
     * (mêmes droits que TenantResourceAdminMiddleware), sinon 403 JSON.
     * Jeton CSRF exigé sur toute écriture : /api/ est exempté par CsrfPostMiddleware.
     */
    private function denyUnlessTenantAdmin(Request $request): ?Response
    {
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.system') && !$gate->allows('admin.organization') && !$gate->allows('admin.access')) {
            return Response::json(['ok' => false, 'error' => 'Accès réservé aux administrateurs de la communauté.'], 403);
        }
        if (!$request->isGet() && !Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'Jeton CSRF invalide — rechargez la page.'], 403);
        }

        return null;
    }

    public function roles(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($request->isGet()) {
            $st = $this->pdo->prepare('SELECT id, name, slug, level, is_system FROM roles WHERE tenant_id = ? ORDER BY name');
            $st->execute([$tenantId]);

            return Response::json(['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Communauté active requise.'], 400);
        }

        return Response::json([
            'ok' => false,
            'error' => 'Créez ou corrigez un niveau d’accès depuis Accès, dans le bureau effectifs.',
        ], 400);
    }

    public function permissions(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($request->isGet()) {
            $st = $this->pdo->prepare('SELECT id, code, label, category FROM permissions WHERE tenant_id = ? OR tenant_id IS NULL ORDER BY code');
            $st->execute([$tenantId]);

            return Response::json(['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        if ($denied = $this->denyUnlessTenantAdmin($request)) {
            return $denied;
        }
        $code = $request->inputString('code');
        $label = $request->inputString('label', $code);
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Communauté active requise.'], 400);
        }
        // Un slug réservé créé dans le périmètre du tenant deviendrait attribuable
        // par les écrans de rôles : refusé à la source.
        if (SystemReservedPermissions::isReserved($code)) {
            return Response::json(['ok' => false, 'error' => 'Cette habilitation est réservée à l’administration de la plateforme.'], 403);
        }
        $st = $this->pdo->prepare('INSERT INTO permissions (tenant_id, code, slug, label, name, category, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $st->execute([$tenantId, $code, $code, $label, $label, $request->inputString('category', 'module')]);

        return Response::json(['ok' => true, 'id' => (int) $this->pdo->lastInsertId()]);
    }

    public function rolePermissions(Request $request, array $params = []): Response
    {
        if ($denied = $this->denyUnlessTenantAdmin($request)) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $roleId = (int) $request->input('role_id');
        $permissionId = (int) $request->input('permission_id');
        $allowed = $request->input('allowed') ? 1 : 0;
        if ($tenantId < 1 || $roleId < 1 || $permissionId < 1) {
            return Response::json(['ok' => false, 'error' => 'Requête incomplète.'], 400);
        }

        // Le rôle doit appartenir à la communauté active : jamais un rôle site,
        // jamais le rôle d’une autre communauté.
        $roleCheck = $this->pdo->prepare('SELECT 1 FROM roles WHERE id = ? AND tenant_id = ? LIMIT 1');
        $roleCheck->execute([$roleId, $tenantId]);
        if (!$roleCheck->fetchColumn()) {
            return Response::json(['ok' => false, 'error' => 'Rôle hors du périmètre de votre communauté.'], 403);
        }

        // La permission doit elle aussi appartenir au tenant, et ne pas être réservée
        // à la plateforme — sinon un rôle communauté deviendrait super-administrateur.
        $permCheck = $this->pdo->prepare('SELECT slug FROM permissions WHERE id = ? AND tenant_id = ? LIMIT 1');
        $permCheck->execute([$permissionId, $tenantId]);
        $slug = $permCheck->fetchColumn();
        if ($slug === false) {
            return Response::json(['ok' => false, 'error' => 'Habilitation hors du périmètre de votre communauté.'], 403);
        }
        if (SystemReservedPermissions::isReserved((string) $slug)) {
            return Response::json(['ok' => false, 'error' => 'Cette habilitation est réservée à l’administration de la plateforme.'], 403);
        }

        $this->pdo->prepare('DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?')->execute([$roleId, $permissionId]);
        // RbacService ne lit pas `allowed` : un refus se traduit par l’absence de ligne,
        // jamais par une ligne allowed=0 qui accorderait quand même l’habilitation.
        if ($allowed === 1) {
            $this->pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, allowed) VALUES (?, ?, ?)')->execute([$roleId, $permissionId, $allowed]);
        }

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

        if ($denied = $this->denyUnlessTenantAdmin($request)) {
            return $denied;
        }
        // Contrôles de saisie avant l’insertion : sans eux, un JSON invalide (colonne json)
        // ou une valeur trop longue en mode strict donnait une PDOException puis un 500.
        $name = trim($request->inputString('name'));
        $targetType = strtoupper(trim($request->inputString('target_type', 'ROLE')));
        $conditionType = strtoupper(trim($request->inputString('condition_type', 'STATUS')));
        $conditionValue = $request->inputString('condition_value', '{}');
        $effect = strtoupper(trim($request->inputString('effect', 'ALLOW')));
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Communauté active requise.'], 400);
        }
        if ($name === '' || mb_strlen($name) > 190) {
            return Response::json(['ok' => false, 'error' => 'Nom de règle requis (190 caractères au plus).'], 422);
        }
        if (!in_array($targetType, self::RULE_TARGET_TYPES, true)
            || !in_array($conditionType, self::RULE_CONDITION_TYPES, true)
            || !in_array($effect, self::RULE_EFFECTS, true)) {
            return Response::json(['ok' => false, 'error' => 'Cible, condition ou effet non reconnu.'], 422);
        }
        if (!is_array(json_decode($conditionValue, true))) {
            return Response::json(['ok' => false, 'error' => 'condition_value doit être un objet JSON valide.'], 422);
        }

        $st = $this->pdo->prepare('INSERT INTO access_rules (tenant_id, name, description, target_type, target_id, condition_type, condition_value, effect, priority, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $st->execute([
            $tenantId,
            $name,
            $request->inputString('description', ''),
            $targetType,
            (int) $request->input('target_id', 0),
            $conditionType,
            $conditionValue,
            $effect,
            (int) $request->input('priority', 100),
            $request->input('is_active') ? 1 : 0,
        ]);

        return Response::json(['ok' => true, 'id' => (int) $this->pdo->lastInsertId()]);
    }

    public function scopes(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($request->isGet()) {
            $ruleId = (int) $request->query('rule_id', 0);
            // La règle doit appartenir à la communauté active : sinon on lirait les
            // scopes d’une autre communauté en faisant varier rule_id.
            if (!$this->ruleBelongsToTenant($ruleId, $tenantId)) {
                return Response::json(['ok' => false, 'error' => 'rule_not_found'], 404);
            }
            $st = $this->pdo->prepare('SELECT * FROM access_scopes WHERE rule_id = ?');
            $st->execute([$ruleId]);

            return Response::json(['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        if ($denied = $this->denyUnlessTenantAdmin($request)) {
            return $denied;
        }
        $ruleId = (int) $request->input('rule_id');
        if (!$this->ruleBelongsToTenant($ruleId, $tenantId)) {
            return Response::json(['ok' => false, 'error' => 'rule_not_found'], 404);
        }
        $scopeType = strtoupper(trim($request->inputString('scope_type', 'MODULE')));
        $scopeIdentifier = trim($request->inputString('scope_identifier'));
        $action = strtoupper(trim($request->inputString('action', 'READ')));
        // Bornes des colonnes access_scopes (varchar 16 / 190 / 16).
        if ($scopeType === '' || strlen($scopeType) > 16 || $scopeIdentifier === '' || mb_strlen($scopeIdentifier) > 190 || $action === '' || strlen($action) > 16) {
            return Response::json(['ok' => false, 'error' => 'Portée incomplète ou trop longue.'], 422);
        }
        $st = $this->pdo->prepare('INSERT INTO access_scopes (rule_id, scope_type, scope_identifier, action) VALUES (?, ?, ?, ?)');
        $st->execute([$ruleId, $scopeType, $scopeIdentifier, $action]);

        return Response::json(['ok' => true, 'id' => (int) $this->pdo->lastInsertId()]);
    }

    private function ruleBelongsToTenant(int $ruleId, int $tenantId): bool
    {
        if ($ruleId < 1 || $tenantId < 1) {
            return false;
        }
        $st = $this->pdo->prepare('SELECT 1 FROM access_rules WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$ruleId, $tenantId]);

        return (bool) $st->fetchColumn();
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
            'data' => $this->acs->decision($user, $request->inputString('resource', 'documents'), strtoupper($request->inputString('action', 'READ'))),
        ]);
    }
}
