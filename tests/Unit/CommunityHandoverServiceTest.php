<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\CommunityHandoverService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Suppression du propriétaire d’une communauté : le rôle passe au membre suivant, ou la
 * communauté disparaît s’il n’y a plus personne. Les autres membres ne perdent rien.
 */
final class CommunityHandoverServiceTest extends TestCase
{
    private PDO $pdo;

    /** @var list<array{0: int, 1: string}> */
    private array $removed = [];

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, settings TEXT, owner_user_id INTEGER)');
        $this->pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, slug TEXT)');
        $this->pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, email TEXT, display_name TEXT, callsign TEXT,
            status TEXT DEFAULT 'active', role_id INTEGER, is_service_account INTEGER DEFAULT 0, deleted_at TEXT,
            deletion_requested_at TEXT, created_at TEXT)");
        $this->pdo->exec('CREATE TABLE user_roles (user_id INTEGER, role_id INTEGER, PRIMARY KEY (user_id, role_id))');
        $this->pdo->exec('CREATE TABLE user_community_memberships (id INTEGER PRIMARY KEY, user_id INTEGER, tenant_id INTEGER, status TEXT, joined_at TEXT)');
        $this->pdo->exec('CREATE TABLE tenant_community_feed (id INTEGER PRIMARY KEY, tenant_id INTEGER, category TEXT, title TEXT, body TEXT)');

        $this->pdo->exec("INSERT INTO tenants (id, name, slug, owner_user_id) VALUES
            (1, 'Pas d’organisation', 'default', NULL), (2, 'Alpha', 'alpha', 10), (3, 'Solo', 'solo', 10), (4, 'Duo', 'duo', 10)");
        $this->pdo->exec("INSERT INTO roles (id, tenant_id, slug) VALUES
            (20, 2, 'community_owner'), (21, 2, 'member'), (22, 2, 'deputy_commander'),
            (30, 3, 'community_owner'), (40, 4, 'community_owner'), (41, 4, 'member')");
        // 10 : propriétaire partout. Alpha : 11 ancien simple membre, 12 adjoint plus récent, 13 compte technique, 14 suppression en cours.
        $this->pdo->exec("INSERT INTO users (id, tenant_id, email, display_name, role_id, created_at) VALUES
            (10, 2, 'chef@x.fr', 'Chef', 20, '2026-01-01'),
            (11, 2, 'vieux@x.fr', 'Vieux', 21, '2026-01-02'),
            (12, 2, 'adjoint@x.fr', 'Adjoint', 21, '2026-05-01'),
            (15, 4, 'deux@x.fr', 'Deux', 41, '2026-01-03'),
            (16, 4, 'trois@x.fr', 'Trois', 40, '2026-01-04')");
        $this->pdo->exec("INSERT INTO users (id, tenant_id, email, display_name, role_id, is_service_account, created_at) VALUES (13, 2, 'bot@x.fr', 'Bot', 21, 1, '2025-01-01')");
        $this->pdo->exec("INSERT INTO users (id, tenant_id, email, display_name, role_id, deletion_requested_at, created_at) VALUES (14, 2, 'part@x.fr', 'Part', 22, '2026-10-01', '2025-06-01')");
        $this->pdo->exec('INSERT INTO user_roles (user_id, role_id) VALUES (12, 22), (10, 30), (10, 40)');
        $this->pdo->exec("INSERT INTO user_community_memberships (user_id, tenant_id, status, joined_at) VALUES (10, 3, 'active', '2026-01-01'), (10, 4, 'active', '2026-01-01')");
    }

    private function service(): CommunityHandoverService
    {
        return new CommunityHandoverService($this->pdo, function (int $tenantId, string $slug): array {
            $this->removed[] = [$tenantId, $slug];

            return ['ok' => true];
        });
    }

    public function testPlanDelegatesDeletesOrKeeps(): void
    {
        $plan = [];
        foreach ($this->service()->plan([10]) as $row) {
            $plan[$row['tenant_id']] = $row;
        }

        self::assertSame('delegate', $plan[2]['action']);
        self::assertSame(12, $plan[2]['successor_id'], 'Le cadre passe avant le plus ancien, les comptes techniques et partants sont exclus.');
        self::assertSame('delete', $plan[3]['action'], 'Seul membre : la communauté disparaît.');
        self::assertSame('keep', $plan[4]['action'], 'Un autre propriétaire existe déjà.');
        self::assertSame(16, $plan[4]['successor_id']);
        self::assertArrayNotHasKey(1, $plan, 'L’espace « Pas d’organisation » n’est jamais concerné.');
    }

    public function testHandOverGrantsOwnerRoleAndMovesOwnerColumn(): void
    {
        $results = $this->service()->handOver([10]);

        self::assertCount(3, $results);
        foreach ($results as $r) {
            self::assertTrue($r['ok'], (string) ($r['error'] ?? ''));
        }
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM user_roles WHERE user_id = 12 AND role_id = 20')->fetchColumn());
        self::assertSame(12, (int) $this->pdo->query('SELECT owner_user_id FROM tenants WHERE id = 2')->fetchColumn());
        self::assertSame(16, (int) $this->pdo->query('SELECT owner_user_id FROM tenants WHERE id = 4')->fetchColumn());
        self::assertSame([[3, 'solo']], $this->removed);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM tenant_community_feed WHERE tenant_id = 2 AND category = 'owner_handover'")->fetchColumn());
        // Les autres membres gardent leurs rôles.
        self::assertSame(21, (int) $this->pdo->query('SELECT role_id FROM users WHERE id = 11')->fetchColumn());
    }

    public function testOldestMemberTakesOverWhenNoOfficer(): void
    {
        $this->pdo->exec('DELETE FROM user_roles WHERE user_id = 12');

        $plan = $this->service()->plan([10], 2);

        self::assertCount(1, $plan);
        self::assertSame(11, $plan[0]['successor_id']);
    }

    public function testNonOwnerDepartureTouchesNothing(): void
    {
        self::assertSame([], $this->service()->handOver([11]));
        self::assertSame([], $this->removed);
    }
}