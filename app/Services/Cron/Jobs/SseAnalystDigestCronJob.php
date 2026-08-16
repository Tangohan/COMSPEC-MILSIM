<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Sse\SseAnalystDigestService;

/**
 * Envoie le digest e-mail SSE (rapprochements, signaux, fiches terrain).
 * Une fois par jour et par communauté (dédup interne).
 */
final class SseAnalystDigestCronJob implements CronJobInterface
{
    public function __construct(
        private SseAnalystDigestService $digest,
    ) {}

    public function key(): string
    {
        return SseAnalystDigestService::JOB_KEY;
    }

    public function label(): string
    {
        return 'Digest e-mail SSE (analystes)';
    }

    public function description(): string
    {
        return 'Envoie un point quotidien aux analystes SSE : rapprochements à trancher, signaux ouverts, nouvelles fiches terrain et dossiers d’intérêt actifs.';
    }

    public function run(): array
    {
        return $this->digest->runAllTenants();
    }
}
