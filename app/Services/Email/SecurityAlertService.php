<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Services\EmailService;

/**
 * Alertes sécurité (liste globale SECURITY_ALERT_EMAILS). Niveaux : INFO, WARNING, CRITICAL.
 */
final class SecurityAlertService
{
    public function __construct(private EmailService $emailService) {}

    public function notify(string $level, string $title, string $body, ?int $tenantId = null): void
    {
        $level = strtoupper($level);
        $cfg = \email_config();
        $recipients = $cfg['security_alert_emails'] ?? [];
        if (!is_array($recipients) || $recipients === []) {
            return;
        }
        foreach ($recipients as $to) {
            $to = trim((string) $to);
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $this->emailService->sendSecurityAlert($to, $level, $title, $body, $tenantId);
        }
    }
}
