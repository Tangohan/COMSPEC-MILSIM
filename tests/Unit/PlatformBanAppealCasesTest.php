<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\PlatformBanAppealRepository;
use App\Services\Account\PlatformBanAppealCaseService;
use App\Services\Audit\AuditService;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Dossiers de contestation : référence unique, rattrapage, fil d'échange, notes internes, périmètres.
 * Base SQLite en mémoire, adresses fictives (example.test).
 */
final class PlatformBanAppealCasesTest extends TestCase
{
    private function oldSchema(): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE platform_account_bans (
            id INTEGER PRIMARY KEY, email_norm TEXT, display_label TEXT, reason TEXT, rule_reference TEXT, public_reason TEXT,
            ends_at TEXT, lifted_at TEXT, created_at TEXT, source_tenant_id INTEGER, appeal_closed INTEGER DEFAULT 0
        )');
        $pdo->exec("INSERT INTO platform_account_bans (id, email_norm, display_label, reason, created_at, source_tenant_id) VALUES
            (40, 'a@example.test', 'A', 'motif A', '2026-06-01 10:00:00', 5),
            (41, 'b@example.test', 'B', 'motif B', '2026-06-02 10:00:00', 6),
            (42, 'c@example.test', 'C', 'motif C', '2026-06-03 10:00:00', 5)");
        $pdo->exec('CREATE TABLE platform_ban_appeal_links (token_hash TEXT PRIMARY KEY, ban_id INTEGER, expires_at TEXT)');
        $pdo->exec('CREATE TABLE platform_ban_appeals (
            ban_id INTEGER PRIMARY KEY, statement TEXT, created_at TEXT,
            decision TEXT, public_reply TEXT, reviewed_at TEXT, reviewed_by INTEGER
        )');
        $pdo->exec("INSERT INTO platform_ban_appeals (ban_id, statement, created_at, decision, public_reply, reviewed_at, reviewed_by) VALUES
            (41, 'Contestation B', '2026-06-05 09:00:00', 'uphold', 'Décision maintenue', '2026-06-06 09:00:00', 1),
            (40, 'Contestation A', '2026-06-04 09:00:00', NULL, NULL, NULL, NULL)");

        return $pdo;
    }

    private function migrate(MysqlDdlSqlitePdo $pdo): void
    {
        $root = dirname(__DIR__, 2);
        (static function () use ($pdo, $root): void {
            require $root . '/database/steps/5080_platform_ban_appeal_cases.php';
        })();
    }

    private function migrated(): MysqlDdlSqlitePdo
    {
        $pdo = $this->oldSchema();
        $this->migrate($pdo);

        return $pdo;
    }

    private function audit(): AuditService
    {
        return new class extends AuditService {
            public function __construct()
            {
            }

            public function log(string $action, ?int $tenantId = null, ?int $userId = null, ?string $entityType = null, ?int $entityId = null, ?string $oldValue = null, ?string $newValue = null, ?string $ip = null, ?string $userAgent = null): void
            {
            }
        };
    }

    private function service(MysqlDdlSqlitePdo $pdo): PlatformBanAppealCaseService
    {
        return new PlatformBanAppealCaseService(new PlatformBanAppealRepository($pdo), new PlatformAccountBanRepository($pdo), null, null, $this->audit());
    }

    public function testReferencesAreReadableAndNeverRepeated(): void
    {
        $pdo = $this->migrated();
        self::assertSame('CTS-26-0042', PlatformBanAppealRepository::formatReference(2026, 42));
        $seen = [];
        for ($i = 0; $i < 30; $i++) {
            $reference = PlatformBanAppealRepository::allocateReference($pdo, 2027);
            self::assertMatchesRegularExpression('/^CTS-27-\d{4}$/', $reference);
            $seen[$reference] = true;
        }
        self::assertSame(30, count($seen));
        self::assertSame('CTS-27-0030', array_key_last($seen));
        // Une nouvelle année repart de 1 sans toucher aux compteurs des autres années.
        self::assertSame('CTS-28-0001', PlatformBanAppealRepository::allocateReference($pdo, 2028));
        self::assertSame('CTS-27-0031', PlatformBanAppealRepository::allocateReference($pdo, 2027));
    }

    public function testMigrationCatchesUpExistingAppealsAndCanBeRunTwice(): void
    {
        $pdo = $this->migrated();
        $appeals = new PlatformBanAppealRepository($pdo);

        self::assertTrue($appeals->casesReady());
        // Ordre de dépôt : 40 (4 juin) avant 41 (5 juin).
        self::assertSame('CTS-26-0001', $appeals->find(40)['reference']);
        self::assertSame('CTS-26-0002', $appeals->find(41)['reference']);
        self::assertSame('received', $appeals->find(40)['status']);
        self::assertSame('upheld', $appeals->find(41)['status']);
        self::assertSame(1, (int) $appeals->find(40)['unread_staff']);
        self::assertSame(0, (int) $appeals->find(41)['unread_staff']);
        self::assertSame(1, count($appeals->messages(40)));
        self::assertSame(['user', 'staff'], array_column($appeals->messages(41), 'author_type'));

        $pdo->changes = 0;
        $this->migrate($pdo);
        self::assertSame(0, $pdo->changes);
        self::assertSame('CTS-26-0001', $appeals->find(40)['reference']);
        self::assertSame(1, count($appeals->messages(40)));
        self::assertSame(2, count($appeals->messages(41)));
    }

    public function testSubmitOpensACaseWithItsReferenceAndFirstMessage(): void
    {
        $pdo = $this->migrated();
        $appeals = new PlatformBanAppealRepository($pdo);

        self::assertTrue($appeals->submit(42, 'Ma version des faits.'));
        self::assertFalse($appeals->submit(42, 'Doublon.'));
        $case = $appeals->find(42);
        self::assertMatchesRegularExpression('/^CTS-\d{2}-\d{4}$/', $case['reference']);
        self::assertNotNull($case['reference']);
        self::assertTrue(!in_array($case['reference'], ['CTS-26-0001', 'CTS-26-0002'], true));
        self::assertSame('received', $case['status']);
        self::assertSame(1, (int) $case['unread_staff']);
        self::assertSame(1, count($appeals->messages(42)));
        self::assertSame($case['reference'], $appeals->findByReference(strtolower($case['reference']))['reference']);
    }

    public function testInternalNotesAreNeverReturnedForThePerson(): void
    {
        $pdo = $this->migrated();
        $service = $this->service($pdo);
        $appeals = new PlatformBanAppealRepository($pdo);

        self::assertTrue($service->note(40, 7, 'Note privée : vérifier les journaux.')['ok']);
        self::assertTrue($service->staffReply(40, 7, 'Pouvez-vous préciser la date ?')['ok']);

        $forPerson = $appeals->messages(40, false);
        self::assertSame(['user', 'staff'], array_column($forPerson, 'author_type'));
        foreach ($forPerson as $message) {
            self::assertStringNotContainsString('Note privée', $message['body']);
            self::assertSame(0, (int) $message['is_internal']);
        }
        self::assertSame(3, count($appeals->messages(40, true)));

        // La page de la personne ne lit jamais les notes ni le nom des modérateurs.
        $controller = str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Auth/PlatformBanAppealController.php'));
        self::assertStringContainsString('->messages((int) $ban[\'id\'], false)', $controller);
        self::assertStringNotContainsString('author_user_id', $controller);
    }

    public function testReplyOpensTheUnreadFlagAndClosedCasesRefuseReplies(): void
    {
        $pdo = $this->migrated();
        $service = $this->service($pdo);
        $appeals = new PlatformBanAppealRepository($pdo);

        self::assertSame(1, $appeals->countUnread());
        $service->markRead(40);
        self::assertSame(0, $appeals->countUnread());

        self::assertTrue($service->staffReply(40, 7, 'Merci de préciser.', true)['ok']);
        self::assertSame('info_requested', $appeals->find(40)['status']);
        self::assertSame(7, (int) $appeals->find(40)['assigned_to']);

        // Réponse de la personne : retour à l'examen et notification interne (non lu).
        self::assertTrue($service->userReply(40, 'Voici les précisions demandées.')['ok']);
        self::assertSame('in_review', $appeals->find(40)['status']);
        self::assertSame(1, $appeals->countUnread());
        self::assertSame(1, $appeals->countOpen());

        self::assertFalse($service->userReply(40, 'x')['ok']);
        self::assertTrue($service->decide(40, 7, 'uphold', 'La décision est maintenue.')['ok']);
        self::assertSame('upheld', $appeals->find(40)['status']);
        self::assertSame('closed', $service->userReply(40, 'Je reviens sur le dossier.')['error']);
        self::assertFalse($service->staffReply(40, 7, 'Encore un message.')['ok']);
        self::assertFalse($service->decide(40, 7, 'closed', 'Clôture après décision.')['ok']);
        self::assertSame(0, $appeals->countOpen());
    }

    public function testQueueAndCountsStayInsideTheCommunityScope(): void
    {
        $pdo = $this->migrated();
        $appeals = new PlatformBanAppealRepository($pdo);
        self::assertTrue($appeals->submit(42, 'Contestation C.'));

        $all = array_column($appeals->listQueue(['status' => 'all'], null, 1), 'ban_id');
        sort($all);
        self::assertSame([40, 41, 42], array_map('intval', $all));

        $five = array_map('intval', array_column($appeals->listQueue(['status' => 'all'], 5, 1), 'ban_id'));
        sort($five);
        self::assertSame([40, 42], $five);
        self::assertSame([41], array_map('intval', array_column($appeals->listQueue(['status' => 'all'], 6, 1), 'ban_id')));
        // Un filtre « communauté » demandé par un administrateur de communauté ne dépasse pas son périmètre.
        self::assertSame([40, 42], array_map('intval', array_column($appeals->listQueue(['status' => 'all', 'tenant' => 6], 5, 1), 'ban_id')));
        self::assertSame(2, $appeals->countOpen(5));
        self::assertSame(0, $appeals->countOpen(99));
        self::assertSame(2, $appeals->countUnread(5));

        $controller = str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/System/BanAppealCaseController.php'));
        self::assertStringContainsString("(int) (\$case['source_tenant_id'] ?? 0) !== \$tenant", $controller);
        self::assertStringContainsString("COMMUNITY_ACTIONS = ['reply', 'info', 'note', 'assign']", $controller);
        self::assertStringNotContainsString("'accept'", substr($controller, (int) strpos($controller, 'COMMUNITY_ACTIONS'), 80));
    }

    public function testFiltersByStatusAgeAndAssignee(): void
    {
        $pdo = $this->migrated();
        $appeals = new PlatformBanAppealRepository($pdo);
        $service = $this->service($pdo);
        $service->assign(40, 7, 7);

        self::assertSame('in_review', $appeals->find(40)['status']);
        self::assertSame([40], array_map('intval', array_column($appeals->listQueue(['status' => 'in_review'], null, 7), 'ban_id')));
        self::assertSame([40], array_map('intval', array_column($appeals->listQueue(['status' => 'open', 'assigned' => 'me'], null, 7), 'ban_id')));
        self::assertSame([], array_column($appeals->listQueue(['status' => 'open', 'assigned' => 'none'], null, 7), 'ban_id'));
        self::assertSame([40], array_map('intval', array_column($appeals->listQueue(['status' => 'open', 'age_days' => 1], null, 7), 'ban_id')));
        self::assertSame([], array_column($appeals->listQueue(['status' => 'upheld', 'age_days' => 0, 'assigned' => 'me'], null, 7), 'ban_id'));
    }

    public function testRateLimitsAndCsrfGuardTheUserReply(): void
    {
        $controller = str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Auth/PlatformBanAppealController.php'));
        self::assertStringContainsString("'ban-appeal-reply:ban:' . (int) \$ban['id'], 10, 3600", $controller);
        self::assertStringContainsString("'ban-appeal-reply:network:' . \$request->ip(), 30, 3600", $controller);
        self::assertStringContainsString('$ban = Csrf::verify($request) ? $this->authorizedBan() : null;', $controller);
        self::assertStringContainsString("/contester-sanction/repondre", (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php'));
    }

    public function testLengthIsValidatedAndRegistryNumberIsUnique(): void
    {
        $pdo = $this->migrated();
        $service = $this->service($pdo);
        self::assertFalse($service->userReply(40, ' ')['ok']);
        self::assertFalse($service->userReply(40, str_repeat('a', 4001))['ok']);

        $registry = (string) file_get_contents(dirname(__DIR__, 2) . '/database/steps/registry.php');
        self::assertSame(1, substr_count($registry, "'5080_platform_ban_appeal_cases.php'"));
        self::assertSame(1, substr_count($registry, "'file' => '5080_"));
    }
}
