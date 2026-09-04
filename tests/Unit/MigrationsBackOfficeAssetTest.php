<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MigrationsBackOfficeAssetTest extends TestCase
{
    public function testRunnerAlwaysAppliesEverySupplementarySqlAndVerifiesResult(): void
    {
        $root = dirname(__DIR__, 2);
        $runner = (string) file_get_contents($root . '/run-migrations.php');
        $post = (string) file_get_contents($root . '/bootstrap/migrations_full_post.php');

        self::assertStringContainsString('comspec_run_all_supplementary_sql_files($pdo, $root, $migrationFlush);', $runner);
        self::assertStringNotContainsString("defined('COMSPEC_MIGRATIONS_WEB_FULL')", $runner);
        self::assertStringContainsString("glob($dir . '/*.sql')", $post);
        self::assertStringContainsString('Aucun compte ni communauté de démonstration', $post);
    }

    public function testBackOfficeUsesDsfrServiceThemeAndChecksDemoAccounts(): void
    {
        $root = dirname(__DIR__, 2);
        $ui = (string) file_get_contents($root . '/bootstrap/migrations_web_ui.php');

        self::assertStringContainsString('assets/css/dsfr-service.css', $ui);
        self::assertStringContainsString('BACK-OFFICE · SERVICE DE MIGRATIONS', $ui);
        self::assertStringContainsString('Comptes de démonstration', $ui);
    }

    public function testCleanupSqlRemovesOnlyDemoIdentities(): void
    {
        $root = dirname(__DIR__, 2);
        $sql = (string) file_get_contents($root . '/migrations/20260904180000_remove_demo_accounts.sql');

        self::assertStringContainsString("LIKE '%@demo.local'", $sql);
        self::assertStringContainsString("slug = 'demo-comspec'", $sql);
        self::assertStringNotContainsString('system.moderation@internal.local', $sql);
    }
}
