<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Personnel\MemberGradeScheduleService;

/**
 * Applique les changements de grade planifiés dont la date est arrivée.
 */
final class MemberGradeScheduleCronJob implements CronJobInterface
{
    public function __construct(
        private MemberGradeScheduleService $schedules,
    ) {
    }

    public function key(): string
    {
        return 'member_grade_schedule';
    }

    public function label(): string
    {
        return 'Grades planifiés';
    }

    public function description(): string
    {
        return 'Applique les changements de grade planifiés à leur échéance ; annule sans erreur ceux dont le grade n’existe plus ou dont le membre a quitté la communauté.';
    }

    public function run(): array
    {
        $out = $this->schedules->applyDue();

        return [
            'ok' => true,
            'summary' => $out['applied'] . ' grade(s) appliqué(s), ' . $out['cancelled'] . ' annulé(s).',
            'details' => $out,
        ];
    }
}
