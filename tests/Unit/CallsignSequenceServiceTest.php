<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\CallsignSequenceService;
use App\Repositories\CallsignSequenceRepository;
use App\Repositories\PersonnelCareerEventRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

final class CallsignSequenceServiceTest extends TestCase
{
    public function testFormatPrefixNumericAndCustomPattern(): void
    {
        $svc = $this->service();

        self::assertSame(
            'A-10',
            $svc->formatFromSequence([
                'mode' => 'PREFIX_NUMERIC',
                'prefix' => 'A',
                'suffix' => '',
                'padding' => 2,
            ], 10)
        );

        self::assertSame(
            'ALPHA-001',
            $svc->formatFromSequence([
                'mode' => 'CUSTOM_PATTERN',
                'prefix' => 'ALPHA',
                'suffix' => '',
                'pattern' => '{PREFIX}-{NUMBER:03}',
                'padding' => 2,
            ], 1)
        );

        self::assertSame(
            '12',
            $svc->formatFromSequence([
                'mode' => 'NUMERIC',
                'prefix' => '',
                'suffix' => '',
                'padding' => 2,
            ], 12)
        );
    }

    public function testAssetsWireMigrationRoutesPermissionsAndCron(): void
    {
        $migration = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/personnel_progression_engine_migration.php');
        self::assertStringContainsString('organization_callsign_sequences', $migration);
        self::assertStringContainsString('personnel_callsign_history', $migration);
        self::assertStringContainsString('personnel_progression_tracks', $migration);
        self::assertStringContainsString('personnel_career_events', $migration);

        $run = (string) file_get_contents(dirname(__DIR__, 2) . '/run-migrations.php');
        self::assertStringContainsString('personnel_progression_engine_migration.php', $run);
        self::assertStringContainsString('run_personnel_progression_engine_migration', $run);

        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertStringContainsString('organisation/indicatifs', $routes);
        self::assertStringContainsString('organisation/progression', $routes);

        $catalog = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Authorization/TenantPermissionCatalog.php');
        self::assertStringContainsString('personnel.callsign.manage', $catalog);
        self::assertStringContainsString('personnel.progression.configure', $catalog);

        $container = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Container.php');
        self::assertStringContainsString('CallsignSequenceService::class', $container);
        self::assertStringContainsString('PersonnelProgressionCronJob::class', $container);

        $schedule = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Cron/CronSchedule.php');
        self::assertStringContainsString('personnel_progression_evaluate', $schedule);

        $audit = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/technique/personnel-progression-engine-audit.md');
        self::assertStringContainsString('MatriculeService', $audit);
        self::assertStringContainsString('REQUIRED_TRAINING', $audit);
    }

    private function service(): CallsignSequenceService
    {
        return new CallsignSequenceService(
            $this->createMock(CallsignSequenceRepository::class),
            $this->createMock(UserRepository::class),
            $this->createMock(PersonnelProfileRepository::class),
            $this->createMock(PersonnelCareerEventRepository::class),
        );
    }
}
