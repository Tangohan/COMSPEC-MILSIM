<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakModReportRepository;
use App\Repositories\MemberNotificationRepository;
use App\Services\Monitoring\AtakModReportReplyService;
use App\Support\ModReportReplyNotice;
use PDO;
use PHPUnit\Framework\TestCase;

/** Fenêtre « l'équipe a répondu à votre signalement » montrée au joueur à sa connexion sur Athena. */
final class ModReportReplyNoticeTest extends TestCase
{
    private const TYPE = AtakModReportReplyService::NOTIFICATION_TYPE;

    private function pdo(): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->exec('CREATE TABLE member_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER,
            type TEXT, payload_json TEXT, read_at TEXT NULL, created_at TEXT)');

        return $pdo;
    }

    private function notifications(PDO $pdo): MemberNotificationRepository
    {
        return new class ($pdo) extends MemberNotificationRepository {
            public function tableExists(): bool
            {
                return true;
            }
        };
    }

    private static function add(PDO $pdo, int $userId, string $type, array $payload, string $createdAt, ?string $readAt = null): int
    {
        $pdo->prepare('INSERT INTO member_notifications (tenant_id, user_id, type, payload_json, read_at, created_at) VALUES (3, ?, ?, ?, ?, ?)')
            ->execute([$userId, $type, json_encode($payload), $readAt, $createdAt]);

        return (int) $pdo->lastInsertId();
    }

    public function testOnlyUnreadRecentRepliesOfThisPlayer(): void
    {
        $pdo = $this->pdo();
        $now = date('Y-m-d H:i:s');
        $mine = self::add($pdo, 7, self::TYPE, ['message' => 'Corrigé'], $now);
        self::add($pdo, 8, self::TYPE, ['message' => 'Rapport d’un autre joueur'], $now);
        self::add($pdo, 7, 'forum_reply', ['message' => 'Autre notification'], $now);
        self::add($pdo, 7, self::TYPE, ['message' => 'Déjà lue'], $now, $now);
        self::add($pdo, 7, self::TYPE, ['message' => 'Trop ancienne'], date('Y-m-d H:i:s', time() - 40 * 86400));

        $rows = $this->notifications($pdo)->listUnreadOfTypeForUser(7, self::TYPE, 30);

        self::assertSame([$mine], array_map(static fn (array $r): int => (int) $r['id'], $rows));
        self::assertSame([], $this->notifications($pdo)->listUnreadOfTypeForUser(0, self::TYPE));
    }

    public function testCompritMarksOnlyThatPlayersReplyRead(): void
    {
        $pdo = $this->pdo();
        $now = date('Y-m-d H:i:s');
        $a = self::add($pdo, 7, self::TYPE, ['message' => 'A'], $now);
        $b = self::add($pdo, 7, self::TYPE, ['message' => 'B'], $now);
        $other = self::add($pdo, 8, self::TYPE, ['message' => 'Autre joueur'], $now);
        $forum = self::add($pdo, 7, 'forum_reply', [], $now);
        $repo = $this->notifications($pdo);

        // Un joueur ne peut pas marquer lue la réponse d'un autre, ni une autre sorte de notification.
        self::assertSame(1, $repo->markReadForUser([$a, $other, $forum], 7, self::TYPE));
        $read = $pdo->query('SELECT id FROM member_notifications WHERE read_at IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([$a], array_map('intval', $read));
        self::assertSame([$b], array_map(static fn (array $r): int => (int) $r['id'], $repo->listUnreadOfTypeForUser(7, self::TYPE)));
        self::assertSame(0, $repo->markReadForUser([$a], 7, self::TYPE), 'une seule fois');
    }

    public function testItemsUseFullMessageCurrentStatusAndLanguage(): void
    {
        $rows = [
            ['id' => 12, 'created_at' => '2026-10-14 09:05:00', 'payload_json' => json_encode([
                'summary' => 'Coupé…', 'message' => "Corrigé.\nMerci !", 'report' => '« Carte noire »', 'report_ids' => [4, 5], 'auto' => false,
            ])],
            // Ancienne notification (avant cette version) : seulement le résumé, pas de rapports cités.
            ['id' => 11, 'created_at' => '2026-10-13 18:00:00', 'payload_json' => json_encode(['summary' => 'Nous regardons.', 'auto' => true])],
            ['id' => 10, 'created_at' => '2026-10-13 17:00:00', 'payload_json' => json_encode(['report' => 'vide'])],
        ];
        self::assertSame([4, 5], ModReportReplyNotice::reportIds($rows));
        $statuses = [4 => AtakModReportRepository::STATUS_FIXED, 5 => AtakModReportRepository::STATUS_IN_PROGRESS];

        $fr = ModReportReplyNotice::items($rows, $statuses, 'fr');
        self::assertCount(2, $fr, 'une réponse sans texte ne s’affiche pas');
        self::assertSame("Corrigé.\nMerci !", $fr[0]['message']);
        self::assertSame('En cours', $fr[0]['status_label'], 'statut le moins avancé des rapports cités');
        self::assertSame('14 oct. 2026 à 09h05', $fr[0]['date']);
        self::assertSame('Nous regardons.', $fr[1]['message']);
        self::assertSame('', $fr[1]['status_label']);
        self::assertTrue($fr[1]['auto']);

        $en = ModReportReplyNotice::items($rows, [4 => 'fixed', 5 => 'fixed'], 'en');
        self::assertSame('Fixed', $en[0]['status_label']);
        self::assertSame('Oct 14, 2026 at 09:05', $en[0]['date']);
        self::assertSame('Got it', ModReportReplyNotice::copy('en', 1)['ok']);
        self::assertSame('Compris', ModReportReplyNotice::copy('fr', 1)['ok']);
        self::assertSame('en', ModReportReplyNotice::locale('en_GB'));
        self::assertSame('fr', ModReportReplyNotice::locale(''));
    }

    private function render(array $items, string $lang, int $total): string
    {
        $modReplyItems = $items;
        $modReplyTotal = $total;
        $modReplyLang = $lang;
        $modReplyAction = '/compte/retours-signalement/vu';
        $modReplyHubUrl = '/activite';
        $modReplyCsrf = '<input type="hidden" name="_csrf_token" value="t">';
        ob_start();
        require dirname(__DIR__, 2) . '/views/partials/mod_report_reply_modal_view.php';

        return (string) ob_get_clean();
    }

    public function testModalEscapesAndStacksReplies(): void
    {
        $rows = [
            ['id' => 3, 'created_at' => '2026-10-14 09:05:00', 'payload_json' => json_encode(['message' => 'Merci <b>!</b>', 'report' => '« Carte »', 'report_ids' => [1]])],
            ['id' => 2, 'created_at' => '2026-10-14 08:00:00', 'payload_json' => json_encode(['message' => 'Deuxième', 'report_ids' => [2]])],
        ];
        $items = ModReportReplyNotice::items($rows, [1 => 'fixed', 2 => 'in_progress'], 'fr');
        $html = $this->render($items, 'fr', 7);

        self::assertStringContainsString('translate="no"', $html);
        self::assertStringContainsString('Merci &lt;b&gt;!&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>!</b>', $html);
        self::assertSame(2, substr_count($html, 'data-mrr-one>'), 'un « Compris » par réponse');
        self::assertStringContainsString('name="id" value="3"', $html);
        self::assertStringContainsString('Tout compris', $html);
        self::assertStringContainsString('+ 5 autre(s)', $html);
        self::assertStringContainsString('href="/activite"', $html);
        self::assertStringContainsString('Corrigé', $html);

        $one = $this->render(array_slice(ModReportReplyNotice::items($rows, [], 'en'), 0, 1), 'en', 1);
        self::assertStringContainsString('The team replied to your report', $one);
        self::assertStringContainsString('Got it', $one);
        self::assertStringNotContainsString('Got it, all of them', $one);
    }

    public function testReplyNotificationCarriesFullMessageAndReports(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("CREATE TABLE atak_mod_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, gravity TEXT DEFAULT 'normal', workflow_status TEXT DEFAULT 'new',
            message TEXT, source TEXT DEFAULT 'player', steam_uid TEXT, player_name TEXT, callsign TEXT,
            last_seen_at TEXT, reply_text TEXT, replied_at TEXT, replied_by TEXT, reply_channels TEXT)");
        $pdo->exec("INSERT INTO atak_mod_reports (message, workflow_status) VALUES ('Carte noire', 'in_progress'), ('Son absent', 'corrigé')");
        $reports = new AtakModReportRepository($pdo);
        self::assertSame([1 => 'in_progress', 2 => 'fixed'], $reports->workflowStatusesByIds([1, 2, 0]));

        $notifications = $this->notifications($pdo);
        $service = new AtakModReportReplyService($reports, $notifications, static fn (): bool => true);
        $account = ['user_id' => 7, 'tenant_id' => 3, 'email' => 'hawk@example.com', 'name' => 'Hawk', 'lang' => 'fr'];
        $long = str_repeat('Texte long. ', 30);

        // « Notifier sur Athena » décoché : pas de notification, donc pas de fenêtre.
        $service->reply($reports->findById(1), $long, $account, false, true, 'TTRD');
        self::assertSame([], $notifications->listUnreadOfTypeForUser(7, self::TYPE));

        $service->reply($reports->findById(1), $long, $account, true, false, 'TTRD');
        $rows = $notifications->listUnreadOfTypeForUser(7, self::TYPE);
        self::assertCount(1, $rows);
        $payload = json_decode((string) $rows[0]['payload_json'], true);
        self::assertSame([1], $payload['report_ids']);
        self::assertSame(trim($long), $payload['message']);
        self::assertLessThanOrEqual(160, mb_strlen((string) $payload['summary']), 'résumé du centre d’activité inchangé');
    }

    public function testWiring(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString("'/compte/retours-signalement/vu'", (string) file_get_contents($root . '/routes/web.php'));
        self::assertStringContainsString('mod_report_reply_modal.php', (string) file_get_contents($root . '/views/layout/main.php'));
        $partial = (string) file_get_contents($root . '/views/partials/mod_report_reply_modal.php');
        self::assertStringContainsString('SESSION_CHECK_KEY', $partial);
        self::assertStringContainsString('$planGiftNotice', $partial, 'pas deux fenêtres en même temps');
    }
}
