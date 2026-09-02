<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\UserRepository;
use App\Support\SqlText;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqlTextTest extends TestCase
{
    public function testSqliteKeepsPlainNormalizedEquals(): void
    {
        $pdo = new PDO('sqlite::memory:');
        self::assertSame(
            'LOWER(TRIM(u.email)) = ?',
            SqlText::normalizedEquals($pdo, 'u.email')
        );
        self::assertSame(
            'r.slug IN (?, ?)',
            SqlText::inPlaceholders($pdo, 'r.slug', 2)
        );
        self::assertSame(
            'slug LIKE \'org-%\'',
            SqlText::likeLiteral($pdo, 'slug', 'org-%')
        );
        self::assertSame(
            'u.status = ?',
            SqlText::equals($pdo, 'u.status')
        );
        self::assertSame(
            'COALESCE(p.status, u.status) = ?',
            SqlText::coalesceEquals($pdo, 'p.status', 'u.status')
        );
    }

    public function testMysqlForcesUnicodeCollationOnBothSides(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');

        $eq = SqlText::normalizedEquals($pdo, 'u.email');
        self::assertStringContainsString('COLLATE utf8mb4_unicode_ci', $eq);
        self::assertStringContainsString('CONVERT(? USING utf8mb4)', $eq);
        self::assertStringContainsString('LOWER(TRIM(u.email))', $eq);

        $status = SqlText::equals($pdo, 'u.status');
        self::assertStringContainsString('u.status COLLATE utf8mb4_unicode_ci', $status);
        self::assertStringContainsString('CONVERT(? USING utf8mb4)', $status);

        $like = SqlText::like($pdo, 'display_name');
        self::assertStringContainsString('LIKE (CONVERT(? USING utf8mb4)', $like);

        $slugLit = SqlText::equalsLiteral($pdo, 't.slug', 'default');
        self::assertStringContainsString("'default' COLLATE utf8mb4_unicode_ci", $slugLit);

        $permLit = SqlText::equalsLiteral($pdo, 'p.slug', 'admin.access');
        self::assertStringContainsString("'admin.access' COLLATE utf8mb4_unicode_ci", $permLit);

        $inPh = SqlText::inPlaceholders($pdo, 'r.slug', 2);
        self::assertStringContainsString('r.slug COLLATE utf8mb4_unicode_ci', $inPh);
        self::assertSame(2, substr_count($inPh, 'CONVERT(? USING utf8mb4)'));

        $inLit = SqlText::inLiterals($pdo, 'p.slug', ['forum.view', 'training.manage']);
        self::assertStringContainsString("'forum.view' COLLATE utf8mb4_unicode_ci", $inLit);

        $likeLit = SqlText::likeLiteral($pdo, 'slug', 'org-%');
        self::assertStringContainsString("'org-%' COLLATE utf8mb4_unicode_ci", $likeLit);

        $aliasEq = SqlText::normalizedCoalesceEmptyEquals($pdo, 'alias');
        self::assertStringContainsString('COALESCE(alias', $aliasEq);
        self::assertStringContainsString('COLLATE utf8mb4_unicode_ci', $aliasEq);

        $slugNotDefault = SqlText::notEqualsLiteral($pdo, 't.slug', 'default');
        self::assertStringContainsString('t.slug COLLATE utf8mb4_unicode_ci', $slugNotDefault);
        self::assertStringContainsString("'default' COLLATE utf8mb4_unicode_ci", $slugNotDefault);

        $statusCase = SqlText::coalesceEqualsLiteral($pdo, 'p.status', 'u.status', 'active');
        self::assertStringContainsString('COALESCE(p.status, u.status) COLLATE utf8mb4_unicode_ci', $statusCase);
        self::assertStringContainsString("'active' COLLATE utf8mb4_unicode_ci", $statusCase);

        $deletedName = SqlText::isNullOrCoalesceTrimNotEqualsLiteral($pdo, 'p.display_name', 'u.display_name', 'Compte supprimé');
        self::assertStringContainsString('TRIM(COALESCE(p.display_name, u.display_name)) COLLATE utf8mb4_unicode_ci', $deletedName);
        self::assertStringContainsString("'Compte supprimé' COLLATE utf8mb4_unicode_ci", $deletedName);

        $trimNotEmpty = SqlText::coalesceTrimIsNotEmpty($pdo, 'ucp.callsign', 'u.callsign');
        self::assertStringContainsString('TRIM(COALESCE(ucp.callsign, u.callsign)) COLLATE utf8mb4_unicode_ci', $trimNotEmpty);
    }

    public function testRejectsUnsafeColumnExpressions(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $this->expectException(InvalidArgumentException::class);
        SqlText::normalizedEquals($pdo, 'email = 1; DROP TABLE users');
    }

    public function testDatabaseSessionAlignsCollationAndTimezone(): void
    {
        $sql = Database::sessionInitSql();
        self::assertStringContainsString('collation_connection = \'utf8mb4_unicode_ci\'', $sql);
        self::assertStringContainsString("time_zone = '+00:00'", $sql);
    }

    public function testListTenantsForEmailMatchesNormalizedAddressOnSqlite(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INT, email TEXT, status TEXT)');
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'Alpha', 'alpha')");
        $pdo->exec("INSERT INTO users (id, tenant_id, email, status) VALUES (1, 1, 'Ada@Example.com', 'active')");

        $repo = new UserRepository($pdo);
        $rows = $repo->listTenantsForEmail('ada@example.com');

        self::assertCount(1, $rows);
        self::assertSame(1, (int) $rows[0]['tenant_id']);
        self::assertSame('alpha', $rows[0]['slug']);
    }

    public function testListTenantsForEmailSourceUsesCollationSafeHelper(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('function listTenantsForEmail', $src);
        self::assertStringContainsString('SqlText::normalizedEquals', $src);
        self::assertStringContainsString('SqlText::equals', $src);
        self::assertStringNotContainsString(
            'WHERE LOWER(TRIM(u.email)) = ? AND u.status = ?',
            $src
        );
    }

    public function testListAllMembershipsByEmailSourceUsesCollationSafeComparisons(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('function listAllMembershipsByEmail', $src);
        self::assertStringContainsString('SqlText::equalsLiteral($pdo, \'t.slug\', \'default\')', $src);
        self::assertStringContainsString('SqlText::coalesceEqualsLiteral($pdo, \'p.status\', \'u.status\', \'active\')', $src);
        self::assertStringNotContainsString("CASE WHEN t.slug = 'default' THEN 1 ELSE 0 END ASC", $src);
    }

    public function testEmailHasActiveNonDefaultMembershipUsesCollationSafeSlugFilter(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('function emailHasActiveNonDefaultMembership', $src);
        if (!preg_match('/function emailHasActiveNonDefaultMembership\b.*?^\    \}/ms', $src, $match)) {
            self::fail('emailHasActiveNonDefaultMembership introuvable.');
        }
        $body = $match[0];
        self::assertStringContainsString("SqlText::notEqualsLiteral(\$pdo, 't.slug', 'default')", $body);
        self::assertStringNotContainsString("t.slug <> 'default'", $body);
    }

    public function testPersonnelDirectoryUsesCollationSafeStatusAndDeletedNameFilters(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        if (!preg_match('/function listPersonnelDirectoryRich\b.*?^\    \}/ms', $src, $match)) {
            self::fail('listPersonnelDirectoryRich introuvable.');
        }
        $body = $match[0];
        self::assertStringContainsString("SqlText::coalesceEqualsLiteral(\$pdo, 'ucp.status', 'u.status', 'active')", $body);
        self::assertStringContainsString("SqlText::isNullOrCoalesceTrimNotEqualsLiteral", $body);
        self::assertStringNotContainsString("COALESCE(ucp.status, u.status) = 'active'", $body);
        self::assertStringNotContainsString("TRIM(" . '$displayNameExpr' . ") <> 'Compte supprimé'", $body);
    }

    public function testMemberPredicateUsesCollationSafeMembershipStatus(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        if (!preg_match('/function sqlMemberOfTenantPredicate\b.*?^\    \}/ms', $src, $match)) {
            self::fail('sqlMemberOfTenantPredicate introuvable.');
        }
        $body = $match[0];
        self::assertStringContainsString("SqlText::equalsLiteral(\$this->pdo(), '__ucm.status', 'active')", $body);
        self::assertStringNotContainsString("__ucm.status = 'active'", $body);
    }
}
