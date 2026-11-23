<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Core\Database;
use App\Repositories\CasNineLineRepository;
use App\Services\Tactical\AirBriefService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Briefs air sur un vrai serveur MySQL ou MariaDB en mode strict : étape 4910 (colonnes de livraison ajoutées une fois,
 * collation explicite), boîte du pilote (indicatifs sous plusieurs graphies, demande affectée par assigned_aircraft),
 * livraison notée au relevé, accusés et état exposé au web. Ignoré sans base de test ; la CI le lance sur MySQL 8 et
 * MariaDB (COMSPEC_TEST_MYSQL_DSN, COMSPEC_TEST_MYSQL_USER, COMSPEC_TEST_MYSQL_PASS). La base est vidée au début.
 * Données fictives uniquement.
 */
final class AtakAirBriefDeliveryMysqlTest extends TestCase
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
        foreach (['atak_nine_line', 'atak_nine_line_recipients', 'atak_air_assets'] as $table) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` \(.*?\) ENGINE=InnoDB[^;]*;/s', $schema, $m) !== 1) {
                self::fail('Table ' . $table . ' introuvable dans migrations/schema.sql.');
            }
            $this->pdo->exec($m[0]);
        }
    }

    private function step(): string
    {
        $pdo = $this->pdo;
        $root = dirname(__DIR__, 2);
        ob_start();
        require $root . '/database/steps/4970_atak_air_brief_delivery.php';

        return (string) ob_get_clean();
    }

    public function testStepAddsTheDeliveryColumnsOnceOnARealServer(): void
    {
        $this->pdo->exec('ALTER TABLE atak_nine_line_recipients DROP COLUMN polled_as, DROP COLUMN last_polled_at');
        self::assertStringContainsString('polled_as, last_polled_at', $this->step());
        self::assertStringContainsString('déjà en place', $this->step());
        $col = $this->pdo->query("SHOW FULL COLUMNS FROM atak_nine_line_recipients WHERE Field = 'polled_as'")->fetch();
        self::assertSame('utf8mb4_unicode_ci', $col['Collation']);
        self::assertSame('YES', $col['Null']);
    }

    public function testPilotInboxDeliveryAndAcknowledgementsOnARealServer(): void
    {
        $logs = [];
        $briefs = new AirBriefService($this->pdo, static function (string $m) use (&$logs): void {
            $logs[] = $m;
        });
        $repo = new CasNineLineRepository($this->pdo);
        $game = $repo->createCas(self::T, 1, 'HAMMER 11', ['mission_id' => 'CAS-1-100', 'lines' => ['line1' => 'IP DELTA', 'line6' => '0234 5678']]);
        $briefs->addRecipients(self::T, (int) $game['id'], ['TIGER 6', 'HOG 21']);
        self::assertCount(2, $logs, 'aucun appareil remonté : les deux indicatifs sont signalés');
        $web = $repo->createCas(self::T, 1, 'HAMMER 11', ['assigned_aircraft' => 'Viper 1-1', 'line1' => 'IP ECHO']);

        $body = $briefs->inboxResponse(self::T, '~air|Pilote|tiger-6|VIPER11');
        self::assertStringStartsWith('[["', $body);
        self::assertStringContainsString('"CAS-1-100"', $body);
        self::assertStringContainsString('"VIPER 1-1"', $body);

        $rows = $this->pdo->query('SELECT nine_line_id, callsign, status, polled_as FROM atak_nine_line_recipients ORDER BY id')->fetchAll();
        $by = [];
        foreach ($rows as $r) {
            $by[$r['callsign']] = [(int) $r['nine_line_id'], $r['status'], $r['polled_as']];
        }
        self::assertSame([(int) $game['id'], 'received', 'tiger-6'], $by['TIGER 6']);
        self::assertSame([(int) $game['id'], 'sent', null], $by['HOG 21']);
        self::assertSame([(int) $web['id'], 'received', 'VIPER11'], $by['VIPER 1-1'], 'destinataire ajouté pour la demande affectée');

        self::assertSame('ok', $briefs->acknowledge(self::T, 'CAS-1-100', 'read', 'TIGER6')['result']);
        self::assertSame('acknowledged', $briefs->acknowledge(self::T, 'CAS-1-100', 'wilco', 'Tiger 6')['recipient']['delivery']['status']);
        $line = $briefs->lineDelivery(self::T, 'CAS-1-100');
        self::assertNotNull($line);
        self::assertSame('acknowledged', $line['delivery']['status']);
        self::assertSame(1, $line['delivery']['counts']['acknowledged']);
        self::assertSame(1, $line['delivery']['counts']['sent'] + $line['delivery']['counts']['expired']);
        self::assertSame(2, count($line['recipients']));
    }
}
