<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Core\Database;
use App\Repositories\AtakIngestPlayerStatsRepository;
use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Annuaire des comptes (administration du site) sur un vrai serveur MySQL ou MariaDB.
 *
 * SQLite accepte des requêtes que MySQL refuse (collations, ONLY_FULL_GROUP_BY) : ce test les exécute pour de bon.
 * Il est ignoré sans base de test ; la CI le lance sur MySQL 8 et MariaDB (voir .github/workflows/ci.yml) avec
 * COMSPEC_TEST_MYSQL_DSN, COMSPEC_TEST_MYSQL_USER et COMSPEC_TEST_MYSQL_PASS. La base est vidée au début.
 * Données fictives uniquement.
 */
final class PlatformUsersDirectoryMysqlTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $dsn = (string) getenv('COMSPEC_TEST_MYSQL_DSN');
        if ($dsn === '') {
            self::markTestSkipped('Base MySQL de test non configurée (COMSPEC_TEST_MYSQL_DSN).');
        }
        $this->pdo = new PDO($dsn, (string) getenv('COMSPEC_TEST_MYSQL_USER'), (string) getenv('COMSPEC_TEST_MYSQL_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Mêmes réglages de session que l'application, et le mode SQL strict par défaut de MySQL 8.
        $this->pdo->exec(Database::sessionInitSql());
        $this->pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $this->resetStaticCaches();
        $this->buildSchema();
        $this->seed();
    }

    private function resetStaticCaches(): void
    {
        foreach ((new \ReflectionClass(UserRepository::class))->getProperties(\ReflectionProperty::IS_STATIC) as $prop) {
            $type = $prop->getType();
            $prop->setValue(null, $type instanceof \ReflectionNamedType && $type->getName() === 'array' ? [] : null);
        }
    }

    private function buildSchema(): void
    {
        $root = dirname(__DIR__, 2);
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $this->pdo->exec('DROP TABLE `' . $table . '`');
        }
        // Tables de base telles que le schéma de départ les crée (collation par défaut du serveur).
        $schema = (string) file_get_contents($root . '/migrations/schema.sql');
        foreach (['tenants', 'roles', 'grades', 'users'] as $table) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` \(.*?\) ENGINE=InnoDB[^;]*;/s', $schema, $m) !== 1) {
                self::fail('Table ' . $table . ' introuvable dans migrations/schema.sql.');
            }
            $this->pdo->exec($m[0]);
        }
        // Colonnes ajoutées plus tard en production.
        $this->pdo->exec('ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN is_service_account TINYINT(1) NOT NULL DEFAULT 0');
        require_once $root . '/bootstrap/user_community_identity_migration.php';
        run_user_community_identity_migration($this->pdo);
        foreach (AtakIngestPlayerStatsRepository::schemaSql() as $sql) {
            $this->pdo->exec($sql);
        }
        $this->pdo->exec(
            'CREATE TABLE game_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                steam_id VARCHAR(32) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id), KEY idx_game_sessions_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        (new PlatformAccountBanRepository($this->pdo))->ready();
    }

    private function seed(): void
    {
        $this->pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'Alpha', 'alpha'), (2, 'Bravo', 'bravo')");
        $now = gmdate('Y-m-d H:i:s');
        $old = gmdate('Y-m-d H:i:s', time() - 200 * 86400);
        $recent = gmdate('Y-m-d H:i:s', time() - 2 * 86400);
        $rows = [
            [1, 'zed@example.test', 'Zed', 'Alpha1', 'active', '76561198000000001', $recent, $now],
            [2, 'amy@example.test', 'Amy', null, 'inactive', '76561198000000002', $old, $old],
            [3, 'bob@example.test', 'Bob', 'Bravo2', 'active', null, null, $old],
            [4, 'eve@example.test', 'Eve', 'Echo', 'active', '76561198000000004', $old, $old],
        ];
        $user = $this->pdo->prepare('INSERT INTO users (id, tenant_id, email, password_hash, display_name, callsign, status, steam_id, last_login_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $member = $this->pdo->prepare("INSERT INTO user_community_memberships (user_id, tenant_id, status) VALUES (?, ?, 'active')");
        foreach ($rows as $r) {
            $user->execute([$r[0], 1, $r[1], 'x', $r[2], $r[3], $r[4], $r[5], $r[6], $old, $r[7]]);
            $member->execute([$r[0], 1]);
        }
        $member->execute([1, 2]);
        $today = gmdate('Y-m-d');
        $this->pdo->exec("INSERT INTO atak_ingest_player_daily (tenant_id, day, steam_uid, callsign, bytes, last_at) VALUES
            (1, '{$today}', '76561198000000001', 'Alpha1', 5000, NOW()),
            (1, '{$today}', '76561198000000002', 'Amy', 100, NOW()),
            (1, '{$today}', 'cs:Ghost', 'Ghost', 70, NOW())");
        // Eve : session de jeu récente alors que sa dernière connexion au site est ancienne.
        $this->pdo->prepare('INSERT INTO game_sessions (user_id, tenant_id, steam_id, created_at, last_seen_at) VALUES (4, 1, ?, ?, ?)')
            ->execute(['76561198000000004', $old, $recent]);
        (new PlatformAccountBanRepository($this->pdo))->create('amy@example.test', 2, 'Amy', 'Motif de test', [], [], 1);
    }

    /** @return list<string> */
    private function emails(array $result): array
    {
        return array_map(static fn (array $g): string => (string) $g['email_key'], $result['groups']);
    }

    public function testEveryAllowedSortRunsOnRealMysqlWithoutError(): void
    {
        $repo = new UserRepository($this->pdo);
        foreach (UserRepository::PLATFORM_DIRECTORY_SORTS as $key) {
            foreach (['asc', 'desc'] as $dir) {
                $result = $repo->listGroupedAccountsForPlatformDirectory(null, null, null, 1, 50, null, ['sort' => $key, 'dir' => $dir]);
                self::assertSame(4, $result['total'], $key);
                self::assertCount(4, $result['groups'], $key . ' ' . $dir);
            }
        }
    }

    public function testVolumeSortPutsTheBiggestSenderFirst(): void
    {
        $repo = new UserRepository($this->pdo);
        $desc = $this->emails($repo->listGroupedAccountsForPlatformDirectory(null, null, null, 1, 50, null, ['sort' => 'volume', 'dir' => 'desc']));
        self::assertSame(['zed@example.test', 'amy@example.test'], array_slice($desc, 0, 2));
        $asc = $this->emails($repo->listGroupedAccountsForPlatformDirectory(null, null, null, 1, 50, null, ['sort' => 'volume', 'dir' => 'asc']));
        self::assertSame(['amy@example.test', 'zed@example.test'], array_slice($asc, -2));
        // Deuxième page : le tri est global, pas seulement sur la page.
        $page2 = $this->emails($repo->listGroupedAccountsForPlatformDirectory(null, null, null, 2, 10, null, ['sort' => 'volume', 'dir' => 'desc']));
        self::assertSame([], $page2);
    }

    public function testVolumeSortFallsBackToAStableOrderWhenTheSourceIsMissing(): void
    {
        $this->pdo->exec('DROP TABLE atak_ingest_player_daily');
        $this->resetStaticCaches();
        $repo = new UserRepository($this->pdo);
        $result = $repo->listGroupedAccountsForPlatformDirectory(null, null, null, 1, 50, null, ['sort' => 'volume', 'dir' => 'desc']);
        self::assertSame(4, $result['total']);
        self::assertCount(4, $result['groups']);
    }

    public function testBothLoginDatesAreSortableAndTheOldKeyStillWorks(): void
    {
        $repo = new UserRepository($this->pdo);
        $site = $this->emails($repo->listGroupedAccountsForPlatformDirectory(null, null, null, 1, 50, null, ['sort' => 'connexion_site', 'dir' => 'desc']));
        self::assertSame('zed@example.test', $site[0]);
        self::assertSame('bob@example.test', end($site), 'Jamais connecté au site : en dernier.');
        $old = $this->emails($repo->listGroupedAccountsForPlatformDirectory(null, null, null, 1, 50, null, ['sort' => 'connexion', 'dir' => 'desc']));
        self::assertSame($site, $old);
        $game = $this->emails($repo->listGroupedAccountsForPlatformDirectory(null, null, null, 1, 50, null, ['sort' => 'connexion_jeu', 'dir' => 'desc']));
        self::assertSame('eve@example.test', $game[0], 'Seule Eve a une session de jeu.');
        self::assertSame([4], array_keys($repo->lastGameSeenByUserIds([1, 2, 3, 4])));
    }

    public function testInactiveCategoryUsesSiteAndGameDatesWithTheExistingThreshold(): void
    {
        $repo = new UserRepository($this->pdo);
        $default = $repo->listGroupedAccountsForPlatformDirectory(null, 'inactifs', null, 1, 50, null, []);
        self::assertSame(['amy@example.test', 'bob@example.test'], $this->sorted($this->emails($default)), 'Eve a joué récemment, Zed s’est connecté au site.');
        self::assertSame(2, $default['total']);
        $thirty = $repo->listGroupedAccountsForPlatformDirectory(null, 'inactifs', null, 1, 50, '30', []);
        self::assertSame(['amy@example.test', 'bob@example.test'], $this->sorted($this->emails($thirty)));
        $never = $repo->listGroupedAccountsForPlatformDirectory(null, 'inactifs', null, 1, 50, 'jamais', []);
        self::assertSame(['bob@example.test'], $this->emails($never), 'Jamais connecté ni au site ni en jeu.');
        $sorted = $repo->listGroupedAccountsForPlatformDirectory(null, 'inactifs', null, 1, 50, null, ['sort' => 'connexion_jeu', 'dir' => 'asc']);
        self::assertSame(2, $sorted['total']);
    }

    public function testInactiveCategoryFallsBackToTheSiteDateWhenGameSessionsAreMissing(): void
    {
        $this->pdo->exec('DROP TABLE game_sessions');
        $this->resetStaticCaches();
        $repo = new UserRepository($this->pdo);
        $result = $repo->listGroupedAccountsForPlatformDirectory(null, 'inactifs', null, 1, 50, null, []);
        self::assertSame(['amy@example.test', 'bob@example.test', 'eve@example.test'], $this->sorted($this->emails($result)));
        $game = $repo->listGroupedAccountsForPlatformDirectory(null, null, null, 1, 50, null, ['sort' => 'connexion_jeu']);
        self::assertSame(4, $game['total']);
    }

    public function testFiltersCombineWithSortsOnRealMysql(): void
    {
        $repo = new UserRepository($this->pdo);
        $r = $repo->listGroupedAccountsForPlatformDirectory(null, 'banned', null, 1, 50, null, ['sort' => 'volume']);
        self::assertSame(['amy@example.test'], $this->emails($r));
        $r = $repo->listGroupedAccountsForPlatformDirectory(null, null, null, 1, 50, '90', ['sort' => 'volume', 'dir' => 'desc']);
        self::assertContains('amy@example.test', $this->emails($r));
        $r = $repo->listGroupedAccountsForPlatformDirectory(null, null, 1, 1, 10, null, ['sort' => 'communautes', 'dir' => 'desc']);
        self::assertSame('zed@example.test', $this->emails($r)[0]);
        $r = $repo->listGroupedAccountsForPlatformDirectory('zed', null, null, 1, 10, null, ['sort' => 'volume', 'created' => '', 'sanctioned' => '']);
        self::assertSame(['zed@example.test'], $this->emails($r));
    }

    /** @param list<string> $list @return list<string> */
    private function sorted(array $list): array
    {
        sort($list);

        return $list;
    }
}
