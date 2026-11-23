<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\RosterAccountReminderRepository;
use App\Services\Effectifs\AccountRequirementService as S;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Compte obligatoire des personnes sans compte : réglage, échéance, état « en retard », relance en lot, restriction.
 */
final class AccountRequirementServiceTest extends TestCase
{
    private const ON = ['enabled' => true, 'days' => 14, 'since' => '2026-11-01 08:00:00', 'restrict' => false];

    public function testConfigDefaultsAndClamp(): void
    {
        self::assertSame(['enabled' => false, 'days' => 14, 'since' => '', 'restrict' => false], S::config([]));
        self::assertSame(90, S::config(['account_required' => ['enabled' => true, 'days' => 500, 'since' => '2026-11-01 08:00:00']])['days']);
        self::assertSame(14, S::config(['account_required' => ['enabled' => true, 'days' => 0, 'since' => '2026-11-01 08:00:00']])['days']);
        self::assertFalse(S::config(['account_required' => ['enabled' => false, 'restrict' => true]])['restrict'], 'la restriction exige l’obligation');
    }

    public function testDeadlineStartsAtLatestOfCreationAndSince(): void
    {
        $old = S::deadline(self::ON, '2026-01-01 10:00:00');
        self::assertSame('2026-11-15', $old->format('Y-m-d'), 'fiche ancienne : délai compté depuis l’activation');
        $new = S::deadline(self::ON, '2026-11-10 10:00:00');
        self::assertSame('2026-11-24', $new->format('Y-m-d'));
        self::assertNull(S::deadline(['enabled' => false, 'days' => 14, 'since' => '', 'restrict' => false], '2026-11-10 10:00:00'));
    }

    public function testStatePendingThenLate(): void
    {
        $pending = S::state(self::ON, '2026-11-10 10:00:00', new DateTimeImmutable('2026-11-20 10:00:00'));
        self::assertSame(S::STATE_PENDING, $pending['state']);
        self::assertSame(4, $pending['days_left']);
        $late = S::state(self::ON, '2026-11-10 10:00:00', new DateTimeImmutable('2026-11-25 10:00:00'));
        self::assertSame(S::STATE_LATE, $late['state']);
        self::assertSame(S::STATE_OFF, S::state(['enabled' => false, 'days' => 14, 'since' => '', 'restrict' => false], '2026-11-10', new DateTimeImmutable('2026-11-25'))['state']);
        self::assertStringStartsWith('À créer avant le ', S::label(S::STATE_PENDING, $pending['deadline']));
        self::assertStringStartsWith('En retard', S::label(S::STATE_LATE, $late['deadline']));
    }

    public function testRemindableSkipsRecentlyRemindedAndRespectsScope(): void
    {
        $now = new DateTimeImmutable('2026-11-30 10:00:00');
        $rows = S::decorate([
            ['id' => 1, 'created_at' => '2026-11-02 10:00:00'],
            ['id' => 2, 'created_at' => '2026-11-03 10:00:00'],
            ['id' => 3, 'created_at' => '2026-11-25 10:00:00'],
        ], self::ON, [2 => ['last_reminded_at' => '2026-11-29 10:00:00', 'reminder_count' => 1]], $now);
        $late = S::remindable($rows, true, $now);
        self::assertSame([1], array_column($late, 'id'), 'en retard seulement, sans la personne relancée hier');
        $all = S::remindable($rows, false, $now);
        self::assertSame([1, 3], array_column($all, 'id'));
    }

    public function testRestrictionOnlyForLateRosterFichesWithTheOption(): void
    {
        $cfg = ['enabled' => true, 'days' => 14, 'since' => '2026-11-01 08:00:00', 'restrict' => true];
        $now = new DateTimeImmutable('2026-12-30 10:00:00');
        $fiche = ['email' => 'fiche-ab@sans-compte.invalid', 'created_at' => '2026-11-02 10:00:00'];
        self::assertTrue(S::isRestricted($cfg, $fiche, $now));
        self::assertFalse(S::isRestricted(['restrict' => false] + $cfg, $fiche, $now), 'option désactivée par défaut');
        self::assertFalse(S::isRestricted($cfg, ['email' => 'a@b.fr', 'created_at' => '2026-11-02 10:00:00'], $now), 'un vrai compte n’est jamais restreint');
        self::assertFalse(S::isRestricted($cfg, $fiche, new DateTimeImmutable('2026-11-05 10:00:00')), 'pas encore en retard');
    }

    public function testStepIsRegisteredOnce(): void
    {
        $root = dirname(__DIR__, 2);
        $registry = (string) file_get_contents($root . '/database/steps/registry.php');
        self::assertSame(1, substr_count($registry, "'5000_roster_account_requirement.php'"));
        self::assertFileExists($root . '/database/steps/5000_roster_account_requirement.php');
        preg_match_all("/'file' => '(\\d+)_/", $registry, $m);
        self::assertSame(count($m[1]), count(array_unique($m[1])), 'aucun numéro d’étape répété');
    }

    public function testReminderRepositoryUpsert(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE roster_account_reminders (tenant_id INT, user_id INT, last_reminded_at TEXT, reminder_count INT DEFAULT 1, last_channel TEXT, last_actor_user_id INT, PRIMARY KEY (tenant_id, user_id))');
        $repo = new RosterAccountReminderRepository($pdo);
        $repo->record(1, 10, 5, 'link', '2026-11-01 10:00:00');
        $repo->record(1, 10, 5, 'email', '2026-11-05 10:00:00');
        $repo->record(2, 10, 5, 'link', '2026-11-06 10:00:00');
        self::assertSame(['last_reminded_at' => '2026-11-05 10:00:00', 'reminder_count' => 2], $repo->forTenant(1)[10]);
        self::assertSame(1, $repo->forTenant(2)[10]['reminder_count'], 'cloisonné par communauté');
        self::assertSame([], $repo->forTenant(3));
    }
}
