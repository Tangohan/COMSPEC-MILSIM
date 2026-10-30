<?php

declare(strict_types=1);

namespace Tests\Contract;

use PDO;
use PHPUnit\Framework\TestCase;

final class MigrationQueueContractTest extends TestCase
{
    private ?string $fixture = null;
    private ?PDO $pdo = null;
    private array $connection = [];

    protected function setUp(): void
    {
        $dsn = getenv('MIGRATION_QUEUE_TEST_DSN');
        if (!$dsn) self::markTestSkipped('Set MIGRATION_QUEUE_TEST_DSN to isolated migration_queue_test.');
        $this->pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        self::assertSame('migration_queue_test', $this->pdo->query('SELECT DATABASE()')->fetchColumn());
        preg_match('/(?:^mysql:|;)host=([^;]+)/', $dsn, $host);
        preg_match('/(?:^mysql:|;)port=([^;]+)/', $dsn, $port);
        $this->connection = ['DB_HOST' => $host[1] ?? '127.0.0.1', 'DB_PORT' => $port[1] ?? '3306', 'DB_NAME' => 'migration_queue_test', 'DB_USER' => 'root', 'DB_PASSWORD' => ''];
        foreach (['schema_migration_runs', 'future_module_fixture', 'preserved_work', 'comspec_schema_migrations'] as $table) $this->pdo->exec('DROP TABLE IF EXISTS ' . $table);
        $this->pdo->exec("CREATE TABLE preserved_work(id INT PRIMARY KEY, label VARCHAR(120)); INSERT INTO preserved_work VALUES(14,'CNOS / RFL');
            CREATE TABLE comspec_schema_migrations(name VARCHAR(120), hash VARCHAR(120)); INSERT INTO comspec_schema_migrations VALUES('old.sql','unchanged-ledger');");
        $this->fixture = sys_get_temp_dir() . '/athena-migration-contract-' . bin2hex(random_bytes(8));
        foreach (['database/steps', 'app/Support/Migrations', 'bootstrap', 'migrations'] as $directory) mkdir($this->fixture . '/' . $directory, 0775, true);
        $root = dirname(__DIR__, 2);
        foreach (['run-migrations.php', 'app/Support/Migrations/MigrationRegistry.php', 'app/Support/Migrations/MigrationRunLog.php', 'app/Support/Migrations/MigrationStepTracker.php', 'bootstrap/migration_pdo.php', 'bootstrap/migration_runner.php', 'database/steps/_context.php'] as $file) copy($root . '/' . $file, $this->fixture . '/' . $file);
        file_put_contents($this->fixture . '/bootstrap/autoload.php', '<?php // Future step is self-contained.');
        file_put_contents($this->fixture . '/migrations/old.sql', 'THIS_ARCHIVE_MUST_NEVER_EXECUTE');
        file_put_contents($this->fixture . '/database/steps/registry.php', <<<'REGISTRY'
<?php
return [['id'=>'future_module', 'label'=>'Future module', 'group'=>'schema', 'file'=>'future_module.php', 'rerun'=>true]];
REGISTRY);
        file_put_contents($this->fixture . '/database/steps/future_module.php', <<<'STEP'
<?php
$pdo->exec('CREATE TABLE IF NOT EXISTS future_module_fixture(id INT PRIMARY KEY)');
file_put_contents($root . '/executions.txt', 'x', FILE_APPEND);
echo "[OK] Nouvelle table prête.\n";
STEP);
    }

    protected function tearDown(): void
    {
        if ($this->fixture === null) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->fixture, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            if ($entry->isDir()) rmdir($entry->getPathname());
            else unlink($entry->getPathname());
        }
        rmdir($this->fixture);
    }

    private function command(array $arguments = []): array
    {
        $pipes = [];
        $process = proc_open([PHP_BINARY, $this->fixture . '/run-migrations.php', ...$arguments], [1=>['pipe','w'], 2=>['pipe','w']], $pipes, $this->fixture, $this->connection);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process), $output];
    }

    public function testFutureStepRunsOnceThenOnlyOnExplicitRetryAndPreservesHistoricData(): void
    {
        [$exit, $output] = $this->command();
        self::assertSame(0, $exit, $output);
        self::assertSame('x', file_get_contents($this->fixture . '/executions.txt'));
        self::assertSame('ok', $this->pdo->query("SELECT status FROM schema_migration_runs WHERE step_id='future_module' ORDER BY id DESC LIMIT 1")->fetchColumn());
        [$exit, $output] = $this->command();
        self::assertSame(0, $exit, $output);
        self::assertStringContainsString('déjà appliquées', $output);
        self::assertSame('x', file_get_contents($this->fixture . '/executions.txt'));
        [$exit, $output] = $this->command(['--status']);
        self::assertSame(0, $exit, $output);
        self::assertStringContainsString('[APPLIQUÉE] future_module', $output);
        [$exit, $output] = $this->command(['--step=future_module']);
        self::assertSame(0, $exit, $output);
        self::assertSame('xx', file_get_contents($this->fixture . '/executions.txt'));
        self::assertSame('CNOS / RFL', $this->pdo->query('SELECT label FROM preserved_work WHERE id=14')->fetchColumn());
        self::assertSame('unchanged-ledger', $this->pdo->query('SELECT hash FROM comspec_schema_migrations')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM schema_migration_runs')->fetchColumn());
    }

    public function testFailedFutureStepStaysPendingAndCanBeAppliedOnTheNextRun(): void
    {
        $file = $this->fixture . '/database/steps/future_module.php';
        file_put_contents($file, '<?php throw new RuntimeException("Expected fixture failure");');
        [$exit, $output] = $this->command();
        self::assertSame(1, $exit, $output);
        self::assertSame('error', $this->pdo->query("SELECT status FROM schema_migration_runs WHERE step_id='future_module' ORDER BY id DESC LIMIT 1")->fetchColumn());
        [$exit, $output] = $this->command(['--status']);
        self::assertSame(0, $exit, $output);
        self::assertStringContainsString('[EN ATTENTE] future_module', $output);
        file_put_contents($file, '<?php $pdo->exec("CREATE TABLE IF NOT EXISTS future_module_fixture(id INT PRIMARY KEY)"); echo "[OK] Table prête.";');
        [$exit, $output] = $this->command();
        self::assertSame(0, $exit, $output);
        self::assertSame('ok', $this->pdo->query("SELECT status FROM schema_migration_runs WHERE step_id='future_module' ORDER BY id DESC LIMIT 1")->fetchColumn());
        self::assertSame('CNOS / RFL', $this->pdo->query('SELECT label FROM preserved_work WHERE id=14')->fetchColumn());
    }
}
