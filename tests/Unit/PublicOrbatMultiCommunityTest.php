<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PublicOrbatMultiCommunityTest extends TestCase
{
    public function testPublicOrbatCountsUseAllAssignmentSourcesAndDistinctMembers(): void
    {
        $repository = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UnitRepository.php');

        self::assertStringContainsString(
            'return $this->countDistinctMembersByUnitForTenant($tenantId);',
            $repository
        );
        self::assertStringContainsString("implode(' UNION ALL ', \$subqueries)", $repository);
        self::assertStringContainsString('COUNT(DISTINCT user_id)', $repository);
    }

    public function testPublicOrbatScopesAssignmentsByUnitTenantAndCommunityMembership(): void
    {
        $repository = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UnitRepository.php');

        self::assertStringContainsString('function activeTenantMemberPredicate', $repository);
        self::assertStringContainsString('FROM user_community_memberships unit_membership', $repository);
        self::assertStringContainsString('INNER JOIN units member_unit ON member_unit.id = uu.unit_id', $repository);
        self::assertStringContainsString('INNER JOIN units member_unit ON member_unit.id = pa.unit_id', $repository);
        self::assertStringContainsString('INNER JOIN units member_unit ON member_unit.id = pp.primary_unit_id', $repository);
        self::assertStringNotContainsString('u.id = uu.user_id AND u.tenant_id = ?', $repository);
    }
}
