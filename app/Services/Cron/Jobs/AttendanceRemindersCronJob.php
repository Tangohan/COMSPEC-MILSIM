<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\CommunityEventRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Services\Cron\CronJobInterface;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use Throwable;

/**
 * Rappels de participation : participants « oui » / « peut-être » pour les événements
 * des prochaines 24 h, une seule fois par inscription (community_event_rsvps.reminder_sent_at).
 *
 * Reprend la logique de send-attendance-reminders.php, resté à la racine hors du registre
 * CronRunner : deux planificateurs coexistaient, sans supervision ni journal communs. Le
 * script délègue désormais à cette tâche.
 */
final class AttendanceRemindersCronJob implements CronJobInterface
{
    public function __construct(
        private CommunityEventRepository $events,
        private EmailService $emailService,
        private TenantRepository $tenants,
        private UserNotificationPreferencesRepository $notificationPreferences,
    ) {}

    public function key(): string
    {
        return 'attendance_reminders';
    }

    public function label(): string
    {
        return 'Rappels de participation';
    }

    public function description(): string
    {
        return 'Envoie un rappel aux participants inscrits aux événements des prochaines 24 h (une fois par inscription).';
    }

    public function run(): array
    {
        $rows = $this->events->listRsvpRowsEligibleForReminder();
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $tenantId = (int) ($row['tenant_id'] ?? 0);
            $eventId = (int) ($row['event_id'] ?? 0);
            $userId = (int) ($row['user_id'] ?? 0);
            $to = trim((string) ($row['email'] ?? ''));
            if ($tenantId < 1 || $eventId < 1 || $userId < 1 || $to === '') {
                $skipped++;
                continue;
            }

            try {
                // Préférence de notification refusée : marquer comme traité pour ne pas
                // repasser dessus à chaque exécution.
                if (!$this->notificationPreferences->isEmailEventEnabled($userId, EmailEvents::ATTENDANCE_REMINDER)) {
                    $this->events->markReminderSent($eventId, $userId);
                    $skipped++;
                    continue;
                }

                $tenant = $this->tenants->findById($tenantId);
                $ok = $this->emailService->sendAttendanceReminder(
                    $to,
                    (string) ($row['display_name'] ?? 'Membre'),
                    (string) ($tenant['name'] ?? 'Communauté'),
                    (string) ($row['title'] ?? ''),
                    (string) ($row['starts_at'] ?? ''),
                    $eventId,
                    $tenantId
                );
                if ($ok) {
                    $this->events->markReminderSent($eventId, $userId);
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (Throwable) {
                // Un destinataire en échec ne doit pas interrompre la tournée.
                $failed++;
            }
        }

        return [
            'ok' => $failed === 0,
            'summary' => "Rappels envoyés : {$sent} · échecs : {$failed} · ignorés : {$skipped} (sur " . count($rows) . ' éligible(s))',
            'details' => [
                'sent' => $sent,
                'failed' => $failed,
                'skipped' => $skipped,
                'eligible' => count($rows),
            ],
        ];
    }
}
