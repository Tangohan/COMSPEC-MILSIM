<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Events\EndlessEventSeriesService;
use App\Services\Integrations\DiscordEventAttendanceService;
use App\Services\Platform\FeatureGateService;

final class EventAutomationCronJob implements CronJobInterface
{
    public function __construct(private FeatureGateService $gate)
    {
    }

    public function key(): string
    {
        return 'event_automation';
    }

    public function label(): string
    {
        return __('events.automation_label');
    }

    public function description(): string
    {
        return __('events.automation_description');
    }

    public function run(): array
    {
        $created = (new EndlessEventSeriesService($this->gate))->replenish();
        $synced = (new DiscordEventAttendanceService())->run();

        return ['ok' => true, 'summary' => __('events.automation_summary', ['created' => $created, 'synced' => $synced]), 'details' => ['created' => $created, 'synced' => $synced]];
    }
}
