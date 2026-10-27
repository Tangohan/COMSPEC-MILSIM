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
            return $this->explainUnusableLink($hash);
        }
        $userId = (int) ($row['user_id'] ?? 0);
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user) {
            Session::flash('error', 'Compte introuvable.');
            return Response::redirect(url('login'));
        }

        try {
            $this->userRepository->markEmailVerified($userId, $tenantId);
        } catch (\Throwable $e) {
            error_log('[verify-email] ' . $e->getMessage());
            Session::flash('error', 'La confirmation n’a pas pu être enregistrée. Réessayez ou demandez un nouveau lien depuis la page de connexion.');

            return Response::redirect(url('login'));
        }
        // Ne consommer le lien qu'après activation effective du compte. Un échec de
        // nettoyage ne doit pas faire croire à l'utilisateur que l'activation a échoué.
        try {
            $this->emailTokens->markConsumed((int) $row['id']);
        } catch (\Throwable $e) {
            error_log('[verify-email.consume] ' . $e->getMessage());
        }

        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = email_community_label(is_array($tenant) ? $tenant : null, (string) ($tenant['name'] ?? ''));
        $registrationInbox = registration_alert_inbox_email();
        if ($registrationInbox !== null) {
            $memberEmail = strtolower(trim((string) ($user['email'] ?? '')));
            $displayName = trim((string) ($user['display_name'] ?? ''));
            $this->emailService->sendPlatformAccountRegistrationAlert(
                $registrationInbox,
                $memberEmail,
                $displayName !== '' ? $displayName : $memberEmail,
                $tenantName,
                'email_verified',
                $tenantId
            );
        }
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

    /**
     * Lien déjà utilisé ou expiré : dire au membre où il en est plutôt qu'un « lien invalide » sec.
     * Les antivirus de messagerie ouvrent souvent le lien avant le membre : le compte est alors déjà
     * actif et le second clic ne doit pas ressembler à un échec.
     */
    private function explainUnusableLink(string $hash): Response
    {
        $row = $this->emailTokens->findAnyByHash($hash);
        if (!$row || ($row['purpose'] ?? '') !== EmailTokenPurpose::REGISTER_CONFIRM) {
            Session::flash('error', 'Ce lien de confirmation n’est plus valide (un lien plus récent a peut-être été envoyé). Utilisez le dernier e-mail reçu, ou connectez-vous pour demander un nouveau lien.');

            return Response::redirect(url('login'));
        }
        $user = $this->userRepository->findById((int) ($row['user_id'] ?? 0), (int) ($row['tenant_id'] ?? 0));
        if (!$user) {
            Session::flash('error', 'Compte introuvable. Il a peut-être été supprimé : vous pouvez créer un nouveau compte.');

            return Response::redirect(url('login'));
        }
        if (($user['status'] ?? '') !== 'pending_verification') {
            Session::forget('pending_verification_email');
            Session::flash('success', 'Votre adresse e-mail est déjà confirmée. Vous pouvez vous connecter.');

            return Response::redirect(url('login'));
        }
        // Compte toujours en attente : proposer le renvoi directement sur la page de connexion.
        Session::set('pending_verification_email', strtolower(trim((string) ($user['email'] ?? ''))));
        $expired = !empty($row['expires_at']) && \App\Support\DbTime::utcToTimestamp((string) $row['expires_at']) <= time();
        Session::flash(
            'error',
            $expired
                ? 'Ce lien de confirmation a expiré. Cliquez sur « Renvoyer le lien de confirmation » ci-dessous pour en recevoir un nouveau.'
                : 'Ce lien n’est plus valide. Cliquez sur « Renvoyer le lien de confirmation » ci-dessous pour en recevoir un nouveau.'
        );

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
            'warning' => Session::getFlash('warning'),
            'success' => Session::getFlash('success'),
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

        $raw = (string) $request->input('from') === 'check-email'
            ? (Session::get('register_pending_email') ?? Session::get('pending_verification_email'))
            : (Session::get('pending_verification_email') ?? Session::get('register_pending_email'));
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

        // Depuis l'écran « Confirmez votre e-mail », rester sur cet écran plutôt que renvoyer vers la connexion.
        $fromCheckEmail = strtolower(trim((string) (Session::get('register_pending_email') ?? ''))) === $email
            && (string) $request->input('from') === 'check-email';
        $back = $fromCheckEmail ? url('register/check-email') : url('login');

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

            return Response::redirect($back);
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
            $expires = new \DateTimeImmutable('+' . EmailTokenPurpose::REGISTER_CONFIRM_TTL_MINUTES . ' minutes');
            $verifyUrl = url('verify-email') . '?token=' . rawurlencode($rawToken);
            try {
                // Créer avant l'envoi empêche qu'un message délivré immédiatement pointe
                // vers un jeton qui n'existe pas encore.
                $tokenId = $this->emailTokens->create(
                    $tenantId,
                    $uid,
                    EmailTokenPurpose::REGISTER_CONFIRM,
                    $tokenHash,
                    bin2hex(random_bytes(16)),
                    $expires
                );
            } catch (\Throwable $e) {
                error_log('[verify-email.resend.token] ' . $e->getMessage());
                $attemptedSend = true;
                $lastMailError = 'token';
                continue;
            }
            $attemptedSend = true;
            $ok = $this->emailService->sendUserRegisterConfirmation(
                $email,
                (string) ($row['display_name'] ?? 'Membre'),
                $tenantName,
                $verifyUrl,
                EmailTokenPurpose::REGISTER_CONFIRM_TTL_MINUTES,
                $tenantId
            );
            if (!$ok) {
                // Ne pas invalider l'ancien lien et ne pas appliquer le cooldown à un
                // message qui n'est jamais parti.
                $this->emailTokens->deleteById($tokenId);
                $lastMailError = trim((string) ($this->emailService->getLastSendError() ?? ''));

                continue;
            }

            $this->emailTokens->deleteOtherPendingForUserPurpose(
                $uid,
                EmailTokenPurpose::REGISTER_CONFIRM,
                $tokenId
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

            return Response::redirect($back);
        }

        if ($attemptedSend && $lastMailError !== null && $lastMailError !== '') {
            Session::flash('error', 'L’e-mail n’a pas pu être envoyé. Réessayez dans quelques minutes ou contactez le support.');

            return Response::redirect($back);
        }

        if ($attemptedSend) {
            Session::flash('error', 'L’e-mail n’a pas pu être envoyé pour le moment. Réessayez dans quelques minutes ou contactez le support.');

            return Response::redirect($back);
        }

        if ($rateLimited) {
            Session::flash('error', 'Un lien vient déjà d’être envoyé. Patientez environ une minute avant d’en redemander un, et vérifiez vos courriers indésirables.');

            return Response::redirect($back);
        }

        Session::flash(
            'success',
            'Si un compte en attente de confirmation existe pour cette adresse, un nouveau lien vient d’être envoyé.'
        );

        return Response::redirect($back);
    }
}
