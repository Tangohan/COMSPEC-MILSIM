<?php

declare(strict_types=1);

namespace App\Services\Cron;

interface CronJobInterface
{
    public function key(): string;

    /** Libellé lisible (admin). */
    public function label(): string;

    /** Description courte pour l’admin. */
    public function description(): string;

    /**
     * @return array{ok: bool, summary: string, details?: array<string, mixed>}
     */
    public function run(): array;
}
