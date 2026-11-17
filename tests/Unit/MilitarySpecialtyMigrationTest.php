<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MilitarySpecialtyCatalog;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\MysqlDdlSqlitePdo;

/** Vérifie la reprise additive et les contraintes de relations, sans connexion applicative ni base déployée. */
final class MilitarySpecialtyMigrationTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function parents(PDO $pdo): void
    {
        foreach ([
            'CREATE TABLE countries (id INTEGER PRIMARY KEY, iso2 TEXT UNIQUE, iso3 TEXT, name_fr TEXT, name_en TEXT)',
            'CREATE TABLE military_services (id INTEGER PRIMARY KEY, country_id INTEGER, code TEXT UNIQUE, name TEXT)',
            'CREATE TABLE military_sources (id INTEGER PRIMARY KEY, name TEXT, publisher TEXT, url TEXT, source_type TEXT, published_at TEXT, checked_at TEXT)',
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY)',
            'CREATE TABLE personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER)',
            'CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY, tenant_id INTEGER)',
            'CREATE TABLE personnel_qualifications (id INTEGER PRIMARY KEY)',
            'CREATE TABLE military_specialties (id INTEGER PRIMARY KEY, code TEXT, label_fr TEXT)',
            "INSERT INTO military_specialties VALUES (4, 'COMBAT_CONTROL', 'Libellé de capacité personnalisé')",
        ] as $sql) {
            $pdo->exec($sql);
        }
    }

    private static function runStep(PDO $pdo): string
    {
        $root = self::root();
        ob_start();
        try {
            require $root . '/database/steps/4910_military_specialty_codes.php';
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    public function testReplayDoesNotRewriteReferencesOrExistingCapabilities(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        self::parents($pdo);
        self::assertStringContainsString('[OK]', self::runStep($pdo));
        self::assertGreaterThan(20, (int) $pdo->query('SELECT COUNT(*) FROM military_specialty_codes')->fetchColumn());
        self::assertSame('Libellé de capacité personnalisé', $pdo->query('SELECT label_fr FROM military_specialties WHERE id = 4')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM personnel_profile_specialty_codes')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM personnel_job_role_specialty_codes')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM qualification_specialty_targets')->fetchColumn());
        $pdo->exec("UPDATE military_specialty_codes SET label_fr = 'Correction administrateur', archived_at = '2026-11-18' WHERE code = 'FUSIL'");
        $before = $pdo->query('SELECT * FROM military_specialty_codes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $writes = (int) $pdo->query('SELECT total_changes()')->fetchColumn();
        $pdo->changes = 0;
        self::assertStringContainsString('0 référence(s) ajoutée(s)', self::runStep($pdo));
        self::assertSame(0, $pdo->changes);
        self::assertSame($writes, (int) $pdo->query('SELECT total_changes()')->fetchColumn());
        self::assertSame($before, $pdo->query('SELECT * FROM military_specialty_codes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testMissingParentFailsBeforeAnySchemaMutation(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('table préalable absente : countries');
        try {
            self::runStep($pdo);
        } finally {
            self::assertSame(0, $pdo->changes);
        }
    }

    public function testPartialInstallationAndConflictingCountryAreExplicit(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        self::parents($pdo);
        self::runStep($pdo);
        $pdo->exec('DROP TABLE qualification_specialty_targets');
        self::runStep($pdo);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM qualification_specialty_targets')->fetchColumn());
        $pdo->exec("UPDATE military_services SET country_id = 999 WHERE code = 'gb-raf'");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pays du service militaire est incohérent');
        self::runStep($pdo);
    }

    public function testSourcesAndLevelsKeepDocumentaryLimits(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        self::parents($pdo);
        self::runStep($pdo);
        self::assertSame('entry_option', $pdo->query("SELECT kind FROM military_specialty_codes WHERE code = '18X'")->fetchColumn());
        self::assertSame('historical', $pdo->query("SELECT verification_status FROM military_specialty_codes WHERE reference_key = 'gb-raf-trade-group-8'")->fetchColumn());
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM military_specialty_levels WHERE verification_status = 'verified' OR awarded_code IS NOT NULL")->fetchColumn());
        self::assertSame(24, (int) $pdo->query('SELECT COUNT(*) FROM military_specialty_levels')->fetchColumn());
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM countries WHERE iso2 = 'NATO'")->fetchColumn());
        $refs = array_column(MilitarySpecialtyCatalog::entries(), 'reference_key');
        self::assertSame(count($refs), count(array_unique($refs)));
    }

    /** Exécute les contraintes du DDL publié sous SQLite, après adaptation des seules syntaxes MySQL de types/index. */
    private static function constrainedDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::parents($pdo);
        $pdo->exec('CREATE UNIQUE INDEX uq_pjr_specialty_tenant_id ON personnel_job_roles (tenant_id, id)');
        $pdo->exec('CREATE UNIQUE INDEX uq_pqd_specialty_tenant_id ON personnel_qualification_definitions (tenant_id, id)');
        $ddl = (string) file_get_contents(self::root() . '/migrations/2026_11_17_100_military_specialty_codes.sql');
        $ddl = preg_replace('/\bBIGINT UNSIGNED NOT NULL AUTO_INCREMENT\b/', 'INTEGER NOT NULL', $ddl);
        $ddl = preg_replace('/^\s*KEY \w+\s*\([^\n]*\),?\s*$/m', '', (string) $ddl);
        $ddl = preg_replace('/UNIQUE KEY \w+/', 'UNIQUE', (string) $ddl);
        $ddl = preg_replace('/\b(?:INT UNSIGNED|BIGINT UNSIGNED|TINYINT\(1\))\b/', 'INTEGER', (string) $ddl);
        $ddl = preg_replace('/\) ENGINE=InnoDB[^;]*;/', ');', (string) $ddl);
        $pdo->exec((string) $ddl);
        MilitarySpecialtyCatalog::seed($pdo);
        $pdo->exec('INSERT INTO tenants VALUES (1), (2)');
        $pdo->exec('INSERT INTO users VALUES (1), (2)');
        $pdo->exec('INSERT INTO personnel_job_roles VALUES (10, 1), (20, 2)');
        $pdo->exec('INSERT INTO personnel_qualification_definitions VALUES (100, 1), (200, 2)');

        return $pdo;
    }

    public function testForeignKeysRejectWrongLevelAndCrossTenantParents(): void
    {
        $pdo = self::constrainedDatabase();
        $cct = (int) $pdo->query("SELECT id FROM military_specialty_codes WHERE code = '1Z2X1'")->fetchColumn();
        $pjLevel = (int) $pdo->query("SELECT l.id FROM military_specialty_levels l JOIN military_specialty_codes c ON c.id = l.specialty_code_id WHERE c.code = '1Z1X1' LIMIT 1")->fetchColumn();
        foreach ([
            "INSERT INTO personnel_job_role_specialty_codes (tenant_id, job_role_id, specialty_code_id) VALUES (1, 20, {$cct})",
            "INSERT INTO qualification_specialty_targets (tenant_id, definition_id, specialty_code_id) VALUES (1, 200, {$cct})",
            "INSERT INTO personnel_profile_specialty_codes (tenant_id, user_id, specialty_code_id, specialty_level_id) VALUES (1, 1, {$cct}, {$pjLevel})",
        ] as $sql) {
            try {
                $pdo->exec($sql);
                self::fail('Une relation incohérente a été acceptée.');
            } catch (PDOException $exception) {
                self::assertStringContainsString('FOREIGN KEY', $exception->getMessage());
            }
        }
        $pdo->exec("INSERT INTO personnel_profile_specialty_codes (tenant_id, user_id, specialty_code_id) VALUES (1, 1, {$cct})");
        $this->expectException(PDOException::class);
        $pdo->exec('DELETE FROM military_specialty_codes WHERE id = ' . $cct);
    }
}
