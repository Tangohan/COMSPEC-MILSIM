<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EmailTokenRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Email\EmailTokenPurpose;

final class SecurityDeviceController
{
    public function __construct(
        private EmailTokenRepository $emailTokens,
        private UserRepository $userRepository,
        private AuditService $auditService
    ) {}

    public function denyDevice(Request $request, array $params = []): Response
    {
        $token = trim((string) $request->query('token', ''));
        if ($token === '') {
            Session::flash('error', 'Lien invalide ou expiré.');
            return Response::redirect(url('login'));
        }
        $hash = hash('sha256', $token);
        $row = $this->emailTokens->findValidByHash($hash);
        if (!$row || ($row['purpose'] ?? '') !== EmailTokenPurpose::DEVICE_DENY) {
            Session::flash('error', 'Lien invalide ou expiré.');
            return Response::redirect(url('login'));
        }
        $userId = (int) ($row['user_id'] ?? 0);
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $this->emailTokens->markConsumed((int) $row['id']);
        $this->userRepository->invalidateAllSessionsForUser($userId, $tenantId);
        $this->auditService->log(
            AuditAction::SECURITY_EVENT,
            $tenantId,
            $userId,
            'security',
            $userId,
            null,
            'sessions_invalidated_device_deny'
        );
        Session::flash('success', 'Toutes les sessions ont été révoquées. Connectez-vous à nouveau si c’était vous.');
        return Response::redirect(url('login'));
    }
}
