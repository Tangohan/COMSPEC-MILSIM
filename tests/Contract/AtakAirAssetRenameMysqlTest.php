<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Core\Database;
use App\Repositories\AtakDataRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Indicatif d'avion choisi par le pilote, sur un vrai serveur MySQL ou MariaDB (clé unique tenant, carte, indicatif ;
 * colonnes JSON ; mode strict). Ignoré sans base de test ; la CI le lance sur MySQL 8 et MariaDB
 * (COMSPEC_TEST_MYSQL_DSN, COMSPEC_TEST_MYSQL_USER, COMSPEC_TEST_MYSQL_PASS). La base est vidée au début.
 */
final class AtakAirAssetRenameMysqlTest extends TestCase
{
    private const T = 7;

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
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->exec(Database::sessionInitSql());
        $this->pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $this->pdo->exec('DROP TABLE `' . $table . '`');
        }
        $schema = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/schema.sql');
        if (preg_match('/CREATE TABLE IF NOT EXISTS `atak_air_assets` \(.*?\) ENGINE=InnoDB[^;]*;/s', $schema, $m) !== 1) {
            self::fail('Table atak_air_assets introuvable dans migrations/schema.sql.');
        }
        $this->pdo->exec($m[0]);
    }

    /** @return array<string, mixed> */
    private static function occupancy(string $callsign, string $vehicleId, bool $explicit, float $x = 1000.0): array
    {
        return ['source' => 'occupancy', 'inferred' => true, 'vehicle_id' => $vehicleId, 'callsign' => $callsign,
            'callsign_explicit' => $explicit, 'pos_x' => $x, 'pos_y' => 2000.0, 'model' => 'A-10C', 'aircraft_type' => 'plane',
            'pilot' => 'paul_d', 'occupants' => [['name' => 'paul_d', 'seat' => 'driver', 'player' => true]], 'ordnance' => 'GBU-12 x2'];
    }

    /** @return list<string> */
    private function callsigns(): array
    {
        return array_map('strval', $this->pdo->query('SELECT callsign FROM atak_air_assets ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testPilotCallsignReplacesTheArmaGroupDefault(): void
    {
        $repo = new AtakDataRepository($this->pdo);
        $repo->upsertAirAsset(self::T, 1, 'Alpha 1-3', self::occupancy('Alpha 1-3', '2:51', false));
        $row = $repo->upsertAirAsset(self::T, 1, 'VIPER 1', self::occupancy('VIPER 1', '2:51', true));

        self::assertSame('VIPER 1', $row['callsign']);
        self::assertSame(['VIPER 1'], $this->callsigns());
    }

    public function testLiveHolderKeepsItsCallsignAndStaleHolderIsReplaced(): void
    {
        $repo = new AtakDataRepository($this->pdo);
        $repo->upsertAirAsset(self::T, 1, 'VIPER 1', self::occupancy('VIPER 1', '2:60', true, 8000.0));
        $repo->upsertAirAsset(self::T, 1, 'Alpha 1-3', self::occupancy('Alpha 1-3', '2:51', false));
        $repo->upsertAirAsset(self::T, 1, 'VIPER 1', self::occupancy('VIPER 1', '2:51', true));
        self::assertSame(['VIPER 1', 'Alpha 1-3'], $this->callsigns());

        $this->pdo->exec("UPDATE atak_air_assets SET updated_at = '2020-01-01 00:00:00' WHERE vehicle_id = '2:60'");
        $repo->upsertAirAsset(self::T, 1, 'VIPER 1', self::occupancy('VIPER 1', '2:51', true));
        self::assertSame(['VIPER 1'], $this->callsigns());
    }
}
