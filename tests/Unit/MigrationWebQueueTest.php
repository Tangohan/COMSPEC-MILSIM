<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\System\SystemOpsConsoleController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Ops\OpsConsoleStepUp;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

require_once dirname(__DIR__, 2) . '/bootstrap/migrations_web_ui.php';

final class MigrationWebQueueTest extends TestCase
{
    private array $globals;
    private string $root;

    protected function setUp(): void
    {
        Session::start();
        $this->globals = [$_SESSION, $_GET, $_POST, $_SERVER];
        $_SESSION = ['user_id'=>7, '_csrf_token'=>str_repeat('a', 64), OpsConsoleStepUp::SESSION_KEY=>['user_id'=>7, 'until'=>time()+600]];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->root = sys_get_temp_dir() . '/migration-web-queue-' . bin2hex(random_bytes(5));
        mkdir($this->root . '/database/steps', 0770, true);
        file_put_contents($this->root . '/database/steps/registry.php', '<?php return [];');
    }

    protected function tearDown(): void
    {
        [$_SESSION, $_GET, $_POST, $_SERVER] = $this->globals;
        foreach (glob($this->root . '/database/steps/*') ?: [] as $file) unlink($file);
        rmdir($this->root . '/database/steps');
        rmdir($this->root . '/database');
        rmdir($this->root);
    }

    private function document(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new DOMXPath($dom);
    }

    public function testEmptyAndHistoricalLaunchesAreRefusedButFutureStepsCanRun(): void
    {
        self::assertStringContainsString('Aucune migration en attente', migrations_web_queue_error($this->root, 'run'));
        self::assertNotNull(migrations_web_queue_error($this->root, 'replay'));
        self::assertNotNull(migrations_web_queue_error($this->root, 'run_step', 'schema'));
        self::assertNotNull(migrations_web_queue_error($this->root, 'drop_database'));
        // Use a different root because the registry deliberately caches each queue.
        $future = $this->root . '/future';
        mkdir($future . '/database/steps', 0770, true);
        file_put_contents($future . '/database/steps/registry.php', "<?php return [['id'=>'next_change','file'=>'next.php','rerun'=>true]];");
        try {
            self::assertNull(migrations_web_queue_error($future, 'run'));
            self::assertNull(migrations_web_queue_error($future, 'run_step', 'next_change'));
            self::assertNotNull(migrations_web_queue_error($future, 'run_step', 'schema'));
            self::assertNotNull(migrations_web_queue_error($future, 'replay'));
        } finally {
            unlink($future . '/database/steps/registry.php');
            rmdir($future . '/database/steps'); rmdir($future . '/database'); rmdir($future);
        }
    }

    public function testConsoleLaunchWithAnEmptyQueueNeverCreatesBackupTicketOrDatabaseWork(): void
    {
        $controller = (new ReflectionClass(SystemOpsConsoleController::class))->newInstanceWithoutConstructor();
        $stepUp = (new ReflectionClass(OpsConsoleStepUp::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($controller, 'stepUp'))->setValue($controller, $stepUp);
        // Other dependencies stay uninitialized: no snapshot, audit or database may be used.
        foreach (['run', 'replay'] as $mode) {
            $_POST = ['mode'=>$mode, 'backup'=>'1', 'file'=>'schema.sql', '_csrf_token'=>$_SESSION['_csrf_token']];
            $response = $controller->launchMigrations(new Request());
            self::assertStringContainsString('onglet=migrations', $response->headerValue('Location'));
            self::assertStringNotContainsString('ticket=', $response->headerValue('Location'));
            self::assertStringContainsString($mode === 'run' ? 'Aucune migration en attente' : 'rejeu', Session::getFlash($mode === 'run' ? 'success' : 'error'));
        }
    }

    public function testOpsViewShowsArchiveOutputWithoutReplayOrSqlPendingControls(): void
    {
        $html = Response::view('admin.system.ops_console', ['opsTab'=>'migrations', 'opsMigrations'=>[
            'steps'=>[], 'results'=>['old_schema'=>['status'=>'error','message'=>'Ancien résultat <script>test</script>','ran_at'=>'2026-10-01 12:00:00']],
            // Even a stale caller must not turn historical SQL into executable work.
            'sql'=>['pending'=>['old.sql'], 'failed'=>[['name'=>'bad.sql','error'=>'ancienne erreur']]],
        ]])->body();
        $xpath = $this->document($html);
        self::assertStringContainsString('Aucune migration en attente', $html);
        self::assertStringContainsString('Historique des étapes archivées', $html);
        self::assertStringContainsString('&lt;script&gt;test&lt;/script&gt;', $html);
        self::assertSame(1, $xpath->query('//a[contains(@href,"sortie=old_schema")]')->length);
        self::assertSame(0, $xpath->query('//input[@name="mode" and @value="replay"]')->length);
        self::assertSame(0, $xpath->query('//input[@name="mode" and @value="run_step"]')->length);
        self::assertSame(1, $xpath->query('//form[input[@name="mode" and @value="run"]]/button[@disabled]')->length);
        self::assertStringNotContainsString('old.sql', $html);
        self::assertStringNotContainsString('bad.sql', $html);
    }

    public function testHistoricalResultsDoNotReenterTheActiveQueue(): void
    {
        $past = migrations_web_historical_steps([['id'=>'new_step']], ['new_step'=>['status'=>'ok'], 'old_step'=>['status'=>'error']]);
        self::assertSame(['old_step'], array_column($past, 'id'));
        self::assertFalse($past[0]['rerun']);
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/migrations_web_ui.php');
        self::assertStringNotContainsString('ComspecMigrationRunner::statusReport', $source);
        self::assertStringNotContainsString("\$step !== null ? MigrationRunLog::lastOutput", $source);
    }
}
