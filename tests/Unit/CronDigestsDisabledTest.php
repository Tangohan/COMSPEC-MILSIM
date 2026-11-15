<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cron\CronSchedule;
use PHPUnit\Framework\TestCase;

final class CronDigestsDisabledTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('CRON_ENABLED_JOBS');
        putenv('CRON_DISABLED_JOBS');
        unset($_ENV['CRON_ENABLED_JOBS'], $_ENV['CRON_DISABLED_JOBS']);
    }

    public function testDigestJobsAreDisabledByDefault(): void
    {
        foreach (['hr_weekly_digest', 'training_forgotten_docs_digest', 'sse_analyst_digest'] as $key) {
            self::assertTrue(CronSchedule::isDisabled($key), $key);
        }
        self::assertFalse(CronSchedule::isDisabled('atak_marker_cycle'));
    }

    public function testTheEnvironmentCanReEnableOrDisableJobs(): void
    {
        putenv('CRON_ENABLED_JOBS=hr_weekly_digest');
        $_ENV['CRON_ENABLED_JOBS'] = 'hr_weekly_digest';
        self::assertFalse(CronSchedule::isDisabled('hr_weekly_digest'));
        self::assertTrue(CronSchedule::isDisabled('sse_analyst_digest'));

        putenv('CRON_DISABLED_JOBS=training_expire');
        $_ENV['CRON_DISABLED_JOBS'] = 'training_expire';
        self::assertTrue(CronSchedule::isDisabled('training_expire'));
    }

    public function testRunnerAndNightlyJobHonourTheSwitch(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString('CronSchedule::isDisabled($key)', (string) file_get_contents($root . '/app/Services/Cron/CronRunner.php'));
        self::assertStringContainsString('CronSchedule::isDisabled(SseAnalystDigestService::JOB_KEY)', (string) file_get_contents($root . '/app/Services/Cron/Jobs/SseAnalyticalNightlyCronJob.php'));
    }
}
