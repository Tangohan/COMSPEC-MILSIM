<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\CommunityInvitationRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;
use App\Services\Rbac\RbacService;

final class InvitationAcceptController
{
    public function __construct(
        private CommunityInvitationRepository $invitations,
        private TenantRepository $tenantRepository,
        private UserRepository $users,
        private AuthService $authService,
        private RbacService $rbacService,
        private AuditService $auditService,
        private FeatureGateService $featureGate
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $token = trim((string) $request->query('token', ''));
        if ($token === '') {
            Session::flash('error', 'Lien d’invitation invalide.');

            return Response::redirect(url(''));
        }
        $hash = hash('sha256', $token);
        $inv = $this->invitations->findValidByTokenHash($hash);
        if (!$inv) {
            Session::flash('error', 'Invitation expirée ou déjà utilisée.');

            return Response::redirect(url(''));
        }

        return Response::view('layout.main', [
            'title' => 'Accepter l’invitation',
            'content' => 'invitations.accept',
            'invitation' => $inv,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url(''));
        }
        $token = trim((string) $request->input('token'));
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');
        $hash = hash('sha256', $token);
        $inv = $this->invitations->findValidByTokenHash($hash);
        if (!$inv) {
            Session::flash('error', 'Invitation expirée ou déjà utilisée.');

            return Response::redirect(url(''));
        }
        $tenantId = (int) $inv['tenant_id'];
        if (!$this->featureGate->canAddMember($tenantId)) {
            Session::flash('error', 'Cette communauté a atteint la limite de membres pour son plan.');

            return Response::redirect(url(''));
        }
        $v = new Validator(
            ['password' => $password, 'password_confirmation' => $confirm],
            ['password' => 'required|min:8', 'password_confirmation' => 'required']
        );
        if (!$v->validate() || $password !== $confirm) {
            Session::flash('error', 'Mot de passe invalide ou confirmation différente.');

            return Response::redirect(url('invitations/accept') . '?token=' . rawurlencode($token));
        }

        $email = (string) $inv['email'];
        if ($this->users->emailExistsInTenant($tenantId, $email)) {
            Session::flash('error', 'Vous avez déjà un compte dans cette communauté.');

            return Response::redirect(url('login'));
        }

        $pdo = Database::getPdo();
        $gradeStmt = $pdo->prepare('SELECT id FROM grades WHERE tenant_id = ? ORDER BY rank_order ASC LIMIT 1');
        $gradeStmt->execute([$tenantId]);
        $gradeId = (int) ($gradeStmt->fetchColumn() ?: 0);
        if ($gradeId <= 0) {
            Session::flash('error', 'Configuration de la communauté incomplète (grades).');

            return Response::redirect(url(''));
        }

        $rstmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $rstmt->execute([$tenantId, 'member']);
        $memberRoleId = (int) ($rstmt->fetchColumn() ?: 0);
        $invRole = $inv['role_id'] !== null && $inv['role_id'] !== '' ? (int) $inv['role_id'] : 0;
        $roleId = $invRole > 0 ? $invRole : $memberRoleId;
        if ($roleId <= 0) {
            Session::flash('error', 'Rôle par défaut introuvable pour cette communauté.');

            return Response::redirect(url(''));
        }

        $existingGlobal = $this->users->findFirstByEmailGlobal($email);
        if ($existingGlobal) {
            if (!password_verify($password, (string) $existingGlobal['password_hash'])) {
                Session::flash('error', 'Mot de passe incorrect pour ce compte email.');

                return Response::redirect(url('invitations/accept') . '?token=' . rawurlencode($token));
            }
            $newId = $this->users->cloneUserToTenant((int) $existingGlobal['id'], $tenantId, $roleId, $gradeId);
        } else {
            $ph = password_hash($password, PASSWORD_ARGON2ID);
            $newId = $this->users->create($tenantId, [
                'email' => $email,
                'password_hash' => $ph,
                'role_id' => $roleId,
                'grade_id' => $gradeId,
                'status' => 'active',
            ]);
        }

        $this->invitations->markAccepted((int) $inv['id'], $newId);
        $this->auditService->log(AuditAction::INVITATION_ACCEPTED, $tenantId, $newId, 'invitation', (int) $inv['id']);

        $u = $this->users->findById($newId, $tenantId);
        if ($u) {
            $this->authService->loginUser($u);
            $this->rbacService->setPermissionsForGate(
                !empty($u['role_id']) ? (int) $u['role_id'] : null,
                (string) ($u['email'] ?? '')
            );
        }
        Session::flash('success', 'Bienvenue dans la communauté.');

        return Response::redirect(url('dashboard'));
    }
}
