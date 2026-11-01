<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\MemberProfileInviteRepository;
use App\Services\Effectifs\MemberProfileInviteService;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/** Liens « compléter son profil » : jeton haché, 24/48 h, usage unique, révocation, prolongation, étapes. */
final class MemberProfileInviteServiceTest extends TestCase
{
    private PDO $pdo;
    private DateTimeImmutable $now;
    /** @var list<string> */
    private array $audit = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, display_name TEXT, callsign TEXT)');
        $this->pdo->exec("CREATE TABLE member_profile_invites (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE, token_cipher TEXT, duration_hours INTEGER NOT NULL DEFAULT 48,
            single_use INTEGER NOT NULL DEFAULT 0, created_by INTEGER, created_at TEXT NOT NULL, expires_at TEXT NOT NULL,
            first_opened_at TEXT, last_opened_at TEXT, open_count INTEGER NOT NULL DEFAULT 0, account_ready_at TEXT,
            used_at TEXT, completed_at TEXT, revoked_at TEXT, revoked_by INTEGER, last_sent_at TEXT,
            last_sent_channel TEXT, sent_count INTEGER NOT NULL DEFAULT 0)");
        $this->pdo->exec("INSERT INTO users VALUES (7, 3, 'Jean Dupont', 'VIPER'), (9, 3, 'Chef', 'SIX')");
        $this->now = new DateTimeImmutable('2026-11-01 12:00:00');
    }

    private function service(): MemberProfileInviteService
    {
        return new MemberProfileInviteService(
            new MemberProfileInviteRepository($this->pdo),
            fn (): DateTimeImmutable => $this->now,
            function (string $action): void {
                $this->audit[] = $action;
            }
        );
    }

    /** @return array<string, mixed> */
    private function row(int $id): array
    {
        $st = $this->pdo->prepare('SELECT * FROM member_profile_invites WHERE id = ?');
        $st->execute([$id]);

        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function testTokenIsRandomAndOnlyItsHashIsStored(): void
    {
        $issued = $this->service()->issue(3, 7, 9, 48, false);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $issued['token']);
        self::assertStringEndsWith('/invitation/profil/' . $issued['token'], $issued['url']);
        $row = $this->row($issued['id']);
        self::assertSame(hash('sha256', $issued['token']), $row['token_hash']);
        foreach ($row as $column => $value) {
            self::assertStringNotContainsString($issued['token'], (string) $value, 'jeton en clair dans ' . $column);
        }
        self::assertNotSame($issued['token'], $this->service()->issue(3, 7, 9, 48, false)['token']);
        self::assertContains('profile_invite.created', $this->audit);
    }

    public function testDurationIs24Or48HoursAndExpiryIsStrict(): void
    {
        $svc = $this->service();
        $a = $svc->issue(3, 7, 9, 24, false);
        self::assertSame('2026-11-02 12:00:00', $a['expires_at']);
        $b = $svc->issue(3, 7, 9, 48, false);
        self::assertSame('2026-11-03 12:00:00', $b['expires_at']);
        self::assertSame(48, MemberProfileInviteService::normalizeHours(12));
        self::assertSame(48, MemberProfileInviteService::normalizeHours('72'));
        self::assertSame(24, MemberProfileInviteService::normalizeHours('24'));

        $this->now = new DateTimeImmutable('2026-11-03 11:59:59');
        self::assertNotNull($svc->findUsableByToken($b['token']));
        $this->now = new DateTimeImmutable('2026-11-03 12:00:00');
        self::assertNull($svc->findUsableByToken($b['token']));
        self::assertSame(MemberProfileInviteService::STATUS_EXPIRED, $svc->statusOf($this->row($b['id'])));
    }

    public function testMalformedOrUnknownTokensAreRefused(): void
    {
        $svc = $this->service();
        $svc->issue(3, 7, 9, 48, false);
        self::assertNull($svc->findUsableByToken(''));
        self::assertNull($svc->findUsableByToken('abc'));
        self::assertNull($svc->findUsableByToken(str_repeat('0', 64)));
        self::assertNull($svc->findUsableByToken(str_repeat('z', 64)));
    }

    public function testSingleUseClosesOnceTheAccountIsReady(): void
    {
        $svc = $this->service();
        $once = $svc->issue(3, 7, 9, 48, true);
        $row = $svc->findUsableByToken($once['token']);
        self::assertNotNull($row);
        $svc->recordOpen($row);
        self::assertSame(MemberProfileInviteService::STATUS_OPENED, $svc->statusOf($this->row($once['id'])));
        $svc->markAccountReady($this->row($once['id']));
        self::assertSame(MemberProfileInviteService::STATUS_USED, $svc->statusOf($this->row($once['id'])));
        self::assertNull($svc->findUsableByToken($once['token']));
        self::assertFalse($svc->extend(3, $once['id'], 48, 9));

        $multi = $svc->issue(3, 9, 9, 48, false);
        $svc->markAccountReady($this->row($multi['id']));
        self::assertNotNull($svc->findUsableByToken($multi['token']), 'sans usage unique, le lien reste ouvert jusqu’à expiration');
    }

    public function testRevocationAndOneLiveLinkPerMember(): void
    {
        $svc = $this->service();
        $first = $svc->issue(3, 7, 9, 48, false);
        $second = $svc->issue(3, 7, 9, 24, false);
        self::assertNull($svc->findUsableByToken($first['token']), 'un nouveau lien ferme l’ancien');
        self::assertSame(MemberProfileInviteService::STATUS_REVOKED, $svc->statusOf($this->row($first['id'])));

        self::assertFalse($svc->revoke(4, $second['id'], 9), 'une autre communauté ne peut pas révoquer');
        self::assertTrue($svc->revoke(3, $second['id'], 9));
        self::assertFalse($svc->revoke(3, $second['id'], 9));
        self::assertNull($svc->findUsableByToken($second['token']));
        self::assertNull($svc->linkFor($this->row($second['id'])));
        self::assertFalse($svc->extend(3, $second['id'], 48, 9));
        self::assertContains('profile_invite.revoked', $this->audit);
    }

    public function testExtendRegenerateAndCopyLink(): void
    {
        $svc = $this->service();
        $issued = $svc->issue(3, 7, 9, 24, true);
        self::assertSame($issued['url'], $svc->linkFor($this->row($issued['id'])));

        $this->now = new DateTimeImmutable('2026-11-05 08:00:00');
        self::assertNull($svc->linkFor($this->row($issued['id'])), 'expiré : rien à copier');
        self::assertTrue($svc->extend(3, $issued['id'], 48, 9));
        self::assertSame('2026-11-07 08:00:00', $this->row($issued['id'])['expires_at']);
        self::assertNotNull($svc->findUsableByToken($issued['token']));

        $new = $svc->regenerate(3, $issued['id'], 9);
        self::assertNotNull($new);
        self::assertNull($svc->findUsableByToken($issued['token']), 'l’ancienne adresse ne marche plus');
        $newRow = $this->row($new['id']);
        self::assertSame(24, (int) $newRow['duration_hours']);
        self::assertSame(1, (int) $newRow['single_use']);
        self::assertNotNull($svc->findUsableByToken($new['token']));
        self::assertNull($svc->regenerate(4, $new['id'], 9));
    }

    public function testCompletedLinkIsClosedAndListedWithItsAuthor(): void
    {
        $svc = $this->service();
        $issued = $svc->issue(3, 7, 9, 48, false);
        $svc->recordSent($this->row($issued['id']), 'discord', 9);
        $svc->markCompleted($this->row($issued['id']));
        self::assertSame(MemberProfileInviteService::STATUS_COMPLETED, $svc->statusOf($this->row($issued['id'])));
        self::assertNull($svc->findUsableByToken($issued['token']));

        $list = (new MemberProfileInviteRepository($this->pdo))->listForTenant(3);
        self::assertCount(1, $list);
        self::assertSame('SIX', $list[0]['creator_callsign']);
        self::assertSame('discord', $list[0]['last_sent_channel']);
        self::assertSame([], (new MemberProfileInviteRepository($this->pdo))->listForTenant(4));
    }

    public function testStepsFollowTheRealAccount(): void
    {
        $pending = MemberProfileInviteService::steps([], ['status' => 'pending_verification']);
        self::assertSame(['compte', 'steam', 'portrait', 'securite'], array_column($pending, 'key'), 'pas de Discord si la liaison n’est pas proposée');
        self::assertFalse(MemberProfileInviteService::allDone($pending));

        $facts = ['discord_available' => true, 'discord_linked' => true, 'steam_linked' => true, 'portrait' => true, 'second_factor' => true];
        $done = MemberProfileInviteService::steps($facts, ['status' => 'active', 'last_login_at' => '2026-11-01 13:00:00']);
        self::assertSame(['compte', 'discord', 'steam', 'portrait', 'securite'], array_column($done, 'key'));
        self::assertTrue(MemberProfileInviteService::allDone($done));

        $noLogin = MemberProfileInviteService::steps($facts, ['status' => 'active'], ['account_ready_at' => '2026-11-01 12:30:00']);
        self::assertTrue($noLogin[0]['done'], 'compte activé par le lien');
        self::assertSame('j•••@example.com', MemberProfileInviteService::maskEmail('jean@example.com'));
    }
}
