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

        self::assertStringContainsString('SeniorityEnrollmentBootstrapService', $service);
        self::assertStringContainsString('SeniorityDossierInferenceSyncService', $service);
        self::assertStringContainsString('provisionSeniorityAfterAcceptance', $service);
        self::assertStringContainsString('ensureEnlistmentDateOnProfile', $service);
        self::assertStringContainsString('syncTenureCommunityFromEnrollment', $service);
        self::assertStringContainsString('syncForUser', $service);
        self::assertStringContainsString('SeniorityEnrollmentBootstrapService::class', $container);
        self::assertStringContainsString('SeniorityDossierInferenceSyncService::class', $container);
    }
}
