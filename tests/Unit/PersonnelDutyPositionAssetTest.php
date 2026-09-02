<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelDutyPositionAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testJoinHookAndCompletedIntegrationPromoteDuty(): void
    {
        $hook = (string) file_get_contents($this->root() . '/app/Services/MemberIntegration/MemberIntegrationEntryHook.php');
        self::assertStringContainsString('applyOnJoin', $hook);
        $roleChange = explode('function afterRoleOrUnitChange', $hook)[1] ?? '';
        self::assertNotSame('', $roleChange);
        self::assertStringNotContainsString('applyOnJoin', $roleChange);

        $refresh = (string) file_get_contents($this->root() . '/app/Services/MemberIntegration/MemberIntegrationService.php');
        self::assertStringContainsString('applyActiveDuty', $refresh);
        $daily = (string) file_get_contents($this->root() . '/app/Services/MemberIntegration/MemberIntegrationAutomationService.php');
        self::assertStringContainsString('backfillTenant', $daily);
        $boot = (string) file_get_contents($this->root() . '/app/Services/Community/TenantBootstrapService.php');
        self::assertStringContainsString('applyActiveDuty($tenantId, $newUserId', $boot);
    }

    public function testOrganizerSurfacesAndCatalog(): void
    {
        $index = (string) file_get_contents($this->root() . '/views/admin/organization/users/index.php');
        self::assertStringContainsString("'POSITION'", $index);
        self::assertStringContainsString('Attribuer les positions manquantes', $index);
        self::assertStringContainsString('duty-positions/backfill', $index);
        $show = (string) file_get_contents($this->root() . '/views/admin/organization/users/show.php');
        self::assertStringContainsString('Passer en service actif', $show);
        $routes = (string) file_get_contents($this->root() . '/routes/web.php');
        self::assertStringContainsString("'/back-office/users/{id}/duty-position'", $routes);
        $catalog = (string) file_get_contents($this->root() . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        self::assertStringContainsString('DUTY_POSITION_V1', $catalog);
        $seed = (string) file_get_contents($this->root() . '/bootstrap/configuration_updates_migration.php');
        self::assertStringContainsString('DUTY_POSITION_V1', $seed);
        $list = (string) file_get_contents($this->root() . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('duty_position', $list);
        self::assertStringContainsString('PersonnelDutyPositionService::SLUG_TRAINING', $list);
    }
}
