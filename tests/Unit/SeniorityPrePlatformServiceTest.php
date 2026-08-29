<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SeniorityRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\SeniorityPrePlatformService;
use App\Services\Personnel\SeniorityTenantDefaultsService;
use PHPUnit\Framework\TestCase;

final class SeniorityPrePlatformServiceTest extends TestCase
{
    public function testPackExposesPrePlatformCodesAsManualOnly(): void
    {
        $codes = SeniorityTenantDefaultsService::listStandardPackCodes();
        self::assertContains(SeniorityPrePlatformService::CODE_ORG, $codes);
        self::assertContains(SeniorityPrePlatformService::CODE_PERSON, $codes);
        self::assertSame(
            [SeniorityPrePlatformService::CODE_ORG, SeniorityPrePlatformService::CODE_PERSON],
            SeniorityTenantDefaultsService::PRE_PLATFORM_CODES
        );
    }

    public function testUpsertPersonInsertsMarkedPeriod(): void
    {
        $seniority = $this->createMock(SeniorityRepository::class);
        $seniority->method('schemaReady')->willReturn(true);
        $seniority->method('findDefinitionIdByTenantAndCode')->willReturn(42);
        $seniority->method('findPeriodIdByRelatedType')->willReturn(null);
        $seniority->method('listPeriodsForUserAndDefinition')->willReturn([]);
        $seniority->expects(self::once())->method('insertPeriod')->with(
            1,
            7,
            42,
            '2019-06-01',
            SeniorityPrePlatformService::PERSON_MARKER,
            null,
            'active',
            self::callback(static fn (?string $json): bool => is_string($json) && str_contains($json, 'personnel_edit'))
        )->willReturn(99);

        $users = $this->createMock(UserRepository::class);
        $svc = new SeniorityPrePlatformService(
            $seniority,
            new SeniorityTenantDefaultsService($seniority),
            $users
        );

        self::assertSame('inserted', $svc->upsertPersonStartDate(1, 7, '2019-06-01'));
    }

    public function testUpsertPersonClearsWhenEmpty(): void
    {
        $seniority = $this->createMock(SeniorityRepository::class);
        $seniority->method('schemaReady')->willReturn(true);
        $seniority->method('findDefinitionIdByTenantAndCode')->willReturn(42);
        $seniority->method('findPeriodIdByRelatedType')->willReturn(11);
        $seniority->expects(self::once())->method('deletePeriodById')->with(11, 1, 7)->willReturn(true);
        $seniority->expects(self::never())->method('insertPeriod');

        $svc = new SeniorityPrePlatformService(
            $seniority,
            new SeniorityTenantDefaultsService($seniority),
            $this->createMock(UserRepository::class)
        );

        self::assertSame('cleared', $svc->upsertPersonStartDate(1, 7, null));
    }

    public function testSyncOrgFoundingPropagatesToActiveMembers(): void
    {
        $seniority = $this->createMock(SeniorityRepository::class);
        $seniority->method('schemaReady')->willReturn(true);
        $seniority->method('findDefinitionIdByTenantAndCode')->willReturn(5);
        $seniority->method('findPeriodIdByRelatedType')->willReturn(null);
        $seniority->method('listPeriodsForUserAndDefinition')->willReturn([]);
        $seniority->expects(self::exactly(2))->method('insertPeriod')->willReturn(1);

        $users = $this->createMock(UserRepository::class);
        $users->method('listActiveUserIdsForTenant')->willReturn([10, 20]);

        $svc = new SeniorityPrePlatformService(
            $seniority,
            new SeniorityTenantDefaultsService($seniority),
            $users
        );

        $stats = $svc->syncOrgFoundingForAllActiveMembers(3, '2018-01-15');
        self::assertSame(2, $stats['members']);
        self::assertSame(2, $stats['inserted']);
        self::assertSame(0, $stats['invalid_date']);
    }

    public function testBoSeniorityViewMentionsPrePlatformAlertAndOrgForm(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/seniority.php');
        self::assertStringContainsString('Ancienneté avant la plateforme', $view);
        self::assertStringContainsString('bo_dsfr_notice.php', $view);
        self::assertStringContainsString('date-creation-entite', $view);
        self::assertStringContainsString('org_founded_on', $view);
        self::assertStringContainsString('Création de l’entité', $view);

        $edit = (string) file_get_contents(dirname(__DIR__, 2) . '/views/personnel/edit.php');
        self::assertStringContainsString('pre_platform_start_date', $edit);
        self::assertStringContainsString('Ancienneté antérieure à la plateforme', $edit);

        $defaults = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Personnel/SeniorityTenantDefaultsService.php'
        );
        self::assertStringContainsString('tenure_pre_platform', $defaults);
        self::assertStringContainsString('tenure_org_pre_platform', $defaults);

        $inference = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Personnel/SeniorityDossierInferenceSyncService.php'
        );
        self::assertStringContainsString('isPrePlatformManualCode', $inference);
    }
}
