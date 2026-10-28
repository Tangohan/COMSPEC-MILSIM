<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\TenantArchiveState;
use App\Services\Community\TenantRemovalService;
use PDO;
use PHPUnit\Framework\TestCase;

final class TenantRemovalServiceTest extends TestCase
{
    private PDO $pdo;
    private string $backupDir;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT UNIQUE, settings TEXT,
            subscription_status TEXT, stripe_subscription_id TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, email TEXT, is_service_account INTEGER DEFAULT 0,
            UNIQUE (tenant_id, email), FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE)');
        $this->pdo->exec('CREATE TABLE user_community_memberships (id INTEGER PRIMARY KEY, user_id INTEGER, tenant_id INTEGER, status TEXT,
            left_at TEXT, joined_at TEXT, created_at TEXT, updated_at TEXT, UNIQUE (user_id, tenant_id),
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE)');
        $this->pdo->exec('CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT,
            FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE)');
        $this->pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY, tenant_id INTEGER, action TEXT)');

        $this->pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'Pas d’organisation', 'default'), (2, 'Doublon', 'doublon'), (3, 'Vraie', 'vraie')");
        // 10 : membre de Doublon (courante) et de Vraie ; 11 : seulement Doublon ; 12 : compte système de Doublon.
        $this->pdo->exec("INSERT INTO users (id, tenant_id, email) VALUES (10, 2, 'a@x.fr'), (11, 2, 'b@x.fr'), (13, 3, 'c@x.fr')");
        $this->pdo->exec("INSERT INTO users (id, tenant_id, email, is_service_account) VALUES (12, 2, 'bot@x.fr', 1)");
        $this->pdo->exec("INSERT INTO user_community_memberships (user_id, tenant_id, status, joined_at) VALUES
            (10, 2, 'active', '2026-01-01'), (10, 3, 'active', '2026-02-01'), (11, 2, 'active', '2026-01-01'), (13, 2, 'active', '2026-01-01'), (13, 3, 'active', '2026-01-01')");
        $this->pdo->exec("INSERT INTO units (tenant_id, name) VALUES (2, 'Alpha'), (2, 'Bravo'), (3, 'Charlie')");
        $this->pdo->exec("INSERT INTO audit_logs (tenant_id, action) VALUES (2, 'x')");

        $this->backupDir = sys_get_temp_dir() . '/tenant-removal-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->backupDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->backupDir);
    }

    private function service(): TenantRemovalService
    {
        return new TenantRemovalService($this->pdo, $this->backupDir);
    }

    public function testPreviewPlansAccountsAndCountsRows(): void
    {
        $p = $this->service()->preview(2);

        self::assertNotNull($p);
        self::assertSame(3, $p['accounts']['members']);
        self::assertSame(2, $p['accounts']['home']);
        self::assertSame(1, $p['accounts']['to_other']);
        self::assertSame(1, $p['accounts']['to_no_org']);
        self::assertSame(1, $p['accounts']['technical']);
        // units (2) + memberships (3) + compte technique (1) partent ; audit_logs reste.
        self::assertSame(6, $p['rows_deleted']);
        self::assertSame(1, $p['rows_kept']);
        self::assertNotEmpty($p['delete_blockers'], 'Une communauté non archivée ne se supprime pas.');
    }

    public function testDefaultTenantIsProtected(): void
    {
        $p = $this->service()->preview(1);

        self::assertTrue($p['protected']);
        self::assertFalse($this->service()->archive(1, null)['ok']);
    }

    public function testArchiveRehomesAccountsAndRestoreBringsMembershipsBack(): void
    {
        $result = $this->service()->archive(2, 99, 'doublon');

        self::assertTrue($result['ok']);
        self::assertSame(3, $result['memberships']);
        self::assertSame(1, $result['moved_other']);
        self::assertSame(1, $result['moved_no_org']);
        self::assertSame(3, (int) $this->pdo->query('SELECT tenant_id FROM users WHERE id = 10')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT tenant_id FROM users WHERE id = 11')->fetchColumn());
        self::assertSame('active', $this->pdo->query('SELECT status FROM user_community_memberships WHERE user_id = 11 AND tenant_id = 1')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM units WHERE tenant_id = 2')->fetchColumn());

        $row = $this->pdo->query('SELECT * FROM tenants WHERE id = 2')->fetch(PDO::FETCH_ASSOC);
        self::assertTrue(TenantArchiveState::isArchived($row));
        self::assertSame('doublon', TenantArchiveState::info($row)['reason']);

        $restore = $this->service()->restore(2);
        self::assertTrue($restore['ok']);
        self::assertSame(3, $restore['memberships']);
        self::assertSame(2, $restore['moved_back']);
        self::assertSame(2, (int) $this->pdo->query('SELECT tenant_id FROM users WHERE id = 10')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT tenant_id FROM users WHERE id = 11')->fetchColumn());
        $row = $this->pdo->query('SELECT * FROM tenants WHERE id = 2')->fetch(PDO::FETCH_ASSOC);
        self::assertFalse(TenantArchiveState::isArchived($row));
    }

    public function testRestoreOfAnOlderArchiveBringsBackAccountsLeftWithoutOrganisation(): void
    {
        self::assertTrue($this->service()->archive(2, 99)['ok']);
        // Archive faite avant l'ajout de `moved_users` : la liste des comptes déplacés manque.
        $this->pdo->exec("UPDATE tenants SET settings = '{\"platform_archive\":{\"archived_at\":\"2026-10-20\"}}' WHERE id = 2");

        $restore = $this->service()->restore(2);

        self::assertTrue($restore['ok']);
        self::assertSame(1, $restore['moved_back']);
        self::assertSame(2, (int) $this->pdo->query('SELECT tenant_id FROM users WHERE id = 11')->fetchColumn());
        // Le compte parti dans une autre vraie communauté y reste.
        self::assertSame(3, (int) $this->pdo->query('SELECT tenant_id FROM users WHERE id = 10')->fetchColumn());
    }

    public function testDeleteRequiresArchiveAndExactSlug(): void
    {
        self::assertFalse($this->service()->delete(2, 'doublon')['ok']);
        $this->service()->archive(2, null);
        self::assertFalse($this->service()->delete(2, 'doublo')['ok']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM tenants WHERE id = 2')->fetchColumn());
    }

    public function testDeleteKeepsPlayerAccountsAndWritesBackup(): void
    {
        $this->service()->archive(2, null);
        $result = $this->service()->delete(2, ' Doublon ');

        self::assertTrue($result['ok'], (string) ($result['error'] ?? ''));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM tenants WHERE id = 2')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM units WHERE tenant_id = 2')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM units WHERE tenant_id = 3')->fetchColumn());
        // Comptes humains conservés, compte de service parti avec la communauté.
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE id IN (10, 11, 13)')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE id = 12')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_logs WHERE tenant_id = 2')->fetchColumn());

        $file = (string) $result['backup'];
        self::assertFileExists($file);
        $backup = json_decode((string) gzdecode((string) file_get_contents($file)), true);
        self::assertSame('doublon', $backup['tenant']['slug']);
        self::assertCount(2, $backup['tables']['units']);
        self::assertCount(1, $backup['tables']['users']);
    }

    public function testDeletePurgesPlatformAdminInterventionSessions(): void
    {
        // Journal des interventions de l’admin du site : clés étrangères RESTRICT vers tenants et vers la session.
        $this->pdo->exec('CREATE TABLE platform_admin_tenant_sessions (id INTEGER PRIMARY KEY, admin_id INTEGER, tenant_id INTEGER NOT NULL,
            status TEXT, FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT)');
        $this->pdo->exec('CREATE TABLE platform_admin_tenant_actions (id INTEGER PRIMARY KEY, session_id INTEGER NOT NULL, tenant_id INTEGER NOT NULL,
            action_type TEXT, FOREIGN KEY (session_id) REFERENCES platform_admin_tenant_sessions (id) ON DELETE RESTRICT)');
        $this->pdo->exec("INSERT INTO platform_admin_tenant_sessions (id, admin_id, tenant_id, status) VALUES (5, 99, 2, 'active'), (6, 99, 3, 'ended')");
        $this->pdo->exec("INSERT INTO platform_admin_tenant_actions (session_id, tenant_id, action_type) VALUES (5, 2, 'write'), (6, 3, 'write')");

        $this->service()->archive(2, null);
        $preview = $this->service()->preview(2);
        self::assertSame([], array_values(array_filter(
            $preview['delete_blockers'],
            static fn (string $b): bool => str_contains($b, 'platform_admin_tenant')
        )), 'Les sessions d’intervention admin ne bloquent plus la suppression.');

        $result = $this->service()->delete(2, 'doublon');

        self::assertTrue($result['ok'], (string) ($result['error'] ?? ''));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM tenants WHERE id = 2')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM platform_admin_tenant_sessions WHERE tenant_id = 2')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM platform_admin_tenant_actions WHERE tenant_id = 2')->fetchColumn());
        // Les interventions sur les autres communautés restent.
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM platform_admin_tenant_sessions WHERE tenant_id = 3')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM platform_admin_tenant_actions WHERE tenant_id = 3')->fetchColumn());

        $backup = json_decode((string) gzdecode((string) file_get_contents((string) $result['backup'])), true);
        self::assertCount(1, $backup['tables']['platform_admin_tenant_sessions']);
    }

    public function testDeleteRefusedWhilePaidSubscriptionRuns(): void
    {
        $this->pdo->exec("UPDATE tenants SET subscription_status = 'active', stripe_subscription_id = 'sub_1' WHERE id = 2");
        $this->service()->archive(2, null);
        $result = $this->service()->delete(2, 'doublon');

        self::assertFalse($result['ok']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM tenants WHERE id = 2')->fetchColumn());
    }
}
