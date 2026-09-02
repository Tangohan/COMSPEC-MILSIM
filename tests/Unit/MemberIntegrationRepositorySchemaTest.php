<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\MemberIntegrationRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class MemberIntegrationRepositorySchemaTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return list<string>
     */
    private function columnsFromCreateTable(string $sql, string $table): array
    {
        $quoted = preg_quote($table, '/');
        if (!preg_match('/CREATE TABLE IF NOT EXISTS `?' . $quoted . '`? \((.*?)\)\s*ENGINE/is', $sql, $m)
            && !preg_match('/CREATE TABLE IF NOT EXISTS `?' . $quoted . '`? \((.*?)\)\s*;/is', $sql, $m)
        ) {
            return [];
        }
        preg_match_all('/^\s*`?([a-z0-9_]+)`?\s+/im', $m[1], $cols);

        return array_values(array_unique($cols[1]));
    }

    public function testDashboardSelectUserColumnsExistOnUsersSchema(): void
    {
        $repo = (string) file_get_contents($this->root() . '/app/Repositories/MemberIntegrationRepository.php');
        $schema = (string) file_get_contents($this->root() . '/migrations/schema.sql');
        $dossier = (string) file_get_contents($this->root() . '/migrations/personnel_dossier.sql');

        $userCols = $this->columnsFromCreateTable($schema, 'users');
        self::assertContains('avatar_url', $userCols);
        self::assertContains('display_name', $userCols);
        self::assertContains('callsign', $userCols);
        self::assertContains('email', $userCols);
        self::assertContains('created_at', $userCols);
        self::assertContains('role_id', $userCols);
        self::assertNotContains('avatar_path', $userCols);

        $portraitCols = $this->columnsFromCreateTable($dossier, 'personnel_profiles');
        self::assertContains('character_portrait_path', $portraitCols);
        self::assertContains('primary_unit_id', $portraitCols);

        preg_match_all('/\bu\.([a-z0-9_]+)\b/', $repo, $used);
        $gated = ['avatar_url', 'avatar_path'];
        foreach (array_unique($used[1]) as $col) {
            if (in_array($col, $gated, true)) {
                continue;
            }
            self::assertContains(
                $col,
                $userCols,
                'La requête d’intégration sélectionne users.' . $col . ' qui n’existe pas dans le schéma.'
            );
        }

        self::assertStringContainsString("hasColumn('users', 'avatar_url')", $repo);
        self::assertStringContainsString("hasColumn('personnel_profiles', 'character_portrait_path')", $repo);
        self::assertStringContainsString('NULL AS avatar_url', $repo);
        self::assertDoesNotMatchRegularExpression('/SELECT[^;]+u\\.avatar_path/s', $repo);
    }

    public function testPhotoMigrationAddsEmptyColumnsOnly(): void
    {
        $boot = (string) file_get_contents($this->root() . '/bootstrap/users_member_photo_columns_migration.php');
        $sql = (string) file_get_contents($this->root() . '/migrations/20260902140000_users_member_photo_columns.sql');
        $runner = (string) file_get_contents($this->root() . '/run-migrations.php');

        self::assertStringContainsString('avatar_url', $boot);
        self::assertStringContainsString('character_portrait_path', $boot);
        self::assertStringContainsString('DEFAULT NULL', $boot);
        self::assertStringContainsString("!\$hasColumn('users', 'avatar_url')", $boot);
        self::assertStringNotContainsString('ADD COLUMN `avatar_path`', $boot);
        self::assertStringNotContainsString('ADD COLUMN avatar_path', $boot);
        self::assertStringContainsString('users.avatar_url', $sql);
        self::assertStringContainsString('users_member_photo_columns_migration.php', $runner);
        self::assertStringContainsString('information_schema.COLUMNS', $boot);
    }

    public function testListDashboardSucceedsWhenAvatarUrlExists(): void
    {
        $pdo = $this->sqliteDashboard(true);
        $rows = (new MemberIntegrationRepository($pdo))->listDashboard(7, [], 50);

        self::assertCount(1, $rows);
        self::assertSame('Alpha', $rows[0]['display_name'] ?? null);
        self::assertSame('uploads/avatars/a.jpg', $rows[0]['avatar_url'] ?? null);
        self::assertSame('uploads/portraits/a.png', $rows[0]['character_portrait_path'] ?? null);
    }

    public function testListDashboardSucceedsWithoutPhotoColumns(): void
    {
        $pdo = $this->sqliteDashboard(false);
        $repo = new MemberIntegrationRepository($pdo);
        $rows = $repo->listDashboard(7, [], 50);

        self::assertCount(1, $rows);
        self::assertSame('Alpha', $rows[0]['display_name'] ?? null);
        self::assertArrayHasKey('avatar_url', $rows[0]);
        self::assertTrue($rows[0]['avatar_url'] === null || $rows[0]['avatar_url'] === '');
        self::assertArrayHasKey('character_portrait_path', $rows[0]);

        $one = $repo->findForTenant(7, 1);
        self::assertNotNull($one);
        self::assertSame('Alpha', $one['display_name'] ?? null);
        self::assertArrayHasKey('avatar_url', $one);
    }

    private function sqliteDashboard(bool $withPhotoColumns): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $avatarCol = $withPhotoColumns ? ', avatar_url TEXT' : '';
        $portraitCol = $withPhotoColumns ? ', character_portrait_path TEXT' : '';
        $pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                tenant_id INT,
                email TEXT,
                display_name TEXT,
                callsign TEXT,
                role_id INT,
                created_at TEXT
                ' . $avatarCol . '
            )'
        );
        $pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec(
            'CREATE TABLE personnel_profiles (
                user_id INTEGER,
                primary_unit_id INTEGER
                ' . $portraitCol . '
            )'
        );
        $pdo->exec('CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INT, name TEXT)');
        $pdo->exec(
            'CREATE TABLE member_integration_templates (
                id INTEGER PRIMARY KEY, tenant_id INT, name TEXT
            )'
        );
        $pdo->exec(
            'CREATE TABLE member_integration_steps (
                id INTEGER PRIMARY KEY, tenant_id INT, title TEXT
            )'
        );
        $pdo->exec(
            'CREATE TABLE member_integrations (
                id INTEGER PRIMARY KEY,
                tenant_id INT,
                user_id INT,
                template_id INT,
                current_step_id INT,
                primary_referent_user_id INT,
                status TEXT,
                progress_percent INT,
                overdue_count INT,
                dossier_complete INT,
                created_at TEXT
            )'
        );
        $avatarVal = $withPhotoColumns ? ", 'uploads/avatars/a.jpg'" : '';
        $portraitVal = $withPhotoColumns ? ", 'uploads/portraits/a.png'" : '';
        $pdo->exec(
            'INSERT INTO users (id, tenant_id, email, display_name, callsign, role_id, created_at'
            . ($withPhotoColumns ? ', avatar_url' : '') . ')
             VALUES (5, 7, \'a@example.com\', \'Alpha\', \'A1\', 1, \'2026-09-01 10:00:00\''
            . $avatarVal . ')'
        );
        $pdo->exec('INSERT INTO roles (id, name) VALUES (1, \'Opérateur\')');
        $pdo->exec(
            'INSERT INTO personnel_profiles (user_id, primary_unit_id'
            . ($withPhotoColumns ? ', character_portrait_path' : '') . ')
             VALUES (5, 3' . $portraitVal . ')'
        );
        $pdo->exec('INSERT INTO units (id, tenant_id, name) VALUES (3, 7, \'Section\')');
        $pdo->exec(
            'INSERT INTO member_integrations
                (id, tenant_id, user_id, template_id, current_step_id, primary_referent_user_id,
                 status, progress_percent, overdue_count, dossier_complete, created_at)
             VALUES (1, 7, 5, NULL, NULL, NULL, \'to_start\', 0, 0, 0, \'2026-09-01 12:00:00\')'
        );

        return $pdo;
    }
}
