<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Monitoring\AtakModReportDiscordTicketService;
use App\Services\Security\FileRateLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

/** Signalements du mod en tickets Discord : ouverture, dédoublonnage, étapes de statut, réponse, suppression. */
final class AtakModReportDiscordTicketServiceTest extends TestCase
{
    private const HOOK = 'https://discord.com/api/webhooks/123/abc';

    private PDO $pdo;
    /** @var list<array{0:string, 1:string, 2:array<string, mixed>}> */
    private array $calls = [];
    private int $now = 1_800_000_000;
    private int $nextId = 900;
    private bool $forum = true;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE atak_mod_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT, severity TEXT DEFAULT 'error', workflow_status TEXT DEFAULT 'new',
            channel TEXT DEFAULT 'Core', message TEXT, detail_text TEXT, fingerprint TEXT, source TEXT DEFAULT 'auto',
            steam_uid TEXT, player_name TEXT, callsign TEXT, client_ip TEXT, mod_version TEXT, extension_version TEXT,
            hit_count INTEGER DEFAULT 1, created_at TEXT DEFAULT '2026-10-14 09:37:00', last_seen_at TEXT DEFAULT '2026-10-14 09:40:00')");
        $this->pdo->exec("CREATE TABLE atak_mod_report_discord_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT, opened_report_id INTEGER, fingerprint TEXT, thread_id TEXT,
            header_message_id TEXT, status TEXT DEFAULT 'new', last_step_at TEXT, header_synced_at TEXT, created_at TEXT)");
        $this->pdo->exec('CREATE TABLE atak_mod_report_discord_links (report_id INTEGER PRIMARY KEY, ticket_id INTEGER, created_at TEXT)');
    }

    private function service(): AtakModReportDiscordTicketService
    {
        $limiter = $this->createStub(FileRateLimiter::class);
        $limiter->method('hit')->willReturn(1);

        return new AtakModReportDiscordTicketService(
            $this->pdo,
            self::HOOK,
            function (string $method, string $url, array $payload): array {
                $this->calls[] = [$method, $url, $payload];
                if (isset($payload['thread_name']) && !$this->forum) {
                    return ['status' => 400, 'body' => ['code' => 220003]];
                }
                $id = (string) $this->nextId++;

                return ['status' => 200, 'body' => ['id' => $id, 'channel_id' => isset($payload['thread_name']) ? 'T' . $id : 'C1']];
            },
            $limiter,
            fn (): int => $this->now
        );
    }

    private function report(string $message, string $fingerprint, array $extra = []): int
    {
        $row = array_merge([
            'message' => $message, 'fingerprint' => $fingerprint, 'player_name' => 'Bet Leader', 'callsign' => 'Operateur',
            'steam_uid' => '76561198039565374', 'client_ip' => '81.12.4.5', 'mod_version' => '1.6.16', 'extension_version' => '2.0.84',
        ], $extra);
        $cols = array_keys($row);
        $this->pdo->prepare('INSERT INTO atak_mod_reports (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')
            ->execute(array_values($row));

        return (int) $this->pdo->lastInsertId();
    }

    public function testNewReportOpensForumTicketWithStatusHeaderAndNoPrivateData(): void
    {
        $id = $this->report('ÉCHEC · fiche opérateur — unauthorized', 'fp1');

        self::assertSame('opened', $this->service()->onReport($id));
        self::assertCount(1, $this->calls);
        [$method, $url, $payload] = $this->calls[0];
        self::assertSame('POST', $method);
        self::assertSame(self::HOOK . '?wait=true', $url);
        self::assertStringStartsWith('#' . $id . ' · ÉCHEC', $payload['thread_name']);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        self::assertStringContainsString('🔴 Nouveau · New', $json);
        self::assertStringContainsString('Bet Leader (Operateur)', $json);
        self::assertStringNotContainsString('76561198039565374', $json);
        self::assertStringNotContainsString('81.12', $json);
        $ticket = $this->pdo->query('SELECT * FROM atak_mod_report_discord_tickets')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('T900', $ticket['thread_id']);
        self::assertSame('900', $ticket['header_message_id']);
    }

    public function testRepeatedHitsRefreshHeaderAtMostEveryTenMinutes(): void
    {
        $id = $this->report('Timeout Link', 'fp2');
        $svc = $this->service();
        $svc->onReport($id);
        $this->pdo->exec('UPDATE atak_mod_reports SET hit_count = 5');

        self::assertSame('quiet', $svc->onReport($id));
        $this->now += 601;
        self::assertSame('refreshed', $svc->onReport($id));
        [$method, $url, $payload] = $this->calls[1];
        self::assertSame('PATCH', $method);
        self::assertSame(self::HOOK . '/messages/900?thread_id=T900', $url);
        self::assertStringContainsString('×5', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function testSameFingerprintJoinsTicketAndReopensWhenFixed(): void
    {
        $svc = $this->service();
        $first = $this->report('Relais ATAK — not_connected', 'fp3');
        $svc->onReport($first);
        $svc->onStatusChanged([$first], 'fixed', 'TA1');
        $this->now += 3600;
        $second = $this->report('Relais ATAK — not_connected', 'fp3', ['player_name' => 'NewPI', 'callsign' => 'TA1']);

        self::assertSame('linked', $svc->onReport($second));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_mod_report_discord_tickets')->fetchColumn());
        self::assertSame('new', $this->pdo->query('SELECT status FROM atak_mod_report_discord_tickets')->fetchColumn());
        $steps = array_values(array_filter($this->calls, static fn (array $c): bool => $c[0] === 'POST' && str_contains($c[1], 'thread_id=T900')));
        self::assertStringContainsString('Statut : Corrigé · Fixed', $steps[0][2]['embeds'][0]['description']);
        self::assertStringContainsString('par TA1', $steps[0][2]['embeds'][0]['description']);
        self::assertStringContainsString('Rouvert', $steps[1][2]['embeds'][0]['description']);
        self::assertStringContainsString('NewPI (TA1)', $steps[1][2]['embeds'][0]['description']);
    }

    public function testStatusStepIsPostedOncePerTicketInBulk(): void
    {
        $svc = $this->service();
        $a = $this->report('Erreur A', 'fpA');
        $b = $this->report('Erreur A', 'fpA', ['player_name' => 'Kilo']);
        $svc->onReport($a);
        $this->now += 700;
        $svc->onReport($b);
        $this->calls = [];

        self::assertSame(1, $svc->onStatusChanged([$a, $b], 'in_progress', 'TA1'));
        self::assertSame(0, $svc->onStatusChanged([$a, $b], 'in_progress', 'TA1'));
        self::assertSame(['POST', 'PATCH'], array_column($this->calls, 0));
        self::assertStringContainsString('🟠 En cours · In progress', json_encode($this->calls[1][2], JSON_UNESCAPED_UNICODE));
    }

    public function testLowSeverityReportsDoNotOpenTickets(): void
    {
        $info = $this->report('Pack ancien', 'fpI', ['severity' => 'info']);
        self::assertSame('skipped_low', $this->service()->onReport($info));
        self::assertFalse(AtakModReportDiscordTicketService::opensTicket(['severity' => 'error', 'gravity' => 'low']));
        self::assertTrue(AtakModReportDiscordTicketService::opensTicket(['severity' => 'info', 'gravity' => 'high']));
        self::assertSame([], $this->calls);
    }

    public function testReplyAndDeleteArePostedAsSteps(): void
    {
        $svc = $this->service();
        $id = $this->report('Carte noire', 'fpR', ['source' => 'player']);
        $svc->onReport($id);

        self::assertTrue($svc->onReply($id, "Merci pour votre signalement.\nCorrigé en 1.93.0.", 'TA1', ['site', 'email']));
        $desc = $this->calls[1][2]['embeds'][0]['description'];
        self::assertStringContainsString('Réponse au joueur', $desc);
        self::assertStringContainsString('Athena + e-mail', $desc);
        self::assertStringContainsString("> Corrigé en 1.93.0.", $desc);

        self::assertSame(1, $svc->onDeleted([$id], 'TA1'));
        self::assertStringContainsString('supprimé', $this->calls[2][2]['embeds'][0]['description']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_mod_report_discord_links')->fetchColumn());
    }

    public function testTextChannelFallsBackToPlainMessages(): void
    {
        $this->forum = false;
        $svc = $this->service();
        $id = $this->report('Erreur texte', 'fpT');

        self::assertSame('opened', $svc->onReport($id));
        self::assertArrayNotHasKey('thread_name', $this->calls[1][2]);
        self::assertNull($this->pdo->query('SELECT thread_id FROM atak_mod_report_discord_tickets')->fetchColumn());
        $svc->onStatusChanged([$id], 'fixed', 'TA1');
        self::assertSame(self::HOOK . '?wait=true', $this->calls[2][1]);
        self::assertStringContainsString('Ticket #' . $id, $this->calls[2][2]['embeds'][0]['description']);
        self::assertSame(self::HOOK . '/messages/900', $this->calls[3][1]);
    }

    public function testDisabledWithoutWebhook(): void
    {
        $svc = new AtakModReportDiscordTicketService($this->pdo, '', fn () => ['status' => 200, 'body' => null]);
        self::assertSame('disabled', $svc->onReport($this->report('X', 'fpX')));
    }
}
