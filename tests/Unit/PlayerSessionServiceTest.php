<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PlayerSessionService as S;
use PHPUnit\Framework\TestCase;

/**
 * Sessions de jeu d'un joueur : fusion des signes de vie, coupures et reconnexions, session en cours, durée aberrante,
 * seuil configurable, fuseau et passage de minuit, agrégats 7/30/90 jours, série et fiabilité.
 */
final class PlayerSessionServiceTest extends TestCase
{
    private const GAP = 25 * 60;
    private const T0 = 1_793_000_000; // 2026-10-26 07:33 UTC environ

    /** @return list<array{at: int, type: string}> signes de vie toutes les $every secondes pendant $minutes minutes */
    private static function beats(int $start, int $minutes, int $every = 600): array
    {
        $out = [];
        for ($t = $start; $t <= $start + $minutes * 60; $t += $every) {
            $out[] = ['at' => $t, 'type' => S::EVENT_HEARTBEAT];
        }

        return $out;
    }

    public function testHeartbeatsEveryTenMinutesMakeOneSession(): void
    {
        $sessions = S::buildSessions(self::beats(self::T0, 90), self::GAP, self::T0 + 86400);
        self::assertCount(1, $sessions);
        self::assertSame(90 * 60, $sessions[0]['duration_s']);
        self::assertSame(self::T0, $sessions[0]['started_at']);
        self::assertSame(self::T0 + 5400, $sessions[0]['ended_at']);
        self::assertFalse($sessions[0]['ongoing']);
        self::assertTrue(S::qualifies($sessions[0], 3600));
    }

    public function testShortConnectionDoesNotQualify(): void
    {
        $sessions = S::buildSessions(self::beats(self::T0, 5, 300), self::GAP);
        self::assertCount(1, $sessions);
        self::assertSame(300, $sessions[0]['duration_s']);
        self::assertFalse(S::qualifies($sessions[0], 3600));
    }

    public function testSingleHeartbeatIsAZeroLengthSession(): void
    {
        $sessions = S::buildSessions([['at' => self::T0, 'type' => S::EVENT_HEARTBEAT]], self::GAP);
        self::assertCount(1, $sessions);
        self::assertSame(0, $sessions[0]['duration_s']);
    }

    public function testShortCutAndQuickReconnectionAreMergedIntoOneSession(): void
    {
        $events = array_merge(
            self::beats(self::T0, 40),
            [['at' => self::T0 + 40 * 60 + 60, 'type' => S::EVENT_DISCONNECT]],
            [['at' => self::T0 + 40 * 60 + 5 * 60, 'type' => S::EVENT_CONNECT]],
            self::beats(self::T0 + 50 * 60, 30)
        );
        $sessions = S::buildSessions($events, self::GAP);
        self::assertCount(1, $sessions, 'une reconnexion rapprochée ne coupe pas la session');
        self::assertSame(80 * 60, $sessions[0]['duration_s']);
        self::assertTrue(S::qualifies($sessions[0], 3600));
    }

    public function testLongGapSplitsTwoSessions(): void
    {
        $events = array_merge(self::beats(self::T0, 40), self::beats(self::T0 + 40 * 60 + 26 * 60, 40));
        $sessions = S::buildSessions($events, self::GAP);
        self::assertCount(2, $sessions);
        self::assertFalse(S::qualifies($sessions[0], 3600));
        self::assertFalse(S::qualifies($sessions[1], 3600));
    }

    public function testExplicitDisconnectClosesTheSessionAndALaterConnectionStartsANewOne(): void
    {
        $events = array_merge(
            self::beats(self::T0, 70),
            [['at' => self::T0 + 72 * 60, 'type' => S::EVENT_DISCONNECT]],
            [['at' => self::T0 + 5 * 3600, 'type' => S::EVENT_CONNECT]],
            [['at' => self::T0 + 5 * 3600 + 300, 'type' => S::EVENT_DISCONNECT]]
        );
        $sessions = S::buildSessions($events, self::GAP, self::T0 + 86400);
        self::assertCount(2, $sessions);
        self::assertSame(72 * 60, $sessions[0]['duration_s']);
        self::assertSame(self::T0 + 72 * 60, $sessions[0]['ended_at']);
        self::assertTrue(S::qualifies($sessions[0], 3600));
        self::assertFalse(S::qualifies($sessions[1], 3600));
    }

    public function testDisconnectWithoutKnownPresenceOpensNothing(): void
    {
        self::assertSame([], S::buildSessions([['at' => self::T0, 'type' => S::EVENT_DISCONNECT]], self::GAP));
    }

    public function testEventsAreSortedAndInvalidOnesIgnored(): void
    {
        $events = [
            ['at' => self::T0 + 1200, 'type' => S::EVENT_HEARTBEAT],
            ['at' => 'n’importe quoi', 'type' => S::EVENT_HEARTBEAT],
            ['at' => self::T0, 'type' => 'inconnu'],
            ['at' => date('Y-m-d H:i:s', self::T0 + 600), 'type' => S::EVENT_HEARTBEAT],
        ];
        $sessions = S::buildSessions($events, self::GAP);
        self::assertCount(1, $sessions);
        self::assertSame(1200, $sessions[0]['duration_s']);
    }

    public function testOngoingSessionCountsOnlyOnceItReachesTheThreshold(): void
    {
        $now = self::T0 + 50 * 60 + 120;
        $young = S::buildSessions(self::beats(self::T0, 50), self::GAP, $now);
        self::assertTrue($young[0]['ongoing']);
        self::assertNull($young[0]['ended_at']);
        self::assertFalse(S::qualifies($young[0], 3600), '50 minutes : pas encore une session qui compte');

        $now2 = self::T0 + 62 * 60;
        $older = S::buildSessions(self::beats(self::T0, 62, 600), self::GAP, $now2 + 60);
        self::assertTrue($older[0]['ongoing']);
        self::assertTrue(S::qualifies($older[0], 3600), 'en direct, elle compte dès qu’elle dépasse le seuil');

        $summary = S::summarize($older, 3600, $now2 + 60, 'Europe/Paris');
        self::assertTrue($summary['ongoing_qualifying']);
        self::assertSame(1, $summary['windows'][7]['sessions']);
    }

    public function testOverlongSessionIsCappedAndFlagged(): void
    {
        $sessions = S::buildSessions(self::beats(self::T0, 30 * 60, 1200), self::GAP);
        self::assertCount(1, $sessions);
        self::assertTrue($sessions[0]['aberrant']);
        self::assertSame(S::MAX_SESSION_SECONDS, $sessions[0]['duration_s']);
        $summary = S::summarize($sessions, 3600, self::T0 + 31 * 3600, 'Europe/Paris');
        self::assertSame(1, $summary['aberrant']);
        self::assertSame(S::RELIABILITY_PARTIAL, $summary['reliability']);
        self::assertSame(S::MAX_SESSION_SECONDS, $summary['windows'][7]['seconds'], 'durée plafonnée, jamais gonflée');
    }

    public function testThresholdIsConfigurablePerCommunity(): void
    {
        $sessions = S::buildSessions(self::beats(self::T0, 45), self::GAP);
        self::assertTrue(S::qualifies($sessions[0], 30 * 60));
        self::assertFalse(S::qualifies($sessions[0], 60 * 60));
        self::assertFalse(S::qualifies($sessions[0], 120 * 60));
        $now = self::T0 + 3600;
        self::assertSame(1, S::summarize($sessions, 30 * 60, $now)['windows'][7]['sessions']);
        self::assertSame(0, S::summarize($sessions, 60 * 60, $now)['windows'][7]['sessions']);
        self::assertSame(0, S::summarize($sessions, 120 * 60, $now)['windows'][7]['sessions']);
        self::assertSame(1, S::summarize($sessions, 60 * 60, $now)['windows'][7]['short'], 'la session courte reste visible comme telle');
    }

    public function testSessionIsNeverSplitAtMidnightAndBelongsToItsStartDayInCommunityTimezone(): void
    {
        // 23 h 30 à Paris (UTC+1 en novembre) jusqu'à 01 h 10 : 100 minutes, une seule session, jour de début.
        $start = (new \DateTimeImmutable('2026-11-10 23:30:00', new \DateTimeZone('Europe/Paris')))->getTimestamp();
        $sessions = S::buildSessions(self::beats($start, 100), self::GAP);
        self::assertCount(1, $sessions);
        self::assertSame(6000, $sessions[0]['duration_s']);
        $now = $start + 4 * 3600;
        $paris = S::summarize($sessions, 3600, $now, 'Europe/Paris');
        self::assertSame(1, $paris['streak_days']);
        // À Tokyo (UTC+9) le même début tombe le lendemain : le jour distinct change, pas le nombre de sessions.
        $tokyoDays = (new \DateTimeImmutable('@' . $start))->setTimezone(new \DateTimeZone('Asia/Tokyo'))->format('Y-m-d');
        $parisDays = (new \DateTimeImmutable('@' . $start))->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d');
        self::assertNotSame($tokyoDays, $parisDays);
        self::assertSame(1, S::summarize($sessions, 3600, $now, 'Asia/Tokyo')['windows'][7]['sessions']);
    }

    public function testWindowsDistinctDaysAndStreak(): void
    {
        $tz = 'Europe/Paris';
        $now = (new \DateTimeImmutable('2026-11-15 12:00:00', new \DateTimeZone($tz)))->getTimestamp();
        $list = [];
        // Sessions d'une heure et demie : aujourd'hui, hier, avant-hier (série de 3), puis il y a 10, 40 et 100 jours.
        foreach ([0, 1, 2, 10, 40, 100] as $ago) {
            $start = $now - $ago * 86400 - 3 * 3600;
            $list = array_merge($list, S::buildSessions(self::beats($start, 90), self::GAP));
        }
        // Deux sessions de 5 à 20 minutes qui ne comptent pas.
        $list = array_merge($list, S::buildSessions(self::beats($now - 5 * 86400 - 3600, 5), self::GAP), S::buildSessions(self::beats($now - 6 * 86400 - 7200, 20), self::GAP));
        $sum = S::summarize($list, 3600, $now, $tz);
        self::assertSame(3, $sum['windows'][7]['sessions']);
        self::assertSame(2, $sum['windows'][7]['short']);
        self::assertSame(4, $sum['windows'][30]['sessions']);
        self::assertSame(5, $sum['windows'][90]['sessions']);
        self::assertSame(3 * 5400, $sum['windows'][7]['seconds']);
        self::assertSame(3, $sum['windows'][7]['days']);
        self::assertSame(3, $sum['streak_days']);
        self::assertSame(6, $sum['qualifying_total']);
        self::assertSame(5400, $sum['last_qualifying']['duration_s']);
        self::assertTrue($sum['active']);
        self::assertSame(S::RELIABILITY_GOOD, $sum['reliability']);
    }

    public function testStreakBreaksWhenYesterdayAndTodayAreEmpty(): void
    {
        $tz = new \DateTimeZone('Europe/Paris');
        $now = (new \DateTimeImmutable('2026-11-15 12:00:00', $tz))->getTimestamp();
        self::assertSame(0, S::streak(['2026-11-12', '2026-11-11'], $now, $tz));
        self::assertSame(2, S::streak(['2026-11-14', '2026-11-13', '2026-11-10'], $now, $tz));
        self::assertSame(0, S::streak([], $now, $tz));
    }

    public function testMemberWithoutSessionAndMemberNeverLinked(): void
    {
        $now = self::T0;
        $none = S::summarize([], 3600, $now, 'Europe/Paris', true);
        self::assertSame(S::RELIABILITY_NO_DATA, $none['reliability']);
        self::assertFalse($none['has_data']);
        self::assertFalse($none['active']);
        self::assertNull($none['last_qualifying']);
        self::assertSame(0, $none['windows'][30]['sessions']);
        $unlinked = S::summarize([], 3600, $now, 'Europe/Paris', false);
        self::assertSame(S::RELIABILITY_NO_UID, $unlinked['reliability']);
    }

    public function testMemberWhoOnlyConnectedFiveMinutesIsNotActive(): void
    {
        $sessions = S::buildSessions(self::beats(self::T0, 5, 300), self::GAP, self::T0 + 3600);
        $sum = S::summarize($sessions, 3600, self::T0 + 3600, 'Europe/Paris');
        self::assertTrue($sum['has_data']);
        self::assertFalse($sum['active']);
        self::assertSame(0, $sum['windows'][7]['sessions']);
        self::assertSame(0, $sum['windows'][7]['seconds']);
        self::assertSame(1, $sum['windows'][7]['short']);
    }

    public function testReconstructedSessionsMakeTheDataPartial(): void
    {
        $s = S::buildSessions([
            ['at' => self::T0, 'type' => S::EVENT_CONNECT, 'source' => 'backfill'],
            ['at' => self::T0 + 7200, 'type' => S::EVENT_DISCONNECT, 'source' => 'backfill'],
        ], self::GAP);
        self::assertSame('backfill', $s[0]['source']);
        self::assertSame(S::RELIABILITY_PARTIAL, S::summarize($s, 3600, self::T0 + 8000)['reliability']);
    }

    public function testThresholdLabelShowsTheConfiguredValue(): void
    {
        self::assertSame('plus d’1 h', S::thresholdLabel(60));
        self::assertSame('plus de 30 min', S::thresholdLabel(30));
        self::assertSame('plus de 2 h', S::thresholdLabel(120));
        self::assertSame('plus d’1 h 30', S::thresholdLabel(90));
        self::assertSame('3 h 05', S::durationLabel(3 * 3600 + 300));
        self::assertSame('45 min', S::durationLabel(2700));
    }

    public function testInvalidTimezoneFallsBackToParis(): void
    {
        $s = S::buildSessions(self::beats(self::T0, 70), self::GAP);
        self::assertSame(1, S::summarize($s, 3600, self::T0 + 5000, 'Nulle/Part')['windows'][7]['sessions']);
    }
}
