<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\SystemReservedPermissions;
use App\Authorization\TenantPermissionCatalog;
use App\Services\Rbac\AccessRoleRules;
use PHPUnit\Framework\TestCase;

final class AccessRoleRulesTest extends TestCase
{
    /** @return array{0: array<int, list<string>>, 1: array<int, list<int>>} */
    private function community(): array
    {
        $perms = [
            1 => ['documents.view'],
            2 => ['documents.view', 'admin.roles.manage'],
            3 => ['training.view', 'training.create'],
        ];
        $members = [1 => [10, 11, 12], 2 => [10], 3 => [11, 12]];

        return [$perms, $members];
    }

    public function testImpactCountsOnlyRealGainsAndLosses(): void
    {
        [$perms, $members] = $this->community();
        $members[2] = [10, 11];
        $impact = AccessRoleRules::impact(3, ['training.view', 'admin.roles.manage'], $perms, $members);
        self::assertSame(2, $impact['members']);
        self::assertSame([12], $impact['gain']['admin.roles.manage'], '11 l’a déjà par Gestionnaire');
        self::assertSame([11, 12], $impact['lose']['training.create']);
    }

    public function testImpactIgnoresRightsHeldByAnotherLevel(): void
    {
        [$perms, $members] = $this->community();
        $impact = AccessRoleRules::impact(3, ['training.view', 'training.create', 'documents.view'], $perms, $members);
        self::assertSame([], $impact['gain'], 'documents.view est déjà donné par Membre');
        self::assertSame([], $impact['lose']);
    }

    public function testCompareSplitsLevels(): void
    {
        $diff = AccessRoleRules::compare(['a.view', 'b.view'], ['b.view', 'c.view']);
        self::assertSame(['a.view'], $diff['only_a']);
        self::assertSame(['c.view'], $diff['only_b']);
        self::assertSame(['b.view'], $diff['common']);
    }

    public function testEscalationBlocksRightsTheActorDoesNotHold(): void
    {
        $held = ['documents.view', 'training.view'];
        $holds = static fn (string $s): bool => in_array($s, $held, true);
        self::assertSame(['finances.export'], AccessRoleRules::escalation(['documents.view'], ['documents.view', 'training.view', 'finances.export'], $holds));
        self::assertSame([], AccessRoleRules::escalation(['finances.export'], ['finances.export'], $holds), 'un droit déjà présent n’est pas une escalade');
    }

    public function testLastAdministratorIsProtected(): void
    {
        $perms = [1 => ['documents.view'], 2 => ['admin.roles.manage'], 3 => ['admin.roles.manage']];
        $members = [1 => [10, 11], 2 => [10], 3 => [11]];
        self::assertFalse(AccessRoleRules::removesLastAdmin($perms, $members, ['type' => 'delete', 'role_id' => 3]), 'un autre administrateur reste');
        $members[3] = [];
        self::assertTrue(AccessRoleRules::removesLastAdmin($perms, $members, ['type' => 'delete', 'role_id' => 2]));
        self::assertTrue(AccessRoleRules::removesLastAdmin($perms, $members, ['type' => 'save', 'role_id' => 2, 'slugs' => ['documents.view']]));
        self::assertFalse(AccessRoleRules::removesLastAdmin($perms, $members, ['type' => 'save', 'role_id' => 2, 'slugs' => ['admin.roles.manage', 'documents.view']]));
    }

    public function testNoAdminAtAllIsNotABlockingState(): void
    {
        $perms = [1 => ['documents.view'], 3 => ['training.view']];
        $members = [1 => [10], 3 => [10]];
        self::assertFalse(AccessRoleRules::removesLastAdmin($perms, $members, ['type' => 'delete', 'role_id' => 3]));
    }

    public function testMissingRequirementsAreReported(): void
    {
        self::assertSame(['atak.sse.grant' => ['atak.sse.access']], AccessRoleRules::missingRequirements(['atak.sse.grant']));
        self::assertSame([], AccessRoleRules::missingRequirements(['atak.sse.grant', 'atak.sse.access']));
    }

    public function testTemplatesOnlyUseEditableCatalogRights(): void
    {
        $templates = AccessRoleRules::templates();
        self::assertTrue($templates !== []);
        $catalog = TenantPermissionCatalog::allSlugs();
        foreach ($templates as $tpl) {
            self::assertTrue($tpl['slugs'] !== [], $tpl['key']);
            foreach ($tpl['slugs'] as $slug) {
                self::assertTrue(in_array($slug, $catalog, true), $slug);
                self::assertFalse(SystemReservedPermissions::isReserved($slug));
            }
        }
    }

    public function testTransferKeepsOnlyHoldersOfTheSourceLevel(): void
    {
        $plan = AccessRoleRules::planTransfer([1, 2, 3], [3, 9], [2, 3, 77], true);
        self::assertSame([2], $plan['receive']);
        self::assertSame([3], $plan['already']);
        self::assertSame([77], $plan['ignored'], 'un identifiant étranger au niveau source n’est jamais appliqué');
        self::assertSame([2, 3], $plan['leave']);
        $all = AccessRoleRules::planTransfer([1, 2, 3], [3], null, false);
        self::assertSame([1, 2], $all['receive']);
        self::assertSame([], $all['leave'], 'une copie ne retire personne');
    }

    public function testMovingMembersCannotRemoveTheLastAdministrator(): void
    {
        $perms = [1 => ['documents.view'], 2 => ['admin.roles.manage'], 3 => ['documents.view']];
        $before = [1 => [10, 11], 2 => [10], 3 => []];
        self::assertTrue(AccessRoleRules::membersChangeRemovesLastAdmin($perms, $before, [1 => [10, 11], 2 => [], 3 => [10]]));
        self::assertFalse(AccessRoleRules::membersChangeRemovesLastAdmin($perms, $before, [1 => [10, 11], 2 => [10, 11], 3 => []]));
    }
}
