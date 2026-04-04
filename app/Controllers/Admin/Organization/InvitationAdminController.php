<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityInvitationRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;

final class InvitationAdminController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private CommunityInvitationRepository $invitations,
        private RoleRepository $roleRepository,
        private FeatureGateService $featureGate,
        private AuditService $auditService
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $this->invitations->expireStale();
        $statusFilter = trim((string) $request->query('status', ''));
        $rows = $this->invitations->listForTenant($tenantId, $statusFilter !== '' ? $statusFilter : null);
        $rolesCommunity = $this->roleRepository->forTenantByLayer($tenantId, 'community');

        return Response::view('layout.main', [
            'title' => 'Invitations',
            'content' => 'admin.organization.invitations',
            'invitations' => $rows,
            'rolesCommunity' => $rolesCommunity,
            'canAdd' => $this->featureGate->canAddMember($tenantId),
            'inviteFilterStatus' => $statusFilter,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/organization/invitations'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->canAddMember($tenantId)) {
            Session::flash('error', 'Limite de membres atteinte pour ce plan.');

            return Response::redirect(url('admin/organization/invitations'));
        }
        $email = strtolower(trim((string) $request->input('email')));
        $roleId = (int) $request->input('role_id');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Email invalide.');

            return Response::redirect(url('admin/organization/invitations'));
        }
        $roleRow = $this->roleRepository->findById($roleId, $tenantId);
        $roleIdFinal = null;
        if ($roleRow && ($roleRow['role_layer'] ?? '') === 'community' && $this->roleRepository->canAssignInTenantAdminContext($roleId, $tenantId)) {
            $roleIdFinal = $roleId;
        } elseif ($roleId > 0) {
            Session::flash('error', 'Seul un rôle de gouvernance communauté peut être choisi à l’invitation.');

            return Response::redirect(url('admin/organization/invitations'));
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = new \DateTimeImmutable('+7 days');
        $this->invitations->create($tenantId, $email, $hash, (int) $user['id'], $roleIdFinal, $expires);

        $tenant = $this->tenantRepository->findById($tenantId);
        $acceptUrl = url('invitations/accept') . '?token=' . rawurlencode($token);
        $subject = 'Invitation — ' . ($tenant['name'] ?? 'Communauté');
        $body = "Bonjour,\n\nVous êtes invité à rejoindre la communauté « " . ($tenant['name'] ?? '') . " ».\n\nAcceptez l’invitation : " . $acceptUrl . "\n\n(Lien valable 7 jours.)";
        @mail($email, $subject, $body, 'From: ' . (string) env('MAIL_FROM', 'noreply@localhost') . "\r\nContent-Type: text/plain; charset=utf-8");

        $this->auditService->log(AuditAction::INVITATION_SENT, $tenantId, (int) $user['id'], 'invitation', null, null, $email);
        Session::flash('success', 'Invitation envoyée.');

        return Response::redirect(url('admin/organization/invitations'));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/organization/invitations'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) $request->input('id');
        if ($this->invitations->markRevoked($id, $tenantId)) {
            Session::flash('success', 'Invitation révoquée.');
        } else {
            Session::flash('error', 'Révocation impossible.');
        }

        return Response::redirect(url('admin/organization/invitations'));
    }
}
