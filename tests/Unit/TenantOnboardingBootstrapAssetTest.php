<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TenantOnboardingBootstrapAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testBootstrapAppliesRecentConfigurationsBeforeMarkSatisfied(): void
    {
        $boot = (string) file_get_contents($this->root() . '/app/Services/Community/TenantBootstrapService.php');
        self::assertStringContainsString('GameOverwatchExperienceService', $boot);
        self::assertStringContainsString('organization_catalog', $boot);
        self::assertStringContainsString('applyWizardCommandChain', $boot);
        self::assertStringContainsString('applyWizardOrgFounding', $boot);
        self::assertStringContainsString('syncOrgFoundingForAllActiveMembers', $boot);
        self::assertStringContainsString("markCompleted(\$tenantId, 'OPERATIONS_WORKSPACE_V1'", $boot);
        self::assertStringContainsString('ensureDefaultRecruitTemplate', $boot);
        self::assertStringNotContainsString("markCompleted(\$tenantId, 'JOB_CATALOGS_FR_US_V1'", $boot);

        $dutyPos = strpos($boot, 'applyActiveDuty($tenantId, $newUserId');
        $satPos = strpos($boot, 'markSatisfiedForNewTenant');
        $owPos = strpos($boot, 'GameOverwatchExperienceService');
        self::assertNotFalse($dutyPos);
        self::assertNotFalse($satPos);
        self::assertNotFalse($owPos);
        self::assertLessThan($satPos, $dutyPos);
        self::assertLessThan($satPos, $owPos);
    }

    public function testWizardSurfacesNewChoicesWithoutJargon(): void
    {
        $wizard = (string) file_get_contents($this->root() . '/views/community/create.php');
        self::assertStringContainsString('wizard_founder_commands_root', $wizard);
        self::assertStringContainsString('wizard_org_founding_choice', $wizard);
        self::assertStringContainsString('Dès la création', $wizard);
        self::assertStringContainsString('Le parcours d’arrivée des nouveaux membres est prêt', $wizard);
        $body = strtolower($wizard);
        self::assertStringNotContainsString('endpoint', $body);
        self::assertStringNotContainsString('payload', $body);
        self::assertStringNotContainsString('marksatisfied', $body);
    }
}
