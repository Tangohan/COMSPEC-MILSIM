<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\UserRepository;
use App\Services\Account\AccountDeletionService;
use App\Services\Ops\OpsAccountDeletionService;
use App\Services\Ops\OpsConsoleStepUp;
use App\Services\Ops\OpsHealthService;
use App\Support\Ops\OpsMigrationTicket;
use PHPUnit\Framework\TestCase;

/**
 * Console d'exploitation : ticket de lancement des migrations, tons de l'état du site,
 * garde-fous des suppressions de comptes et câblage (routes, double vérification, CLI conservée).
 */
final class OpsConsoleTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ops-console-test-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob(OpsMigrationTicket::dir($this->root) . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir(OpsMigrationTicket::dir($this->root));
        @rmdir($this->root . '/storage/cache');
        @rmdir($this->root . '/storage');
        @rmdir($this->root);
    }

    public function testTicketIsSingleUse(): void
    {
        $t = OpsMigrationTicket::mint($this->root, ['action' => 'run_step', 'step' => 'schema']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $t);
        // Le ticket n'est jamais rangé en clair.
        self::assertFileDoesNotExist(OpsMigrationTicket::dir($this->root) . '/' . $t . '.json');

        $payload = OpsMigrationTicket::consume($this->root, $t);
        self::assertIsArray($payload);
        self::assertSame('run_step', $payload['action']);
        self::assertSame('schema', $payload['step']);
        self::assertNull(OpsMigrationTicket::consume($this->root, $t), 'Un ticket déjà utilisé est refusé.');
    }

    public function testTicketExpires(): void
    {
        $now = 1_800_000_000;
        $t = OpsMigrationTicket::mint($this->root, ['action' => 'run'], $now);
        self::assertNull(OpsMigrationTicket::consume($this->root, $t, $now + OpsMigrationTicket::TTL_SECONDS + 1));
    }

    public function testTicketRejectsBadInput(): void
    {
        self::assertNull(OpsMigrationTicket::consume($this->root, '../../etc/passwd'));
        self::assertNull(OpsMigrationTicket::consume($this->root, str_repeat('a', 48)));
        $this->expectException(\InvalidArgumentException::class);
        OpsMigrationTicket::mint($this->root, ['action' => 'drop_database']);
    }

    public function testHealthTones(): void
    {
        self::assertSame('ok', OpsHealthService::diskTone(61.4));
        self::assertSame('warn', OpsHealthService::diskTone(85.0));
        self::assertSame('bad', OpsHealthService::diskTone(95.0));
        self::assertSame('warn', OpsHealthService::diskTone(null));

        self::assertSame('bad', OpsHealthService::backupTone(null));
        self::assertSame('ok', OpsHealthService::backupTone(5));
        self::assertSame('warn', OpsHealthService::backupTone(72));
        self::assertSame('bad', OpsHealthService::backupTone(24 * 8));

        $sum = OpsHealthService::summarize([
            ['key' => 'a', 'title' => 'A', 'items' => [['label' => 'x', 'value' => '', 'tone' => 'ok'], ['label' => 'y', 'value' => '', 'tone' => 'warn']]],
            ['key' => 'b', 'title' => 'B', 'items' => [['label' => 'z', 'value' => '', 'tone' => 'info']]],
        ]);
        self::assertSame(['ok' => 1, 'warn' => 1, 'bad' => 0, 'tone' => 'warn'], $sum);
        self::assertSame('il y a 5 min', OpsHealthService::formatAge(300));
        self::assertSame('il y a 3 j', OpsHealthService::formatAge(3 * 86400));
    }

    public function testDeletionGuards(): void
    {
        $service = new OpsAccountDeletionService(
            $this->createMock(UserRepository::class),
            $this->createMock(AccountDeletionService::class),
        );
        $member = ['id' => 12, 'email' => 'joueur@example.com', 'display_name' => 'Joueur'];

        self::assertNull($service->refusal($member, 1));
        self::assertSame('Compte introuvable.', $service->refusal(null, 1));
        self::assertStringContainsString('propre compte', (string) $service->refusal($member, 12));
        self::assertStringContainsString('administrateur du site', (string) $service->refusal($member + ['is_platform_admin' => 1], 1));
        self::assertStringContainsString('technique', (string) $service->refusal($member + ['is_service_account' => 1], 1));
        self::assertStringContainsString('déjà supprimé', (string) $service->refusal(['id' => 13, 'email' => 'deleted-13@deleted.invalid'], 1));

        self::assertSame(1, OpsAccountDeletionService::clampDays(0));
        self::assertSame(7, OpsAccountDeletionService::clampDays(7));
        self::assertSame(OpsAccountDeletionService::MAX_DAYS, OpsAccountDeletionService::clampDays(400));
    }

    public function testEmailMasking(): void
    {
        self::assertSame('t••••••@gmail.com', OpsConsoleStepUp::maskEmail('ttrd.pro@gmail.com'));
        self::assertSame('***', OpsConsoleStepUp::maskEmail('sans-arobase'));
    }

    public function testEveryConsoleRouteIsPlatformAdminOnly(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        preg_match_all("#\\\$router->(?:get|post)\('/admin/system/exploitation[^\n]*#", $routes, $m);
        self::assertGreaterThanOrEqual(13, count($m[0]));
        foreach ($m[0] as $line) {
            self::assertStringContainsString('SystemAdminMiddleware::class', $line, $line);
        }
    }

    public function testControllerGuardsEveryAction(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/System/SystemOpsConsoleController.php');
        foreach (['launchMigrations', 'createBackup', 'verifyBackup', 'downloadBackup', 'restoreBackup', 'deleteBackup', 'scheduleDeletion', 'cancelDeletion', 'executeDeletion'] as $action) {
            self::assertMatchesRegularExpression(
                '/function ' . $action . '\(Request \$request, array \$params = \[\]\): Response\s*\{\s*if \(\(\$deny = \$this->guard\(\$request\)\) !== null\)/',
                $src,
                $action . ' doit commencer par guard() (CSRF + double vérification).'
            );
        }
        self::assertStringContainsString("createSnapshot('avant-restauration'", $src);
        self::assertStringContainsString("createSnapshot('avant-suppression'", $src);
    }

    public function testMigrationPipelineKeepsCliAndAcceptsConsoleTicket(): void
    {
        $root = dirname(__DIR__, 2);
        $runner = (string) file_get_contents($root . '/run-migrations.php');
        self::assertStringContainsString("if (PHP_SAPI !== 'cli')", $runner, 'Le CLI ne passe pas par la porte web.');
        self::assertStringNotContainsString("'--replay=' . \$migrationReplayFile", $runner);

        $gate = (string) file_get_contents($root . '/bootstrap/migrations_web_ui.php');
        self::assertStringContainsString('OpsMigrationTicket::consume', $gate);
        self::assertStringContainsString('migrations_web_queue_error', $gate, 'La porte web refuse les étapes historiques et le rejeu SQL.');
        self::assertStringContainsString("migrations_web_password_ok", $gate, 'L’accès de secours par mot de passe reste en place.');
    }
}
