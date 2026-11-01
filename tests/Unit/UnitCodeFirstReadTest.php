<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\UnitCodeRepository;
use App\Services\Effectifs\UnitCodeService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Non-régression (2026-11-01) : en production, les codes unité restaient invisibles.
 * Le fichier SQL était découpé sur chaque « ; », y compris celui d'un commentaire de colonne :
 * la création des tables échouait, l'erreur était seulement journalisée, et aucun code n'était calculé.
 */
final class UnitCodeFirstReadTest extends TestCase
{
    protected function setUp(): void
    {
        UnitCodeService::forget();
        UnitCodeRepository::resetCache();
    }

    protected function tearDown(): void
    {
        UnitCodeService::forget();
        UnitCodeRepository::resetCache();
    }

    public function testMigrationFileSplitsIntoTwoCompleteStatements(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/' . UnitCodeRepository::MIGRATION_FILE);
        $statements = UnitCodeRepository::statements($sql);

        self::assertCount(2, $statements);
        foreach ($statements as $statement) {
            self::assertStringStartsWith('CREATE TABLE IF NOT EXISTS', $statement);
            self::assertStringEndsWith('COLLATE=utf8mb4_unicode_ci', $statement);
            self::assertSame(substr_count($statement, '('), substr_count($statement, ')'));
            self::assertSame(0, substr_count($statement, "'") % 2);
        }
        // Un « ; » entre guillemets ne coupe plus l'instruction.
        self::assertSame(
            ["CREATE TABLE t (a int COMMENT 'x ; y')", 'SELECT 1'],
            UnitCodeRepository::statements("-- en-tête ; ignoré\nCREATE TABLE t (a int COMMENT 'x ; y');\nSELECT 1;\n")
        );
    }

    public function testCodesExistOnFirstReadWithoutAnyPriorAction(): void
    {
        $pdo = new MysqlDdlOnSqlitePdo();
        $pdo->exec("CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT);
            CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, parent_id INTEGER NULL, name TEXT NOT NULL, code TEXT NULL, display_order INTEGER DEFAULT 0);
            INSERT INTO tenants VALUES (3, 'Task Force Ranger', 'tfr'), (14, '160th SOAR', 'soar');
            INSERT INTO units (id, tenant_id, parent_id, name) VALUES (1, 14, NULL, '160th SOAR'), (2, 14, 1, 'B Squadron'), (3, 14, 2, 'Troop A'), (4, 14, 3, 'Groupe 1')");

        // Aucune table de codes, aucune unité modifiée : la première lecture doit tout initialiser.
        $codes = (new UnitCodeService(new UnitCodeRepository($pdo)))->sync(14);

        self::assertSame(2, $pdo->createdTables, 'Les deux tables sont créées par le fichier SQL réel.');
        self::assertNotNull($codes['community']);
        self::assertSame('SOAR', $codes['community']['code'], 'Code de communauté proposé depuis le nom.');
        self::assertSame('0002', $codes['community']['admin_id'], 'Communautés numérotées dans l’ordre de création.');
        self::assertSame(['SOAR', 'SOAR-B', 'SOAR-B-A', 'SOAR-B-A-1'], array_column($codes['units'], 'code'));
        self::assertSame(['0002-0001', '0002-0002', '0002-0003', '0002-0004'], array_column($codes['units'], 'admin_id'));
        self::assertSame(4, (int) $pdo->query('SELECT COUNT(*) FROM unit_codes WHERE tenant_id = 14')->fetchColumn());
        self::assertSame('TFR', $pdo->query('SELECT community_code FROM tenant_unit_codes WHERE tenant_id = 3')->fetchColumn());

        // Lecture suivante : rien n'est recréé, les identifiants restent les mêmes.
        UnitCodeService::forget();
        $again = (new UnitCodeService(new UnitCodeRepository($pdo)))->sync(14);
        self::assertSame(2, $pdo->createdTables);
        self::assertSame(array_column($codes['units'], 'admin_id'), array_column($again['units'], 'admin_id'));
    }
}

/**
 * SQLite qui accepte le fichier de création MySQL des codes unité : chaque instruction doit être
 * complète (sinon échec, comme MySQL), puis l'équivalent SQLite est créé.
 */
final class MysqlDdlOnSqlitePdo extends PDO
{
    public int $createdTables = 0;

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    public function exec(string $statement): int|false
    {
        if (!str_contains($statement, 'ENGINE=InnoDB') && !preg_match('/^CREATE TABLE IF NOT EXISTS `(tenant_unit_codes|unit_codes)`/', $statement)) {
            return parent::exec($statement);
        }
        if (!str_ends_with(trim($statement), 'COLLATE=utf8mb4_unicode_ci') || substr_count($statement, "'") % 2 !== 0) {
            throw new \PDOException('SQLSTATE[42000]: Syntax error near ' . substr($statement, -40));
        }
        $this->createdTables++;
        if (str_contains($statement, '`tenant_unit_codes`')) {
            return parent::exec('CREATE TABLE IF NOT EXISTS tenant_unit_codes (tenant_id INTEGER PRIMARY KEY, community_code TEXT NOT NULL UNIQUE,
                admin_number INTEGER NOT NULL UNIQUE, next_unit_number INTEGER NOT NULL DEFAULT 1, is_custom INTEGER NOT NULL DEFAULT 0, updated_at TEXT)');
        }

        return parent::exec("CREATE TABLE IF NOT EXISTS unit_codes (unit_id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, segment TEXT NOT NULL DEFAULT '',
            code_path TEXT NOT NULL, admin_number INTEGER NOT NULL, updated_at TEXT, UNIQUE (tenant_id, code_path), UNIQUE (tenant_id, admin_number))");
    }
}
