<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityInvitationRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Services\EmailService;
use App\Services\Platform\FeatureGateService;

final class InvitationAdminController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private CommunityInvitationRepository $invitations,
        private RoleRepository $roleRepository,
        private FeatureGateService $featureGate,
        private AuditService $auditService,
        private EmailService $emailService
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

            return Response::redirect(url('back-office/invitations'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->canAddMember($tenantId)) {
            Session::flash('error', 'Limite de membres atteinte pour ce plan.');

            return Response::redirect(url('back-office/invitations'));
        }
        $email = strtolower(trim((string) $request->input('email')));
        $roleId = (int) $request->input('role_id');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Email invalide.');

            return Response::redirect(url('back-office/invitations'));
        }
        $roleRow = $this->roleRepository->findById($roleId, $tenantId);
        $roleIdFinal = null;
        if ($roleRow && ($roleRow['role_layer'] ?? '') === 'community' && $this->roleRepository->canAssignInTenantAdminContext($roleId, $tenantId)) {
            $roleIdFinal = $roleId;
        } elseif ($roleId > 0) {
            Session::flash('error', 'Seul un rôle de gouvernance communauté peut être choisi à l’invitation.');

            return Response::redirect(url('back-office/invitations'));
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = new \DateTimeImmutable('+7 days');
        $this->invitations->create($tenantId, $email, $hash, (int) $user['id'], $roleIdFinal, $expires);

        $tenant = $this->tenantRepository->findById($tenantId);
        $acceptUrl = url('invitations/accept') . '?token=' . rawurlencode($token);
        $roleLabel = '';
        if ($roleIdFinal !== null && $roleIdFinal > 0) {
            $rr = $this->roleRepository->findById($roleIdFinal, $tenantId);
            $roleLabel = $rr ? trim((string) ($rr['name'] ?? '')) : '';
        }
        $inviterLabel = trim((string) ($user['display_name'] ?? '')) !== ''
            ? (string) $user['display_name']
            : (string) ($user['email'] ?? '');
        $replyTo = (string) ($user['email'] ?? '');
        $this->emailService->sendCommunityInvitation(
            $email,
            (string) ($tenant['name'] ?? 'Communauté'),
            $acceptUrl,
            $roleLabel,
            $inviterLabel,
            $tenantId,
            $replyTo !== '' ? $replyTo : null
        );

        $this->auditService->log(AuditAction::INVITATION_SENT, $tenantId, (int) $user['id'], 'invitation', null, null, $email);
        Session::flash('success', 'Invitation envoyée.');

        return Response::redirect(url('back-office/invitations'));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/invitations'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) $request->input('id');
        $inv = $this->invitations->findByIdForTenant($id, $tenantId);
        if (!$inv) {
            Session::flash('error', 'Invitation introuvable.');
            return Response::redirect(url('back-office/invitations'));
        }
        $actor = $this->authService->user();
        $gate = Gate::getInstance();
        $canAdmin = $gate->allows('admin.organization') || $gate->allows('admin.access');
        if (!$canAdmin && $gate->allows('invitations.send') && $actor) {
            if ((int) ($inv['invited_by_user_id'] ?? 0) !== (int) ($actor['id'] ?? 0)) {
                Session::flash('error', 'Vous ne pouvez révoquer que les invitations que vous avez envoyées.');
                return Response::redirect(url('back-office/invitations'));
            }
        }
        if ($this->invitations->markRevoked($id, $tenantId)) {
            Session::flash('success', 'Invitation révoquée.');
        } else {
            Session::flash('error', 'Révocation impossible.');
        }

        return Response::redirect(url('back-office/invitations'));
    }
}
