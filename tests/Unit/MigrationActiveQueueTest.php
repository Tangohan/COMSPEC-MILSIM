<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\UnitCodeRepository;
use App\Support\Migrations\MigrationRegistry;
use App\Support\Migrations\SchemaInspector;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;
use Tests\Support\MysqlInformationSchemaPdo;

require_once dirname(__DIR__, 2) . '/bootstrap/migration_runner.php';

/**
 * Qualité de chaque entrée de la file active : fichier présent, identifiant unique et neuf, étape autonome
 * qui ne balaie aucune archive, et relançable sans rien modifier une seconde fois.
 */
final class MigrationActiveQueueTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function setUp(): void
    {
        UnitCodeRepository::resetCache();
    }

    /** @return list<array<string, mixed>> */
    private static function rawRegistry(): array
    {
        $raw = require self::root() . '/database/steps/registry.php';
        self::assertIsArray($raw);

        return array_values($raw);
    }

    public function testEveryActiveEntryIsExplicitUniqueRerunnableAndOutsideTheArchive(): void
    {
        $raw = self::rawRegistry();
        $active = MigrationRegistry::load(self::root());
        // Aucune entrée écartée en silence par le chargeur (identifiant invalide, chemin, doublon).
        self::assertCount(count($raw), $active);
        $ids = array_column($raw, 'id');
        self::assertSame(count($ids), count(array_unique($ids)), 'Identifiant en double dans la file active.');
        $files = array_column($raw, 'file');
        self::assertSame(count($files), count(array_unique($files)), 'Fichier inscrit deux fois.');

        $archive = require self::root() . '/database/steps/registry-archive.php';
        $archivedIds = array_column($archive, 'id');
        $archivedFiles = array_column($archive, 'file');
        foreach ($active as $step) {
            self::assertFileExists($step['path']);
            self::assertSame(self::root() . '/database/steps/' . $step['file'], $step['path']);
            self::assertTrue($step['rerun'], $step['id'] . ' : une étape de la file active doit être relançable (idempotente).');
            self::assertSame('schema', $step['group'], $step['id']);
            self::assertNotSame($step['id'], $step['label'], $step['id'] . ' : libellé lisible attendu.');
            self::assertNotContains($step['id'], $archivedIds, $step['id'] . ' : identifiant de l’ancien registre réutilisé.');
            self::assertNotContains($step['file'], $archivedFiles, $step['file'] . ' : fichier de l’ancien registre réutilisé.');
            self::assertNotContains($step['file'], ['_context.php', 'registry.php', 'registry-archive.php']);
        }
    }

    public function testActiveStepsNeverScanArchivesNorCallTheHistoricalPipeline(): void
    {
        foreach (MigrationRegistry::load(self::root()) as $step) {
            $source = (string) file_get_contents($step['path']);
            foreach (['glob(', 'scandir(', 'opendir(', 'DirectoryIterator', 'registry-archive', 'runPendingSqlFiles', 'listSqlFiles', 'runSchema(', 'schema.sql', '--replay', 'migrations_full_post'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $step['file'] . ' : ' . $forbidden);
            }
            // Un fichier SQL utilisé est désigné précisément et existe.
            preg_match_all('#migrations/[^\'"\s*]+\.sql#', $source, $sql);
            foreach ($sql[0] as $file) {
                self::assertFileExists(self::root() . '/' . $file, $step['file']);
            }
        }
    }

    /** Base vide de départ : seules les tables que les étapes complètent existent déjà. */
    private static function baseline(): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        foreach ([
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, callsign TEXT, steam_id TEXT, deleted_at TEXT)',
            'CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT)',
            'CREATE TABLE arsenal_wardrobes (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, collection_id INTEGER)',
            'CREATE TABLE atak_pings (id INTEGER PRIMARY KEY, tenant_id INTEGER, map_id INTEGER, created_at TEXT)',
            'CREATE TABLE atak_units (id INTEGER PRIMARY KEY, tenant_id INTEGER, map_id INTEGER, call_sign TEXT)',
            "INSERT INTO tenants (id, name, slug) VALUES (1, 'Special Operations Aviation', 'soar'), (2, 'Task Force Phantom', 'tf-phantom')",
        ] as $sql) {
            $pdo->exec($sql);
        }
        $pdo->changes = 0;
        $pdo->ddl = [];

        return $pdo;
    }

    /** Exécute une étape comme run-migrations.php : fichier inclus avec $pdo et $root, sortie capturée. */
    private static function runStep(string $path, PDO $pdo): string
    {
        $root = self::root();
        $migrationFlush = static function (): void {
        };
        $migrationEnsurePdo = static function (): void {
        };
        require $root . '/database/steps/_context.php';
        ob_start();
        try {
            require $path;
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    private static function runQueue(PDO $pdo): string
    {
        $out = '';
        foreach (MigrationRegistry::load(self::root()) as $step) {
            $out .= self::runStep($step['path'], $pdo);
        }

        return $out;
    }

    public function testEveryActiveStepRunsTwiceAndTheSecondPassChangesNothing(): void
    {
        $pdo = self::baseline();
        $first = self::runQueue($pdo);
        self::assertStringNotContainsString('[ERREUR]', $first);
        self::assertGreaterThan(0, $pdo->changes);

        $schema = new SchemaInspector($pdo);
        foreach (['academy_courses', 'academy_learners', 'personnel_job_role_structure', 'personnel_job_role_recovery_log', 'user_public_cv',
            'member_grade_changes', 'tenant_unit_codes', 'unit_codes', 'steam_conflict_reports', 'user_totp_used_steps',
            'academy_course_shares', 'academy_course_links', 'workflow_instances', 'workflow_events'] as $table) {
            self::assertTrue($schema->tableExists($table), $table);
        }
        self::assertTrue($schema->columnExists('user_public_cv', 'banner_path'));
        self::assertTrue($schema->columnExists('arsenal_wardrobes', 'visibility'));
        self::assertTrue($schema->columnExists('units', 'sidc'));
        self::assertTrue($schema->columnExists('units', 'echelon'));
        self::assertTrue($schema->columnExists('academy_courses', 'archived_at'));
        self::assertTrue($schema->columnExists('academy_courses', 'deleted_at'));
        self::assertTrue($schema->indexExists('users', 'idx_users_steam_id'));
        self::assertTrue($schema->indexExists('atak_pings', 'idx_atak_pings_tenant_map_created'));
        self::assertTrue($schema->indexExists('atak_units', 'idx_atak_units_tenant_map_callsign'));
        $codes = $pdo->query('SELECT tenant_id, admin_number FROM tenant_unit_codes ORDER BY tenant_id')->fetchAll(PDO::FETCH_KEY_PAIR);
        self::assertSame([1 => 1, 2 => 2], array_map('intval', $codes));

        // Relance complète (passage manuel, --step=… ou nouveau ticket) : rien n'est modifié.
        $pdo->changes = 0;
        $pdo->ddl = [];
        UnitCodeRepository::resetCache();
        $second = self::runQueue($pdo);
        self::assertSame(0, $pdo->changes, 'Modifications au second passage : ' . implode(' | ', $pdo->ddl));
        self::assertStringNotContainsString('[ERREUR]', $second);
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM tenant_unit_codes')->fetchColumn());

        // Une communauté créée entre deux passages reçoit le numéro suivant, les autres gardent le leur.
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (3, 'Nouvelle communauté', 'nouvelle')");
        self::runStep(MigrationRegistry::find(self::root(), 'unit_codes')['path'], $pdo);
        self::assertSame(3, (int) $pdo->query('SELECT admin_number FROM tenant_unit_codes WHERE tenant_id = 3')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT admin_number FROM tenant_unit_codes WHERE tenant_id = 1')->fetchColumn());
    }

    public function testTablesAlreadyCreatedOnDemandAreLeftUntouched(): void
    {
        $pdo = self::baseline();
        // Filet de sécurité déjà passé en production : la table existe, sans la colonne ajoutée plus tard.
        (new SchemaInspector($pdo))->runSqlFile(self::root() . '/' . \App\Repositories\UserPublicCvRepository::MIGRATION_FILE);
        $pdo->exec("INSERT INTO user_public_cv (user_id, tenant_id, enabled, slug) VALUES (7, 1, 1, 'viper')");
        $pdo->changes = 0;
        $pdo->ddl = [];
        $out = self::runStep(MigrationRegistry::find(self::root(), 'user_public_cv')['path'], $pdo);
        self::assertStringContainsString('déjà présente', $out);
        self::assertSame(0, $pdo->changes);
        self::runStep(MigrationRegistry::find(self::root(), 'user_public_cv_banner')['path'], $pdo);
        self::assertSame(1, $pdo->changes);
        self::assertSame('viper', $pdo->query('SELECT slug FROM user_public_cv WHERE user_id = 7')->fetchColumn());
    }

    public function testIndexAndColumnStepsSkipMissingTablesWithoutError(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        foreach (['arsenal_wardrobes_visibility', 'users_steam_id_index', 'atak_api_hot_indexes'] as $id) {
            $out = self::runStep(MigrationRegistry::find(self::root(), $id)['path'], $pdo);
            self::assertStringContainsString('[OK]', $out, $id);
        }
        self::assertSame(0, $pdo->changes);
    }

    public function testMysqlIndexesAlreadyAppliedByHandAreDetectedThroughInformationSchema(): void
    {
        $atak = MigrationRegistry::find(self::root(), 'atak_api_hot_indexes')['path'];
        // Production : scripts/sql/api-hot-indexes.sql déjà passé à la main.
        $pdo = (new MysqlInformationSchemaPdo())
            ->givenTable('atak_pings', ['tenant_id', 'map_id', 'created_at'])
            ->givenTable('atak_units', ['tenant_id', 'map_id', 'call_sign'])
            ->givenIndex('atak_pings', 'idx_atak_pings_tenant_map_created', 'tenant_id')
            ->givenIndex('atak_units', 'idx_atak_units_tenant_map_callsign', 'tenant_id');
        self::assertStringContainsString('déjà présent', self::runStep($atak, $pdo));
        self::assertSame([], $pdo->ddl);

        // Base sans les index : un seul ALTER par index, puis plus rien à la relance.
        $fresh = (new MysqlInformationSchemaPdo())
            ->givenTable('atak_pings', ['tenant_id', 'map_id', 'created_at'])
            ->givenTable('atak_units', ['tenant_id', 'map_id', 'call_sign']);
        self::runStep($atak, $fresh);
        self::assertSame([
            'ALTER TABLE `atak_pings` ADD INDEX `idx_atak_pings_tenant_map_created` (`tenant_id`, `map_id`, `created_at`)',
            'ALTER TABLE `atak_units` ADD INDEX `idx_atak_units_tenant_map_callsign` (`tenant_id`, `map_id`, `call_sign`)',
        ], $fresh->ddl);
        self::runStep($atak, $fresh);
        self::assertCount(2, $fresh->ddl);

        // Colonne d'une table absente : aucun ALTER.
        $partial = (new MysqlInformationSchemaPdo())->givenTable('atak_units', ['tenant_id', 'map_id']);
        self::runStep($atak, $partial);
        self::assertSame([], $partial->ddl);
    }

    public function testMysqlSteamIdIndexReusesAnyExistingIndexLeadingWithSteamId(): void
    {
        $steam = MigrationRegistry::find(self::root(), 'users_steam_id_index')['path'];
        $indexed = (new MysqlInformationSchemaPdo())->givenTable('users', ['id', 'steam_id'])->givenIndex('users', 'users_steam_id_unique', 'steam_id');
        self::assertStringContainsString('déjà indexée', self::runStep($steam, $indexed));
        self::assertSame([], $indexed->ddl);

        $bare = (new MysqlInformationSchemaPdo())->givenTable('users', ['id', 'steam_id']);
        self::runStep($steam, $bare);
        self::runStep($steam, $bare);
        self::assertSame(['ALTER TABLE `users` ADD INDEX `idx_users_steam_id` (`steam_id`)'], $bare->ddl);
    }

    public function testMysqlColumnStepsAlterOnlyOnce(): void
    {
        $wardrobe = MigrationRegistry::find(self::root(), 'arsenal_wardrobes_visibility')['path'];
        $pdo = (new MysqlInformationSchemaPdo())->givenTable('arsenal_wardrobes', ['id', 'collection_id']);
        self::runStep($wardrobe, $pdo);
        self::runStep($wardrobe, $pdo);
        self::assertSame(["ALTER TABLE `arsenal_wardrobes` ADD COLUMN `visibility` ENUM('tenant','personal') NOT NULL DEFAULT 'tenant' AFTER collection_id"], $pdo->ddl);

        $banner = MigrationRegistry::find(self::root(), 'user_public_cv_banner')['path'];
        $cv = (new MysqlInformationSchemaPdo())->givenTable('user_public_cv', ['user_id', 'banner_path']);
        self::runStep($banner, $cv);
        self::assertSame([], $cv->ddl);
    }
}
