<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelDirectoryTenantScopeTest extends TestCase
{
    public function testMemberPredicateIncludesLegacyTenantFallback(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('function sqlMemberOfTenantPredicate', $src);
        self::assertStringContainsString(") OR ' . \$a . '.tenant_id = ' . \$tenantId . ')'", $src);
    }

    public function testPersonnelDirectoryUsesCommunityProfileOverlay(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('function listPersonnelDirectoryRich', $src);
        self::assertStringContainsString('user_community_profiles ucp', $src);
        self::assertStringContainsString('COALESCE(ucp.tenant_member_number, u.tenant_member_number)', $src);
        self::assertStringContainsString('personnelProfilesJoinSql', $src);
        self::assertStringContainsString('gradesJoinForTenant', $src);
    }

    public function testCommunityIdentityMigrationRepairsLeftMemberships(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/user_community_identity_migration.php');
        self::assertStringContainsString("SET m.status = 'active', m.left_at = NULL", $src);
    }

    /**
     * Régression : dans une chaîne double-quote, `$this->sqlMemberOfTenantPredicate`
     * est lu comme propriété (Undefined property), pas comme appel de méthode.
     */
    public function testMemberPredicateNeverInterpolatedAsPropertyInDoubleQuotedSql(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertStringNotContainsString(
            "WHERE ' . \$this->sqlMemberOfTenantPredicate('u', \$tenantId) . ' AND u.status = 'active'",
            $src
        );
        self::assertStringContainsString(
            'WHERE " . $this->sqlMemberOfTenantPredicate(\'u\', $tenantId) . " AND u.status = \'active\'',
            $src
        );
    }
}
