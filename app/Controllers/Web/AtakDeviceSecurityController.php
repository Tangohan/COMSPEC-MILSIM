<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakRealismRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;

final class AtakDeviceSecurityController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?AtakRealismRepository $realism = null,
        private ?UserRepository $userRepository = null,
        private ?AuditService $auditService = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->realism ??= Container::get(AtakRealismRepository::class);
        $this->userRepository ??= Container::get(UserRepository::class);
        $this->auditService ??= Container::get(AuditService::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $userId = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $fresh = $this->userRepository->findById($userId, $tenantId);
        if (is_array($fresh)) {
            $user = array_merge($user, $fresh);
        }

        return Response::view('layout.main', [
            'title' => 'Appareils liés',
            'content' => 'account.devices',
            'accountHubPage' => true,
            'user' => $user,
            'devices' => $this->realism->listPhysicalTerminalsForUser($tenantId, $userId),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function revoke(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('account/security/devices'));
        }
        $userId = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $terminalId = (int) $request->input('terminal_id', 0);
        $ok = $this->realism->revokePhysicalTerminalForUser($tenantId, $userId, $terminalId);
        if ($ok) {
            $this->auditService->log(
                AuditAction::SECURITY_EVENT,
                $tenantId,
                $userId,
                'atak_terminal',
                $terminalId,
                null,
                'terminal_revoked_by_owner'
            );
            Session::flash('success', 'Cet appareil n’est plus autorisé pour votre compte.');
        } else {
            Session::flash('error', 'Impossible de retirer cet appareil.');
        }

        return Response::redirect(url('account/security/devices'));
    }
}
