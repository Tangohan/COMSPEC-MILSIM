<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MigrationsBackOfficeAssetTest extends TestCase
{
    public function testRunnerAppliesPendingSqlThroughLedgerAndVerifiesResult(): void
    {
        $root = dirname(__DIR__, 2);
        $runner = (string) \Tests\Support\MigrationPipelineSource::read($root);
        $engine = (string) file_get_contents($root . '/bootstrap/migration_runner.php');
        $post = (string) file_get_contents($root . '/bootstrap/migrations_full_post.php');

        self::assertStringContainsString("\$migrationRunner->runPendingSqlFiles(\$root . '/migrations');", $runner);
        self::assertStringContainsString('$migrationRunner->printReport()', $runner);
        self::assertStringNotContainsString("defined('COMSPEC_MIGRATIONS_WEB_FULL')", $runner);
        self::assertStringContainsString("glob(rtrim(\$dir, '/') . '/*.sql')", $engine);
        self::assertStringContainsString('comspec_schema_migrations', $engine);
        self::assertStringContainsString('EXECUTE|CALL', $engine);
        self::assertStringContainsString('_manual\\.sql', $engine);
        self::assertStringContainsString('Aucun compte ni communauté de démonstration', $post);
    }

    public function testWebUiUsesBackOfficeThemeAndKeepsProtections(): void
    {
        $root = dirname(__DIR__, 2);
        $ui = (string) file_get_contents($root . '/bootstrap/migrations_web_ui.php');

        foreach (['back-office-shell.css', 'back-office-dark.css', 'migrations-console.css', 'back-office-theme.js', 'ath-page-head', 'ath-kpi-grid', 'ath-table-panel'] as $needle) {
            self::assertStringContainsString($needle, $ui);
        }
        self::assertStringNotContainsString('dsfr-service.css', $ui);
        self::assertStringContainsString('Comptes de démonstration', $ui);
        // Protections : mot de passe, CSRF sur chaque action, verrouillage par IP, session courte, en-têtes.
        self::assertStringContainsString('migrations_web_csrf_ok($_POST[\'csrf\'] ?? null)', $ui);
        self::assertStringContainsString('migrations_web_ip_lock_path', $ui);
        self::assertStringContainsString('MIGRATIONS_WEB_IDLE_SECONDS', $ui);
        self::assertStringContainsString("header('X-Frame-Options: DENY')", $ui);
        self::assertStringContainsString("'run_step'", $ui);
        // La console échappe la sortie du pipeline avant de l'afficher.
        self::assertStringContainsString('return migrations_web_colorize($chunk);', $ui);
        self::assertStringContainsString('$h = migrations_web_h($line);', $ui);
        self::assertFileExists($root . '/public/assets/css/migrations-console.css');
        self::assertFileExists($root . '/public/assets/js/migrations-console.js');
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
