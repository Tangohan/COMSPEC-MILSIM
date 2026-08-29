<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AuditLogRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelOrgHistoryRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\RoleAssignmentLogRepository;
use App\Repositories\SeniorityRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\SeniorityDossierInferenceSyncService;
use App\Services\Personnel\SeniorityTenantDefaultsService;
use PHPUnit\Framework\TestCase;

final class SeniorityDossierInferenceSyncServiceTest extends TestCase
{
    public function testInferenceMarkerFormat(): void
    {
        self::assertSame(
            'system:dossier_inference:tenure_unit_primary',
            SeniorityDossierInferenceSyncService::inferenceMarker('tenure_unit_primary')
        );
    }

    public function testInferenceCodesCoverStandardIndicators(): void
    {
        self::assertSame(
            [
                'tenure_service',
                'tenure_unit_primary',
                'tenure_group_attachment',
                'tenure_role_community',
                'tenure_rank_current',
                'tenure_training_track',
                'tenure_qualification_hold',
                'tenure_garrison',
                'tenure_operational_commitment',
                'tenure_staff_assignment',
                'tenure_reserve_status',
            ],
            SeniorityDossierInferenceSyncService::INFERENCE_CODES
        );
    }

    public function testSyncForUserIncrementsSkippedManualWhenBlockingPeriodExists(): void
    {
        $seniority = $this->createMock(SeniorityRepository::class);
        $seniority->method('schemaReady')->willReturn(true);
        $seniority->method('findDefinitionIdByTenantAndCode')->willReturn(100);
        $seniority->method('userHasBlockingPeriodOutsideInferenceMarker')->willReturn(true);

        $svc = new SeniorityDossierInferenceSyncService(
            $seniority,
            new SeniorityTenantDefaultsService($seniority),
            $this->createMock(PersonnelAssignmentRepository::class),
            $this->createMock(RoleAssignmentLogRepository::class),
            $this->createMock(PersonnelOrgHistoryRepository::class),
            $this->createMock(AuditLogRepository::class),
            $this->createMock(UserRepository::class),
            $this->createMock(PersonnelQualificationRepository::class),
            $this->createMock(TrainingCertificateRepository::class),
        );

        $stats = $svc->syncForUser(1, 2, true);
        self::assertSame(count(SeniorityDossierInferenceSyncService::INFERENCE_CODES), $stats['skipped_manual']);
        self::assertSame(0, $stats['inserted']);
    }

    public function testSyncForUserInsertsWhenNoBlockingAndNoExistingInferenceRow(): void
    {
        $seniority = $this->createMock(SeniorityRepository::class);
        $seniority->method('schemaReady')->willReturn(true);
        $seniority->method('findDefinitionIdByTenantAndCode')->willReturn(100);
        $seniority->method('userHasBlockingPeriodOutsideInferenceMarker')->willReturn(false);
        $seniority->method('findPeriodIdByRelatedType')->willReturn(null);
        $seniority->expects(self::exactly(count(SeniorityDossierInferenceSyncService::INFERENCE_CODES)))->method('insertPeriod')->willReturn(1);

        $pa = $this->createMock(PersonnelAssignmentRepository::class);
        $pa->method('inferCurrentAttachmentStartYmd')->willReturnCallback(
            static fn (int $tenantId, int $userId, bool $group): ?string => $group ? '2024-02-10' : '2024-01-05'
        );

        $ral = $this->createMock(RoleAssignmentLogRepository::class);
        $ral->method('isTableReady')->willReturn(true);
        $ral->method('earliestAssignDateYmdForRoles')->willReturn('2023-07-01');

        $poh = $this->createMock(PersonnelOrgHistoryRepository::class);
        $poh->method('schemaReady')->willReturn(true);
        $poh->method('latestGradeChangeDateYmd')->willReturn('2022-11-20');

        $audit = $this->createMock(AuditLogRepository::class);

        $users = $this->createMock(UserRepository::class);
        $users->method('listOrganizationRoleIdsForUser')->willReturn([9]);
        $users->method('findById')->willReturn(['created_at' => '2021-01-01 00:00:00']);
        $users->method('getRoleSlugForUser')->willReturn('tenant_admin');

        $quals = $this->createMock(PersonnelQualificationRepository::class);
        $quals->method('listForUser')->willReturn([
            ['obtained_at' => '2020-06-15', 'status' => 'active'],
        ]);

        $certs = $this->createMock(TrainingCertificateRepository::class);
        $certs->method('listByUserId')->willReturn([
            ['issued_at' => '2020-08-01'],
        ]);

        $profiles = $this->createMock(\App\Repositories\PersonnelProfileRepository::class);
        $profiles->method('getByUserId')->willReturn([
            'enlistment_date' => '2021-01-01',
            'operator_status' => 'réserve',
        ]);

        $svc = new SeniorityDossierInferenceSyncService(
            $seniority,
            new SeniorityTenantDefaultsService($seniority),
            $pa,
            $ral,
            $poh,
            $audit,
            $users,
            $quals,
            $certs,
            $profiles,
        );

        $stats = $svc->syncForUser(1, 2, true);
        self::assertSame(count(SeniorityDossierInferenceSyncService::INFERENCE_CODES), $stats['inserted']);
        self::assertSame(0, $stats['skipped_manual']);
    }

    public function testSyncForUserDeletesInferenceRowWhenResolvedDateMissing(): void
    {
        $seniority = $this->createMock(SeniorityRepository::class);
        $seniority->method('schemaReady')->willReturn(true);
        $seniority->method('findDefinitionIdByTenantAndCode')->willReturnCallback(
            static fn (int $tenantId, string $code): ?int => $code === 'tenure_unit_primary' ? 10 : null
        );
        $seniority->method('userHasBlockingPeriodOutsideInferenceMarker')->willReturn(false);
        $seniority->method('findPeriodIdByRelatedType')->willReturnCallback(
            static function (int $tenantId, int $userId, int $definitionId, string $marker): ?int {
                if (str_contains($marker, 'tenure_unit_primary')) {
                    return 77;
                }

                return null;
            }
        );
        $seniority->expects(self::once())->method('deletePeriodById')->with(77, 1, 2)->willReturn(true);

        $pa = $this->createMock(PersonnelAssignmentRepository::class);
        $pa->method('inferCurrentAttachmentStartYmd')->willReturnCallback(
            static fn (int $tenantId, int $userId, bool $group): ?string => $group ? null : null
        );

        $svc = new SeniorityDossierInferenceSyncService(
            $seniority,
            new SeniorityTenantDefaultsService($seniority),
            $pa,
            $this->createMock(RoleAssignmentLogRepository::class),
            $this->createMock(PersonnelOrgHistoryRepository::class),
            $this->createMock(AuditLogRepository::class),
            $this->createMock(UserRepository::class),
            $this->createMock(PersonnelQualificationRepository::class),
            $this->createMock(TrainingCertificateRepository::class),
        );

        $stats = $svc->syncForUser(1, 2, true);
        self::assertSame(1, $stats['cleared']);
        self::assertSame(count(SeniorityDossierInferenceSyncService::INFERENCE_CODES) - 1, $stats['skipped_no_definition']);
    }

    public function testAcceptanceSeedMarkerFormat(): void
    {
        self::assertSame(
            'system:acceptance_seed:tenure_field_deployment',
            SeniorityDossierInferenceSyncService::acceptanceSeedMarker('tenure_field_deployment')
        );
    }

    public function testAcceptanceSeedCodesCoverEpisodicIndicators(): void
    {
        self::assertSame(
            [
                'tenure_field_deployment',
                'tenure_instructor_capacity',
                'tenure_campaign_participation',
                'tenure_joint_interop',
                'tenure_custom_engagement',
                'tenure_tenant_wide_recognition',
            ],
            SeniorityDossierInferenceSyncService::ACCEPTANCE_SEED_CODES
        );
        $pack = SeniorityTenantDefaultsService::listStandardPackCodes();
        foreach (SeniorityDossierInferenceSyncService::ACCEPTANCE_SEED_CODES as $code) {
            self::assertContains($code, $pack);
        }
        foreach (SeniorityDossierInferenceSyncService::INFERENCE_CODES as $code) {
            self::assertContains($code, $pack);
        }
        self::assertContains('tenure_community', $pack);
    }

    public function testSeedMissingPackPeriodsInsertsOnlyWhenEmpty(): void
    {
        $seniority = $this->createMock(SeniorityRepository::class);
        $seniority->method('schemaReady')->willReturn(true);
        $seniority->method('findDefinitionIdByTenantAndCode')->willReturn(50);
        $seniority->method('listPeriodsForUserAndDefinition')->willReturnCallback(
            static function (int $userId, int $definitionId): array {
                /* Première définition déjà pourvue (communauté bootstrap). */
                static $calls = 0;
                ++$calls;

                return $calls === 1 ? [['id' => 1]] : [];
            }
        );
        $expectedInserts = count(SeniorityTenantDefaultsService::listStandardPackCodes()) - 1;
        $seniority->expects(self::exactly($expectedInserts))->method('insertPeriod')->willReturn(99);

        $profiles = $this->createMock(\App\Repositories\PersonnelProfileRepository::class);
        $profiles->method('getByUserId')->willReturn(['enlistment_date' => '2024-03-15']);

        $svc = new SeniorityDossierInferenceSyncService(
            $seniority,
            new SeniorityTenantDefaultsService($seniority),
            $this->createMock(PersonnelAssignmentRepository::class),
            $this->createMock(RoleAssignmentLogRepository::class),
            $this->createMock(PersonnelOrgHistoryRepository::class),
            $this->createMock(AuditLogRepository::class),
            $this->createMock(UserRepository::class),
            $this->createMock(PersonnelQualificationRepository::class),
            $this->createMock(TrainingCertificateRepository::class),
            $profiles,
        );

        $stats = $svc->seedMissingPackPeriodsAfterAcceptance(1, 2, true);
        self::assertSame($expectedInserts, $stats['inserted']);
        self::assertSame(1, $stats['skipped_existing']);
        self::assertSame(0, $stats['insert_failed']);
    }

    public function testSyncForUserFallsBackToEnlistmentWhenSpecificSignalMissing(): void
    {
        $seniority = $this->createMock(SeniorityRepository::class);
        $seniority->method('schemaReady')->willReturn(true);
        $seniority->method('findDefinitionIdByTenantAndCode')->willReturn(100);
        $seniority->method('userHasBlockingPeriodOutsideInferenceMarker')->willReturn(false);
        $seniority->method('findPeriodIdByRelatedType')->willReturn(null);
        $seniority->expects(self::exactly(count(SeniorityDossierInferenceSyncService::INFERENCE_CODES)))
            ->method('insertPeriod')
            ->willReturn(1);

        $pa = $this->createMock(PersonnelAssignmentRepository::class);
        $pa->method('inferCurrentAttachmentStartYmd')->willReturn(null);

        $ral = $this->createMock(RoleAssignmentLogRepository::class);
        $ral->method('isTableReady')->willReturn(false);

        $poh = $this->createMock(PersonnelOrgHistoryRepository::class);
        $poh->method('schemaReady')->willReturn(false);

        $users = $this->createMock(UserRepository::class);
        $users->method('listOrganizationRoleIdsForUser')->willReturn([]);
        $users->method('findById')->willReturn(['created_at' => '2020-01-01 00:00:00']);
        $users->method('getRoleSlugForUser')->willReturn('member');

        $quals = $this->createMock(PersonnelQualificationRepository::class);
        $quals->method('listForUser')->willReturn([]);

        $certs = $this->createMock(TrainingCertificateRepository::class);
        $certs->method('listByUserId')->willReturn([]);

        $profiles = $this->createMock(\App\Repositories\PersonnelProfileRepository::class);
        $profiles->method('getByUserId')->willReturn(['enlistment_date' => '2023-05-01']);

        $audit = $this->createMock(AuditLogRepository::class);
        $audit->method('earliestRoleAssignedDateYmdForTargetUser')->willReturn(null);

        $svc = new SeniorityDossierInferenceSyncService(
            $seniority,
            new SeniorityTenantDefaultsService($seniority),
            $pa,
            $ral,
            $poh,
            $audit,
            $users,
            $quals,
            $certs,
            $profiles,
        );

        $stats = $svc->syncForUser(1, 2, true);
        self::assertSame(count(SeniorityDossierInferenceSyncService::INFERENCE_CODES), $stats['inserted']);
    }
}
