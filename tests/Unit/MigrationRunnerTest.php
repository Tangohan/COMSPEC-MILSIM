<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bootstrap/migration_runner.php';

final class MigrationRunnerTest extends TestCase
{
    public function testSplitKeepsDelimitersInsideStringsAndDropsComments(): void
    {
        $sql = "-- commentaire ; ici\n"
            . "INSERT INTO t VALUES ('a;b', 'it''s -- pas un commentaire', \"x;\\\"y\");\n"
            . "/* bloc ; */ SELECT 1; # dièse ; commentaire\n"
            . "ALTER TABLE `x;y` ADD COLUMN z INT";

        self::assertSame([
            "INSERT INTO t VALUES ('a;b', 'it''s -- pas un commentaire', \"x;\\\"y\")",
            'SELECT 1',
            'ALTER TABLE `x;y` ADD COLUMN z INT',
        ], ComspecMigrationRunner::splitStatements($sql));
    }

    public function testSplitHandlesDelimiterDirectiveAndTriggerBodies(): void
    {
        $sql = "CREATE TRIGGER trg BEFORE INSERT ON t FOR EACH ROW BEGIN\n"
            . "  IF NEW.a = 1 THEN SET NEW.b = CASE WHEN 1 THEN 2 ELSE 3 END; END IF;\n"
            . "END;\n"
            . "DELIMITER $$\n"
            . "CREATE PROCEDURE p() BEGIN SELECT 1; END$$\n"
            . "DELIMITER ;\n"
            . "SELECT 2;";

        $statements = ComspecMigrationRunner::splitStatements($sql);

        self::assertCount(3, $statements);
        self::assertStringEndsWith('END IF;' . "\nEND", $statements[0]);
        self::assertSame('CREATE PROCEDURE p() BEGIN SELECT 1; END', $statements[1]);
        self::assertSame('SELECT 2', $statements[2]);
    }

    public function testEveryRepositorySqlFileSplitsWithoutDirectivesOrComments(): void
    {
        $files = ComspecMigrationRunner::listSqlFiles(dirname(__DIR__, 2) . '/migrations');
        self::assertNotSame([], $files);
        foreach ($files as $path) {
            // Certains fichiers ne sont que de la documentation (commentaires) : zéro instruction est valide.
            foreach (ComspecMigrationRunner::splitStatements((string) file_get_contents($path)) as $stmt) {
                self::assertDoesNotMatchRegularExpression('/^(DELIMITER\b|--\s|#)/i', $stmt, basename($path));
            }
        }
    }

    public function testAlreadyAppliedErrorsAreNotFailures(): void
    {
        self::assertTrue(ComspecMigrationRunner::isAlreadyApplied($this->pdoError(1060, "Duplicate column name 'x'")));
        self::assertTrue(ComspecMigrationRunner::isAlreadyApplied($this->pdoError(1061, "Duplicate key name 'k'")));
        self::assertTrue(ComspecMigrationRunner::isAlreadyApplied($this->pdoError(1005, "Can't create table `d`.`t` (errno: 121 \"Duplicate key on write or update\")")));
        self::assertFalse(ComspecMigrationRunner::isAlreadyApplied($this->pdoError(1054, "Unknown column 'u.username'")));
        self::assertFalse(ComspecMigrationRunner::isAlreadyApplied($this->pdoError(1005, "Can't create table `d`.`t` (errno: 150 \"Foreign key constraint is incorrectly formed\")")));
    }

    public function testManualAndOwnedFilesAreExcludedFromAutomaticReplay(): void
    {
        self::assertNotNull(ComspecMigrationRunner::exclusionReason('schema.sql'));
        self::assertNotNull(ComspecMigrationRunner::exclusionReason('setup_system_admin_manual.sql'));
        self::assertNotNull(ComspecMigrationRunner::exclusionReason('lms_training.sql'));
        self::assertNotNull(ComspecMigrationRunner::exclusionReason('2026_07_24_005_atak_qrf_system.sql'));
        self::assertNull(ComspecMigrationRunner::exclusionReason('20261002000001_community_events_series.sql'));
        foreach (array_keys(ComspecMigrationRunner::OWNED_SQL_FILES) as $owned) {
            self::assertFileExists(dirname(__DIR__, 2) . '/migrations/' . $owned);
        }
    }

    private function pdoError(int $code, string $message): PDOException
    {
        $e = new PDOException("SQLSTATE[42S21]: Error: {$code} {$message}");
        $e->errorInfo = ['42S21', $code, $message];

        return $e;
    }
}
