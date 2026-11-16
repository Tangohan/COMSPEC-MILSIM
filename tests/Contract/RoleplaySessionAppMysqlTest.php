<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Core\Database;
use App\Repositories\RoleplayGameSessionRepository;
use App\Repositories\RoleplaySessionAppRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\RoleplayGameSessionService;
use App\Services\Personnel\RoleplaySessionAttendance;
use App\Services\Personnel\RoleplaySessionCodeService;
use App\Services\Personnel\RoleplaySessionGameApp;
use App\Services\Personnel\RoleplaySessionLiveRoster;
use App\Services\Personnel\RoleplaySessionNotifier;
use App\Services\Security\FileRateLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * App Session (code, rattachement, lecture, pointage, enregistrement, rappels, joueurs connectés) sur un vrai serveur MySQL
 * ou MariaDB en mode strict : vraies tables des sessions (bootstrap), vraie migration de l'étape 4910, colonnes ENUM et
 * NOT NULL, unicités, collations. Ignoré sans base de test ; la CI le lance sur MySQL 8 et MariaDB (COMSPEC_TEST_MYSQL_DSN,
 * COMSPEC_TEST_MYSQL_USER, COMSPEC_TEST_MYSQL_PASS). La base est vidée au début. Données fictives uniquement.
 */
final class RoleplaySessionAppMysqlTest extends TestCase
{
    private const T = 31;
    private const OTHER = 32;

    private PDO $pdo;
    private int $now;
    /** @var list<array<string, mixed>> */
    private array $sent = [];

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
        $root = dirname(__DIR__, 2);
        $schema = (string) file_get_contents($root . '/migrations/schema.sql');
        foreach (['users', 'atak_units'] as $table) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` \(.*?\) ENGINE=InnoDB[^;]*;/s', $schema, $m) !== 1) {
                self::fail('Table ' . $table . ' introuvable dans migrations/schema.sql.');
            }
            $this->pdo->exec($m[0]);
        }
        require_once $root . '/bootstrap/roleplay_game_sessions_migration.php';
        run_roleplay_game_sessions_migration($this->pdo);
        foreach (['/migrations/tenant_game_servers.sql', '/migrations/2026_11_23_100_roleplay_session_app.sql'] as $file) {
            $sql = preg_replace('/^--.*$/m', '', (string) file_get_contents($root . $file)) ?? '';
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                $this->pdo->exec($statement);
            }
        }
        $add = $this->pdo->prepare("INSERT INTO users (id, tenant_id, email, password_hash, display_name, callsign, steam_id, status) VALUES (?, ?, ?, 'x', ?, ?, ?, ?)");
        foreach ([[1, self::T, 'ANIMA', '76561198000000101'], [2, self::T, 'FAUCON', '76561198000000102'], [3, self::T, 'LYNX', '76561198000000103'], [9, self::OTHER, 'ALIEN', '76561198000000109']] as [$id, $t, $cs, $steam]) {
            $add->execute([$id, $t, 'u' . $id . '@x.test', 'Membre ' . $id, $cs, $steam, 'active']);
        }
        $this->now = time();
    }

    protected function tearDown(): void
    {
        $l = new FileRateLimiter();
        foreach ([1, 2, 3] as $u) {
            $l->clear('rp_session_join|u|' . self::T . '|' . $u);
            $l->clear('rp_session_act|' . self::T . '|' . $u);
        }
        $l->clear('rp_session_join|ip|mysql-test');
        $l->clear('rp_session_tick|' . self::T);
    }

    private function sessions(): RoleplayGameSessionRepository
    {
        return new RoleplayGameSessionRepository($this->pdo);
    }

    private function codes(): RoleplaySessionCodeService
    {
        return new RoleplaySessionCodeService(new RoleplaySessionAppRepository($this->pdo), $this->sessions(), fn (): int => $this->now);
    }

    private function service(): RoleplayGameSessionService
    {
        return new RoleplayGameSessionService($this->sessions(), new UserRepository($this->pdo));
    }

    private function notifier(): RoleplaySessionNotifier
    {
        return new RoleplaySessionNotifier(new RoleplaySessionAppRepository($this->pdo), function (int $t, string $target, string $label, array $args, array $wire): ?int {
            $this->sent[] = $wire;

            return count($this->sent);
        }, fn (): int => $this->now);
    }

    /** @param array<string, mixed> $over @return array<string, mixed> */
    private function session(array $over = []): array
    {
        $id = $this->sessions()->create(self::T, $over + [
            'session_uid' => 'staff-' . bin2hex(random_bytes(4)),
            'mission_name' => 'Opération Dague',
            'session_kind' => 'officielle',
            'hour_category' => 'Opération',
            'attendance_enabled' => 1,
            'status' => 'open',
            'created_by_user_id' => 1,
        ]);

        return (array) $this->sessions()->findById(self::T, $id);
    }

    public function testCodeJoinCurrentCheckinAndLeaveOnAStrictServer(): void
    {
        $s = $this->session(['planned_starts_at' => date('Y-m-d H:i:s', $this->now + 12 * 60)]);
        $view = $this->codes()->ensure(self::T, $s, 1);
        self::assertSame('active', $view['state']);
        $repo = new RoleplaySessionAppRepository($this->pdo);
        self::assertFalse($repo->insertCode(self::T, (int) $s['id'], (string) $view['code'], 1, date('Y-m-d H:i:s')), 'unicité tenant + code');

        $app = new RoleplaySessionGameApp($this->codes(), $this->service(), new FileRateLimiter(), $this->notifier());
        [$st, $body] = $app->join(self::T, 2, '76561198000000102', (string) $view['formatted'], 'mysql-test');
        self::assertSame(200, $st);
        self::assertSame('upcoming', $body['session']['state']);
        self::assertSame('code_required', $app->current(self::T, 3)[1]['app_state']);
        self::assertSame('active', $app->current(self::T, 2)[1]['app_state']);
        self::assertSame('no_session', $app->current(self::OTHER, 9)[1]['app_state']);
        self::assertNotEmpty($this->sent, 'rappel T-15 déclenché à la lecture');
        self::assertSame('reminder_15', $this->sent[0]['ev']);

        $this->service()->startSession(self::T, (int) $s['id']);
        [$ok, $done] = $app->checkin(self::T, 2, (int) $s['id']);
        self::assertSame(200, $ok);
        self::assertSame(['joined' => 1, 'checked_in' => 1, 'playing' => 1], $done['session']['gauge']);
        self::assertSame(200, $app->leave(self::T, 2, (int) $s['id'])[0]);
        $repo->saveSettings(self::T, (int) $s['id'], ['notify_enabled' => true, 'remind_15' => false, 'remind_5' => true, 'briefing' => str_repeat('é', 400)], 1, date('Y-m-d H:i:s'));
        $repo->saveSettings(self::T, (int) $s['id'], ['notify_enabled' => false, 'remind_15' => false, 'remind_5' => true, 'briefing' => 'Fréquence 45'], 1, date('Y-m-d H:i:s'));
        self::assertSame('Fréquence 45', $repo->settings(self::T, (int) $s['id'])['briefing']);
        self::assertGreaterThan(0, count($repo->journal(self::T, (int) $s['id'])));
    }

    public function testStaffCheckinRecordingAndExpiryOnAStrictServer(): void
    {
        $s = $this->session(['planned_starts_at' => date('Y-m-d H:i:s', $this->now + 3600)]);
        $this->codes()->ensure(self::T, $s, 1);
        $att = new RoleplaySessionAttendance($this->sessions(), $this->service(), new RoleplaySessionAppRepository($this->pdo), fn (): int => $this->now);
        self::assertSame(['pointed' => 2, 'already' => 0, 'refused' => 1], $att->point(self::T, $s, [2, 3, 9], 1));
        self::assertSame(['removed' => 1], $att->unpoint(self::T, $s, [3], 1));
        self::assertSame(['ok' => true, 'opened' => 1], $att->startRecording(self::T, $s, 1));
        $live = (array) $this->sessions()->findById(self::T, (int) $s['id']);
        self::assertTrue($att->stopRecording(self::T, $live, 1)['ok']);
        self::assertSame('closed', $this->sessions()->findById(self::T, (int) $s['id'])['status']);
        $codes = new RoleplaySessionCodeService(new RoleplaySessionAppRepository($this->pdo), $this->sessions(), fn (): int => $this->now + 7200);
        self::assertSame(1, $codes->expireDue());
        self::assertSame([], (new RoleplaySessionAppRepository($this->pdo))->activeCodesFor(self::T, [(int) $s['id']]));
    }

    public function testLiveRosterReadsUtcSignalsOfThisCommunityOnly(): void
    {
        $this->pdo->exec("INSERT INTO tenant_game_servers (id, tenant_id, label) VALUES (5, " . self::T . ", 'Serveur Alpha')");
        $u = $this->pdo->prepare('INSERT INTO atak_units (tenant_id, map_id, call_sign, group_name, status, updated_at, extra) VALUES (?, 1, ?, ?, ?, UTC_TIMESTAMP() - INTERVAL ? SECOND, ?)');
        $u->execute([self::T, 'FAUCON', '1re section', 'linked', 20, '{"steam_uid":"76561198000000102"}']);
        $u->execute([self::OTHER, 'ALIEN', '', 'linked', 20, '{"steam_uid":"76561198000000109"}']);
        $this->pdo->exec("INSERT INTO tenant_game_server_presence (tenant_id, steam_uid, player_name, server_name, mission, matched_server_id, seen_at)
            VALUES (" . self::T . ", '76561198000000103', 'Diaz', 'alpha', 'Dague', 5, UTC_TIMESTAMP() - INTERVAL 60 SECOND)");
        $roster = (new RoleplaySessionLiveRoster($this->pdo))->roster(self::T, []);
        self::assertSame(2, $roster['connected']);
        self::assertEqualsCanonicalizing([2, 3], array_column($roster['players'], 'user_id'));
        foreach ($roster['players'] as $p) {
            self::assertLessThan(120, $p['age_s'], 'dates relues en UTC');
        }
        self::assertSame([], (new RoleplaySessionLiveRoster($this->pdo))->diagnostics(self::T));
    }
}
