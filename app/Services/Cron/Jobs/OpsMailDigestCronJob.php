<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Email\OpsMailDigestService;

/**
 * Récapitulatif e-mail des alertes d'exploitation (cadence réglée dans Administration > Envois d'e-mails).
 */
final class OpsMailDigestCronJob implements CronJobInterface
{
    public function __construct(private OpsMailDigestService $service)
    {
    }

    public function key(): string
    {
        return 'mail_ops_digest';
    }

    public function label(): string
    {
        return 'Récapitulatif des alertes e-mail';
    }

    public function description(): string
    {
        return 'Regroupe les alertes d’exploitation (incidents, nouveaux comptes, signalements du mod) en un seul e-mail.';
    }

    public function run(): array
    {
        $r = $this->service->sendDue();

        return [
            'ok' => true,
            'summary' => sprintf('%d récapitulatif(s) envoyé(s), %d alerte(s) regroupée(s)', $r['emails'], $r['items']),
            'details' => $r,
        ];
    }
}
