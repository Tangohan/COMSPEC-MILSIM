<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Admin\PlatformAccountsOverviewService;
use App\Support\UserActivity;
use PDO;
use PHPUnit\Framework\TestCase;

/** Dernière activité réelle (users.last_active_at) : écriture limitée, expression GREATEST, étape de migration, aperçu admin. */
final class UserActivityTest extends TestCase
{
    private function pdo(bool $withActive): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // SQLite n'a pas GREATEST : même contrat que MySQL (NULL dès qu'un argument est NULL).
        $pdo->sqliteCreateFunction('GREATEST', static fn ($a, $b) => $a === null || $b === null ? null : max($a, $b), 2);
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, created_at TEXT)');
        $pdo->exec("INSERT INTO tenants VALUES (2, 'Alpha', 'alpha', '2026-02-01 00:00:00')");
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, email TEXT, display_name TEXT, callsign TEXT, status TEXT, deleted_at TEXT, created_at TEXT, last_login_at TEXT'
            . ($withActive ? ', last_active_at TEXT' : '') . ')');

        return $pdo;
    }

    private function user(PDO $pdo, int $id, ?string $login, ?string $active = null): void
    {
        $pdo->prepare('INSERT INTO users (id, tenant_id, email, display_name, status, created_at, last_login_at) VALUES (?, 2, ?, ?, ?, ?, ?)')
            ->execute([$id, "u{$id}@x.fr", 'U' . $id, 'active', '2026-01-01 00:00:00', $login]);
        if ($active !== null) {
            $pdo->prepare('UPDATE users SET last_active_at = ? WHERE id = ?')->execute([$active, $id]);
        }
    }

    public function testTouchIsLimitedToOncePerHourPerSession(): void
    {
        $now = 1_800_000_000;
        self::assertTrue(UserActivity::shouldTouch(null, $now));
        self::assertTrue(UserActivity::shouldTouch('', $now));
        self::assertFalse(UserActivity::shouldTouch($now - 10, $now));
        self::assertFalse(UserActivity::shouldTouch($now - 3599, $now));
        self::assertTrue(UserActivity::shouldTouch($now - 3600, $now));
        self::assertTrue(UserActivity::shouldTouch((string) ($now - 7200), $now));
        // Horloge qui recule (valeur de session dans le futur) : on réécrit plutôt que de rester muet.
        self::assertTrue(UserActivity::shouldTouch($now + 500, $now));
    }

    public function testLastSeenSqlToleratesMissingColumns(): void
    {
        self::assertSame('GREATEST(COALESCE(u.last_login_at, u.last_active_at), COALESCE(u.last_active_at, u.last_login_at))', UserActivity::lastSeenSql('u', true, true));
        self::assertSame('u.last_login_at', UserActivity::lastSeenSql('u', true, false));
        self::assertSame('u.last_active_at', UserActivity::lastSeenSql('u', false, true));
        self::assertSame('NULL', UserActivity::lastSeenSql('u', false, false));
    }

    public function testGreatestExpressionPicksTheMostRecentAndToleratesNull(): void
    {
        $pdo = $this->pdo(true);
        $this->user($pdo, 1, '2026-01-01 10:00:00', '2026-06-01 10:00:00');
        $this->user($pdo, 2, '2026-07-01 10:00:00', '2026-06-01 10:00:00');
        $this->user($pdo, 3, '2026-03-01 10:00:00');
        $this->user($pdo, 4, null, '2026-04-01 10:00:00');
        $this->user($pdo, 5, null);
        $expr = UserActivity::lastSeenSql('u', true, true);
        $rows = $pdo->query("SELECT u.id, {$expr} AS seen FROM users u ORDER BY u.id")->fetchAll(PDO::FETCH_KEY_PAIR);

        self::assertSame('2026-06-01 10:00:00', $rows[1]);
        self::assertSame('2026-07-01 10:00:00', $rows[2]);
        self::assertSame('2026-03-01 10:00:00', $rows[3]);
        self::assertSame('2026-04-01 10:00:00', $rows[4]);
        self::assertNull($rows[5]);
    }

    public function testTouchWritesAndNeverThrowsWithoutTheColumn(): void
    {
        $pdo = $this->pdo(true);
        $this->user($pdo, 1, null);
        self::assertTrue(UserActivity::touch($pdo, 1));
        self::assertNotNull($pdo->query('SELECT last_active_at FROM users WHERE id = 1')->fetchColumn());

        $old = $this->pdo(false);
        $this->user($old, 1, null);
        self::assertFalse(UserActivity::touch($old, 1));
    }

    public function testMigrationStepAddsTheColumnOnceAndIsRerunnable(): void
    {
        $pdo = $this->pdo(false);
        $root = dirname(__DIR__, 2);
        $run = static function () use ($pdo, $root): string {
            ob_start();
            try {
                (static function () use ($pdo, $root): void {
                    require $root . '/database/steps/5090_user_last_active.php';
                })();
            } finally {
                $out = (string) ob_get_clean();
            }

            return $out;
        };
        self::assertStringContainsString('ajoutée', $run());
        $columns = static fn (): array => array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        self::assertContains('last_active_at', $columns());
        $before = $columns();
        self::assertStringContainsString('déjà présente', $run());
        self::assertSame($before, $columns());

        $registry = (string) file_get_contents($root . '/database/steps/registry.php');
        self::assertSame(1, substr_count($registry, "'file' => '5090_user_last_active.php'"));
        self::assertSame(1, substr_count($registry, "'file' => '5090_"));
        self::assertSame(1, preg_match("/'5090_user_last_active\\.php'.*'rerun' => true/", $registry));
    }

    public function testInactivityUsesTheMostRecentOfLoginAndActivity(): void
    {
        $now = strtotime('2026-11-01 12:00:00');
        $ago = static fn (int $d): string => date('Y-m-d H:i:s', $now - $d * 86400);
        $pdo = $this->pdo(true);
        $this->user($pdo, 1, $ago(400), $ago(2));   // session longue : connecté il y a 400 j, actif il y a 2 j
        $this->user($pdo, 2, $ago(400));            // vraiment inactif
        $this->user($pdo, 3, null, $ago(45));       // activité seule
        $this->user($pdo, 4, null);                 // jamais

        $res = (new PlatformAccountsOverviewService($pdo))->inactivity(6, $now);

        self::assertTrue($res['available']);
        self::assertSame(4, $res['total']);
        self::assertSame(['30' => 2, '90' => 1, '180' => 1, 'jamais' => 1], $res['buckets']);
        self::assertSame([2, 3], array_map(static fn (array $u): int => (int) $u['id'], $res['oldest']));
    }

    public function testInactivityFallsBackToLoginBeforeMigration(): void
    {
        $now = strtotime('2026-11-01 12:00:00');
        $pdo = $this->pdo(false);
        $this->user($pdo, 1, date('Y-m-d H:i:s', $now - 100 * 86400));
        $svc = new PlatformAccountsOverviewService($pdo);

        self::assertSame(['30' => 1, '90' => 1, '180' => 0, 'jamais' => 0], $svc->inactivity(6, $now)['buckets']);
        self::assertSame(['available' => false, 'since' => null], $svc->activityTracking());
    }

    public function testActivityTrackingReportsItsStartDate(): void
    {
        $pdo = $this->pdo(true);
        $svc = new PlatformAccountsOverviewService($pdo);
        self::assertSame(['available' => true, 'since' => null], $svc->activityTracking());

        $this->user($pdo, 1, null, '2026-11-20 08:00:00');
        $this->user($pdo, 2, null, '2026-11-18 09:30:00');
        self::assertSame(['available' => true, 'since' => '2026-11-18 09:30:00'], $svc->activityTracking());
    }

    public function testAuthMiddlewareAndLoginWriteTheActivity(): void
    {
        $root = dirname(__DIR__, 2);
        $middleware = (string) file_get_contents($root . '/app/Middleware/AuthMiddleware.php');
        self::assertStringContainsString('UserActivity::shouldTouch(Session::get(', $middleware);
        self::assertStringContainsString('UserActivity::touch(', $middleware);
        $repo = (string) file_get_contents($root . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('SET last_login_at = NOW(), last_active_at = NOW()', $repo);
        // Clés ShortCache versionnées : l'ancienne mesure (connexion seule) ne survit pas au déploiement.
        $service = (string) file_get_contents($root . '/app/Services/Admin/PlatformAccountsOverviewService.php');
        self::assertStringContainsString('admin:account-engagement:v2:', $service);
        self::assertStringContainsString('admin:accounts-overview:v2:', $service);
    }
}
