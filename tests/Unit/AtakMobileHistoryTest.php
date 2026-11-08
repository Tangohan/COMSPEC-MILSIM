<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\AtakAdminControlRepository;
use App\Services\Atak\AtakMobileHistoryService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * « Sur mon téléphone » : historique et lecture des comptes rendus, tchat et messages privés. Cloisonnement par
 * communauté et par joueur, aucune donnée inventée, et passage en premier des comptes rendus faits sur le téléphone.
 */
final class AtakMobileHistoryTest extends TestCase
{
    private const OWN = 7;

    private const FOREIGN = 3;

    private const ME = 5;

    private PDO $pdo;

    private int $tick = 0;

    private ?PDO $savedPdo = null;

    private AtakMobileHistoryService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $this->pdo->sqliteCreateFunction('UTC_TIMESTAMP', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        $this->pdo->sqliteCreateFunction('FIELD', static function (...$a): int {
            $i = array_search($a[0], array_slice($a, 1), true);

            return $i === false ? 0 : $i + 1;
        });
        $this->createSchema();
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $saved = $db->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;
        $db->setValue(null, $this->pdo);
        $this->svc = new AtakMobileHistoryService($this->pdo);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        parent::tearDown();
    }

    public function testSentListIsMineOnlyAndStaysInMyCommunityAndGame(): void
    {
        $mine = $this->report(self::OWN, 1, self::ME, 'CONTACT', 'Contact au nord');
        $this->report(self::OWN, 1, 99, 'SPOTREP', 'Un autre joueur');
        $this->report(self::FOREIGN, 1, self::ME, 'SPOTREP', 'Autre communauté');
        $this->report(self::OWN, 2, self::ME, 'SPOTREP', 'Autre partie');

        $rows = $this->svc->sent($this->link());
        self::assertCount(1, $rows);
        self::assertSame($mine, $rows[0]['id']);
        self::assertSame('Contact', $rows[0]['type']);
        self::assertSame(['id', 'type', 'priority', 'urgent', 'by', 'grid', 'summary', 'status', 'at'], array_keys($rows[0]));
    }

    public function testReadingAReportNeedsToBeItsAuthorOrItsRecipient(): void
    {
        $other = $this->report(self::OWN, 1, 99, 'SALUTE', 'Pas pour moi');
        $foreign = $this->report(self::FOREIGN, 1, self::ME, 'SALUTE', 'Autre communauté');
        $routed = $this->report(self::OWN, 1, 99, 'SITREP', 'Pour moi');
        $this->route($routed, 'USER', (string) self::ME);

        self::assertNull($this->svc->report($this->link(), $other));
        self::assertNull($this->svc->report($this->link(), $foreign));
        self::assertNull($this->svc->report($this->link(), 0));
        $got = $this->svc->report($this->link(), $routed);
        self::assertNotNull($got);
        self::assertFalse($got['mine']);
        self::assertFalse($got['read']);
        self::assertGreaterThan(0, $got['routing_id']);
    }

    public function testReceivedListShowsUnreadFirstAndMarkReadIsLimitedToTheRecipient(): void
    {
        $old = $this->report(self::OWN, 1, 99, 'SPOTREP', 'Ancien', '2026-01-01 10:00:00');
        $new = $this->report(self::OWN, 1, 99, 'CONTACT', 'Récent', '2026-01-02 10:00:00');
        $this->route($old, 'USER', (string) self::ME);
        $this->route($new, 'USER', (string) self::ME);
        $link = $this->link();

        $rows = $this->svc->received($link);
        self::assertSame([$new, $old], array_column($rows, 'id'));
        self::assertSame(2, $this->svc->unreadCount($link));

        $first = $this->svc->report($link, $new);
        self::assertTrue($this->svc->markRead($link, $new, (int) $first['routing_id']));
        self::assertFalse($this->svc->markRead($link, $new, (int) $first['routing_id']), 'déjà lu');
        self::assertSame(1, $this->svc->unreadCount($link));
        self::assertSame([$old, $new], array_column($this->svc->received($link), 'id'), 'le lu passe après');

        $stranger = $link;
        $stranger['user_id'] = 42;
        self::assertFalse($this->svc->markRead($stranger, $old, 1), 'pas son compte rendu');
        self::assertSame(1, $this->svc->unreadCount($link));
    }

    public function testChannelsAndPrivateMessagesShowOnlyWhatConcernsMe(): void
    {
        $this->chat(self::OWN, 1, 'general', 'Vega', 'Rendez-vous au point bravo');
        $this->chat(self::OWN, 1, 'general', 'Vega', 'MP|Vega|Viper 1|Pour toi');
        $this->chat(self::OWN, 1, 'general', 'Vega', 'MP|Vega|Autre|Pas pour toi');
        $this->chat(self::OWN, 1, 'general', 'Viper 1', 'MP|Viper 1|Vega|Réponse');
        $this->chat(self::FOREIGN, 1, 'general', 'Intrus', 'Autre communauté');

        $texts = array_column($this->svc->channelMessages($this->link(), 'general'), 'text');
        self::assertSame(['Rendez-vous au point bravo'], $texts, 'les messages privés ne passent jamais par un canal');

        $private = $this->svc->privateMessages($this->link());
        self::assertSame(['Pour toi', 'Réponse'], array_column($private, 'text'));
        self::assertSame([false, true], array_column($private, 'mine'));
        self::assertNotContains('Pas pour toi', array_column($private, 'text'));
    }

    public function testMpuBoardShowsOnlyMySideAndOnlyWhatTheGameSends(): void
    {
        $unit = static fn (string $cs, string $uid, string $side, array $more = [], string $status = 'linked'): array => [
            'call_sign' => $cs,
            'status' => $status,
            'extra' => ['steam_uid' => $uid, 'side' => $side] + $more,
        ];
        $board = AtakMobileHistoryService::mpuBoard([
            $unit('MOI', '111', 'WEST', ['radio_net' => 'ALPHA', 'radio_channel' => '3']),
            $unit('AMI', '222', 'WEST', ['wr_mpu5' => true, 'wr_node' => 'N2', 'wr_tg' => 4]),
            $unit('SANS', '333', 'WEST'),
            $unit('ENI', '444', 'EAST', ['wr_mpu5' => true]),
            $unit('PARTI', '555', 'WEST', ['wr_mpu5' => true], 'offline'),
        ], '111');

        self::assertSame('ALPHA', $board['me']['radio_net']);
        self::assertFalse($board['me']['mpu'], 'pas de MPU5 inventée');
        self::assertSame(['AMI'], array_column($board['peers'], 'callsign'));
    }

    public function testMpuBoardWithoutMyUnitShowsNobody(): void
    {
        $board = AtakMobileHistoryService::mpuBoard([
            ['call_sign' => 'AMI', 'status' => 'linked', 'extra' => ['steam_uid' => '222', 'side' => 'WEST', 'wr_mpu5' => true]],
        ], '');

        self::assertNull($board['me']);
        self::assertSame([], $board['peers']);
    }

    public function testPhoneReportsComeFirstEvenBehindTwentyOlderPendingNotices(): void
    {
        $repo = new AtakAdminControlRepository($this->pdo);
        (new \ReflectionProperty(AtakAdminControlRepository::class, 'ready'))->setValue($repo, true);
        for ($i = 0; $i < 25; $i++) {
            $this->notice('message', 'Ancien avis ' . $i);
        }
        $this->notice('mobile_report', 'Compte rendu depuis le téléphone');

        $kinds = array_column($repo->pendingForPlayer(self::OWN, self::ME, null), 'kind');
        self::assertCount(20, $kinds);
        self::assertSame('mobile_report', $kinds[0], 'sans cela, il restait au-delà de la vingtième ligne et n’arrivait jamais');
    }

    public function testRoutesViewsAndControllerAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (["'/m/cr/envoyes'", "'/m/cr/recus'", "'/m/cr/lire/{id}'", "'/m/radio'", "'/m/radio/prives'", "'/m/radio/absent'", "'/m/radio/sms'", "'/m/radio/mpu'", "'/m/musique'", "'/m/radio/canal/{key}'"] as $r) {
            self::assertStringContainsString($r, $routes);
            self::assertLessThan(strpos($routes, "'/m/{token}'"), strpos($routes, $r), 'pages avant le jeton');
        }
        foreach (['cr_list', 'cr_read', 'radio', 'radio_thread', 'radio_sms', 'radio_mpu', 'musique', '_sub'] as $v) {
            $file = (string) file_get_contents($root . '/views/m/' . $v . '.php');
            self::assertStringContainsString('declare(strict_types=1);', $file, $v);
        }
        $js = (string) file_get_contents($root . '/public/assets/js/atak-m.js');
        self::assertStringContainsString("'/m/cr/lire/'", $js);
        self::assertStringNotContainsString('Envoyé par votre ATAK', $js, 'l’avis est pris en charge, pas encore envoyé');
    }

    /** @return array<string, mixed> */
    private function link(): array
    {
        return ['id' => 1, 'tenant_id' => self::OWN, 'user_id' => self::ME, 'map_id' => 1, 'steam_uid' => '', 'player_name' => 'Viper 1'];
    }

    private function report(int $tenant, int $map, int $user, string $type, string $summary, string $at = '2026-01-01 09:00:00'): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO atak_tactical_reports (tenant_id, context_id, report_type, priority, submitter_user_id, submitter_callsign, summary, status, report_timestamp)
             VALUES (?, ?, ?, \'ROUTINE\', ?, \'Vega\', ?, \'SUBMITTED\', ?)'
        );
        $st->execute([$tenant, $map, $type, $user, $summary, $at]);

        return (int) $this->pdo->lastInsertId();
    }

    private function route(int $reportId, string $type, string $identifier): void
    {
        $this->pdo->prepare('INSERT INTO atak_report_routing_history (report_id, routed_to_type, routed_to_identifier) VALUES (?, ?, ?)')
            ->execute([$reportId, $type, $identifier]);
    }

    private function chat(int $tenant, int $map, string $channel, string $author, string $body): void
    {
        $this->pdo->prepare('INSERT INTO atak_chat_messages (tenant_id, map_id, author, body, channel_key, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$tenant, $map, $author, $body, $channel, gmdate('Y-m-d H:i:s', 1_700_000_000 + ++$this->tick)]);
    }

    private function notice(string $kind, string $title): void
    {
        $this->pdo->prepare('INSERT INTO atak_admin_messages (tenant_id, user_id, kind, title, body, require_ack, created_at) VALUES (?, ?, ?, ?, \'x\', 0, ?)')
            ->execute([self::OWN, self::ME, $kind, $title, gmdate('Y-m-d H:i:s')]);
    }

    private function createSchema(): void
    {
        $this->pdo->exec("CREATE TABLE atak_tactical_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER NOT NULL,
            report_type TEXT, priority TEXT, submitter_user_id INTEGER, submitter_callsign TEXT, submitter_steam_id TEXT, grid_reference TEXT, summary TEXT,
            details TEXT, remarks TEXT, structured_data TEXT, status TEXT, report_timestamp TEXT, deleted_at TEXT)");
        $this->pdo->exec('CREATE TABLE atak_report_routing_history (id INTEGER PRIMARY KEY AUTOINCREMENT, report_id INTEGER NOT NULL, routed_to_type TEXT NOT NULL,
            routed_to_identifier TEXT NOT NULL, acknowledged INTEGER DEFAULT 0, acknowledged_by_user_id INTEGER, acknowledged_at TEXT, routed_at TEXT)');
        $this->pdo->exec('CREATE TABLE atak_chat_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL,
            author TEXT, body TEXT, source TEXT, channel_key TEXT, created_at TEXT)');
        $this->pdo->exec('CREATE TABLE atak_chat_channels (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, map_id INTEGER, channel_key TEXT, label TEXT, kind TEXT, created_by_callsign TEXT)');
        $this->pdo->exec("CREATE TABLE atak_admin_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, user_id INTEGER, steam_uid TEXT,
            terminal_uid TEXT, kind TEXT NOT NULL DEFAULT 'message', title TEXT NOT NULL, body TEXT NOT NULL, level TEXT NOT NULL DEFAULT 'info',
            require_ack INTEGER NOT NULL DEFAULT 1, expires_at TEXT, created_by_user_id INTEGER, created_at TEXT, delivered_at TEXT, acked_at TEXT, cancelled_at TEXT)");
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, display_name TEXT, callsign TEXT)');
        $this->pdo->exec("INSERT INTO users (id, tenant_id, display_name, callsign) VALUES (5, 7, 'paul_d', 'Viper 1')");
    }
}
