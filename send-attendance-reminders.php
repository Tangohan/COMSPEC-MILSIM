<?php

declare(strict_types=1);

/**
 * Rappels de présence (e-mail) : participants oui/peut-être pour les événements
 * dans les prochaines 24 h, une seule fois par inscription (reminder_sent_at).
 *
 * Planification type cron (toutes les heures) :
 *   php /chemin/vers/send-attendance-reminders.php
 */

$root = dirname(__FILE__);
require $root . '/bootstrap/app.php';

use App\Core\Container;
use App\Repositories\CommunityEventRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

$repo = Container::get(CommunityEventRepository::class);
$email = Container::get(EmailService::class);
$tenants = Container::get(TenantRepository::class);
$notifPrefs = Container::get(UserNotificationPreferencesRepository::class);

$rows = $repo->listRsvpRowsEligibleForReminder();
$sent = 0;
$failed = 0;

foreach ($rows as $row) {
    $tenantId = (int) ($row['tenant_id'] ?? 0);
    $eventId = (int) ($row['event_id'] ?? 0);
    $userId = (int) ($row['user_id'] ?? 0);
    $to = trim((string) ($row['email'] ?? ''));
    if ($tenantId < 1 || $eventId < 1 || $userId < 1 || $to === '') {
        continue;
    }
    $tenant = $tenants->findById($tenantId);
    $tenantName = (string) ($tenant['name'] ?? 'Communauté');
    $title = (string) ($row['title'] ?? '');
    $starts = (string) ($row['starts_at'] ?? '');
    $displayName = (string) ($row['display_name'] ?? 'Membre');

    if (!$notifPrefs->isEmailEventEnabled($userId, EmailEvents::ATTENDANCE_REMINDER)) {
        $repo->markReminderSent($eventId, $userId);
        continue;
    }

    $ok = $email->sendAttendanceReminder(
        $to,
        $displayName,
        $tenantName,
        $title,
        $starts,
        $eventId,
        $tenantId
    );
    if ($ok) {
        $repo->markReminderSent($eventId, $userId);
        $sent++;
    } else {
        $failed++;
    }
}

echo date('c') . " — rappels pointage : {$sent} envoyé(s), {$failed} échec(s), " . count($rows) . " ligne(s) éligible(s).\n";
exit(0);
