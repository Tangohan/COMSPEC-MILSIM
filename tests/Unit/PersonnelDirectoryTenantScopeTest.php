<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PersonnelDirectoryTenantScopeTest extends TestCase
{
    public function testMemberPredicateKeepsLegacyFallbackAndExcludesRemovedRosterEntries(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, email TEXT)');
        $pdo->exec('CREATE TABLE user_community_memberships (user_id INTEGER, tenant_id INTEGER, status TEXT)');
        $pdo->exec("INSERT INTO users VALUES
            (1, 21, 'compte@example.test'), (2, 21, 'fiche-retiree@sans-compte.invalid'),
            (3, 21, 'fiche-ancienne@sans-compte.invalid'), (4, 22, 'fiche-active@sans-compte.invalid')");
        $pdo->exec("INSERT INTO user_community_memberships VALUES (1, 21, 'left'), (2, 21, 'left'), (4, 21, 'active')");
        $users = new class($pdo) extends UserRepository {
            public function hasCommunityMembershipTable(): bool
            {
                return true;
            }
        };

        $base = 'SELECT u.id FROM users u WHERE ';
        $query = $pdo->query($base . $users->sqlMemberOfTenantPredicate('u', 21) . ' ORDER BY u.id');
        self::assertSame([1, 3, 4], $query->fetchAll(PDO::FETCH_COLUMN));
        $query = $pdo->query($base . $users->sqlMemberOfTenantPredicate('u', 22));
        self::assertSame([4], $query->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testPersonnelDirectoryUsesCommunityProfileOverlay(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('function listPersonnelDirectoryRich', $src);
        self::assertStringContainsString('user_community_profiles ucp', $src);
        // Le matricule d’organisation du compte d’origine ne s’affiche que dans sa communauté d’origine.
        self::assertStringContainsString("COALESCE(ucp.tenant_member_number, CASE WHEN u.tenant_id = ' . \$tenantLit . ' THEN u.tenant_member_number END)", $src);
        self::assertStringContainsString('personnelProfilesJoinSql', $src);
        self::assertStringContainsString('gradesJoinForTenant', $src);
    }

    public function testCommunityIdentityMigrationRepairsLeftMemberships(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/user_community_identity_migration.php');
        self::assertStringContainsString("SET m.status = 'active', m.left_at = NULL", $src);
        self::assertStringContainsString("m.left_at IS NULL OR LOWER(TRIM(u.email)) NOT LIKE '%@sans-compte.invalid'", $src);
    }

    /**
     * Régression prod : dans une chaîne double-quote, `$this->sqlMemberOfTenantPredicate`
     * est lu comme propriété (Undefined property), pas comme appel de méthode.
     */
    public function testMemberPredicateNeverInterpolatedAsPropertyInDoubleQuotedSql(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        // Motif cassé typique après remplacement mécanique dans des "…".
        self::assertStringNotContainsString(
            "WHERE ' . \$this->sqlMemberOfTenantPredicate('u', \$tenantId) . ' AND u.status = 'active'",
            $src
        );
        // Forme correcte : fermeture de chaîne puis concaténation réelle.
        self::assertStringContainsString(
            'WHERE " . $this->sqlMemberOfTenantPredicate(\'u\', $tenantId) . " AND u.status = \'active\'',
            $src
        );
    }
}
