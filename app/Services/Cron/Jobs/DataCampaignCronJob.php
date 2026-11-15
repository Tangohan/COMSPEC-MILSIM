<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\DataCampaign\DataCampaignService;

/**
 * Campagne de fiabilisation des données : ouvre la campagne annuelle des communautés qui l'ont activée (désactivée par
 * défaut : sans ce réglage, rien ne s'ouvre seul), clôture les campagnes automatiques échues et envoie les rappels
 * (J-14, J-3, jour J) une seule fois chacun. Un passage est idempotent et borné (une communauté à la fois,
 * DataCampaignService::BATCH envois au plus).
 */
final class DataCampaignCronJob implements CronJobInterface
{
    public function __construct(private ?DataCampaignService $service = null)
    {
    }

    public function key(): string
    {
        return 'data_campaign';
    }

    public function label(): string
    {
        return 'Fiabilisation des données : campagne annuelle et rappels';
    }

    public function description(): string
    {
        return 'Ouvre la campagne annuelle des communautés qui l’ont activée, clôture les campagnes automatiques échues et envoie les rappels aux membres qui n’ont pas confirmé leur fiche (une fois chacun).';
    }

    public function run(): array
    {
        $r = ($this->service ??= new DataCampaignService())->runScheduled();

        return [
            'ok' => $r['errors'] === 0,
            'summary' => sprintf(
                'Fiabilisation : %d campagne(s) ouverte(s), %d clôturée(s), %d rappel(s) envoyé(s), %d communauté(s) examinée(s), %d erreur(s).',
                $r['opened'],
                $r['closed'],
                $r['reminders'],
                $r['tenants'],
                $r['errors']
            ),
            'details' => $r,
        ];
    }
}
