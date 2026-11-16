<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakWebCommandRepository;
use App\Repositories\RoleplayGameSessionRepository;
use App\Repositories\RoleplaySessionAppRepository;
use App\Repositories\UserRepository;
use App\Services\AtakToc\TocPhoneService;
use App\Services\Personnel\RoleplayGameSessionService;
use App\Services\Personnel\RoleplaySessionAttendance;
use App\Services\Personnel\RoleplaySessionCodeService;
use App\Services\Personnel\RoleplaySessionGameApp;
use App\Services\Personnel\RoleplaySessionLiveRoster;
use App\Services\Personnel\RoleplaySessionNotifier;
use App\Services\Security\FileRateLimiter;
use App\Support\SessionJoinCode;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Sessions de jeu, app Session : code de session (format, unicité, expiration, régénération, révocation), rattachement par
 * le code (succès, idempotence, erreurs, débit), lecture de la session en cours, pointage depuis le téléphone, départ,
 * notifications publiées dans la file existante des téléphones, joueurs ATAK connectés, pointage par l'animateur,
 * lancement de l'enregistrement, rendu de la fiche, routes, migration et contrat.
 * Base SQLite en mémoire avec la vraie migration SQL de l'étape ; aucun double de test.
 */
final class RoleplaySessionAppTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const OTHER = 77;

    private MysqlDdlSqlitePdo $pdo;
    private int $tenant;
    private string $ip;
    /** @var list<array<string, mixed>> */
    private array $sent = [];
    private int $now;
    /** @var list<string> */
    private array $limiterKeys = [];

    protected function setUp(): void
    {
        $this->tenant = random_int(100000, 900000);
        $this->ip = 'test-' . bin2hex(random_bytes(6));
        $this->now = time();
        $this->pdo = new MysqlDdlSqlitePdo();
        foreach ([
            'CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, display_name TEXT, callsign TEXT, email TEXT, steam_id TEXT, status TEXT)',
            "CREATE TABLE roleplay_game_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, session_uid TEXT, server_name TEXT, mission_name TEXT, session_kind TEXT, hour_category TEXT, community_event_id INTEGER, is_official INTEGER, attendance_enabled INTEGER, status TEXT, planned_starts_at TEXT, planned_ends_at TEXT, started_at TEXT, ended_at TEXT, min_minutes INTEGER, min_percent INTEGER, late_tolerance_minutes INTEGER, created_by_user_id INTEGER, notes TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE roleplay_game_session_members (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, session_id INTEGER, user_id INTEGER, steam_uid TEXT, joined_at TEXT, left_at TEXT, last_heartbeat_at TEXT, raw_seconds INTEGER DEFAULT 0, eligible_seconds INTEGER DEFAULT 0, validated_seconds INTEGER DEFAULT 0, attendance_status TEXT DEFAULT 'absent', attendance_source TEXT, checked_in_at TEXT, checked_out_at TEXT, attendance_valid INTEGER DEFAULT 0, rh_excluded INTEGER DEFAULT 0, staff_validated_at TEXT, staff_validated_by INTEGER, UNIQUE (session_id, user_id))",
            'CREATE TABLE roleplay_game_session_segments (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, session_id INTEGER, member_id INTEGER, reason TEXT, started_at TEXT, ended_at TEXT, seconds INTEGER DEFAULT 0)',
            "CREATE TABLE atak_units (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, map_id INTEGER DEFAULT 1, call_sign TEXT, group_name TEXT, status TEXT, updated_at TEXT, extra TEXT)",
            'CREATE TABLE tenant_game_servers (id INTEGER PRIMARY KEY, tenant_id INTEGER, label TEXT)',
            'CREATE TABLE tenant_game_server_presence (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, steam_uid TEXT, player_name TEXT, server_name TEXT, mission TEXT, matched_server_id INTEGER, seen_at TEXT, UNIQUE (tenant_id, steam_uid))',
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        $this->migrate();
        $add = $this->pdo->prepare('INSERT INTO users (id, tenant_id, display_name, callsign, email, steam_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ([
            [1, $this->tenant, 'Capitaine Morel', 'ANIMA', 'a@x.test', '76561198000000001', 'active'],
            [2, $this->tenant, 'Sergent Faure', 'FAUCON', 'b@x.test', '76561198000000002', 'active'],
            [3, $this->tenant, 'Caporal Diaz', 'LYNX', 'c@x.test', '76561198000000003', 'active'],
            [4, $this->tenant, 'Ancien membre', 'OLD', 'd@x.test', '76561198000000004', 'suspended'],
            [9, self::OTHER, 'Autre communauté', 'ALIEN', 'e@x.test', '76561198000000009', 'active'],
        ] as $u) {
            $add->execute($u);
        }
    }

    protected function tearDown(): void
    {
        $limiter = new FileRateLimiter();
        foreach ($this->limiterKeys as $k) {
            $limiter->clear($k);
        }
        foreach ([1, 2, 3, 4, 9] as $uid) {
            foreach (['rp_session_join|u|' . $this->tenant . '|' . $uid, 'rp_session_act|' . $this->tenant . '|' . $uid] as $k) {
                $limiter->clear($k);
            }
        }
        $limiter->clear('rp_session_join|ip|' . $this->ip);
        $limiter->clear('rp_session_tick|' . $this->tenant);
    }

    private function migrate(): void
    {
        $sql = preg_replace('/^--.*$/m', '', self::read('migrations/2026_11_23_100_roleplay_session_app.sql')) ?? '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $this->pdo->exec($statement);
        }
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . '/' . $path));
    }

    private function sessions(): RoleplayGameSessionRepository
    {
        return new RoleplayGameSessionRepository($this->pdo);
    }

    private function codes(?int $now = null, ?callable $random = null): RoleplaySessionCodeService
    {
        $at = $now ?? $this->now;

        return new RoleplaySessionCodeService(new RoleplaySessionAppRepository($this->pdo), $this->sessions(), static fn (): int => $at, $random);
    }

    private function notifier(?RoleplaySessionAppRepository $repo = null): RoleplaySessionNotifier
    {
        return new RoleplaySessionNotifier($repo ?? new RoleplaySessionAppRepository($this->pdo), function (int $tenantId, string $target, string $label, array $args, array $wire, int $ttl, ?int $userId): ?int {
            $this->sent[] = ['tenant' => $tenantId, 'target' => $target, 'args' => $args, 'wire' => $wire, 'ttl' => $ttl];

            return count($this->sent);
        }, fn (): int => $this->now);
    }

    private function service(): RoleplayGameSessionService
    {
        return new RoleplayGameSessionService($this->sessions(), new UserRepository($this->pdo));
    }

    private function app(?RoleplaySessionCodeService $codes = null): RoleplaySessionGameApp
    {
        return new RoleplaySessionGameApp($codes ?? $this->codes(), $this->service(), new FileRateLimiter(), $this->notifier());
    }

    /** @param array<string, mixed> $over */
    private function session(array $over = [], ?int $tenant = null): array
    {
        $id = $this->sessions()->create($tenant ?? $this->tenant, $over + [
            'session_uid' => 'staff-' . bin2hex(random_bytes(4)),
            'mission_name' => 'Opération Dague',
            'session_kind' => 'officielle',
            'hour_category' => 'Opération',
            'attendance_enabled' => 1,
            'status' => 'open',
            'started_at' => date('Y-m-d H:i:s', $this->now - 600),
            'created_by_user_id' => 1,
        ]);

        return (array) $this->sessions()->findById($tenant ?? $this->tenant, $id);
    }

    // —— Code de session ——

    public function testCodeFormatIsReadableAndUnambiguous(): void
    {
        self::assertSame(32, strlen(SessionJoinCode::ALPHABET));
        foreach (['0', 'O', '1', 'I'] as $ambiguous) {
            self::assertStringNotContainsString($ambiguous, SessionJoinCode::ALPHABET);
        }
        for ($i = 0; $i < 200; $i++) {
            $code = SessionJoinCode::generate();
            self::assertMatchesRegularExpression('/\A[A-HJ-NP-Z2-9]{6}\z/', $code);
        }
        self::assertSame('K7M-4QX', SessionJoinCode::format('K7M4QX'));
        self::assertSame('K7M4QX', SessionJoinCode::normalize(' k7m-4qx '));
        self::assertSame('K7M4QX', SessionJoinCode::normalize('K7M 4QX'));
        self::assertNull(SessionJoinCode::normalize('K7M-4Q0'), 'zéro hors alphabet');
        self::assertNull(SessionJoinCode::normalize('K7M4Q'));
        self::assertNull(SessionJoinCode::normalize(['K7M4QX']));
        self::assertNull(SessionJoinCode::normalize(str_repeat('A', 40)));
    }

    public function testCodeIsIssuedOnceUniquePerCommunityAndRegenerated(): void
    {
        $s = $this->session();
        $view = $this->codes()->ensure($this->tenant, $s, 1);
        self::assertTrue($view['ready']);
        self::assertSame('active', $view['state']);
        self::assertMatchesRegularExpression('/\A[A-HJ-NP-Z2-9]{3}-[A-HJ-NP-Z2-9]{3}\z/', (string) $view['formatted']);
        self::assertSame($view['code'], $this->codes()->ensure($this->tenant, $s, 1)['code'], 'relire la fiche ne change pas le code');

        // Tirages imposés : le premier entre en collision avec le code existant, il est retiré.
        $first = (string) $view['code'];
        $other = $this->session(['mission_name' => 'Tir']);
        $draws = array_merge(str_split($first), str_split('ABCDEF'));
        $alphabet = SessionJoinCode::ALPHABET;
        $random = static function (int $min, int $max) use (&$draws, $alphabet): int {
            return (int) strpos($alphabet, (string) array_shift($draws));
        };
        $second = $this->codes(null, $random)->ensure($this->tenant, $other, 1);
        self::assertSame('ABCDEF', $second['code'], 'collision retirée');
        // Une autre communauté peut tomber sur le même code : unicité par communauté.
        $foreign = $this->session([], self::OTHER);
        $draws = str_split('ABCDEF');
        self::assertSame('ABCDEF', $this->codes(null, $random)->ensure(self::OTHER, $foreign, 9)['code']);

        $res = $this->codes()->regenerate($this->tenant, $s, 1);
        self::assertTrue($res['ok']);
        self::assertNotSame($first, $res['code']);
        $old = (new RoleplaySessionAppRepository($this->pdo))->findCode($this->tenant, $first);
        self::assertSame('replaced', $old['status']);
        $journal = array_column((new RoleplaySessionAppRepository($this->pdo))->journal($this->tenant, (int) $s['id']), 'event');
        self::assertContains('code_issued', $journal);
        self::assertContains('code_regenerated', $journal);
    }

    public function testRevokedCodeIsNotReissuedAndPastSessionGetsNoCode(): void
    {
        $s = $this->session();
        $this->codes()->ensure($this->tenant, $s, 1);
        self::assertTrue($this->codes()->revoke($this->tenant, $s, 1)['ok']);
        self::assertSame('revoked', $this->codes()->ensure($this->tenant, $s, 1)['state'], 'une révocation n’est jamais annulée toute seule');
        self::assertFalse($this->codes()->revoke($this->tenant, $s, 1)['ok']);

        $closed = $this->session(['status' => 'closed', 'ended_at' => date('Y-m-d H:i:s', $this->now - 7200)]);
        $view = $this->codes()->ensure($this->tenant, $closed, 1);
        self::assertSame('none', $view['state']);
        self::assertFalse($view['can_issue']);
        self::assertSame('session_closed', $this->codes()->regenerate($this->tenant, $closed, 1)['error']);
    }

    public function testCodeExpiresAfterTheSessionPlusMargin(): void
    {
        $s = $this->session();
        $code = (string) $this->codes()->ensure($this->tenant, $s, 1)['code'];
        $this->sessions()->update($this->tenant, (int) $s['id'], ['status' => 'closed', 'ended_at' => date('Y-m-d H:i:s', $this->now)]);
        $closed = (array) $this->sessions()->findById($this->tenant, (int) $s['id']);
        $row = (new RoleplaySessionAppRepository($this->pdo))->findCode($this->tenant, $code);
        self::assertSame('active', $this->codes($this->now + 29 * 60)->state($closed, $row), 'marge de 30 min');
        self::assertSame('expired', $this->codes($this->now + 31 * 60)->state($closed, $row));
        self::assertSame(1, $this->codes($this->now + 31 * 60)->expireDue());
        self::assertSame('expired', (new RoleplaySessionAppRepository($this->pdo))->findCode($this->tenant, $code)['status']);
        // Session ouverte jamais clôturée : 24 h après le démarrage.
        $live = $this->session();
        $c = $this->codes()->ensure($this->tenant, $live, 1);
        self::assertSame((int) strtotime((string) $live['started_at']) + 24 * 3600, $c['expires_at']);
    }

    // —— Rejoindre avec le code ——

    public function testJoinAttachesThePlayerOnceAndChecksInALiveSession(): void
    {
        $s = $this->session();
        $code = (string) $this->codes()->ensure($this->tenant, $s, 1)['formatted'];
        [$status, $body] = $this->app()->join($this->tenant, 2, '76561198000000002', strtolower($code), $this->ip);
        self::assertSame(200, $status);
        self::assertSame(1, $body['v']);
        self::assertTrue($body['ok']);
        self::assertSame('active', $body['app_state']);
        self::assertSame('Opération Dague', $body['session']['name']);
        self::assertSame('live', $body['session']['state']);
        self::assertSame('ANIMA', $body['session']['host']);
        self::assertTrue($body['joined']['checked_in_now']);
        self::assertFalse($body['joined']['already']);
        self::assertNotNull($body['me']['checked_in_at']);
        self::assertTrue($body['me']['playing'], 'temps ouvert : session en cours');
        self::assertSame(['joined' => 1, 'checked_in' => 1, 'playing' => 1], $body['session']['gauge']);

        [$again, $body2] = $this->app()->join($this->tenant, 2, '76561198000000002', $code, $this->ip);
        self::assertSame(200, $again);
        self::assertTrue($body2['joined']['already']);
        self::assertFalse($body2['joined']['checked_in_now']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM roleplay_game_session_members')->fetchColumn(), 'jamais de doublon');
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM roleplay_game_session_segments WHERE reason = 'check_in'")->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM roleplay_game_session_journal WHERE event = 'join'")->fetchColumn());
    }

    public function testJoinBeforeTheStartOnlyEnrolsThenCheckinWhenLive(): void
    {
        $s = $this->session(['started_at' => null, 'planned_starts_at' => date('Y-m-d H:i:s', $this->now + 3600)]);
        $code = (string) $this->codes()->ensure($this->tenant, $s, 1)['code'];
        [, $body] = $this->app()->join($this->tenant, 3, '', $code, $this->ip);
        self::assertSame('active', $body['app_state']);
        self::assertSame('upcoming', $body['session']['state']);
        self::assertNull($body['me']['checked_in_at']);
        [$st, $err] = $this->app()->checkin($this->tenant, 3, (int) $s['id']);
        self::assertSame([409, 'session_not_started'], [$st, $err['error']]);

        $this->service()->startSession($this->tenant, (int) $s['id']);
        [, $cur] = $this->app()->current($this->tenant, 3);
        self::assertSame('checkin', $cur['app_state']);
        [$ok, $done] = $this->app()->checkin($this->tenant, 3, (int) $s['id']);
        self::assertSame(200, $ok);
        self::assertFalse($done['checkin']['already']);
        self::assertSame('active', $done['app_state']);
        self::assertTrue($this->app()->checkin($this->tenant, 3, (int) $s['id'])[1]['checkin']['already']);

        [$left, $bye] = $this->app()->leave($this->tenant, 3, (int) $s['id']);
        self::assertSame(200, $left);
        self::assertNotNull($bye['me']['left_at']);
        self::assertFalse($bye['me']['playing']);
        self::assertTrue($this->app()->leave($this->tenant, 3, (int) $s['id'])[1]['leave']['already']);
    }

    public function testJoinErrorsAreDistinctAndDoNotEnumerate(): void
    {
        $s = $this->session();
        $code = (string) $this->codes()->ensure($this->tenant, $s, 1)['code'];
        $app = $this->app();
        self::assertSame([422, 'invalid_format'], $this->err($app->join($this->tenant, 2, '', 'A0', $this->ip)));
        self::assertSame([404, 'code_unknown'], $this->err($app->join($this->tenant, 2, '', 'ZZZZZZ', $this->ip)));
        // Le code d'une autre communauté est inconnu ici.
        self::assertSame([404, 'code_unknown'], $this->err($app->join(self::OTHER, 9, '', $code, $this->ip)));
        self::assertSame([403, 'not_allowed'], $this->err($app->join($this->tenant, 4, '', $code, $this->ip)), 'compte suspendu');
        self::assertSame([403, 'not_allowed'], $this->err($app->join($this->tenant, 9, '', $code, $this->ip)), 'membre d’une autre communauté');
        self::assertSame([401, 'account_link_required'], $this->err($app->join($this->tenant, 0, '', $code, $this->ip)));
        // Révoqué : même réponse qu'un code inconnu.
        $this->codes()->revoke($this->tenant, $s, 1);
        self::assertSame([404, 'code_unknown'], $this->err($app->join($this->tenant, 2, '', $code, $this->ip)));

        $ended = $this->session();
        $endedCode = (string) $this->codes()->ensure($this->tenant, $ended, 1)['code'];
        $this->service()->closeSession($this->tenant, (int) $ended['id']);
        self::assertSame([409, 'session_closed'], $this->err($app->join($this->tenant, 3, '', $endedCode, $this->ip)));
        $later = $this->app($this->codes($this->now + 3600));
        self::assertSame([410, 'code_expired'], $this->err($later->join($this->tenant, 3, '', $endedCode, $this->ip)));
        self::assertGreaterThan(0, (int) $this->pdo->query("SELECT COUNT(*) FROM roleplay_game_session_journal WHERE event = 'join_refused'")->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM roleplay_game_session_journal WHERE detail LIKE '%ZZZZZZ%'")->fetchColumn(), 'le code saisi n’est pas journalisé');
    }

    public function testJoinIsRateLimitedPerPlayer(): void
    {
        $app = $this->app();
        for ($i = 0; $i < RoleplaySessionGameApp::JOIN_PER_PLAYER; $i++) {
            self::assertSame(404, $app->join($this->tenant, 2, '', 'ZZZZZZ', $this->ip)[0]);
        }
        [$status, $body] = $app->join($this->tenant, 2, '', 'ZZZZZZ', $this->ip);
        self::assertSame(429, $status);
        self::assertSame('rate_limited', $body['error']);
        self::assertGreaterThan(0, $body['retry_after']);
        self::assertSame(404, $app->join($this->tenant, 3, '', 'ZZZZZZ', $this->ip)[0], 'un autre joueur n’est pas bloqué');
    }

    public function testCurrentWithoutSessionAndOtherCommunityIsolation(): void
    {
        [$st, $body] = $this->app()->current($this->tenant, 2);
        self::assertSame(200, $st);
        self::assertSame('no_session', $body['app_state']);
        $foreign = $this->session([], self::OTHER);
        $this->codes()->ensure(self::OTHER, $foreign, 9);
        self::assertSame('no_session', $this->app()->current($this->tenant, 2)[1]['app_state'], 'session d’une autre communauté invisible');
        self::assertSame([404, 'not_joined'], $this->err($this->app()->checkin($this->tenant, 2, (int) $foreign['id'])));
        $s = $this->session();
        $this->codes()->ensure($this->tenant, $s, 1);
        $cur = $this->app()->current($this->tenant, 2)[1];
        self::assertSame('code_required', $cur['app_state']);
        self::assertNull($cur['me']);
        self::assertArrayNotHasKey('code', $cur['session'], 'le code n’est jamais renvoyé au joueur');
    }

    public function testWithoutTheTablesTheApiAnswersFeatureUnavailable(): void
    {
        $bare = new MysqlDdlSqlitePdo();
        $codes = new RoleplaySessionCodeService(new RoleplaySessionAppRepository($bare), new RoleplayGameSessionRepository($bare));
        self::assertFalse($codes->ready());
        self::assertSame([503, 'feature_unavailable'], $this->err((new RoleplaySessionGameApp($codes, new RoleplayGameSessionService(new RoleplayGameSessionRepository($bare), new UserRepository($bare))))->current($this->tenant, 2)));
        self::assertFalse($codes->ensure($this->tenant, ['id' => 1, 'status' => 'open'], 1)['ready']);
    }

    /** @param array{0: int, 1: array<string, mixed>} $r @return array{0: int, 1: string} */
    private function err(array $r): array
    {
        return [$r[0], (string) ($r[1]['error'] ?? '')];
    }

    // —— Notifications en jeu ——

    public function testNotificationsUseThePhoneQueueOncePerEvent(): void
    {
        $upcoming = $this->session(['started_at' => null, 'planned_starts_at' => date('Y-m-d H:i:s', $this->now + 12 * 60)]);
        $n = $this->notifier();
        self::assertSame(1, $n->tickReminders($this->tenant));
        self::assertSame(0, $n->tickReminders($this->tenant), 'rappel envoyé une seule fois');
        self::assertSame('*', $this->sent[0]['target']);
        self::assertSame('reminder_15', $this->sent[0]['wire']['ev']);
        self::assertSame('ses', $this->sent[0]['wire']['src']);
        self::assertSame((int) $upcoming['id'], $this->sent[0]['wire']['sid']);
        self::assertStringContainsString('Opération Dague commence dans 15 min', TocPhoneService::unwireText((string) $this->sent[0]['wire']['m']));

        // Fil réellement lu par le mod (fn_webCmdDispatch → fn_tocPhone « notify »).
        $wire = AtakWebCommandRepository::buildWire('phone', '*', 'notify', 'session:1', $this->sent[0]['wire']);
        self::assertStringStartsWith('WC1~phone~*~notify~session:1~lv=i~m=', $wire);
        self::assertLessThanOrEqual(480, strlen($wire));
        self::assertStringNotContainsString('|', $wire);

        $this->now += 8 * 60;
        self::assertSame(1, $this->notifier()->tickReminders($this->tenant));
        self::assertSame('reminder_5', $this->sent[1]['wire']['ev']);
        self::assertSame('a', $this->sent[1]['wire']['lv']);

        $before = $upcoming;
        $this->service()->startSession($this->tenant, (int) $upcoming['id']);
        $after = (array) $this->sessions()->findById($this->tenant, (int) $upcoming['id']);
        self::assertSame('started', $n->onStateChange($this->tenant, $before, $after, 1));
        $this->service()->closeSession($this->tenant, (int) $upcoming['id']);
        $closed = (array) $this->sessions()->findById($this->tenant, (int) $upcoming['id']);
        self::assertSame('ended', $n->onStateChange($this->tenant, $after, $closed, 1));
        self::assertSame(['reminder_15', 'reminder_5', 'started', 'ended'], array_map(static fn (array $s): string => (string) $s['wire']['ev'], $this->sent));
    }

    public function testDisabledSessionSendsNothingButTheTestStillReachesTheHost(): void
    {
        $s = $this->session();
        $repo = new RoleplaySessionAppRepository($this->pdo);
        $repo->saveSettings($this->tenant, (int) $s['id'], ['notify_enabled' => false, 'remind_15' => true, 'remind_5' => true, 'briefing' => 'Fréquence 45.0, tenue de nuit'], 1, date('Y-m-d H:i:s'));
        self::assertSame('Fréquence 45.0, tenue de nuit', $repo->settings($this->tenant, (int) $s['id'])['briefing']);
        $n = $this->notifier($repo);
        self::assertSame('disabled', $n->publish($this->tenant, $s, 'checkin_call', 1)['reason']);
        self::assertSame([], $this->sent);
        $res = $n->publish($this->tenant, $s, 'test', 1, null, '76561198000000001');
        self::assertTrue($res['sent']);
        self::assertSame('76561198000000001', $this->sent[0]['target'], 'test : seul le téléphone de l’animateur');
        [, $body] = $this->app()->join($this->tenant, 2, '', (string) $this->codes()->ensure($this->tenant, $s, 1)['code'], $this->ip);
        self::assertSame('Fréquence 45.0, tenue de nuit', $body['session']['briefing']);
    }

    public function testMessagesStayShortAndPlain(): void
    {
        foreach (array_keys(RoleplaySessionNotifier::EVENTS) as $event) {
            $m = RoleplaySessionNotifier::message($event, ['mission_name' => str_repeat('Opération <b>longue</b> ', 10), 'planned_starts_at' => '2026-11-20 21:00:00']);
            self::assertLessThanOrEqual(RoleplaySessionNotifier::MESSAGE_MAX, mb_strlen($m));
            self::assertStringNotContainsString('<b>', $m);
            self::assertDoesNotMatchRegularExpression('/fiction|simulation|\bRP\b/i', $m);
        }
    }

    // —— Joueurs ATAK connectés, pointage, enregistrement ——

    private function seedLive(): void
    {
        $unit = $this->pdo->prepare('INSERT INTO atak_units (tenant_id, call_sign, group_name, status, updated_at, extra) VALUES (?, ?, ?, ?, ?, ?)');
        $utc = fn (int $ago): string => gmdate('Y-m-d H:i:s', $this->now - $ago);
        $unit->execute([$this->tenant, 'FAUCON', '1re section', 'linked', $utc(20), '{"steam_uid":"76561198000000002"}']);
        $unit->execute([$this->tenant, 'LYNX', '1re section', 'linked', $utc(900), '{"steam_uid":"76561198000000003"}']);
        $unit->execute([$this->tenant, 'INCONNU', '', 'linked', $utc(30), '{"steam_uid":"76561198000000055"}']);
        $unit->execute([self::OTHER, 'ALIEN', '', 'linked', $utc(10), '{"steam_uid":"76561198000000009"}']);
        $this->pdo->exec("INSERT INTO tenant_game_servers (id, tenant_id, label) VALUES (5, {$this->tenant}, 'Serveur Alpha')");
        $p = $this->pdo->prepare('INSERT INTO tenant_game_server_presence (tenant_id, steam_uid, player_name, server_name, mission, matched_server_id, seen_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $p->execute([$this->tenant, '76561198000000002', 'Faure', 'alpha', 'Dague', 5, $utc(120)]);
        $p->execute([$this->tenant, '76561198000000001', 'Morel', 'Serveur public', 'Autre', null, $utc(60)]);
    }

    public function testLiveRosterMergesLinkAndPresenceForThisCommunityOnly(): void
    {
        $this->seedLive();
        $s = $this->session();
        $roster = (new RoleplaySessionLiveRoster($this->pdo, fn (): int => $this->now))->roster($this->tenant, $this->sessions()->listMembers($this->tenant, (int) $s['id']));
        $byLabel = [];
        foreach ($roster['players'] as $p) {
            $byLabel[$p['callsign'] !== '' ? $p['callsign'] : $p['label']] = $p;
        }
        self::assertArrayNotHasKey('ALIEN', $byLabel, 'autre communauté filtrée');
        self::assertSame(3, $roster['connected'], 'FAUCON (liaison), INCONNU (liaison), Morel (serveur)');
        self::assertTrue($byLabel['FAUCON']['connected']);
        self::assertSame('Serveur Alpha', $byLabel['FAUCON']['server']);
        self::assertTrue($byLabel['FAUCON']['server_known']);
        self::assertSame('1re section', $byLabel['FAUCON']['unit']);
        self::assertSame(2, $byLabel['FAUCON']['user_id']);
        self::assertFalse($byLabel['LYNX']['connected'], 'liaison vieille de 15 min : inactif');
        self::assertSame('offline', $byLabel['LYNX']['link']);
        self::assertFalse($byLabel['INCONNU']['linked_account']);
        $host = array_values(array_filter($roster['players'], static fn (array $p): bool => $p['user_id'] === 1))[0];
        self::assertSame('server_only', $host['link'], 'vu sur un serveur, sans liaison ATAK');
        self::assertTrue($host['connected']);
        self::assertFalse($host['server_known']);
        foreach ($roster['players'] as $p) {
            self::assertArrayNotHasKey('steam_uid', $p, 'pas de Steam exposé');
        }
        $diag = array_column((new RoleplaySessionLiveRoster($this->pdo, fn (): int => $this->now))->diagnostics($this->tenant), 'reason');
        self::assertContains('account_unlinked', $diag);
        self::assertContains('no_signal', $diag);
        self::assertContains('server_unknown', $diag);
        self::assertEqualsCanonicalizing([1, 2], (new RoleplaySessionLiveRoster($this->pdo, fn (): int => $this->now))->connectedUserIds($this->tenant));
    }

    public function testStaffCheckinIsIdempotentAndRecordingStartsForCheckedInPlayers(): void
    {
        $s = $this->session(['started_at' => null, 'planned_starts_at' => date('Y-m-d H:i:s', $this->now + 600)]);
        $repo = new RoleplaySessionAppRepository($this->pdo);
        $att = new RoleplaySessionAttendance($this->sessions(), $this->service(), $repo, fn (): int => $this->now);
        self::assertSame(['pointed' => 2, 'already' => 0, 'refused' => 2], $att->point($this->tenant, $s, [2, 3, 4, 9, 'x', 2], 1), 'suspendu et autre communauté refusés, doublon ignoré');
        self::assertSame(['pointed' => 0, 'already' => 2, 'refused' => 0], $att->point($this->tenant, $s, [2, 3], 1));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM roleplay_game_session_members')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM roleplay_game_session_segments WHERE reason = 'check_in'")->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM roleplay_game_session_segments WHERE ended_at IS NULL')->fetchColumn(), 'rien ne court avant le démarrage');

        self::assertSame(['removed' => 1], $att->unpoint($this->tenant, $s, [3], 1));
        self::assertNull($this->sessions()->findMember($this->tenant, (int) $s['id'], 3)['checked_in_at']);

        $res = $att->startRecording($this->tenant, $s, 1);
        self::assertSame(['ok' => true, 'opened' => 1], $res);
        $live = (array) $this->sessions()->findById($this->tenant, (int) $s['id']);
        self::assertNotEmpty($live['started_at']);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM roleplay_game_session_segments WHERE ended_at IS NULL AND reason = 'join'")->fetchColumn());
        self::assertSame(['ok' => true, 'opened' => 0], $att->startRecording($this->tenant, $live, 1), 'deuxième démarrage sans effet');
        // Pointé pendant l'enregistrement : son temps court tout de suite.
        $att->point($this->tenant, $live, [3], 1);
        self::assertNotNull($this->sessions()->openSegment($this->tenant, (int) $this->sessions()->findMember($this->tenant, (int) $s['id'], 3)['id']));

        self::assertTrue($att->stopRecording($this->tenant, $live, 1)['ok']);
        $closed = (array) $this->sessions()->findById($this->tenant, (int) $s['id']);
        self::assertSame('closed', $closed['status']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM roleplay_game_session_segments WHERE ended_at IS NULL')->fetchColumn());
        self::assertSame(['pointed' => 0, 'already' => 0, 'refused' => 1], $att->point($this->tenant, $closed, [2], 1), 'session terminée');
        self::assertFalse($att->stopRecording($this->tenant, $closed, 1)['ok']);
        $events = array_column($repo->journal($this->tenant, (int) $s['id']), 'event');
        self::assertContains('recording_started', $events);
        self::assertContains('staff_checkin', $events);
        self::assertContains('staff_checkout', $events);
    }

    // —— Interface, routes, droits, migration, contrat ——

    /** @param array<string, mixed> $vars */
    private function render(array $vars): string
    {
        $started = new \ReflectionProperty(\App\Core\Session::class, 'started');
        $was = (bool) $started->getValue();
        $started->setValue(null, true);
        $_SESSION['_csrf_token'] ??= 'test';
        try {
            return (static function (string $__file, array $__vars): string {
                extract($__vars);
                ob_start();
                require $__file;

                return (string) ob_get_clean();
            })(self::ROOT . '/views/admin/organization/roleplay_game_session_show.php', $vars);
        } finally {
            $started->setValue(null, $was);
        }
    }

    /** @return array<string, mixed> */
    private function showVars(): array
    {
        return [
            'gameSession' => ['id' => 12, 'mission_name' => 'Opération <Dague>', 'session_kind' => 'officielle', 'hour_category' => 'Opération', 'status' => 'open', 'started_at' => date('Y-m-d H:i:s', time() - 900), 'attendance_enabled' => 1, 'min_minutes' => 45, 'min_percent' => 50],
            'sessionMembers' => [['id' => 1, 'user_id' => 2, 'display_name' => 'Sergent Faure', 'callsign' => 'FAUCON', 'checked_in_at' => date('Y-m-d H:i:s'), 'raw_seconds' => 600, 'attendance_source' => 'code']],
            'sessionSettings' => ['hour_categories' => ['Opération']],
            'sessionEvents' => [],
            'sessionLinkedEvent' => null,
            'sessionRoster' => [['id' => 3, 'label' => 'Caporal Diaz']],
            'sessionLengthSeconds' => 0,
            'sessionCode' => ['ready' => true, 'state' => 'active', 'code' => 'K7M4QX', 'formatted' => 'K7M-4QX', 'expires_at' => time() + 3600, 'created_at' => date('Y-m-d H:i:s'), 'can_issue' => true],
            'sessionCodeQr' => null,
            'sessionApp' => ['notify_enabled' => true, 'remind_15' => true, 'remind_5' => false, 'briefing' => 'Tenue de nuit', 'stored' => true],
            'sessionJournal' => [['event' => 'join_refused', 'detail' => 'code_unknown', 'created_at' => date('Y-m-d H:i:s'), 'display_name' => '', 'callsign' => 'LYNX']],
            'sessionCanTestNotify' => false,
            'sessionLive' => ['players' => [
                ['key' => 'a1', 'user_id' => 2, 'label' => 'Sergent Faure', 'callsign' => 'FAUCON', 'unit' => '1re section', 'server' => 'Serveur Alpha', 'server_known' => true, 'mission' => 'Dague', 'last_seen' => time(), 'age_s' => 12, 'link' => 'linked', 'connected' => true, 'linked_account' => true, 'in_session' => true, 'checked_in' => true],
                ['key' => 'b2', 'user_id' => 3, 'label' => 'Caporal Diaz', 'callsign' => 'LYNX', 'unit' => '', 'server' => 'public', 'server_known' => false, 'mission' => '', 'last_seen' => time(), 'age_s' => 75, 'link' => 'linked', 'connected' => true, 'linked_account' => true, 'in_session' => false, 'checked_in' => false],
                ['key' => 'c3', 'user_id' => null, 'label' => 'Joueur non relié', 'callsign' => 'X', 'unit' => '', 'server' => '', 'server_known' => false, 'mission' => '', 'last_seen' => time(), 'age_s' => 900, 'link' => 'offline', 'connected' => false, 'linked_account' => false, 'in_session' => false, 'checked_in' => false],
            ], 'connected' => 2, 'server_time' => time()],
            'sessionDiagnostics' => [['label' => 'Joueur', 'reason' => 'account_unlinked', 'detail' => 'Son compte Steam n’est lié à aucun membre de la communauté.']],
        ];
    }

    public function testShowPageRendersSectionsCodeLiveRosterAndDialogs(): void
    {
        $html = $this->render($this->showVars());
        self::assertStringContainsString('Opération &lt;Dague&gt;', $html);
        self::assertStringContainsString('K7M-4QX', $html);
        foreach (['/ 01', 'Informations', 'Code de session', 'Notifications en jeu', 'Membres et présence', 'Joueurs ATAK connectés', 'Pourquoi je ne le vois pas ?'] as $needle) {
            self::assertStringContainsString($needle, $html);
        }
        foreach (['id="modifier-dialog"', 'id="rpsx-big"', 'id="rpsx-regen"', 'id="rpsx-revoke"', 'id="rpsx-notify"', 'id="rpsx-rec-stop"', 'id="rpsx-why"', 'id="rpsx-add"'] as $dialog) {
            self::assertStringContainsString($dialog, $html);
        }
        self::assertStringContainsString('/back-office/roleplay/sessions/12/code/regenerer', $html);
        self::assertStringContainsString('/back-office/roleplay/sessions/12/notifications/test', $html);
        self::assertStringContainsString('/back-office/roleplay/sessions/12/notifications/pointage', $html);
        self::assertStringContainsString('/back-office/roleplay/sessions/12/connectes', $html);
        self::assertStringContainsString('value="remove:2"', $html, 'pointé : retrait');
        self::assertStringContainsString('value="point:3"', $html, 'connecté non pointé : pointer');
        self::assertStringContainsString('name="user_ids[]" value="3"', $html);
        self::assertStringNotContainsString('name="user_ids[]" value="2"', $html, 'déjà pointé : pas de case');
        self::assertStringContainsString('Compte non lié', $html);
        self::assertStringContainsString('Terminer l’enregistrement', $html);
        self::assertStringContainsString('Inscrit avec le code', $html . 'Inscrit avec le code');
        self::assertStringContainsString('Code refusé · code inconnu · LYNX', $html);
        self::assertStringContainsString('disabled aria-describedby="rpsx-test-help"', $html, 'pas de Steam : test désactivé, expliqué');
        self::assertSame(1, substr_count($html, '<h1'));
        self::assertDoesNotMatchRegularExpression('/sse-ds|class="sse-|Archivo/', $html, 'design system rejeté absent');
        self::assertDoesNotMatchRegularExpression('/fiction|simulation|usage RP/i', strip_tags($html));
        self::assertStringNotContainsString('76561198', $html, 'aucun Steam ID affiché');
    }

    public function testRoutesControllerAndCronAreWired(): void
    {
        $routes = self::read('routes/web.php');
        foreach ([
            "get('/api/atak/session/current'", "post('/api/atak/session/join'", "post('/api/atak/session/checkin'", "post('/api/atak/session/leave'",
            "post('/back-office/roleplay/sessions/{id}/code/regenerer'", "post('/back-office/roleplay/sessions/{id}/code/revoquer'",
            "post('/back-office/roleplay/sessions/{id}/notifications'", "post('/back-office/roleplay/sessions/{id}/notifications/test'",
            "get('/back-office/roleplay/sessions/{id}/connectes'", "post('/back-office/roleplay/sessions/{id}/pointage'", "post('/back-office/roleplay/sessions/{id}/enregistrement'",
        ] as $needle) {
            self::assertStringContainsString($needle, $routes);
        }
        // Mêmes droits que la page : toutes les routes de la fiche passent par les deux mêmes intergiciels.
        preg_match_all("~->(?:get|post)\('/back-office/roleplay/sessions/\{id\}[^']*', [^\n]+~", $routes, $m);
        self::assertGreaterThanOrEqual(11, count($m[0]));
        foreach ($m[0] as $line) {
            self::assertStringContainsString('[AuthMiddleware::class, OrganizationAdminMiddleware::class]', $line);
        }
        $ctrl = self::read('app/Controllers/Admin/Organization/RoleplayGameSessionAdminController.php');
        foreach (['regenerateCode', 'revokeCode', 'saveNotifications', 'testNotification', 'checkinCall', 'liveRoster', 'staffCheckin', 'recording'] as $action) {
            self::assertMatchesRegularExpression('/public function ' . $action . '\(Request \$request, array \$params = \[\]\): Response\s*\{\s*(\$ctx = \$this->appAction|if \(\(\$denied = \$this->guardBackOfficeRight)/', $ctrl, $action . ' : garde du droit en tête');
        }
        self::assertStringContainsString("Csrf::validate((string) \$request->input('_csrf_token'))", $ctrl);
        self::assertStringContainsString('RoleplaySessionAppCronJob', self::read('app/Core/Container.php'));
        self::assertSame(5, \App\Services\Cron\CronSchedule::intervalMinutes('roleplay_session_app'));
    }

    public function testMigrationStepIsIdempotentAndHasARollback(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        $root = self::ROOT;
        ob_start();
        require self::ROOT . '/database/steps/4920_roleplay_session_app.php';
        self::assertStringContainsString('Tables créées', (string) ob_get_clean());
        $changes = $pdo->changes;
        ob_start();
        require self::ROOT . '/database/steps/4920_roleplay_session_app.php';
        self::assertStringContainsString('déjà présentes', (string) ob_get_clean());
        self::assertSame($changes, $pdo->changes);
        $sql = self::read('migrations/2026_11_23_100_roleplay_session_app.sql');
        self::assertSame(3, substr_count($sql, 'COLLATE=utf8mb4_unicode_ci'));
        self::assertSame(3, substr_count($sql, 'UNIQUE KEY'));
        self::assertSame(0, preg_match('/ALTER\s+TABLE/i', $sql));
        $down = self::read('migrations/rollback/roleplay_session_app_down.sql');
        foreach (['roleplay_game_session_codes', 'roleplay_game_session_app', 'roleplay_game_session_journal'] as $t) {
            self::assertStringContainsString('DROP TABLE IF EXISTS ' . $t, $down);
        }
        $registry = require self::ROOT . '/database/steps/registry.php';
        $entry = array_values(array_filter($registry, static fn (array $s): bool => ($s['id'] ?? '') === 'roleplay_session_app'));
        self::assertCount(1, $entry);
        self::assertSame('4920_roleplay_session_app.php', $entry[0]['file']);
    }

    public function testContractDocumentsEveryRouteErrorAndState(): void
    {
        $icd = self::read('docs/technical/ICD-SESSION-JEU.md');
        foreach (['GET /api/atak/session/current', 'POST /api/atak/session/join', 'POST /api/atak/session/checkin', 'POST /api/atak/session/leave',
            'invalid_format', 'code_unknown', 'code_expired', 'session_closed', 'not_allowed', 'account_link_required', 'rate_limited', 'feature_unavailable', 'not_joined', 'session_not_started',
            'no_session', 'code_required', 'active', 'checkin', 'ended', 'src=ses', 'reminder_15', 'checkin_call', '"v": 1'] as $needle) {
            self::assertStringContainsString($needle, $icd);
        }
        self::assertFileExists(self::ROOT . '/docs/dev/SESSIONS-JEU-CODE.md');
    }
}
