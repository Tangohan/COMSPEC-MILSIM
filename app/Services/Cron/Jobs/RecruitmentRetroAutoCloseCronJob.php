<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Recruitment\EnlistmentRetroAutoCloseService;
use App\Support\EnlistmentRetroAutoClose;

/**
 * Bilan 30 jours attendu depuis 45 jours sans bilan : un « néant » est enregistré, validé par le système Athena.
 * Règle et seuil : EnlistmentRetroAutoClose. Simulation sans écriture : `php recruitment-retro-auto-close.php --dry-run`.
 */
final class RecruitmentRetroAutoCloseCronJob implements CronJobInterface
{
    public function __construct(private EnlistmentRetroAutoCloseService $service) {}

    public function key(): string
    {
        return 'recruitment_retro_auto_close';
    }

    public function label(): string
    {
        return 'Bilans recrutement : clôture automatique (néant)';
    }

    public function description(): string
    {
        return 'Enregistre un bilan « néant » (sans note, clos par Athena) pour les candidatures acceptées dont le bilan 30 jours est attendu depuis '
            . EnlistmentRetroAutoClose::AUTO_CLOSE_LATE_DAYS . ' jours sans avoir été rédigé.';
    }

    public function run(): array
    {
        $r = $this->service->run();
        if (!$r['available']) {
            return ['ok' => true, 'summary' => 'Rien à faire : ' . $r['reason'], 'details' => ['available' => false]];
        }

        return [
            'ok' => $r['ok'],
            'summary' => sprintf('%d bilan(s) clos automatiquement (néant), %d erreur(s).', $r['closed'], $r['errors']),
            'details' => ['closed' => $r['closed'], 'errors' => $r['errors']],
        ];
    }
}
