<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Platform\AthenaHeritageService;

/**
 * Snapshot quotidien du patrimoine statistique + détection des jalons uniques.
 */
final class AthenaHeritageSnapshotCronJob implements CronJobInterface
{
    public function __construct(
        private AthenaHeritageService $heritage,
    ) {}

    public function key(): string
    {
        return 'athena_heritage_snapshot';
    }

    public function label(): string
    {
        return 'Patrimoine ATHENA — snapshots & jalons';
    }

    public function description(): string
    {
        return 'Enregistre le snapshot du jour (opérateurs, organisations, distance, temps de jeu) '
            . 'et crée une seule fois les jalons symboliques atteints.';
    }

    public function run(): array
    {
        return $this->heritage->refresh();
    }
}
