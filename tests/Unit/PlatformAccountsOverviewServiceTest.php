<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\UserRepository;
use App\Services\Admin\PlatformAccountsOverviewService;
use PDO;
use PHPUnit\Framework\TestCase;

final class PlatformAccountsOverviewServiceTest extends TestCase
{
    private function pdo(bool $withLastLogin = true): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, email TEXT, display_name TEXT, callsign TEXT, status TEXT, deleted_at TEXT, created_at TEXT'
            . ($withLastLogin ? ', last_login_at TEXT' : '') . ')');
        $pdo->exec("INSERT INTO tenants VALUES (1, 'Aucune organisation', 'default', '2026-01-01 00:00:00'), (2, 'Alpha', 'alpha', '2026-02-01 00:00:00'), (3, 'Bravo', 'bravo', '2026-03-01 00:00:00')");

        return $pdo;
    }

    private function addUser(PDO $pdo, int $id, int $tenant, string $email, ?string $lastLogin, ?string $deletedAt = null): void
    {
        $st = $pdo->prepare('INSERT INTO users (id, tenant_id, email, display_name, status, deleted_at, created_at, last_login_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$id, $tenant, $email, 'U' . $id, 'active', $deletedAt, '2026-01-01 00:00:00', $lastLogin]);
    }

    public function testInactivityBucketsAndOldestExcludeAnonymizedAccounts(): void
    {
        $now = strtotime('2026-11-01 12:00:00');
        $ago = static fn (int $d): string => date('Y-m-d H:i:s', $now - $d * 86400);
        $pdo = $this->pdo();
        $this->addUser($pdo, 1, 2, 'recent@x.fr', $ago(2));
        $this->addUser($pdo, 2, 2, 'mois@x.fr', $ago(45));
        $this->addUser($pdo, 3, 3, 'trimestre@x.fr', $ago(120));
        $this->addUser($pdo, 4, 3, 'vieux@x.fr', $ago(400));
        $this->addUser($pdo, 5, 2, 'jamais@x.fr', null);
        $this->addUser($pdo, 6, 2, 'anon-6@deleted.invalid', $ago(900));
        $this->addUser($pdo, 7, 2, 'supprime@x.fr', $ago(900), '2026-05-01 00:00:00');
        // Même personne sur deux fiches : la connexion la plus récente compte.
        $this->addUser($pdo, 8, 3, 'Recent@x.fr', $ago(500));

        $res = (new PlatformAccountsOverviewService($pdo))->inactivity(6, $now);

        self::assertTrue($res['available']);
        self::assertSame(5, $res['total']);
        self::assertSame(['30' => 3, '90' => 2, '180' => 1, 'jamais' => 1], $res['buckets']);
        self::assertSame([4, 3, 2], array_map(static fn (array $u): int => (int) $u['id'], $res['oldest']));
        self::assertSame('Bravo', $res['oldest'][0]['community_name']);
    }

    public function testMissingLastLoginColumnDoesNotFail(): void
    {
        $pdo = $this->pdo(false);
        $pdo->exec("INSERT INTO users (id, tenant_id, email, status) VALUES (1, 2, 'a@x.fr', 'active')");
        $svc = new PlatformAccountsOverviewService($pdo);

        self::assertFalse($svc->inactivity()['available']);
        self::assertCount(1, $svc->latestAccounts());
        self::assertNull(UserRepository::platformInactivityHaving('30', false));
    }

    public function testLatestListsSkipPlaceholderAndDuplicates(): void
    {
        $pdo = $this->pdo();
        $this->addUser($pdo, 1, 1, 'solo@x.fr', null);
        $this->addUser($pdo, 2, 2, 'dup@x.fr', null);
        $this->addUser($pdo, 3, 3, 'DUP@x.fr', null);
        $svc = new PlatformAccountsOverviewService($pdo);

        self::assertSame(['bravo', 'alpha'], array_column($svc->latestCommunities(), 'slug'));
        $accounts = $svc->latestAccounts();
        self::assertSame([3, 1], array_map(static fn (array $u): int => (int) $u['id'], $accounts));
        self::assertSame('', $accounts[1]['community_name']);
    }

    public function testDirectoryInactivityFilter(): void
    {
        self::assertNull(UserRepository::platformInactivityHaving('x', true));
        self::assertSame('MAX(u.last_login_at) IS NULL', UserRepository::platformInactivityHaving('jamais', true)['sql'] ?? null);
        $f = UserRepository::platformInactivityHaving('90', true, strtotime('2026-11-01 00:00:00'));
        self::assertStringStartsWith('2026-08-03', (string) ($f['params'][0] ?? ''));
    }
}
