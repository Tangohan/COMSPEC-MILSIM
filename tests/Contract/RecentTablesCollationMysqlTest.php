<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Core\Database;
use App\Repositories\PlayerSessionRepository;
use App\Repositories\QualificationAwardRuleRepository;
use App\Support\Migrations\SchemaInspector;
use App\Support\Migrations\TableCollationAligner;
use App\Support\SqlText;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Piège des collations sur un vrai serveur MySQL 8 ou MariaDB 10.11 (job CI mysql-directory).
 *
 * Production (16/11) : « Illegal mix of collations (utf8mb4_unicode_ci,IMPLICIT) and (utf8mb4_uca1400_ai_ci,IMPLICIT)
 * for operation '=' » sur les widgets « Disponibilité à vérifier » et « Sessions de jeu récentes ». Les tables
 * historiques (migrations/schema.sql : users…) n'ont pas de COLLATE et suivent la collation par défaut du serveur
 * (utf8mb4_uca1400_ai_ci sous MariaDB 11.4+) ; player_sessions et les tables récentes sont en utf8mb4_unicode_ci.
 *
 * Ici, une base dédiée est créée avec une collation par défaut différente : utf8mb4_uca1400_ai_ci si le serveur la
 * connaît (MariaDB ≥ 10.10), sinon utf8mb4_0900_ai_ci (MySQL 8), sinon utf8mb4_general_ci. Le test prouve d'abord que
 * l'ancienne jointure échoue bien en 1267 (le piège est reproduit), puis que les requêtes réelles des widgets et
 * quelques jointures récentes ↔ historiques passent, et que l'étape 4900 aligne les tables récentes sans toucher
 * aux tables historiques. Ignoré sans base de test (COMSPEC_TEST_MYSQL_DSN, COMSPEC_TEST_MYSQL_USER,
 * COMSPEC_TEST_MYSQL_PASS) ; il faut le droit CREATE DATABASE.
 */
final class RecentTablesCollationMysqlTest extends TestCase
{
    private const DB = 'comspec_collation_trap';

    private PDO $root;

    private PDO $pdo;

    private string $trap = '';

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function setUp(): void
    {
        $dsn = (string) getenv('COMSPEC_TEST_MYSQL_DSN');
        if ($dsn === '') {
            self::markTestSkipped('Base MySQL de test non configurée (COMSPEC_TEST_MYSQL_DSN).');
        }
        $user = (string) getenv('COMSPEC_TEST_MYSQL_USER');
        $pass = (string) getenv('COMSPEC_TEST_MYSQL_PASS');
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
        $this->root = new PDO($dsn, $user, $pass, $options);
        foreach (['utf8mb4_uca1400_ai_ci', 'utf8mb4_0900_ai_ci', 'utf8mb4_general_ci'] as $candidate) {
            $st = $this->root->prepare('SELECT COUNT(*) FROM information_schema.COLLATIONS WHERE COLLATION_NAME = ?');
            $st->execute([$candidate]);
            if ((int) $st->fetchColumn() > 0) {
                $this->trap = $candidate;
                break;
            }
        }
        self::assertNotSame('', $this->trap);
        $this->root->exec('DROP DATABASE IF EXISTS ' . self::DB);
        $this->root->exec('CREATE DATABASE ' . self::DB . ' CHARACTER SET utf8mb4 COLLATE ' . $this->trap);

        $trapDsn = (string) preg_replace('/dbname=[^;]*/', 'dbname=' . self::DB, $dsn);
        $this->pdo = new PDO($trapDsn, $user, $pass, $options);
        // Connexion comme l'application : utf8mb4_unicode_ci (Database::sessionInitSql).
        $this->pdo->exec(Database::sessionInitSql());
        $this->pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Tables historiques : définitions de migrations/schema.sql, sans COLLATE (collation par défaut de la base).
        $schema = (string) file_get_contents(self::root() . '/migrations/schema.sql');
        foreach (['tenants', 'users'] as $table) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` \(.*?\) ENGINE=InnoDB[^;]*;/s', $schema, $m) !== 1) {
                self::fail('Table ' . $table . ' introuvable dans migrations/schema.sql.');
            }
            $this->pdo->exec($m[0]);
        }
        // Tables récentes : fichiers réels des étapes 4710, 4860 et de l'équipement remonté par le mod.
        $inspector = new SchemaInspector($this->pdo);
        foreach (['2026_11_18_100_player_sessions.sql', '2026_11_22_100_member_availability_checks.sql', '20261018000001_game_item_usage.sql'] as $file) {
            $inspector->runSqlFile(self::root() . '/migrations/' . $file);
        }
        // atak_terminal_sync telle que l'étape 2080 la créait (sans COLLATE) et atak_terminals (bootstrap réel).
        $this->pdo->exec(
            "CREATE TABLE atak_terminal_sync (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id INT UNSIGNED NOT NULL,
             terminal_uid VARCHAR(128) NOT NULL, callsign VARCHAR(64) DEFAULT NULL, reported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
             PRIMARY KEY (id), UNIQUE KEY uniq_atak_sync_terminal (tenant_id, terminal_uid)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $realism = require self::root() . '/bootstrap/atak_realism_registry_migration.php';
        ob_start();
        try {
            $realism($this->pdo);
        } finally {
            ob_end_clean();
        }

        $this->pdo->exec("INSERT INTO tenants (id, name, slug, created_at, updated_at) VALUES (1, 'Groupement Alpha', 'alpha', NOW(), NOW())");
        $this->pdo->exec("INSERT INTO users (id, tenant_id, email, password_hash, display_name, steam_id, status) VALUES
            (1, 1, 'a@exemple.fr', 'x', 'Tom Durand', '76561198000000001', 'active'),
            (2, 1, 'b@exemple.fr', 'x', 'Léa Martin', '76561198000000002', 'active'),
            (3, 1, 'c@exemple.fr', 'x', 'Sans Steam', NULL, 'active')");
        $recent = date('Y-m-d H:i:s', time() - 2 * 86400);
        $start = date('Y-m-d H:i:s', time() - 2 * 86400 - 5400);
        $this->pdo->exec("INSERT INTO player_sessions (tenant_id, user_id, steam_uid, started_at, last_seen_at, duration_s) VALUES (1, 1, '76561198000000001', '{$start}', '{$recent}', 5400)");
        $this->pdo->exec("INSERT INTO game_item_usage (tenant_id, steam_id, class_name, kind, usage_day) VALUES (1, '76561198000000002', 'arifle_MX_F', 'weapon', CURDATE())");
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            $this->root->exec('DROP DATABASE IF EXISTS ' . self::DB);
        }
    }

    private function collation(string $table, string $column = ''): string
    {
        if ($column === '') {
            $st = $this->pdo->prepare('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $st->execute([$table]);
        } else {
            $st = $this->pdo->prepare('SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $st->execute([$table, $column]);
        }

        return (string) $st->fetchColumn();
    }

    public function testTheTrapIsReproducedOnThisServer(): void
    {
        self::assertSame($this->trap, $this->collation('users', 'steam_id'), 'Table historique : collation par défaut de la base.');
        self::assertSame('utf8mb4_unicode_ci', $this->collation('player_sessions', 'steam_uid'));
        try {
            $this->pdo->query('SELECT COUNT(*) FROM player_sessions ps LEFT JOIN users u ON u.tenant_id = ps.tenant_id AND u.steam_id = ps.steam_uid')->fetchColumn();
            self::fail('L’ancienne jointure aurait dû échouer en 1267 (' . $this->trap . ').');
        } catch (PDOException $e) {
            self::assertStringContainsString('1267', $e->getMessage());
            self::assertStringContainsString('Illegal mix of collations', $e->getMessage());
        }
    }

    public function testDashboardWidgetQueriesRunWithoutCollationErrors(): void
    {
        $repo = new PlayerSessionRepository($this->pdo);
        $toVerify = $repo->membersToVerify(1, time() - 30 * 86400, 3600, 5);
        self::assertNull($repo->lastError(), (string) $repo->lastError());
        self::assertIsArray($toVerify);
        self::assertSame(2, $toVerify['total'], 'Léa (aucune session) et le membre sans Steam.');
        self::assertSame([2, 3], array_column($toVerify['rows'], 'id'), 'Jamais vus en jeu, par identifiant.');

        $recent = $repo->recentForTenant(1, time() - 14 * 86400, 8);
        self::assertNull($repo->lastError(), (string) $repo->lastError());
        self::assertIsArray($recent);
        self::assertCount(1, $recent);
        self::assertSame(1, $recent[0]['user_id']);
        self::assertSame('Tom Durand', $recent[0]['name']);

        // Fragment des alertes RH (RhAlertAggregatorService) : membres sans session qui compte.
        [$exists, $params] = PlayerSessionRepository::qualifyingExistsClause('u', time() - 30 * 86400, 3600, $this->pdo);
        $st = $this->pdo->prepare('SELECT u.id FROM users u WHERE u.tenant_id = 1 AND NOT ' . $exists . ' ORDER BY u.id');
        $st->execute($params);
        self::assertSame([2, 3], array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function testOtherRecentToHistoricJoinsRun(): void
    {
        // Règles d'attribution des qualifications : jours d'usage d'un objet (game_item_usage ↔ users).
        $values = (new QualificationAwardRuleRepository($this->pdo))->metricValues(1, 'item_days', 'kind:weapon', 365);
        self::assertSame([2 => 1], array_map('intval', $values));

        $st = $this->pdo->query('SELECT COUNT(*) FROM member_availability_checks c JOIN users u ON u.id = c.user_id AND u.tenant_id = c.tenant_id');
        self::assertSame(0, (int) $st->fetchColumn());
        $st = $this->pdo->query('SELECT COUNT(*) FROM game_item_usage g JOIN users u ON ' . SqlText::columnEquals($this->pdo, 'g.steam_id', 'u.steam_id'));
        self::assertSame(1, (int) $st->fetchColumn());
    }

    public function testStep4900AlignsRecentTablesOnlyAndIsIdempotent(): void
    {
        self::assertSame($this->trap, $this->collation('atak_terminal_sync'));
        $join = 'SELECT COUNT(*) FROM atak_terminals a LEFT JOIN atak_terminal_sync s ON s.tenant_id = a.tenant_id AND s.terminal_uid = a.terminal_uid';
        try {
            $this->pdo->query($join)->fetchColumn();
            self::fail('Jointure atak_terminals ↔ atak_terminal_sync attendue en 1267 avant l’étape.');
        } catch (PDOException $e) {
            self::assertStringContainsString('1267', $e->getMessage());
        }

        $first = $this->runStep();
        self::assertStringContainsString('[OK] atak_terminal_sync : ' . $this->trap . ' → utf8mb4_unicode_ci', $first);
        self::assertStringContainsString('Table user_community_membership_periods créée', $first);
        self::assertStringContainsString('table(s) historique(s) en ' . $this->trap . ', non modifiée(s)', $first);
        self::assertSame('utf8mb4_unicode_ci', $this->collation('atak_terminal_sync'));
        self::assertSame('utf8mb4_unicode_ci', $this->collation('atak_terminal_sync', 'terminal_uid'));
        self::assertSame('utf8mb4_unicode_ci', $this->collation('user_community_membership_periods'));
        self::assertSame(0, (int) $this->pdo->query($join)->fetchColumn());

        // Tables historiques jamais converties ; tables des étapes déjà en utf8mb4_unicode_ci même dans cette base.
        self::assertSame($this->trap, $this->collation('users'));
        self::assertSame($this->trap, $this->collation('users', 'steam_id'));
        foreach (['player_sessions', 'member_availability_checks', 'game_item_usage', 'atak_terminals'] as $table) {
            self::assertSame('utf8mb4_unicode_ci', $this->collation($table), $table);
            self::assertSame([], (new TableCollationAligner($this->pdo))->divergingColumns($table), $table);
        }

        $second = $this->runStep();
        self::assertStringContainsString('0 convertie(s)', $second);
        self::assertStringContainsString('Table user_community_membership_periods déjà présente', $second);
    }

    public function testNativeJsonBinaryCollationIsIgnoredButBinaryTextStillBlocksConversion(): void
    {
        self::assertContains(
            $this->collation('atak_certificates', 'metadata_json'),
            ['', 'utf8mb4_bin'],
            'MySQL expose JSON sans collation ; MariaDB expose son alias LONGTEXT en utf8mb4_bin.'
        );

        $this->pdo->exec(
            'CREATE TABLE binary_collation_probe (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $messages = '';
        $aligner = new TableCollationAligner($this->pdo, static function (string $message) use (&$messages): void {
            $messages .= $message;
        });

        self::assertSame([], $aligner->divergingColumns('atak_certificates'), 'Le JSON natif en utf8mb4_bin est attendu.');
        self::assertSame(
            ['payload' => 'utf8mb4_bin'],
            $aligner->divergingColumns('binary_collation_probe'),
            'Un vrai texte binaire reste divergent.'
        );
        self::assertSame(
            [
                'converted' => [],
                'already' => ['atak_certificates'],
                'missing' => [],
                'skipped' => ['binary_collation_probe'],
            ],
            $aligner->align(['atak_certificates', 'binary_collation_probe'])
        );
        self::assertStringNotContainsString('atak_certificates laissée telle quelle', $messages);
        self::assertStringContainsString('binary_collation_probe laissée telle quelle : colonne payload en utf8mb4_bin', $messages);
    }

    private function runStep(): string
    {
        $pdo = $this->pdo;
        $root = self::root();
        ob_start();
        try {
            require $root . '/database/steps/4900_mysql_recent_tables_collation.php';
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }
}
