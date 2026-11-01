<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Sse\SseAnalystDigestService;

/**
 * Point quotidien SSE (rapprochements, signaux, fiches terrain) : Discord seulement, plus d'e-mail.
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
        return 'Point quotidien SSE (Discord)';
    }

    public function description(): string
    {
        return 'Publie sur le salon Discord de la communauté (si activé) un point quotidien SSE : rapprochements à trancher, signaux ouverts, nouvelles fiches terrain et dossiers d’intérêt actifs.';
    }

    public function run(): array
    {
        return $this->digest->runAllTenants();
    }
}
