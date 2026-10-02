<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cooperation\CooperationReminderService;
use App\Services\Cron\CronJobInterface;

/**
 * Coopération inter-unités : échéances dépassées, relances J-2 des invitations sans réponse,
 * avis d’expiration des autorisations de partage. Voir CooperationReminderService.
 */
final class CooperationRemindersCronJob implements CronJobInterface
{
    public function __construct(private CooperationReminderService $service) {}

    public function key(): string
    {
        return 'cooperation_reminders';
    }

    public function label(): string
    {
        return 'Relances de coopération';
    }

    public function description(): string
    {
        return 'Relance à J-2 les unités invitées sans réponse (une fois par 24 h), signale les dates limites dépassées et prévient avant l’expiration d’une autorisation de partage.';
    }

    public function run(): array
    {
        $s = $this->service->run();

        return [
            'ok' => $s['errors'] === 0,
            'summary' => "Relances : {$s['reminders']} · échéances signalées : {$s['deadlines']} · avis d’expiration : {$s['consent_notices']} · erreurs : {$s['errors']}",
            'details' => $s,
        ];
    }
}
