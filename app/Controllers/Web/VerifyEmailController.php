<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EmailTokenRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailTokenPurpose;
use App\Services\EmailService;

final class VerifyEmailController
{
    public function __construct(
        private EmailTokenRepository $emailTokens,
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private EmailService $emailService
    ) {}

    public function verify(Request $request, array $params = []): Response
    {
        $token = trim((string) $request->query('token', ''));
        if ($token === '') {
            Session::flash('error', 'Lien invalide ou expiré.');
            return Response::redirect(url('login'));
        }
        $hash = hash('sha256', $token);
        $row = $this->emailTokens->findValidByHash($hash);
        if (!$row || ($row['purpose'] ?? '') !== EmailTokenPurpose::REGISTER_CONFIRM) {
            Session::flash('error', 'Lien invalide ou expiré.');
            return Response::redirect(url('login'));
        }
        $userId = (int) ($row['user_id'] ?? 0);
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user) {
            Session::flash('error', 'Compte introuvable.');
            return Response::redirect(url('login'));
        }

        $this->emailTokens->markConsumed((int) $row['id']);
        $this->userRepository->markEmailVerified($userId, $tenantId);

        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = (string) ($tenant['name'] ?? 'Communauté');
        $staff = $this->userRepository->listGovernanceEmailsForTenant($tenantId);
        $ip = trim($request->ip());
        foreach ($staff as $adminEmail) {
            $this->emailService->sendNewCommunityMemberStaff(
                $adminEmail,
                $tenantName,
                (string) ($user['email'] ?? ''),
                $ip !== '' ? $ip : '—',
                'Inscription confirmée (vérification e-mail)',
                $tenantId
            );
        }

        Session::flash('success', 'Adresse e-mail confirmée. Vous pouvez vous connecter.');
        return Response::redirect(url('login'));
    }

    public function showCheckEmail(Request $request, array $params = []): Response
    {
        $email = Session::get('register_pending_email');
        if ($email === null || $email === '') {
            return Response::redirect(url('register'));
        }

        return Response::view('auth.register-check-email', [
            'title' => 'Confirmez votre e-mail',
            'email' => (string) $email,
        ]);
    }
}
