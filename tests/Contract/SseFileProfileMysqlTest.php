<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Core\Database;
use App\Repositories\SseFileRecordRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/** Le signalement des identités doit fonctionner avec les préparations natives de MySQL et MariaDB. */
final class SseFileProfileMysqlTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?PDO $savedPdo = null;
    private bool $pdoReplaced = false;
    private SseFileRecordRepository $records;

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
        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $this->savedPdo = $prop->getValue();
        $prop->setValue(null, $this->pdo);
        $this->pdoReplaced = true;

        (require dirname(__DIR__, 2) . '/bootstrap/sse_files_records_migration.php')($this->pdo);
        $this->records = new SseFileRecordRepository();
        // Schéma réel, données isolées puis restaurées à la fin du test.
        $this->pdo->beginTransaction();
        $this->pdo->exec('DELETE FROM sse_file_profiles');
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        if ($this->pdoReplaced) {
            (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        }
        $this->pdo = null;
    }

    public function testCreatesAnEmptyProfileWithBothTimestamps(): void
    {
        $this->records->saveProfile(1, 10, [], null);
        $row = $this->records->profile(1, 10);
        self::assertNotNull($row);
        self::assertNull($row['height_cm']);
        self::assertNull($row['marks']);
        self::assertNull($row['updated_by_user_id']);
        self::assertNotEmpty($row['created_at']);
        self::assertSame($row['created_at'], $row['updated_at']);
    }

    public function testCreatesAProfileWithEmptyOptionalFields(): void
    {
        $this->records->saveProfile(1, 10, [
            'height_cm' => '', 'weight_kg' => '', 'hair' => '', 'eyes' => '', 'marks' => '',
        ], 11);
        $row = $this->records->profile(1, 10);
        self::assertNotNull($row);
        foreach (['height_cm', 'weight_kg', 'hair', 'eyes', 'marks'] as $key) {
            self::assertNull($row[$key], $key);
        }
        self::assertSame(11, (int) $row['updated_by_user_id']);
        self::assertSame($row['created_at'], $row['updated_at']);
    }

    public function testUpdatesOnlySuppliedFieldsInTheRightCommunity(): void
    {
        $this->records->saveProfile(1, 10, [
            'height_cm' => '182', 'weight_kg' => '80', 'hair' => 'brown', 'eyes' => 'blue', 'marks' => 'Cicatrice au sourcil',
        ], 11);
        $created = $this->records->profile(1, 10);
        self::assertNotNull($created);
        self::assertSame(182, $created['height_cm']);
        self::assertSame(80, $created['weight_kg']);
        self::assertSame('Cicatrice au sourcil', $created['marks']);
        self::assertSame($created['created_at'], $created['updated_at']);

        $this->records->saveProfile(2, 10, ['height_cm' => 175], 20);
        $other = $this->records->profile(2, 10);
        $this->records->saveProfile(1, 10, ['height_cm' => 190, 'hair' => null], 12);
        $updated = $this->records->profile(1, 10);
        self::assertNotNull($updated);
        self::assertSame($created['id'], $updated['id']);
        self::assertSame($created['created_at'], $updated['created_at']);
        self::assertSame(190, $updated['height_cm']);
        self::assertSame(80, $updated['weight_kg']);
        self::assertNull($updated['hair']);
        self::assertSame('blue', $updated['eyes']);
        self::assertSame($created['marks'], $updated['marks']);
        self::assertSame(12, (int) $updated['updated_by_user_id']);
        self::assertSame($other, $this->records->profile(2, 10));

        $this->records->saveProfile(1, 10, [], null);
        self::assertSame($updated, $this->records->profile(1, 10));
    }
}
