<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Integrations\DiscordPhotoQueueService;

/**
 * Publie sur Discord les photos en attente : un seul message par joueur et par mission (10 photos au plus).
 */
final class DiscordPhotoQueueCronJob implements CronJobInterface
{
    public function key(): string
    {
        return 'discord_photo_queue';
    }

    public function label(): string
    {
        return 'Discord : photos regroupées en un seul message';
    }

    public function description(): string
    {
        return 'Envoie sur Discord les photos prises par un même joueur dans une même mission en un seul message (10 photos au plus). '
            . 'Une photo qui n’a pas pu partir est retentée ; elle reste toujours disponible sur Athena.';
    }

    public function run(): array
    {
        $r = (new DiscordPhotoQueueService())->flush();

        return [
            'ok' => true,
            'summary' => $r['messages'] . ' message(s) envoyé(s) · ' . $r['photos'] . ' photo(s) · ' . $r['failed'] . ' à retenter',
            'details' => $r,
        ];
    }
}
