<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Organization\PositionCapabilityCatalog;
use App\Support\AthenaTechnicalRoles;
use App\Support\OrgDomainModel;
use PHPUnit\Framework\TestCase;

final class OrgDutyWorkbenchArchitectureTest extends TestCase
{
    public function testMigrationDefinesWorkbenchTables(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/org_duty_workbench_migration.php');
        self::assertStringContainsString('work_tasks', $src);
        self::assertStringContainsString('mission_duty_assignments', $src);
        self::assertStringContainsString('duty_roster_slots', $src);
        self::assertStringContainsString('capability_template', $src);
        self::assertStringContainsString('assigned_position_slug', $src);
    }

    public function testRunMigrationsRegistersWorkbench(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/run-migrations.php');
        self::assertStringContainsString('org_duty_workbench_migration.php', $src);
        self::assertStringContainsString('run_org_duty_workbench_migration($pdo)', $src);
    }

    public function testTechnicalRolesAreFourAndSeparateFromPosts(): void
    {
        $catalog = AthenaTechnicalRoles::catalog();
        self::assertCount(4, $catalog);
        self::assertSame(AthenaTechnicalRoles::MEMBER, AthenaTechnicalRoles::fromLegacySlug('member'));
        self::assertSame(AthenaTechnicalRoles::OWNER, AthenaTechnicalRoles::fromLegacySlug('community_owner'));
        self::assertSame(AthenaTechnicalRoles::ADMIN, AthenaTechnicalRoles::fromLegacySlug('tenant_admin'));
        self::assertSame('Membre', AthenaTechnicalRoles::label(AthenaTechnicalRoles::MEMBER));
    }

    public function testCapabilityCatalogCoversStaffAndTacticalPosts(): void
    {
        self::assertNotNull(PositionCapabilityCatalog::find(PositionCapabilityCatalog::S2));
        self::assertNotNull(PositionCapabilityCatalog::find(PositionCapabilityCatalog::S3));
        self::assertNotNull(PositionCapabilityCatalog::find(PositionCapabilityCatalog::S4));
        self::assertNotNull(PositionCapabilityCatalog::find(PositionCapabilityCatalog::GROUP_LEADER));
        self::assertNotNull(PositionCapabilityCatalog::find(PositionCapabilityCatalog::MEDIC));
        $hq = PositionCapabilityCatalog::headquartersBillets();
        self::assertGreaterThanOrEqual(8, count($hq));
        $group = PositionCapabilityCatalog::combatGroupBillets('N-10');
        self::assertGreaterThanOrEqual(6, count($group));
        self::assertStringContainsString('S2', implode(' ', array_column($hq, 'code')));
    }

    public function testOrgDomainModelSeparatesDutyAndWorkflow(): void
    {
        self::assertContains(OrgDomainModel::DUTY_MISSION, OrgDomainModel::ALL);
        self::assertContains(OrgDomainModel::WORKFLOW, OrgDomainModel::ALL);
        self::assertSame('Duty mission', OrgDomainModel::label(OrgDomainModel::DUTY_MISSION));
        self::assertStringContainsString('temporaire', OrgDomainModel::description(OrgDomainModel::DUTY_MISSION));
    }

    public function testMonServiceRoutesExist(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertStringContainsString("/mon-service'", $routes);
        self::assertStringContainsString('acknowledgeTask', $routes);
        self::assertStringContainsString('completeTask', $routes);
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/ActionCenterController.php');
        self::assertStringContainsString('MemberServiceContextService', $controller);
        self::assertStringContainsString('WorkTaskService', $controller);
    }

    public function testArchitectureDocExists(): void
    {
        $path = dirname(__DIR__, 2) . '/docs/architecture/2026-10-01-org-duty-workbench.md';
        self::assertFileExists($path);
        $doc = (string) file_get_contents($path);
        self::assertStringContainsString('OWNER', $doc);
        self::assertStringContainsString('work_tasks', $doc);
        self::assertStringContainsString('mission_duty_assignments', $doc);
    }
}
