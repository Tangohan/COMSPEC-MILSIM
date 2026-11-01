<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Platform\LowMemberOrgService;

/**
 * Communautés restées à un ou deux membres : rappel aux responsables à 3 mois, relance à 4 mois (un envoi par
 * palier, journal org_low_member_reminders), puis liste « Organisations à supprimer » pour l'administration
 * (notification du site, résumé e-mail au plus une fois par semaine). Aucune suppression automatique.
 */
final class LowMemberOrgRemindersCronJob implements CronJobInterface
{
    public function __construct(private ?LowMemberOrgService $service = null)
    {
    }

    public function key(): string
    {
        return 'org_low_member_reminders';
    }

    public function label(): string
    {
        return 'Communautés à un ou deux membres';
    }

    public function description(): string
    {
        return 'Rappel aux responsables d’une communauté qui ne compte qu’un ou deux membres après 3 mois, relance à 4 mois. Signale ensuite à l’administration les organisations à supprimer (rien n’est supprimé automatiquement).';
    }

    public function run(): array
    {
        $r = ($this->service ??= new LowMemberOrgService())->run();
        if (!$r['ready']) {
            return ['ok' => true, 'summary' => 'Tables absentes : lancer la mise à jour de la base (étape org_low_member_reminders).', 'details' => $r];
        }

        return [
            'ok' => true,
            'summary' => sprintf(
                '%d rappel(s), %d e-mail(s) ; %d organisation(s) à supprimer ; %d notification(s) et %d e-mail(s) à l’administration',
                $r['reminders'],
                $r['emails'],
                $r['listed'],
                $r['admin_notified'],
                $r['admin_emails']
            ),
            'details' => $r,
        ];
    }
}
