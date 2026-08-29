<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelCapabilityAxes;
use App\Services\Personnel\QualificationCurrencyService;
use App\Repositories\PersonnelCareerEventRepository;
use App\Repositories\PersonnelQualificationRepository;
use PHPUnit\Framework\TestCase;

final class PersonnelCapabilityAxesTest extends TestCase
{
    public function testFourAxesRemainSeparatedInSnapshot(): void
    {
        $axes = new PersonnelCapabilityAxes(
            1,
            42,
            ['name' => 'Opérateur confirmé', 'nato_code' => 'OR-5'],
            [
                'primary_role' => 'Rifleman',
                'temporary_assignment' => [
                    'type' => 'ACTING',
                    'title' => 'Team Leader',
                    'does_not_change_grade' => true,
                ],
            ],
            [
                [
                    'name' => 'Medic',
                    'admin_valid' => true,
                    'currency_status' => 'NON_CURRENT',
                    'is_current' => false,
                ],
            ],
            [
                'availability' => 'AVAILABLE',
                'deployable' => 0,
                'readiness_percent' => 82,
                'blocking_codes' => ['MISSING_CURRENCY:Medic'],
            ],
        );

        $arr = $axes->toArray();
        self::assertSame('Opérateur confirmé', $arr['axes'][PersonnelCapabilityAxes::AXIS_GRADE]['name']);
        self::assertSame('Team Leader', $arr['axes'][PersonnelCapabilityAxes::AXIS_FUNCTION]['temporary_assignment']['title']);
        self::assertFalse($arr['axes'][PersonnelCapabilityAxes::AXIS_QUALIFICATION][0]['is_current']);
        self::assertSame(82, $axes->readinessPercent());
        self::assertFalse($axes->isDeployable());
        self::assertContains('GRADE_IS_NOT_FUNCTION', $axes->invariants());
        self::assertContains('QUALIFICATION_VALID_IS_NOT_CURRENCY', $axes->invariants());
        self::assertSame(['MISSING_CURRENCY:Medic'], $axes->blockingCodes());
    }

    public function testAdministrativelyValidDoesNotImplyCurrent(): void
    {
        $svc = new QualificationCurrencyService(
            $this->createMock(PersonnelQualificationRepository::class),
            $this->createMock(PersonnelCareerEventRepository::class),
        );

        $row = [
            'status' => 'active',
            'expires_at' => '2027-12-31',
            'currency_status' => 'NON_CURRENT',
            'last_practiced_at' => '2026-01-01 00:00:00',
        ];

        self::assertTrue($svc->isAdministrativelyValid($row));
        self::assertFalse($svc->isCurrent($row));
    }

    public function testUnknownCurrencyWithoutRuleTreatedAsCurrent(): void
    {
        $svc = new QualificationCurrencyService(
            $this->createMock(PersonnelQualificationRepository::class),
            $this->createMock(PersonnelCareerEventRepository::class),
        );

        $row = [
            'status' => 'valid',
            'expires_at' => null,
            'currency_status' => 'UNKNOWN',
        ];

        self::assertTrue($svc->isAdministrativelyValid($row));
        self::assertTrue($svc->isCurrent($row));
    }

    public function testExpiredAdminStatusBlocksCurrent(): void
    {
        $svc = new QualificationCurrencyService(
            $this->createMock(PersonnelQualificationRepository::class),
            $this->createMock(PersonnelCareerEventRepository::class),
        );

        self::assertFalse($svc->isAdministrativelyValid(['status' => 'expired', 'currency_status' => 'CURRENT']));
        self::assertFalse($svc->isCurrent(['status' => 'revoked', 'currency_status' => 'CURRENT']));
    }

    public function testLot2AssetsWired(): void
    {
        $migration = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/personnel_capability_axes_migration.php');
        self::assertStringContainsString('personnel_qualification_definitions', $migration);
        self::assertStringContainsString('currency_days', $migration);
        self::assertStringContainsString('currency_status', $migration);
        self::assertStringContainsString('orbat_billets', $migration);
        self::assertStringContainsString('personnel_progression_waivers', $migration);
        self::assertStringContainsString('personnel_progression_boards', $migration);
        self::assertStringContainsString('personnel_temporary_assignments', $migration);
        self::assertStringContainsString('personnel_operational_capability', $migration);
        self::assertStringContainsString('level_rank', $migration);

        $run = (string) file_get_contents(dirname(__DIR__, 2) . '/run-migrations.php');
        self::assertStringContainsString('personnel_capability_axes_migration.php', $run);
        self::assertStringContainsString('run_personnel_capability_axes_migration', $run);

        $container = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Container.php');
        self::assertStringContainsString('QualificationCurrencyService::class', $container);
        self::assertStringContainsString('OperationalCapabilityService::class', $container);
        self::assertStringContainsString('PersonnelCapabilityCronJob::class', $container);

        $schedule = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Cron/CronSchedule.php');
        self::assertStringContainsString('personnel_capability_refresh', $schedule);

        $hub = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/progression_hub.php');
        self::assertStringContainsString('Quatre axes séparés', $hub);
        self::assertStringNotContainsString('legacy placeholders', $hub);

        $audit = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/technique/personnel-progression-engine-audit.md');
        self::assertStringContainsString('quatre axes', $audit);
        self::assertStringContainsString('NON_CURRENT', $audit);
    }
}
