<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\EmailTokenRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\Email\EmailTokenPurpose;
use App\Services\EmailService;

final class VerifyEmailController
{
    private const RESEND_COOLDOWN_SEC = 90;

    public function __construct(
        private EmailTokenRepository $emailTokens,
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private EmailService $emailService,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository
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
        $tenantName = email_community_label(is_array($tenant) ? $tenant : null, (string) ($tenant['name'] ?? ''));
        // Pas de notif « nouveau membre » sur le tenant système : ce n’est pas une vraie communauté.
        if (is_array($tenant) && ($tenant['slug'] ?? '') !== 'default') {
            $staff = $this->userRepository->listGovernanceEmailsForTenant($tenantId);
            $ip = trim($request->ip());
            foreach ($staff as $adminEmail) {
                $em = strtolower(trim($adminEmail));
                $adm = $em !== '' ? $this->userRepository->findByEmail($tenantId, $em) : null;
                if ($adm && !$this->notificationPreferencesRepository->isEmailEventEnabled((int) ($adm['id'] ?? 0), EmailEvents::NEW_COMMUNITY_MEMBER)) {
                    continue;
                }
                $this->emailService->sendNewCommunityMemberStaff(
                    $adminEmail,
                    $tenantName,
                    (string) ($user['email'] ?? ''),
                    $ip !== '' ? $ip : '—',
                    'Inscription confirmée (vérification e-mail)',
                    $tenantId
                );
            }
        }

        Session::forget('pending_verification_email');
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
            'error' => Session::getFlash('error'),
        ]);
    }

    public function resendVerification(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('login'));
        }

        $raw = Session::get('pending_verification_email') ?? Session::get('register_pending_email');
        $email = strtolower(trim((string) (($raw !== null && $raw !== '') ? $raw : $request->input('email'))));
        if ($email === '') {
            Session::flash('error', 'Saisissez d’abord votre identifiant sur la page de connexion, puis réessayez.');

            return Response::redirect(url('login'));
        }

        $v = new Validator(['email' => $email], ['email' => 'required|email']);
        if (!$v->validate()) {
            Session::flash('error', 'Adresse e-mail invalide.');

            return Response::redirect(url('login'));
        }

        $candidates = $this->userRepository->listUsersForLoginByEmail($email);
        $pending = array_values(array_filter(
            $candidates,
            static fn (array $r): bool => ($r['status'] ?? '') === 'pending_verification'
        ));

        if ($pending === []) {
            Session::flash(
                'success',
                'Si un compte en attente de confirmation existe pour cette adresse, un nouveau lien vient d’être envoyé.'
            );

            return Response::redirect(url('login'));
        }

        $sent = 0;
        $rateLimited = false;
        $lastMailError = null;
        $attemptedSend = false;

        foreach ($pending as $row) {
            $uid = (int) $row['id'];
            $last = $this->emailTokens->getLatestTokenCreatedAtForUserPurpose($uid, EmailTokenPurpose::REGISTER_CONFIRM);
            if ($last !== null && (time() - $last->getTimestamp()) < self::RESEND_COOLDOWN_SEC) {
                $rateLimited = true;

                continue;
            }

            $tenantId = (int) $row['tenant_id'];
            $tenant = $this->tenantRepository->findById($tenantId);
            $tenantName = email_community_label(is_array($tenant) ? $tenant : null, (string) ($tenant['name'] ?? ''));
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expires = new \DateTimeImmutable('+15 minutes');
            $verifyUrl = url('verify-email') . '?token=' . rawurlencode($rawToken);
            $attemptedSend = true;
            $ok = $this->emailService->sendUserRegisterConfirmation(
                $email,
                (string) ($row['display_name'] ?? 'Membre'),
                $tenantName,
                $verifyUrl,
                15,
                $tenantId
            );
            if (!$ok) {
                $lastMailError = trim((string) ($this->emailService->getLastSendError() ?? ''));

                continue;
            }

            $this->emailTokens->deletePendingForUserPurpose($uid, EmailTokenPurpose::REGISTER_CONFIRM);
            $this->emailTokens->create(
                $tenantId,
                $uid,
                EmailTokenPurpose::REGISTER_CONFIRM,
                $tokenHash,
                bin2hex(random_bytes(16)),
                $expires
            );
            $sent++;
        }

        if ($sent > 0) {
            $notice = \email_file_mailer_notice();
            if ($notice !== '') {
                Session::flash('warning', $notice);
            }
            Session::flash(
                'success',
                'Un nouveau lien de confirmation a été envoyé. Vérifiez votre boîte e-mail (et les courriers indésirables).'
            );

            return Response::redirect(url('login'));
        }

        if ($attemptedSend && $lastMailError !== null && $lastMailError !== '') {
            Session::flash('error', 'L’e-mail n’a pas pu être envoyé : ' . $lastMailError);

            return Response::redirect(url('login'));
        }

        if ($attemptedSend) {
            Session::flash('error', 'L’e-mail n’a pas pu être envoyé. Vérifiez la configuration SMTP dans .env ou contactez un administrateur.');

            return Response::redirect(url('login'));
        }

        if ($rateLimited) {
            Session::flash('error', 'Veuillez patienter une minute avant de redemander un lien.');

            return Response::redirect(url('login'));
        }

        Session::flash(
            'success',
            'Si un compte en attente de confirmation existe pour cette adresse, un nouveau lien vient d’être envoyé.'
        );

        return Response::redirect(url('login'));
    }
}
