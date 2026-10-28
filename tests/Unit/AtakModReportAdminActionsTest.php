<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakModReportRepository;
use App\Repositories\MemberNotificationRepository;
use App\Services\Monitoring\AtakModReportReplyService;
use PDO;
use PHPUnit\Framework\TestCase;

/** Page /admin/atak-mod-reports : actions groupées, réponse au joueur, compte lié au Steam. */
final class AtakModReportAdminActionsTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE atak_mod_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT, severity TEXT DEFAULT 'error', gravity TEXT DEFAULT 'normal', workflow_status TEXT DEFAULT 'new',
            channel TEXT DEFAULT 'Core', message TEXT, detail_text TEXT, context_json TEXT, fingerprint TEXT,
            source TEXT DEFAULT 'auto', steam_uid TEXT, player_uid TEXT, player_name TEXT, callsign TEXT, client_ip TEXT,
            mod_version TEXT, extension_version TEXT, arma_build TEXT, hit_count INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP, last_seen_at TEXT DEFAULT '2026-10-01 10:00:00',
            status_changed_at TEXT, reply_text TEXT, replied_at TEXT, replied_by TEXT, reply_channels TEXT)");
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, email TEXT, display_name TEXT, callsign TEXT, steam_id TEXT)');
        $this->pdo->exec("INSERT INTO users VALUES (7, 3, 'hawk@example.com', 'Hawk', 'VIPER 2-1', '76561198000000001')");
        $ins = $this->pdo->prepare('INSERT INTO atak_mod_reports (severity, message, source, steam_uid, player_name) VALUES (?, ?, ?, ?, ?)');
        $ins->execute(['bug', 'Carte noire après respawn', 'player', '76561198000000001', 'Hawk']);
        $ins->execute(['bug', 'Son des SMS absent', 'player', '76561198000000001', 'Hawk']);
        $ins->execute(['error', 'Timeout Link', 'auto', '76561198000000099', 'Kilo']);
        $ins->execute(['warn', 'Pack ancien', 'auto', null, null]);
    }

    /** Notifications Athena gardées en mémoire (pas de base MySQL dans les tests). */
    private function notifications(): MemberNotificationRepository
    {
        return new class () extends MemberNotificationRepository {
            /** @var list<array{0:int, 1:int, 2:string, 3:array<string, mixed>}> */
            public array $sent = [];

            public function __construct()
            {
            }

            public function tableExists(): bool
            {
                return true;
            }

            public function create(int $tenantId, int $userId, string $type, array $payload = []): ?int
            {
                $this->sent[] = [$tenantId, $userId, $type, $payload];

                return count($this->sent);
            }
        };
    }

    private function repo(): AtakModReportRepository
    {
        return new AtakModReportRepository($this->pdo);
    }

    public function testBulkStatusKeepsLastSeenAndSkipsUnchanged(): void
    {
        $repo = $this->repo();
        self::assertSame(2, $repo->updateWorkflowStatusMany([1, 2], 'fixed'));
        self::assertSame(1, $repo->updateWorkflowStatusMany([1, 2, 3], 'fixed'));
        $row = $repo->findById(1);
        self::assertSame('fixed', $row['workflow_status']);
        self::assertSame('2026-10-01 10:00:00', $row['last_seen_at']);
        self::assertNotNull($row['status_changed_at']);
        self::assertSame(['new' => 1, 'in_progress' => 0, 'fixed' => 3, 'dismissed' => 0], $repo->countByStatus());
    }

    public function testDismissedStatusAndAliases(): void
    {
        $repo = $this->repo();
        self::assertSame('dismissed', $repo->normalizeWorkflowStatus('sans_suite'));
        self::assertSame('Sans suite', AtakModReportRepository::statusLabel('dismissed'));
        self::assertTrue($repo->updateWorkflowStatus(4, 'dismissed'));
        self::assertFalse($repo->updateWorkflowStatus(4, 'dismissed'));
    }

    public function testFilterSearchAndDeleteMany(): void
    {
        $repo = $this->repo();
        self::assertSame([2, 1], $repo->idsMatching('bug', null, null));
        self::assertSame([3], $repo->idsMatching(null, null, 'kilo'));
        self::assertSame(0, $repo->countAll(null, null, '100%'));
        self::assertSame(2, $repo->deleteMany([1, 4, 999]));
        self::assertSame(2, $repo->countAll());
    }

    public function testLinkedAccountFoundBySteam(): void
    {
        $accounts = $this->repo()->linkedAccountsBySteam(['76561198000000001', '76561198000000099', '']);
        // Clés numériques : PHP les range en entiers, la lecture par Steam (chaîne) reste valable.
        self::assertCount(1, $accounts);
        self::assertTrue(isset($accounts['76561198000000001']));
        self::assertSame(['user_id' => 7, 'tenant_id' => 3, 'email' => 'hawk@example.com', 'name' => 'VIPER 2-1', 'lang' => 'fr'], $accounts['76561198000000001']);
    }

    public function testNotifyManySendsOncePerPlayerAndStoresReply(): void
    {
        $notifications = $this->notifications();
        $mails = [];
        $email = static function (string $to, array $vars) use (&$mails): bool {
            $mails[] = $to;

            return true;
        };

        $repo = $this->repo();
        $service = new AtakModReportReplyService($repo, $notifications, $email);
        $stats = $service->notifyMany($repo->findByIds([1, 2, 3, 4]), 'Corrigé, merci !', 'TTRD');

        self::assertSame(['players' => 1, 'site' => 1, 'email' => 1, 'unreachable' => 2, 'low_no_email' => 0], $stats);
        self::assertSame(['hawk@example.com'], $mails);
        self::assertCount(1, $notifications->sent);
        self::assertSame([3, 7, AtakModReportReplyService::NOTIFICATION_TYPE], array_slice($notifications->sent[0], 0, 3));
        self::assertStringContainsString('et 1 autre', (string) $notifications->sent[0][3]['report']);
        $row = $repo->findById(2);
        self::assertSame('Corrigé, merci !', $row['reply_text']);
        self::assertSame('site,email', $row['reply_channels']);
        self::assertSame('TTRD', $row['replied_by']);
        self::assertNull($repo->findById(3)['reply_text']);
    }

    public function testReplyWithoutAccountIsOnlyStored(): void
    {
        $repo = $this->repo();
        $service = new AtakModReportReplyService($repo, $this->notifications(), static fn (): bool => true);
        $result = $service->reply($repo->findById(3), 'On regarde.', null, true, true, 'TTRD');

        self::assertSame(['site' => false, 'email' => false, 'channels' => []], $result);
        self::assertSame('On regarde.', $repo->findById(3)['reply_text']);
        self::assertNull($repo->findById(3)['reply_channels']);
    }

    public function testGravityFilterSortAndManualChange(): void
    {
        $repo = $this->repo();
        // Rapports de départ : 2 signalements joueurs, une erreur auto, une alerte auto.
        self::assertSame(2, $repo->updateGravityMany([1, 2], 'high'));
        self::assertSame(1, $repo->updateGravityMany([3], 'critique'));
        self::assertSame(1, $repo->updateGravityMany([4], 'low'));
        self::assertSame(0, $repo->updateGravityMany([4], 'low'));
        self::assertSame(0, $repo->updateGravityMany([4], 'n’importe quoi'));
        self::assertSame('2026-10-01 10:00:00', $repo->findById(3)['last_seen_at']);

        $ids = array_map(static fn (array $r): int => (int) $r['id'], $repo->listRecent(10, null, null, null, null, AtakModReportRepository::SORT_GRAVITY));
        self::assertSame(3, $ids[0]);
        self::assertSame(4, $ids[3]);
        self::assertSame([4], $repo->idsMatching(null, null, null, 'faible'));
        self::assertSame(2, $repo->countAll(null, null, null, 'high'));
        self::assertSame(['critical' => 1, 'high' => 2, 'normal' => 0, 'low' => 1], $repo->countOpenByGravity());
        $repo->updateWorkflowStatusMany([3], 'fixed');
        self::assertSame(0, $repo->countOpenByGravity()['critical']);
    }

    public function testAutoReportReplyIsWordedAsADetectionInThePlayersLanguage(): void
    {
        $this->pdo->exec('CREATE TABLE user_profiles (user_id INTEGER PRIMARY KEY, language TEXT)');
        $this->pdo->exec("INSERT INTO user_profiles VALUES (7, 'en')");
        $this->pdo->exec("INSERT INTO atak_mod_reports (severity, gravity, message, source, steam_uid) VALUES ('error', 'normal', 'ÉCHEC · fiche opérateur — unauthorized', 'auto', '76561198000000001')");
        $mails = [];
        $email = static function (string $to, array $vars) use (&$mails): bool {
            $mails[] = $vars;

            return true;
        };
        $repo = $this->repo();
        $account = $repo->linkedAccountsBySteam(['76561198000000001'])['76561198000000001'];
        self::assertSame('en', $account['lang']);

        $service = new AtakModReportReplyService($repo, $this->notifications(), $email);
        $text = AtakModReportReplyService::template(AtakModReportReplyService::TPL_IN_PROGRESS, true, 'en');
        $service->reply($repo->findById(5), $text, $account, false, true, 'TTRD');

        self::assertSame('en', $mails[0]['lang']);
        self::assertTrue($mails[0]['auto']);
        self::assertStringStartsWith('Your COMSPEC ATAK mod sent us this error automatically', $text);
        self::assertSame('COMSPEC ATAK: about the error your mod reported', AtakModReportReplyService::subject(true, 'en'));

        $rendered = self::render('atak_mod_report_reply', $mails[0]);
        self::assertStringContainsString('your COMSPEC ATAK mod detected an error and sent it to us automatically', $rendered['text']);
        self::assertStringNotContainsString('votre signalement', $rendered['text']);
        self::assertStringNotContainsString('Bonjour', $rendered['html']);

        // Signalement écrit par le joueur, profil français : « votre signalement », en français.
        $fr = self::render('atak_mod_report_reply', [
            'displayName' => 'Hawk', 'reportLabel' => '« Carte noire »', 'replyText' => 'Merci.',
            'activityUrl' => 'https://x/activite', 'auto' => false, 'lang' => 'fr',
        ]);
        self::assertStringContainsString('le problème que vous avez signalé depuis le jeu', $fr['text']);
    }

    public function testBulkFixedNotifyUsesDefaultMessagePerPlayerAndSkipsEmailForLowGravity(): void
    {
        $this->pdo->exec("UPDATE atak_mod_reports SET source = 'auto', severity = 'warn', gravity = 'low' WHERE id IN (1, 2)");
        $notifications = $this->notifications();
        $mails = [];
        $email = static function (string $to) use (&$mails): bool {
            $mails[] = $to;

            return true;
        };
        $repo = $this->repo();
        $stats = (new AtakModReportReplyService($repo, $notifications, $email))->notifyMany($repo->findByIds([1, 2]), '', 'TTRD');

        self::assertSame(['players' => 1, 'site' => 1, 'email' => 0, 'unreachable' => 0, 'low_no_email' => 1], $stats);
        self::assertSame([], $mails);
        self::assertTrue($notifications->sent[0][3]['auto']);
        self::assertSame(AtakModReportReplyService::defaultFixedMessage(true, 'fr'), $repo->findById(1)['reply_text']);
        self::assertSame('site', $repo->findById(1)['reply_channels']);
    }

    /**
     * @param array<string, mixed> $vars
     * @return array{html:string, text:string}
     */
    private static function render(string $template, array $vars): array
    {
        $file = dirname(__DIR__, 2) . '/views/emails/' . $template . '.php';

        return (static function (string $__file, array $__vars): array {
            extract($__vars);

            return require $__file;
        })($file, $vars);
    }

    public function testPageAndRoutesStayPlatformOnly(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (['bulk', 'reply', 'gravity' => 'updateGravity'] as $action => $method) {
            if (is_int($action)) {
                $action = $method;
            }
            self::assertStringContainsString("'/admin/atak-mod-reports/" . $action . "', [\\App\\Controllers\\Admin\\AdminAtakModReportsController::class, '" . $method . "'], [AuthMiddleware::class, SystemAdminMiddleware::class]", $routes);
        }
        $view = (string) file_get_contents($root . '/views/admin/atak-mod-reports/index.php');
        self::assertStringContainsString('class="pa amr"', $view);
        self::assertStringContainsString("confirm('Supprimer définitivement", $view);
        self::assertFileExists($root . '/public/assets/css/admin-atak-mod-reports.css');
        self::assertFileExists($root . '/views/emails/atak_mod_report_reply.php');
    }
}
