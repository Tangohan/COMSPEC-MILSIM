<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Migrations\MigrationRegistry;
use PHPUnit\Framework\TestCase;

final class MigrationQueueResetTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        $this->fixture = sys_get_temp_dir() . '/athena-migration-queue-' . bin2hex(random_bytes(8));
        foreach (['database/steps', 'app/Support/Migrations', 'bootstrap', 'migrations'] as $directory) {
            mkdir($this->fixture . '/' . $directory, 0775, true);
        }
        $root = dirname(__DIR__, 2);
        foreach (['run-migrations.php', 'app/Support/Migrations/MigrationRegistry.php', 'app/Support/Migrations/MigrationRunLog.php', 'app/Support/Migrations/MigrationStepTracker.php'] as $file) {
            copy($root . '/' . $file, $this->fixture . '/' . $file);
        }
        file_put_contents($this->fixture . '/database/steps/registry.php', '<?php return [];');
        // Any accidental historical bootstrap/environment or SQL execution must fail the test.
        file_put_contents($this->fixture . '/bootstrap/autoload.php', '<?php throw new RuntimeException("BOOTSTRAP_MUST_NOT_RUN");');
        file_put_contents($this->fixture . '/bootstrap/migration_pdo.php', '<?php throw new RuntimeException("DB_MUST_NOT_CONNECT");');
        file_put_contents($this->fixture . '/migrations/20990101000000_should_never_run.sql', 'THIS_SQL_MUST_NEVER_RUN');
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->fixture, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            if ($entry->isDir()) rmdir($entry->getPathname());
            else unlink($entry->getPathname());
        }
        rmdir($this->fixture);
    }

    private function runCommand(array $arguments = []): array
    {
        $pipes = [];
        $process = proc_open([PHP_BINARY, $this->fixture . '/run-migrations.php', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->fixture, []);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    }

    public function testEmptyRunListAndStatusSucceedWithoutEnvironmentDatabaseOrOldSql(): void
    {
        foreach ([[], ['--list'], ['--status']] as $arguments) {
            [$exit, $output] = $this->runCommand($arguments);
            self::assertSame(0, $exit, $output);
            self::assertStringContainsString('file active est vide', $output);
            self::assertStringNotContainsString('MUST_NOT', $output);
            self::assertStringNotContainsString('[ERREUR]', $output);
        }
    }

    public function testHistoricalReplayAndRetiredStepAreRejectedBeforeAnyConnection(): void
    {
        foreach ([['--replay-all'], ['--replay=old.sql'], ['--step=sql_files'], ['--step=community_access_roles_purge_v1'], ['--step=']] as $arguments) {
            [$exit, $output] = $this->runCommand($arguments);
            self::assertSame(1, $exit, $output);
            self::assertStringContainsString('[ERREUR]', $output);
            self::assertStringNotContainsString('MUST_NOT', $output);
        }
    }

    public function testFutureRegistryOnlyExposesExplicitValidNewSteps(): void
    {
        file_put_contents($this->fixture . '/database/steps/registry.php', <<<'REGISTRY'
<?php
return [
    ['id'=>'future_table', 'label'=>'Future table', 'group'=>'schema', 'file'=>'future_table.php', 'rerun'=>true],
    ['id'=>'invalid/path', 'file'=>'old.php'],
    ['id'=>'escape', 'file'=>'../old.php'],
    ['id'=>'future_table', 'file'=>'duplicate.php'],
];
REGISTRY);
        $steps = MigrationRegistry::load($this->fixture);
        self::assertCount(1, $steps);
        self::assertSame('future_table', $steps[0]['id']);
        self::assertSame($this->fixture . '/database/steps/future_table.php', $steps[0]['path']);
        [$exit, $output] = $this->runCommand(['--list']);
        self::assertSame(0, $exit);
        self::assertStringContainsString('future_table', $output);
        self::assertStringNotContainsString('sql_files', $output);
        self::assertStringNotContainsString('MUST_NOT', $output);
    }
}
