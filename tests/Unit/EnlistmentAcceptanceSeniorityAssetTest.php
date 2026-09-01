<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EnlistmentAcceptanceSeniorityAssetTest extends TestCase
{
    public function testAcceptanceProvisioningWiresSeniorityBootstrapAndInference(): void
    {
        $service = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Recruitment/EnlistmentAcceptanceProvisioningService.php'
        );
        $container = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Container.php');
        $inference = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Personnel/SeniorityDossierInferenceSyncService.php'
        );

        self::assertStringContainsString('SeniorityEnrollmentBootstrapService', $service);
        self::assertStringContainsString('SeniorityDossierInferenceSyncService', $service);
        self::assertStringContainsString('provisionSeniorityAfterAcceptance', $service);
        self::assertStringContainsString('ensureEnlistmentDateOnProfile', $service);
        self::assertStringContainsString('syncTenureCommunityFromEnrollment', $service);
        self::assertStringContainsString('applyStoredOrgFoundingToUser', $service);
        self::assertStringContainsString('syncForUser', $service);
        self::assertStringContainsString('seedMissingPackPeriodsAfterAcceptance', $service);
        self::assertStringContainsString('MatriculeService', $service);
        self::assertStringContainsString('assignNextForUser', $service);
        self::assertStringContainsString("ppPatch['primary_unit_id']", $service);
        self::assertStringContainsString('readiness_score', $service);
        self::assertStringContainsString("ppPatch['operator_status']", $service);
        self::assertStringContainsString('RecruitmentOpeningRepository', $service);
        self::assertStringContainsString('SeniorityEnrollmentBootstrapService::class', $container);
        self::assertStringContainsString('SeniorityDossierInferenceSyncService::class', $container);
        self::assertStringContainsString('MatriculeService::class', $container);
        self::assertStringContainsString('tenure_garrison', $inference);
        self::assertStringContainsString('tenure_operational_commitment', $inference);
        self::assertStringContainsString('tenure_staff_assignment', $inference);
        self::assertStringContainsString('tenure_reserve_status', $inference);
        self::assertStringContainsString('ACCEPTANCE_SEED_CODES', $inference);
        self::assertStringContainsString('acceptanceSeedMarker', $inference);
        self::assertStringContainsString('seedMissingPackPeriodsAfterAcceptance', $inference);
        self::assertStringContainsString('tenure_field_deployment', $inference);
        self::assertStringContainsString('listStandardPackCodes', (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Personnel/SeniorityTenantDefaultsService.php'
        ));
    }
}
