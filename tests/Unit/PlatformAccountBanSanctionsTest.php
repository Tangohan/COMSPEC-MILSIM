<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\UserRepository;
use App\Services\Account\PlatformAccountBanService;
use App\Services\Audit\AuditService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

require_once dirname(__DIR__, 2) . '/bootstrap/platform_account_bans_migration.php';

/**
 * Sanctions du site : date de fin, historique par personne, fin automatique d'une sanction temporaire.
 * Base SQLite en mémoire, adresses fictives (example.test), aucune donnée réelle.
 */
final class PlatformAccountBanSanctionsTest extends TestCase
{
    private const NOW = 1_790_000_000; // 2026-09-21 environ, UTC

    private function pdo(): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, display_name TEXT, callsign TEXT, status TEXT, updated_at TEXT, deleted_at TEXT)');
        $pdo->exec("INSERT INTO users (id, email, display_name, callsign, status) VALUES
            (1, 'admin@example.test', 'Admin Test', 'Zeus', 'active'),
            (2, 'joueur@example.test', 'Joueur Test', 'Viper', 'inactive'),
            (3, 'joueur@example.test', 'Joueur Test', 'Viper', 'inactive')");
        run_platform_account_bans_migration($pdo);

        return $pdo;
    }

    private function repo(PDO $pdo, ?int $at = null): PlatformAccountBanRepository
    {
        $at ??= self::NOW;

        return new PlatformAccountBanRepository($pdo, static fn (): int => $at);
    }

    /** Service avec une base de fiches simulée : les statuts d'avant sont rendus tels qu'enregistrés. */
    private function service(PDO $pdo, int $at): PlatformAccountBanService
    {
        $users = new class($pdo) extends UserRepository {
            public function findById(int $id, ?int $tenantId = null): ?array
            {
                return ['id' => $id, 'status' => 'inactive', 'deleted_at' => null];
            }
        };
        $audit = new class extends AuditService {
            /** @var list<string> */
            public array $actions = [];

            public function __construct()
            {
            }

            public function logChange(string $action, ?int $tenantId, ?int $userId, ?string $entityType, ?int $entityId, array $oldFields, array $newFields): void
            {
                $this->actions[] = $action . ':' . json_encode($newFields);
            }
        };

        return new PlatformAccountBanService($users, $this->repo($pdo, $at), null, null, $pdo, $audit);
    }

    public function testMigrationAddsEndDateToAnOlderTableAndCanBeRunTwice(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        // Table d'avant la mise à jour : ni date de fin ni note de levée.
        $pdo->exec('CREATE TABLE platform_account_bans (id INTEGER PRIMARY KEY AUTOINCREMENT, email_norm TEXT, reason TEXT, created_at TEXT, lifted_at TEXT)');
        $pdo->exec("INSERT INTO platform_account_bans (email_norm, reason, created_at) VALUES ('ancien@example.test', 'ancien motif', '2026-01-01 10:00:00')");
        $pdo->changes = 0;

        run_platform_account_bans_migration($pdo);
        self::assertGreaterThan(0, $pdo->changes);
        $columns = array_column($pdo->query('PRAGMA table_info(platform_account_bans)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        self::assertContains('ends_at', $columns);
        self::assertContains('lift_note', $columns);

        $pdo->changes = 0;
        run_platform_account_bans_migration($pdo);
        self::assertSame(0, $pdo->changes, 'Une seconde passe ne doit rien modifier.');
        // Les bannissements existants restent sans date de fin.
        self::assertNull($pdo->query('SELECT ends_at FROM platform_account_bans')->fetchColumn() ?: null);
    }

    public function testTemporaryBanStopsBlockingWhenTheEndDateIsReached(): void
    {
        $pdo = $this->pdo();
        $ends = gmdate('Y-m-d H:i:s', self::NOW + 3600);
        $this->repo($pdo)->create('Joueur@Example.test ', 2, 'Viper', 'Insultes', [11], [2 => 'active', 3 => 'active'], 1, $ends);
        $this->repo($pdo)->create('perm@example.test', null, 'Perm', 'Triche', [12], [], 1);

        self::assertNotNull($this->repo($pdo)->findActiveForEmail('joueur@example.test'), 'En cours avant la date de fin.');
        self::assertSame(2, $this->repo($pdo)->countActive());

        $later = $this->repo($pdo, self::NOW + 3601);
        self::assertNull($later->findActiveForEmail('joueur@example.test'), 'Échue : ne bloque plus.');
        self::assertNotNull($later->findActiveForEmail('perm@example.test'), 'Sans fin : bloque toujours.');
        self::assertSame(1, $later->countActive());
        self::assertSame([], $later->activeByEmails(['joueur@example.test']));
        self::assertCount(1, $later->listDue(), 'Seule la sanction échue attend la remise en état.');
    }

    public function testExpiredBanRestoresTheSheetsOnceAndKeepsTheHistory(): void
    {
        $pdo = $this->pdo();
        $ends = gmdate('Y-m-d H:i:s', self::NOW + 60);
        $this->repo($pdo)->create('joueur@example.test', 2, 'Viper', 'Insultes', [], [2 => 'active', 3 => 'pending_verification'], 1, $ends);

        // Avant l'échéance : rien ne bouge.
        self::assertSame(0, $this->service($pdo, self::NOW)->expireDue('joueur@example.test'));
        self::assertSame('inactive', $pdo->query('SELECT status FROM users WHERE id = 2')->fetchColumn());

        // Après l'échéance (par exemple à la tentative de connexion) : fiches remises dans leur état d'avant.
        $service = $this->service($pdo, self::NOW + 61);
        self::assertSame(1, $service->expireDue('joueur@example.test'));
        self::assertSame('active', $pdo->query('SELECT status FROM users WHERE id = 2')->fetchColumn());
        self::assertSame('pending_verification', $pdo->query('SELECT status FROM users WHERE id = 3')->fetchColumn());

        // Une seconde passe ne refait rien.
        self::assertSame(0, $service->expireDue('joueur@example.test'));

        $history = $this->repo($pdo, self::NOW + 61)->historyForEmail('joueur@example.test');
        self::assertCount(1, $history);
        self::assertNotEmpty($history[0]['lifted_at']);
        self::assertNull($history[0]['lifted_by_user_id'], 'Levée automatique : aucun acteur.');
        self::assertStringContainsString('Fin de la sanction', (string) $history[0]['lift_note']);
    }

    public function testExpiryIsLoggedInTheAuditTrail(): void
    {
        $pdo = $this->pdo();
        $this->repo($pdo)->create('joueur@example.test', 2, 'Viper', 'Motif', [], [2 => 'active'], 1, gmdate('Y-m-d H:i:s', self::NOW - 5));
        $service = $this->service($pdo, self::NOW);
        $service->expireDue();
        $ref = new \ReflectionProperty($service, 'audit');
        $audit = $ref->getValue($service);
        self::assertCount(1, $audit->actions);
        self::assertStringStartsWith('user.ban_expired:', $audit->actions[0]);
    }

    public function testHistoryListsEveryDecisionWithWhoAndWhenButNeverAdminEmails(): void
    {
        $pdo = $this->pdo();
        $repo = $this->repo($pdo);
        $first = $repo->create('joueur@example.test', 2, 'Viper', 'Premier motif', [], [], 1);
        self::assertTrue($repo->lift($first, 1, 'Erreur de modération'));
        self::assertFalse($repo->lift($first, 1), 'Une sanction déjà levée ne se lève pas deux fois.');
        $repo->create('joueur@example.test', 2, 'Viper', 'Second motif', [], [], 1, gmdate('Y-m-d H:i:s', self::NOW + 86400));

        $history = $repo->historyForEmail('JOUEUR@example.test');
        self::assertSame(['Second motif', 'Premier motif'], array_column($history, 'reason'));
        self::assertSame('Zeus', $history[1]['banned_by_callsign']);
        self::assertSame('Zeus', $history[1]['lifted_by_callsign']);
        self::assertSame('Erreur de modération', $history[1]['lift_note']);
        self::assertNotEmpty($history[0]['ends_at']);
        foreach ($history as $row) {
            self::assertArrayNotHasKey('email', $row);
        }
        self::assertSame(['joueur@example.test' => 2], $repo->countsByEmails(['joueur@example.test', 'inconnu@example.test']));
    }

    public function testBanRequiresAReasonOfAtLeastFiveCharacters(): void
    {
        $service = $this->service($this->pdo(), self::NOW);
        foreach ([' ', '   ', ' ok '] as $reason) {
            $result = $service->ban('joueur@example.test', $reason, [], false, 1);
            self::assertFalse($result['ok'], 'Motif : ' . json_encode($reason));
            self::assertStringContainsString('motif', mb_strtolower((string) $result['error']));
        }
    }

    public function testRepositoryCompletesAnOlderTableOnFirstUseSoEndDatesBecomePossible(): void
    {
        $old = new MysqlDdlSqlitePdo();
        $old->exec('CREATE TABLE platform_account_bans (id INTEGER PRIMARY KEY AUTOINCREMENT, email_norm TEXT, user_id INTEGER, display_label TEXT, reason TEXT, indicator_ids TEXT, status_snapshot TEXT, banned_by_user_id INTEGER, created_at TEXT, lifted_at TEXT, lifted_by_user_id INTEGER)');
        $old->exec("INSERT INTO platform_account_bans (email_norm, reason, created_at) VALUES ('ancien@example.test', 'ancien', '1999-01-01 00:00:00')");
        $repo = new PlatformAccountBanRepository($old);
        self::assertTrue($repo->supportsEndDate());
        self::assertNotNull($repo->findActiveForEmail('ancien@example.test'), 'Un ancien bannissement reste sans fin, donc en cours.');
    }

    public function testResolveEndOnlyAcceptsKnownDurationsAndFutureDates(): void
    {
        $now = self::NOW;
        self::assertNull(PlatformAccountBanService::resolveEnd('definitif', '', $now)['endsAt']);
        self::assertNull(PlatformAccountBanService::resolveEnd('', '', $now)['endsAt']);
        $week = PlatformAccountBanService::resolveEnd('7j', '', $now);
        self::assertTrue($week['ok']);
        self::assertSame($now + 7 * 86400, $week['endsAt']->getTimestamp());

        foreach (['1 OR 1=1', '999j', 'u.email', '7J'] as $bad) {
            self::assertFalse(PlatformAccountBanService::resolveEnd($bad, '', $now)['ok'], $bad);
        }
        self::assertFalse(PlatformAccountBanService::resolveEnd('date', '', $now)['ok']);
        self::assertFalse(PlatformAccountBanService::resolveEnd('date', '2026-02-30', $now)['ok'], 'Date impossible.');
        self::assertFalse(PlatformAccountBanService::resolveEnd('date', '2020-01-01', $now)['ok'], 'Date passée.');
        self::assertFalse(PlatformAccountBanService::resolveEnd('date', '2099-01-01', $now)['ok'], 'Au-delà de cinq ans.');

        $day = gmdate('Y-m-d', $now + 10 * 86400);
        $until = PlatformAccountBanService::resolveEnd('date', $day, $now);
        self::assertTrue($until['ok']);
        // Fin du jour choisi, heure de Paris : entre 21 h et 22 h UTC la veille au soir, jamais avant ce jour.
        self::assertGreaterThan($now + 10 * 86400 - 86400, $until['endsAt']->getTimestamp());
    }

    public function testEveryLoginPathSettlesExpiredBansAndTheAdminPagesDo(): void
    {
        $root = dirname(__DIR__, 2);
        $users = (string) file_get_contents($root . '/app/Repositories/UserRepository.php');
        // Toutes les connexions (site, jeu, vérification d'e-mail) lisent leurs comptes ici.
        self::assertMatchesRegularExpression('/function listUsersForLoginByEmail\(string \$email\): array\s*\{\s*\$email = strtolower\(trim\(\$email\)\);\s*\$this->settleExpiredPlatformBans\(\$email\);/', $users);
        self::assertStringContainsString('->expireDue($email)', $users);

        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemUsersController.php');
        self::assertStringContainsString('banService()->expireDue(', $ctrl);

        $auth = (string) file_get_contents($root . '/app/Controllers/Auth/AuthController.php');
        self::assertStringContainsString('listUsersForLoginByEmail($email)', $auth);
    }

    public function testBanRouteNeedsReasonConfirmationAndStillSitsBehindSystemAdminMiddleware(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemUsersController.php');
        self::assertStringContainsString("inputString('confirm_ban') !== '1'", $ctrl);
        self::assertStringContainsString('PlatformAccountBanService::resolveEnd(', $ctrl);
        self::assertStringContainsString('AuditAction::USER_BANNED', $ctrl);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (['/admin/users/bannir', '/admin/users/lever-bannissement', '/admin/users/export', '/admin/users/donnees'] as $path) {
            self::assertMatchesRegularExpression('#\$router->(get|post)\(\'' . preg_quote($path, '#') . '\', \[[^\]]+\], \[AuthMiddleware::class, SystemAdminMiddleware::class\]\);#', $routes, $path);
        }

        $form = (string) file_get_contents($root . '/views/admin/system/partials/platform_ban_form.php');
        self::assertStringContainsString('name="confirm_ban"', $form);
        self::assertStringContainsString('name="reason"', $form);
        self::assertStringContainsString('required', $form);
        self::assertStringContainsString('name="duree"', $form);
    }
}
