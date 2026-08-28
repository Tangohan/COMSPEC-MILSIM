<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\Cron\CronSchedule;
use PHPUnit\Framework\TestCase;

final class CronScheduleAndDatabaseQueryAssetTest extends TestCase
{
    public function testDatabaseExposesQueryForAtakRepositories(): void
    {
        self::assertTrue(method_exists(Database::class, 'query'));
        $ref = new \ReflectionMethod(Database::class, 'query');
        self::assertSame(\PDOStatement::class, $ref->getReturnType()?->getName());
    }

    public function testEscalationIsDueEveryFiveMinutesAndNightlyOnceADay(): void
    {
        self::assertSame(5, CronSchedule::intervalMinutes('atak_report_routing_escalations'));
        self::assertSame(1440, CronSchedule::intervalMinutes('sse_analytical_nightly'));
        self::assertSame(1440, CronSchedule::intervalMinutes('sse_analyst_digest'));
        self::assertSame(60, CronSchedule::intervalMinutes('unknown_job'));

        $now = 1_700_000_000;
        $okRecent = [
            'status' => 'ok',
            'finished_at' => date('Y-m-d H:i:s', $now - 60),
        ];
        self::assertFalse(CronSchedule::isDue('atak_report_routing_escalations', $okRecent, false, $now));
        self::assertTrue(CronSchedule::isDue('atak_report_routing_escalations', $okRecent, false, $now + 400));
        self::assertTrue(CronSchedule::isDue('atak_report_routing_escalations', $okRecent, true, $now));

        $failed = [
            'status' => 'error',
            'finished_at' => date('Y-m-d H:i:s', $now - 10),
        ];
        self::assertTrue(CronSchedule::isDue('sse_analyst_digest', $failed, false, $now));

        $stuck = [
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s', $now - 40 * 60),
        ];
        self::assertTrue(CronSchedule::isDue('training_expire', $stuck, false, $now));
    }

    public function testSchedulerLooksActiveOnlyForAutomaticTriggers(): void
    {
        $now = 1_700_000_000;
        $cli = [[
            'trigger_source' => 'cli',
            'finished_at' => date('Y-m-d H:i:s', $now - 120),
        ]];
        $admin = [[
            'trigger_source' => 'admin',
            'finished_at' => date('Y-m-d H:i:s', $now - 30),
        ]];
        self::assertTrue(CronSchedule::schedulerLooksActive($cli, 600, $now));
        self::assertFalse(CronSchedule::schedulerLooksActive($admin, 600, $now));
        self::assertFalse(CronSchedule::schedulerLooksActive($cli, 60, $now));
    }

    public function testCrontabLineTicksEveryFiveMinutes(): void
    {
        $line = CronSchedule::crontabLine('php', '/srv/scripts/cron-run.php', '/srv/storage/logs/cron.log');
        self::assertStringContainsString('*/5 * * * *', $line);
        self::assertStringContainsString('cron-run.php', $line);
        self::assertStringContainsString('flock', $line);
    }

    public function testInstallScriptAndAdminPageDescribeAutomaticPass(): void
    {
        $root = dirname(__DIR__, 2);
        $sh = (string) file_get_contents($root . '/scripts/install-system-cron.sh');
        $ps1 = (string) file_get_contents($root . '/scripts/install-system-cron.ps1');
        $view = (string) file_get_contents($root . '/views/admin/system/cron.php');
        $runner = (string) file_get_contents($root . '/app/Services/Cron/CronRunner.php');
        $watchdog = (string) file_get_contents($root . '/app/Services/Cron/CronWatchdog.php');
        $app = (string) file_get_contents($root . '/app/Core/Application.php');

        self::assertStringContainsString('cron-run.php', $sh);
        self::assertStringContainsString('*/5 * * * *', $sh);
        self::assertStringContainsString('Athena-TachesAutomatiques', $ps1);
        self::assertStringContainsString('cinq minutes', $view);
        self::assertStringContainsString('install-system-cron.sh', $view);
        self::assertStringContainsString('CronSchedule::isDue', $runner);
        self::assertStringContainsString('maybeKick', $watchdog);
        self::assertStringContainsString('CronWatchdog::maybeKick', $app);
    }
}
