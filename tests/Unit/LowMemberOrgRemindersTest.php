<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\LowMemberOrgRepository;
use App\Services\Platform\LowMemberOrgService;
use App\Support\LowMemberOrgPolicy;
use PDO;
use PHPUnit\Framework\TestCase;

final class LowMemberOrgRemindersTest extends TestCase
{
    private const DAY = 86400;

    private int $created;

    protected function setUp(): void
    {
        $this->created = gmmktime(12, 0, 0, 1, 10, 2026);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE org_low_member_reminders (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, stage VARCHAR(16) NOT NULL,
            member_count INTEGER NOT NULL DEFAULT 0, recipients INTEGER NOT NULL DEFAULT 0, emails INTEGER NOT NULL DEFAULT 0, sent_at DATETIME NOT NULL,
            UNIQUE (tenant_id, stage))');
        $pdo->exec('CREATE TABLE org_low_member_watch (tenant_id INTEGER PRIMARY KEY, listed_at DATETIME NULL, admin_notified_at DATETIME NULL,
            admin_emailed_at DATETIME NULL, kept_at DATETIME NULL, kept_by INTEGER NULL)');
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name VARCHAR(255) NOT NULL)');
        $pdo->exec("INSERT INTO tenants (id, name) VALUES (5, 'Org 5'), (6, 'Org 6'), (7, 'Org 7')");

        return $pdo;
    }

    /** @return array<string, mixed> */
    private function tenant(int $id, string $slug = 'alpha', ?array $archive = null): array
    {
        return ['id' => $id, 'name' => 'Org ' . $id, 'slug' => $slug, 'created_at' => gmdate('Y-m-d H:i:s', $this->created), 'archive' => $archive];
    }

    private function at(int $days): int
    {
        return $this->created + $days * self::DAY;
    }

    public function testDetectionNeedsThreeMonthsAndOneOrTwoMembers(): void
    {
        $t = $this->tenant(5);
        self::assertFalse(LowMemberOrgPolicy::matches($t, 1, false, $this->at(89)));
        self::assertTrue(LowMemberOrgPolicy::matches($t, 1, false, $this->at(90)));
        self::assertTrue(LowMemberOrgPolicy::matches($t, 2, false, $this->at(90)));
        self::assertFalse(LowMemberOrgPolicy::matches($t, 3, false, $this->at(200)));
        self::assertFalse(LowMemberOrgPolicy::matches($t, 0, false, $this->at(200)));
    }

    public function testPlatformDemoArchivedAndKeptAreExcluded(): void
    {
        $now = $this->at(200);
        self::assertFalse(LowMemberOrgPolicy::matches($this->tenant(1, 'comspec'), 1, false, $now));
        self::assertFalse(LowMemberOrgPolicy::matches($this->tenant(7, 'demo-comspec'), 1, false, $now));
        self::assertFalse(LowMemberOrgPolicy::matches($this->tenant(8, 'demo-otan'), 1, false, $now));
        self::assertFalse(LowMemberOrgPolicy::matches($this->tenant(9, 'bravo', ['archived_at' => '2026-05-01 00:00:00']), 1, false, $now));
        self::assertFalse(LowMemberOrgPolicy::matches($this->tenant(10), 1, true, $now));
        self::assertTrue(LowMemberOrgPolicy::matches($this->tenant(11, 'demolition'), 1, false, $now));
    }

    public function testStagesFollowEachOtherWithAGap(): void
    {
        $t = $this->tenant(5);
        self::assertSame('m3', LowMemberOrgPolicy::dueStage($t, 1, [], false, $this->at(150)));
        // Communauté déjà ancienne : la relance attend 21 jours après le premier rappel.
        $sent = ['m3' => gmdate('Y-m-d H:i:s', $this->at(150))];
        self::assertNull(LowMemberOrgPolicy::dueStage($t, 1, $sent, false, $this->at(160)));
        self::assertSame('m4', LowMemberOrgPolicy::dueStage($t, 1, $sent, false, $this->at(171)));
        $sent['m4'] = gmdate('Y-m-d H:i:s', $this->at(171));
        self::assertNull(LowMemberOrgPolicy::dueStage($t, 1, $sent, false, $this->at(400)));
        self::assertFalse(LowMemberOrgPolicy::readyForDeletion($sent, $this->at(184)));
        self::assertTrue(LowMemberOrgPolicy::readyForDeletion($sent, $this->at(185)));
    }

    public function testClaimIsUniquePerStage(): void
    {
        $repo = new LowMemberOrgRepository($this->pdo());
        self::assertTrue($repo->claimReminder(5, 'm3', 1, $this->at(90)));
        self::assertFalse($repo->claimReminder(5, 'm3', 1, $this->at(91)));
        self::assertTrue($repo->claimReminder(5, 'm4', 1, $this->at(120)));
    }

    public function testRemindersAreNeverSentTwiceAndAdminIsAlertedOnce(): void
    {
        $tenants = [$this->tenant(5), $this->tenant(6, 'charlie'), $this->tenant(7, 'demo-comspec')];
        $members = [5 => 1, 6 => 4, 7 => 1];
        $sent = [];
        $alerts = [];
        $repo = new LowMemberOrgRepository($this->pdo());
        $service = new LowMemberOrgService(
            $repo,
            static fn (): array => $tenants,
            static fn (int $id): int => $members[$id] ?? 0,
            static function (array $row, string $stage) use (&$sent): array {
                $sent[] = $row['id'] . ':' . $stage;

                return ['recipients' => 1, 'emails' => 1];
            },
            static function (array $fresh, ?array $digest) use (&$alerts): array {
                $alerts[] = [array_column($fresh, 'id'), $digest === null ? null : array_column($digest, 'id')];

                return ['notified' => count($fresh), 'emails' => $digest === null ? 0 : 1];
            },
        );

        self::assertSame(0, $service->run($this->at(80))['reminders']);
        self::assertSame(1, $service->run($this->at(95))['reminders']);
        self::assertSame(0, $service->run($this->at(95))['reminders']);
        self::assertSame(0, $service->run($this->at(110))['reminders']);
        self::assertSame(1, $service->run($this->at(121))['reminders']);
        self::assertSame(0, $service->run($this->at(122))['reminders']);
        self::assertSame(['5:m3', '5:m4'], $sent);
        self::assertSame([], $alerts);

        // 14 jours après la relance : dans la liste, une alerte (notification + résumé e-mail).
        $r = $service->run($this->at(135));
        self::assertSame(1, $r['listed']);
        self::assertSame(['count' => 1, 'names' => ['Org 5']], $repo->listedSummary());
        self::assertSame([[[5], [5]]], $alerts);
        $service->run($this->at(136));
        $service->run($this->at(150));
        self::assertCount(1, $alerts, 'Pas de nouvelle alerte tant que la liste ne change pas.');

        $rows = $service->evaluate($this->at(150));
        self::assertSame([5], array_column($rows, 'id'));
        self::assertSame('ready', $rows[0]['status']);
        self::assertSame(['m3', 'm4'], array_keys($rows[0]['reminders']));
    }

    public function testKeptOrganisationGetsNoReminderAndLeavesTheList(): void
    {
        $sent = [];
        $repo = new LowMemberOrgRepository($this->pdo());
        $service = new LowMemberOrgService(
            $repo,
            fn (): array => [$this->tenant(5)],
            static fn (): int => 2,
            static function (array $row, string $stage) use (&$sent): array {
                $sent[] = $stage;

                return ['recipients' => 1, 'emails' => 0];
            },
            static fn (array $fresh, ?array $digest): array => ['notified' => count($fresh), 'emails' => 0],
        );
        $service->keep(5, 3, $this->at(91));
        self::assertSame(0, $service->run($this->at(95))['reminders']);
        self::assertSame(0, $service->run($this->at(200))['listed']);
        self::assertSame([], $sent);
        self::assertSame('kept', $service->evaluate($this->at(200))[0]['status']);
        self::assertSame(0, $repo->listedSummary()['count']);

        $service->unkeep(5);
        self::assertSame(1, $service->run($this->at(201))['reminders']);
        self::assertSame(['m3'], $sent);
    }

    public function testCopyIsWrittenInTheRecipientLanguage(): void
    {
        $fr = LowMemberOrgPolicy::copy('fr', 'm4', 'Alpha', 1, $this->at(150));
        $en = LowMemberOrgPolicy::copy('en-GB', 'm4', 'Alpha', 2, $this->at(150));
        self::assertStringContainsString('1 membre actif', $fr['lead']);
        self::assertStringContainsString('supprimer', $fr['warning']);
        self::assertStringContainsString('2 active members', $en['lead']);
        self::assertStringContainsString('delete', $en['warning']);
        self::assertSame(['invitations', 'new_member', 'invite_codes', 'public_page'], array_column($en['tips'], 'key'));
    }
}
