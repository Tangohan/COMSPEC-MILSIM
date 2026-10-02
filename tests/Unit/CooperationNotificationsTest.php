<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cooperation\CooperationAnnouncementEvents as E;
use App\Services\Cooperation\CooperationEmailLayout as L;
use App\Services\Cooperation\CooperationTransitionRules as R;
use PHPUnit\Framework\TestCase;

final class CooperationNotificationsTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function testEveryEventHasALabelAndAnActionForTheEmailHeader(): void
    {
        foreach ([E::PARTNER_REMOVED, E::PROPOSAL_CANCELLED, E::INVITATION_REMINDER, E::CONSENT_EXPIRING, E::SITREP_ADDED, E::OPERATIONAL_STAGE_UPDATED] as $k) {
            self::assertTrue(E::isKnown($k), $k);
        }
        foreach (E::allKeys() as $k) {
            $a = L::action($k);
            self::assertNotSame('', $a['expected'], $k);
            self::assertNotSame('', $a['cta'], $k);
        }
        self::assertSame([E::SITREP_ADDED], E::optionalKeys());
    }

    public function testActionableEventsPointToTheRightScreen(): void
    {
        self::assertTrue(L::action(E::INVITATION_REMINDER)['action_required']);
        self::assertSame(L::TARGET_SHOW, L::action(E::INVITATION_SENT)['target']);
        self::assertSame(L::TARGET_CONSENT, L::action(E::CONSENT_EXPIRING)['target']);
        self::assertSame(L::TARGET_CONSENT, L::action(E::MISSION_ACTIVATED)['target']);
        self::assertSame(L::TARGET_NEGOTIATE, L::action(E::COUNTER_PROPOSAL_SUBMITTED)['target']);
        self::assertSame(L::TARGET_REX, L::action(E::MISSION_CLOSED)['target']);
        // Unité retirée ou proposition annulée : plus d’accès au dossier, on renvoie à la liste.
        self::assertSame(L::TARGET_INDEX, L::action(E::PARTNER_REMOVED)['target']);
        self::assertFalse(L::action(E::PROPOSAL_CANCELLED)['action_required']);
    }

    public function testEmailHeaderIsEscapedAndCarriesTheDeadlineAndButton(): void
    {
        $head = [
            'title' => 'Op <Sirocco>',
            'issuer' => '2e REP',
            'expected' => 'Accepter ou refuser',
            'deadline' => '05/10/2026 19:00',
            'cta' => 'Répondre',
            'url' => 'https://exemple.test/back-office/cooperation/missions/7?a=1&b=2',
            'action_required' => true,
        ];
        $html = L::html($head, "Bonjour\n<script>x</script>");
        self::assertStringContainsString('Op &lt;Sirocco&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('Échéance', $html);
        self::assertStringContainsString('href="https://exemple.test/back-office/cooperation/missions/7?a=1&amp;b=2"', $html);
        $text = L::text($head, 'Bonjour');
        self::assertStringContainsString('Ce qui est attendu : Accepter ou refuser', $text);
        self::assertStringContainsString('Répondre : https://exemple.test', $text);

        $head['deadline'] = '';
        self::assertStringNotContainsString('Échéance', L::html($head, 'x'));
    }

    public function testAutomaticReminderOnlyInTheTwoDaysBeforeTheDeadline(): void
    {
        $pending = ['status' => 'pending', 'cooperation_phase' => 'proposed'];
        $at = static fn (int $s): string => date('Y-m-d H:i:s', self::NOW + $s);
        self::assertTrue(R::autoReminderDue($pending + ['proposal_deadline_at' => $at(36 * 3600)], self::NOW));
        self::assertFalse(R::autoReminderDue($pending + ['proposal_deadline_at' => $at(72 * 3600)], self::NOW));
        self::assertFalse(R::autoReminderDue($pending + ['proposal_deadline_at' => $at(-3600)], self::NOW));
        self::assertFalse(R::autoReminderDue($pending, self::NOW));
        self::assertFalse(R::autoReminderDue(['status' => 'active', 'proposal_deadline_at' => $at(3600)], self::NOW));
        self::assertFalse(R::autoReminderDue(['status' => 'archived', 'cooperation_phase' => 'cancelled', 'proposal_deadline_at' => $at(3600)], self::NOW));
    }

    public function testAutomaticAndManualRemindersShareTheDailyLimit(): void
    {
        $mission = ['status' => 'pending', 'cooperation_phase' => 'proposed'];
        $parts = [['tenant_id' => 5, 'status' => 'invited'], ['tenant_id' => 6, 'status' => 'active']];
        self::assertTrue(R::canRemind($mission, 5, $parts, null, self::NOW)['allowed']);
        self::assertFalse(R::canRemind($mission, 5, $parts, date('Y-m-d H:i:s', self::NOW - 3600), self::NOW)['allowed']);
        self::assertTrue(R::canRemind($mission, 5, $parts, date('Y-m-d H:i:s', self::NOW - 25 * 3600), self::NOW)['allowed']);
        self::assertFalse(R::canRemind($mission, 6, $parts, null, self::NOW)['allowed']);
    }

    public function testConsentExpiryNoticeWindowAndAntiSpam(): void
    {
        $in = static fn (int $s): string => date('Y-m-d H:i:s', self::NOW + $s);
        self::assertTrue(R::consentExpiryNoticeDue($in(6 * 3600), null, self::NOW));
        self::assertFalse(R::consentExpiryNoticeDue($in(20 * 3600), null, self::NOW));
        self::assertFalse(R::consentExpiryNoticeDue($in(-60), null, self::NOW));
        self::assertFalse(R::consentExpiryNoticeDue(null, null, self::NOW));
        self::assertFalse(R::consentExpiryNoticeDue($in(6 * 3600), $in(-2 * 3600), self::NOW));
        self::assertTrue(R::consentExpiryNoticeDue($in(6 * 3600), $in(-30 * 3600), self::NOW));
    }

    public function testWiringCronMigrationRecipientsAndDisabledTemplates(): void
    {
        $root = dirname(__DIR__, 2);
        $container = (string) file_get_contents($root . '/app/Core/Container.php');
        self::assertStringContainsString('self::get(\App\Services\Cron\Jobs\CooperationRemindersCronJob::class)', $container);
        self::assertFileExists($root . '/send-cooperation-reminders.php');
        $migrations = (string) file_get_contents($root . '/run-migrations.php');
        self::assertStringContainsString('cooperation_announcement_events_v3_migration.php', $migrations);
        $seed = (string) file_get_contents($root . '/bootstrap/cooperation_announcement_events_v3_migration.php');
        self::assertStringContainsString("'coop_sitrep_added'", $seed);
        self::assertStringContainsString('updated_at IS NULL', $seed);

        $dispatcher = (string) file_get_contents($root . '/app/Services/Cooperation/CooperationAnnouncementDispatcher.php');
        self::assertStringContainsString('missionDesigneeIds', $dispatcher);
        self::assertStringContainsString('findExact(0, $eventKey, $channel)', $dispatcher);
        self::assertStringContainsString('CooperationEmailLayout::html', $dispatcher);

        $controller = (string) file_get_contents($root . '/app/Controllers/Web/InterteamMissionWebController.php');
        self::assertStringContainsString('CooperationAnnouncementEvents::SITREP_ADDED', $controller);

        self::assertFileExists($root . '/docs/COOPERATION-INTER-UNITES.md');
    }
}
