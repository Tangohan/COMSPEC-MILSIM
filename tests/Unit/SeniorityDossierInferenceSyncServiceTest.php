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

        $quals = $this->createMock(PersonnelQualificationRepository::class);
        $quals->method('listForUser')->willReturn([
            ['obtained_at' => '2020-06-15', 'status' => 'active'],
        ]);

        $certs = $this->createMock(TrainingCertificateRepository::class);
        $certs->method('listByUserId')->willReturn([
            ['issued_at' => '2020-08-01'],
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
}
