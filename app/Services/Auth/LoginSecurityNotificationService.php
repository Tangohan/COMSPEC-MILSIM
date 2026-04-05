<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Request;
use App\Repositories\EmailDeliveryRepository;
use App\Repositories\EmailTokenRepository;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\UserLoginDeviceRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailTokenPurpose;
use App\Services\Email\GeoIpLookupService;
use App\Services\EmailService;

/**
 * Tentatives de connexion, alerte brute-force, nouveau device (après login réussi).
 */
final class LoginSecurityNotificationService
{
    public function __construct(
        private LoginAttemptRepository $loginAttempts,
        private EmailService $emailService,
        private EmailDeliveryRepository $emailDeliveries,
        private UserLoginDeviceRepository $devices,
        private EmailTokenRepository $emailTokens,
        private UserRepository $userRepository,
        private GeoIpLookupService $geoIp
    ) {}

    public function onFailedLogin(Request $request, string $email): void
    {
        $ip = trim($request->ip());
        $this->loginAttempts->record($email, $ip !== '' ? $ip : '0.0.0.0', false);
        $cfg = \email_config();
        $window = (int) ($cfg['login_attempt_window_sec'] ?? 60);
        $threshold = (int) ($cfg['login_attempt_threshold'] ?? 8);
        $count = $this->loginAttempts->countRecentFailuresForEmailAndIp($email, $ip !== '' ? $ip : '0.0.0.0', $window);
        if ($count < $threshold) {
            return;
        }
        if ($this->emailDeliveries->countRecentSameEventForRecipient(
            \App\Services\Email\EmailEvents::MULTIPLE_LOGIN_ATTEMPTS,
            $email,
            3600
        ) > 0) {
            return;
        }
        $forgotUrl = \url('forgot-password');
        $this->emailService->sendMultipleLoginAttempts(
            $email,
            $email,
            $ip,
            date('c'),
            $forgotUrl,
            null
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    public function onSuccessfulLogin(Request $request, array $user): void
    {
        $ip = trim($request->ip());
        $this->loginAttempts->record((string) ($user['email'] ?? ''), $ip !== '' ? $ip : '0.0.0.0', true);

        $userId = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($userId < 1 || $tenantId < 1) {
            return;
        }

        $ua = $request->userAgent();
        $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $fp = hash('sha256', $ua . '|' . $lang);
        $country = $this->geoIp->countryForIp($ip);
        $geoLabel = $country !== null ? $country : 'inconnue';

        $res = $this->devices->touchOrCreate($userId, $tenantId, $fp, $ua, $ip, $country);
        if (!($res['new'] ?? false)) {
            return;
        }

        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $this->emailTokens->deletePendingForUserPurpose($userId, EmailTokenPurpose::DEVICE_DENY);
        $expires = new \DateTimeImmutable('+2 days');
        $this->emailTokens->create(
            $tenantId,
            $userId,
            EmailTokenPurpose::DEVICE_DENY,
            $hash,
            bin2hex(random_bytes(8)),
            $expires,
            ['ip' => $ip, 'ua' => mb_substr($ua, 0, 500)]
        );

        $denyUrl = \url('security/device-deny') . '?token=' . rawurlencode($raw);
        $to = (string) ($user['email'] ?? '');
        $display = (string) ($user['display_name'] ?? $to);
        $this->emailService->sendNewDeviceLogin(
            $to,
            $display,
            $ip,
            $ua,
            $geoLabel,
            $denyUrl,
            $tenantId
        );
    }
}
