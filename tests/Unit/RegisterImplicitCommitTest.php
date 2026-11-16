<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\UserCommunityMembershipRepository;
use App\Support\SafeTransaction;
use App\Support\UserFacingExceptionMapper;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Inscription (POST /register) face au commit implicite de MySQL / MariaDB.
 *
 * Le 16/11 à 06:49 : « PDOException: There is no active transaction » au rollBack() de RegisterController. Cause :
 * UserCommunityMembershipRepository::periodsReady() lançait « CREATE TABLE IF NOT EXISTS user_community_membership_periods »
 * au milieu de la transaction (ensureMembership → openPeriod, à chaque nouveau compte) ; MySQL valide alors la
 * transaction, commit() lève, puis rollBack() lève à son tour et masque tout (page 500).
 *
 * SQLite ne valide pas la transaction sur un DDL : ImplicitCommitPdo reproduit le comportement MySQL (un DDL valide la
 * transaction ouverte). Les commit() / rollBack() natifs de PHP 8 lèvent ensuite comme en production.
 */
final class RegisterImplicitCommitTest extends TestCase
{
    private string $log = '';

    private string|false $previousLog = false;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->log = (string) tempnam(sys_get_temp_dir(), 'reglog');
        $this->previousLog = ini_get('error_log');
        ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousLog === false ? '' : $this->previousLog);
        if ($this->log !== '' && is_file($this->log)) {
            unlink($this->log);
        }
    }

    private function logged(): string
    {
        return (string) file_get_contents($this->log);
    }

    private static function pdo(): ImplicitCommitPdo
    {
        $pdo = new ImplicitCommitPdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)');
        $pdo->ddl = [];

        return $pdo;
    }

    public function testTheSimulationBehavesLikeMysql(): void
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO users (email) VALUES ('a@exemple.fr')");
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)');
        self::assertFalse($pdo->inTransaction(), 'Un DDL valide la transaction, comme MySQL.');
        try {
            $pdo->commit();
            self::fail('PHP 8 lève sur commit() sans transaction.');
        } catch (PDOException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }
    }

    public function testDdlDuringRegistrationNoLongerCrashesAndTheAccountIsKept(): void
    {
        $pdo = self::pdo();
        $result = SafeTransaction::run($pdo, static function () use ($pdo): int {
            $pdo->exec("INSERT INTO users (email) VALUES ('nouveau@exemple.fr')");
            $id = (int) $pdo->lastInsertId();
            $pdo->exec('CREATE TABLE IF NOT EXISTS user_community_membership_periods (id INTEGER PRIMARY KEY)');
            $pdo->exec("UPDATE users SET email = 'nouveau@exemple.fr' WHERE id = " . $id);

            return $id;
        }, 'auth.register');

        self::assertTrue($result['ok']);
        self::assertNull($result['error']);
        self::assertTrue($result['closed_early']);
        self::assertSame(1, $result['value']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'Le compte reste enregistré.');
        self::assertStringContainsString('[auth.register] transaction déjà close avant la validation', $this->logged());
    }

    public function testFailureAfterAnImplicitCommitIsLoggedFirstAndShowsTheUserMessage(): void
    {
        $pdo = self::pdo();
        $result = SafeTransaction::run($pdo, static function () use ($pdo): int {
            $pdo->exec("INSERT INTO users (email) VALUES ('partiel@exemple.fr')");
            $pdo->exec('ALTER TABLE users ADD COLUMN steam_id TEXT');
            throw new PDOException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded');
        }, 'auth.register');

        self::assertFalse($result['ok']);
        self::assertInstanceOf(PDOException::class, $result['error']);
        self::assertStringContainsString('1205 Lock wait timeout', $result['error']->getMessage(), 'L’erreur d’origine, pas « There is no active transaction ».');
        self::assertSame('Inscription impossible pour le moment. Réessayez dans quelques instants.', UserFacingExceptionMapper::registrationMessage($result['error']));

        $log = $this->logged();
        $origin = strpos($log, '1205 Lock wait timeout');
        $rollback = strpos($log, 'annulation impossible');
        self::assertNotFalse($origin, $log);
        self::assertNotFalse($rollback, $log);
        self::assertLessThan($rollback, $origin, 'L’erreur d’origine est journalisée avant tout le reste.');
        self::assertStringNotContainsString('There is no active transaction', $log);
    }

    public function testOrdinaryFailureStillRollsBack(): void
    {
        $pdo = self::pdo();
        $result = SafeTransaction::run($pdo, static function () use ($pdo): int {
            $pdo->exec("INSERT INTO users (email) VALUES ('annule@exemple.fr')");
            throw new RuntimeException('Un compte existe déjà avec cette adresse e-mail.');
        }, 'auth.register');

        self::assertFalse($result['ok']);
        self::assertFalse($result['closed_early']);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertFalse($pdo->inTransaction());
    }

    public function testMembershipPeriodNeverRunsDdlInsideTheRegistrationTransaction(): void
    {
        $pdo = self::pdo();
        $pdo->exec('CREATE TABLE user_community_membership_periods (id INTEGER PRIMARY KEY, user_id INTEGER, tenant_id INTEGER, joined_at TEXT, left_at TEXT)');
        $pdo->ddl = [];
        $open = new \ReflectionMethod(UserCommunityMembershipRepository::class, 'openPeriod');

        $pdo->beginTransaction();
        $open->invoke(new UserCommunityMembershipRepository($pdo), 7, 3);
        self::assertTrue($pdo->inTransaction(), 'Aucun commit implicite pendant l’inscription.');
        self::assertSame([], $pdo->ddl);
        $pdo->commit();
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM user_community_membership_periods WHERE user_id = 7 AND tenant_id = 3')->fetchColumn());

        // Table absente pendant une transaction : le séjour n'est pas noté, la transaction reste ouverte.
        $bare = self::pdo();
        $bare->beginTransaction();
        $open->invoke(new UserCommunityMembershipRepository($bare), 7, 3);
        self::assertTrue($bare->inTransaction());
        self::assertSame([], $bare->ddl);
        $bare->rollBack();
    }

    public function testRegisterControllerUsesTheGuardedTransaction(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/RegisterController.php');
        self::assertStringContainsString("SafeTransaction::run(Database::getPdo(), function ()", $src);
        self::assertStringContainsString("}, 'auth.register');", $src);
        self::assertStringNotContainsString('$pdo->rollBack()', $src);
        self::assertStringNotContainsString('$pdo->commit()', $src);
    }
}

/** SQLite qui valide la transaction ouverte sur un DDL, comme MySQL / MariaDB (tests seulement). */
final class ImplicitCommitPdo extends PDO
{
    /** @var list<string> */
    public array $ddl = [];

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
    }

    public function exec(string $statement): int|false
    {
        $this->implicitCommit($statement);

        return parent::exec($statement);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $this->implicitCommit($query);

        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    private function implicitCommit(string $sql): void
    {
        if (preg_match('/^\s*(CREATE|ALTER|DROP|RENAME|TRUNCATE)\s/i', $sql) !== 1) {
            return;
        }
        $this->ddl[] = $sql;
        if (parent::inTransaction()) {
            parent::commit();
        }
    }
}
