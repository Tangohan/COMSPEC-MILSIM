<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PlayerSessionRepository;
use App\Services\Personnel\PlayerSessionRebuildService;
use App\Services\Personnel\PlayerSessionService as S;
use App\Support\Migrations\SchemaInspector;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Sessions de jeu enregistrées (player_sessions) : signes de vie fusionnés à l'écriture, agrégats par membre en lot,
 * rattachement Steam ↔ membre relu à la lecture, isolation par communauté, mode dégradé sans la table, rattrapage
 * (essai à blanc, application, rejeu sans doublon) et étape de migration.
 */
final class PlayerSessionRepositoryTest extends TestCase
{
    private const GAP = 25 * 60;
    private const STEAM_A = '76561198000000001';
    private const STEAM_B = '76561198000000002';
    private const STEAM_C = '76561198000000003';

    private MysqlDdlSqlitePdo $pdo;
    private PlayerSessionRepository $repo;
    private int $now;

    protected function setUp(): void
    {
        date_default_timezone_set('Europe/Paris');
        $this->now = (new \DateTimeImmutable('2026-11-15 20:00:00'))->getTimestamp();
        $this->pdo = new MysqlDdlSqlitePdo();
        foreach ([
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, timezone TEXT)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, steam_id TEXT, display_name TEXT)',
            "INSERT INTO tenants (id, name, timezone) VALUES (1, 'Alpha', 'Europe/Paris'), (2, 'Bravo', 'America/Montreal')",
            // Membre 11 : Steam A. 12 : jamais lié à un Steam. 13 : Steam B. 21 (communauté 2) : même Steam A.
            "INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (11, 1, '" . self::STEAM_A . "', 'Raven'), (12, 1, NULL, 'Sans Steam'), (13, 1, '" . self::STEAM_B . "', 'Viper'), (21, 2, '" . self::STEAM_A . "', 'Raven ailleurs')",
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        $this->repo = new PlayerSessionRepository($this->pdo);
    }

    private function migrate(): void
    {
        (new SchemaInspector($this->pdo))->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_18_100_player_sessions.sql');
    }

    /** Signes de vie toutes les 10 minutes pendant $minutes. */
    private function play(int $tenant, string $steam, int $start, int $minutes, ?int $user = null, ?int $server = 5): void
    {
        for ($t = $start; $t <= $start + $minutes * 60; $t += 600) {
            $this->repo->recordHeartbeat($tenant, $steam, $t, self::GAP, $user, $server);
        }
    }

    public function testWithoutTheTableEverythingDegradesToNothing(): void
    {
        self::assertFalse($this->repo->schemaReady());
        self::assertNull($this->repo->recordHeartbeat(1, self::STEAM_A, $this->now, self::GAP));
        self::assertSame([11 => []], $this->repo->sessionsForUsers(1, [11], 0, $this->now));
        self::assertSame([], $this->repo->qualifyingUserIds(1, [11], 0, 3600));
        self::assertFalse((new SchemaInspector($this->pdo))->tableExists('player_sessions'));
    }

    public function testMigrationSqlCreatesTheTableWithItsIndexesAndRerunsCleanly(): void
    {
        $this->migrate();
        self::assertTrue((new PlayerSessionRepository($this->pdo))->schemaReady());
        $before = $this->pdo->changes;
        $this->migrate(); // CREATE TABLE IF NOT EXISTS : relançable
        self::assertSame($before, $this->pdo->changes);
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/2026_11_18_100_player_sessions.sql');
        self::assertStringNotContainsString('ALTER TABLE', $sql, 'uniques dans le CREATE TABLE, compatible SQLite');
        foreach (['uq_player_sessions_start (tenant_id, steam_uid, started_at)', 'idx_player_sessions_user (tenant_id, user_id, started_at)', 'idx_player_sessions_steam (steam_uid, started_at)'] as $needle) {
            self::assertStringContainsString($needle, $sql);
        }
        $down = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/rollback/player_sessions_down.sql');
        self::assertStringContainsString('DROP TABLE IF EXISTS player_sessions', $down);
    }

    public function testStepIsRegisteredAndIdempotent(): void
    {
        $step = \App\Support\Migrations\MigrationRegistry::find(dirname(__DIR__, 2), 'player_sessions');
        self::assertNotNull($step);
        self::assertSame('schema', $step['group']);
        self::assertTrue($step['rerun']);
        $root = dirname(__DIR__, 2);
        $pdo = $this->pdo;
        $run = static function () use ($root, $pdo, $step): string {
            ob_start();
            try {
                require $step['path'];
            } finally {
                $out = (string) ob_get_clean();
            }

            return $out;
        };
        self::assertStringContainsString('créée', $run());
        $changes = $this->pdo->changes;
        self::assertStringContainsString('déjà présente', $run());
        self::assertSame($changes, $this->pdo->changes, 'le second passage ne modifie rien');
    }

    public function testHeartbeatsAreMergedAtWriteTimeAndTheProcessIsIdempotent(): void
    {
        $this->migrate();
        $start = $this->now - 3 * 3600;
        $r1 = $this->repo->recordHeartbeat(1, self::STEAM_A, $start, self::GAP, 11, 5);
        self::assertSame('started', $r1['action']);
        $r2 = $this->repo->recordHeartbeat(1, self::STEAM_A, $start + 600, self::GAP, 11, 5);
        self::assertSame('extended', $r2['action']);
        self::assertSame($r1['session_id'], $r2['session_id']);
        // Même instant rejoué, ou signe de vie reçu dans le désordre : aucune duplication.
        $this->repo->recordHeartbeat(1, self::STEAM_A, $start + 600, self::GAP, 11, 5);
        $this->repo->recordHeartbeat(1, self::STEAM_A, $start + 300, self::GAP, 11, 5);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM player_sessions')->fetchColumn());
        // Coupure de 30 minutes : la précédente est fermée à son dernier signe de vie, une nouvelle commence.
        $r3 = $this->repo->recordHeartbeat(1, self::STEAM_A, $start + 600 + 30 * 60, self::GAP, 11, 5);
        self::assertSame('started', $r3['action']);
        $first = $this->pdo->query('SELECT * FROM player_sessions ORDER BY started_at ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(600, (int) $first['duration_s']);
        self::assertSame($first['last_seen_at'], $first['ended_at']);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM player_sessions')->fetchColumn());
        self::assertNull($this->repo->recordHeartbeat(1, 'LOCAL', $start, self::GAP), 'Steam factice du solo : ignoré');
        self::assertNull($this->repo->recordHeartbeat(0, self::STEAM_A, $start, self::GAP));
    }

    public function testLongContinuousPresenceIsCappedAndFlaggedInTheTable(): void
    {
        $this->migrate();
        $start = $this->now - 40 * 3600;
        for ($t = $start; $t <= $start + 26 * 3600; $t += 1200) {
            $this->repo->recordHeartbeat(1, self::STEAM_A, $t, self::GAP, 11);
        }
        $row = $this->pdo->query('SELECT duration_s, aberrant FROM player_sessions')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(S::MAX_SESSION_SECONDS, (int) $row['duration_s']);
        self::assertSame(1, (int) $row['aberrant']);
    }

    /** Membre réaliste : 12 sessions dont 5 d'au moins 1 h, 3 de 5 à 20 min, et deux reconnexions rapprochées fusionnées. */
    private function seedRealisticMember(): void
    {
        $day = 86400;
        $base = $this->now - 20 * $day;
        // 5 sessions qui comptent (75 à 150 min), sur 5 jours différents ; la 2e est coupée puis reprise 8 min plus tard.
        $this->play(1, self::STEAM_A, $base, 90, 11);
        $this->play(1, self::STEAM_A, $base + 3 * $day, 40, 11);
        $this->play(1, self::STEAM_A, $base + 3 * $day + 40 * 60 + 8 * 60, 50, 11); // reconnexion de 8 min : même session (90+ min)
        $this->play(1, self::STEAM_A, $base + 8 * $day, 150, 11);
        $this->play(1, self::STEAM_A, $base + 12 * $day, 75, 11);
        $this->play(1, self::STEAM_A, $base + 18 * $day, 100, 11);
        // 3 connexions courtes (5 à 20 min) et 4 autres sessions de 20 à 50 min (jamais 1 h).
        foreach ([5, 12, 20] as $i => $minutes) {
            $this->play(1, self::STEAM_A, $base + (2 + $i) * $day + 40000, $minutes, 11);
        }
        foreach ([20, 30, 45, 50] as $i => $minutes) {
            $this->play(1, self::STEAM_A, $base + (9 + $i) * $day + 50000, $minutes, 11);
        }
    }

    public function testRealisticMemberSummary(): void
    {
        $this->migrate();
        $this->seedRealisticMember();
        $sum = $this->repo->summariesForUsers(1, [11], 3600, self::GAP, $this->now)[11];
        self::assertTrue($sum['linked']);
        self::assertSame(5, $sum['windows'][30]['sessions'], '5 sessions d’au moins 1 h');
        self::assertSame(7, $sum['windows'][30]['short'], '3 connexions courtes et 4 sessions de moins d’1 h');
        self::assertSame(1, $sum['windows'][7]['sessions']);
        self::assertSame(5, $sum['windows'][30]['days']);
        self::assertSame(5, $sum['windows'][90]['sessions']);
        self::assertGreaterThan(5 * 3600, $sum['windows'][30]['seconds']);
        self::assertTrue($sum['active']);
        self::assertSame(100 * 60, $sum['last_qualifying']['duration_s']);
        self::assertSame(S::RELIABILITY_GOOD, $sum['reliability']);
        // Le même membre avec un seuil de 30 minutes compte davantage de sessions (seuil par communauté).
        $lenient = $this->repo->summariesForUsers(1, [11], 1800, self::GAP, $this->now)[11];
        self::assertGreaterThan($sum['windows'][30]['sessions'], $lenient['windows'][30]['sessions']);
        $strict = $this->repo->summariesForUsers(1, [11], 7200, self::GAP, $this->now)[11];
        self::assertLessThan($sum['windows'][30]['sessions'], $strict['windows'][30]['sessions']);
    }

    public function testMemberWithoutSessionAndMemberNeverLinkedAndBatchWithoutNPlusOne(): void
    {
        $this->migrate();
        $this->seedRealisticMember();
        $sums = $this->repo->summariesForUsers(1, [11, 12, 13, 999], 3600, self::GAP, $this->now);
        self::assertSame(S::RELIABILITY_NO_DATA, $sums[13]['reliability'], 'Steam relié mais aucune session');
        self::assertFalse($sums[13]['active']);
        self::assertSame(S::RELIABILITY_NO_UID, $sums[12]['reliability'], 'jamais lié à un Steam');
        self::assertFalse($sums[12]['linked']);
        self::assertSame(S::RELIABILITY_NO_UID, $sums[999]['reliability'], 'compte inconnu de cette communauté');
        self::assertTrue($sums[11]['active']);
    }

    public function testCommunitiesAreIsolatedEvenForTheSameSteam(): void
    {
        $this->migrate();
        $this->play(1, self::STEAM_A, $this->now - 5 * 3600, 120, 11);
        $this->play(2, self::STEAM_A, $this->now - 2 * 86400, 70, 21);
        $one = $this->repo->summariesForUsers(1, [11, 21], 3600, self::GAP, $this->now);
        self::assertSame(1, $one[11]['windows'][7]['sessions']);
        self::assertSame(S::RELIABILITY_NO_UID, $one[21]['reliability'], 'le membre 21 appartient à une autre communauté');
        $two = $this->repo->summariesForUsers(2, [21], 3600, self::GAP, $this->now);
        self::assertSame(1, $two[21]['windows'][7]['sessions']);
        self::assertSame(70 * 60, $two[21]['last_qualifying']['duration_s']);
        self::assertSame([11 => true], $this->repo->qualifyingUserIds(1, [11, 21], $this->now - 30 * 86400, 3600));
        self::assertSame([21 => true], $this->repo->qualifyingUserIds(2, [11, 21], $this->now - 30 * 86400, 3600));
        self::assertSame('Europe/Paris', $this->repo->tenantTimezone(1));
        self::assertSame('America/Montreal', $this->repo->tenantTimezone(2));
        self::assertSame('Europe/Paris', $this->repo->tenantTimezone(404));
    }

    public function testSteamLinkedAfterwardsRecoversItsOldSessions(): void
    {
        $this->migrate();
        $this->play(1, self::STEAM_C, $this->now - 4 * 3600, 90, null);
        $this->pdo->exec("UPDATE users SET steam_id = '" . self::STEAM_C . "' WHERE id = 12");
        $sum = $this->repo->summariesForUsers(1, [12], 3600, self::GAP, $this->now)[12];
        self::assertSame(1, $sum['windows'][7]['sessions'], 'rattachement relu depuis users.steam_id');
        self::assertSame(S::RELIABILITY_GOOD, $sum['reliability']);
    }

    public function testQualifyingExistsClauseKeepsFiveMinuteConnectionsOutOfActivity(): void
    {
        $this->migrate();
        $this->play(1, self::STEAM_A, $this->now - 2 * 86400, 5, 11);
        $this->play(1, self::STEAM_B, $this->now - 2 * 86400, 80, 13);
        [$sql, $params] = PlayerSessionRepository::qualifyingExistsClause('u', $this->now - 30 * 86400, 3600);
        $st = $this->pdo->prepare("SELECT u.id FROM users u WHERE u.tenant_id = 1 AND {$sql} ORDER BY u.id");
        $st->execute($params);
        self::assertSame([13], array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
    }

    private function seedMissionSegments(): void
    {
        foreach ([
            'CREATE TABLE roleplay_game_session_members (id INTEGER PRIMARY KEY, tenant_id INTEGER, session_id INTEGER, user_id INTEGER, steam_uid TEXT)',
            'CREATE TABLE roleplay_game_session_segments (id INTEGER PRIMARY KEY, tenant_id INTEGER, session_id INTEGER, member_id INTEGER, reason TEXT, started_at TEXT, ended_at TEXT, seconds INTEGER)',
            "INSERT INTO roleplay_game_session_members (id, tenant_id, session_id, user_id, steam_uid) VALUES (1, 1, 1, 11, NULL), (2, 1, 1, NULL, '" . self::STEAM_C . "'), (3, 1, 1, 13, NULL)",
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        $ins = $this->pdo->prepare('INSERT INTO roleplay_game_session_segments (tenant_id, session_id, member_id, reason, started_at, ended_at, seconds) VALUES (1, 1, ?, ?, ?, ?, 0)');
        $d = static fn (int $ts): string => date('Y-m-d H:i:s', $ts);
        $t = $this->now - 10 * 86400;
        // Membre 11 : 70 min, coupure de 6 min, 30 min (une session de 106 min) ; Steam C : 10 min ; membre 13 : check-in seul.
        $ins->execute([1, 'join', $d($t), $d($t + 70 * 60)]);
        $ins->execute([1, 'reconnect', $d($t + 76 * 60), $d($t + 106 * 60)]);
        $ins->execute([2, 'join', $d($t), $d($t + 10 * 60)]);
        $ins->execute([3, 'check_in', $d($t), $d($t)]);
    }

    public function testRebuildDryRunThenApplyThenReplayDoesNotDuplicate(): void
    {
        $this->migrate();
        $this->seedMissionSegments();
        $svc = new PlayerSessionRebuildService($this->repo);
        $logs = [];
        $dry = $svc->run(1, false, self::GAP, 3600, 200, null, static function (string $m) use (&$logs): void {
            $logs[] = $m;
        });
        self::assertTrue($dry['ready']);
        self::assertSame(2, $dry['players'], 'players');
        self::assertSame(2, $dry['sessions'], 'sessions');
        self::assertSame(1, $dry['qualifying'], 'qual');
        self::assertSame(2, $dry['inserted']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM player_sessions')->fetchColumn(), 'essai à blanc : rien d’écrit');

        $applied = $svc->run(1, true, self::GAP, 3600);
        self::assertSame(2, $applied['inserted']);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM player_sessions')->fetchColumn());
        $replay = $svc->run(1, true, self::GAP, 3600);
        self::assertSame(0, $replay['inserted']);
        self::assertSame(2, $replay['unchanged']);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM player_sessions')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM player_sessions WHERE source = 'backfill'")->fetchColumn());

        $sum = $this->repo->summariesForUsers(1, [11, 13], 3600, self::GAP, $this->now);
        self::assertSame(1, $sum[11]['windows'][30]['sessions']);
        self::assertSame(106 * 60, $sum[11]['last_qualifying']['duration_s']);
        self::assertSame(S::RELIABILITY_PARTIAL, $sum[11]['reliability'], 'données reconstituées : fiabilité partielle');
        self::assertSame(S::RELIABILITY_NO_DATA, $sum[13]['reliability'], 'un pointage n’est pas une session');
        self::assertSame([1], $this->repo->tenantsWithHistory());
    }

    public function testRebuildWithoutTheTableSaysSoAndWritesNothing(): void
    {
        $this->seedMissionSegments();
        $logs = [];
        $r = (new PlayerSessionRebuildService($this->repo))->run(1, true, self::GAP, 3600, 200, null, static function (string $m) use (&$logs): void {
            $logs[] = $m;
        });
        self::assertFalse($r['ready']);
        self::assertStringContainsString('player_sessions est absente', implode('', $logs));
    }

    public function testCliToolIsDryRunByDefaultAndWiredToTheService(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/tools/rebuild-player-sessions.php');
        self::assertStringContainsString("!empty(\$options['apply'])", $src);
        self::assertStringContainsString('ESSAI À BLANC', $src);
        self::assertStringContainsString("'tenant'", $src);
        self::assertStringContainsString('PlayerSessionRebuildService', $src);
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $src);
    }
}
