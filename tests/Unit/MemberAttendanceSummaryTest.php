<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\MemberAttendanceSummary;
use PHPUnit\Framework\TestCase;

/** Présence aux sessions : classement d’une réponse, carré des 26 sessions, taux sur 90 jours. */
final class MemberAttendanceSummaryTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-01 12:00:00');
    }

    public function testClassifiesAnswersWithoutInventingPresence(): void
    {
        self::assertSame(MemberAttendanceSummary::NO_REPLY, MemberAttendanceSummary::classify(null));
        self::assertSame(MemberAttendanceSummary::PRESENT, MemberAttendanceSummary::classify(['status' => 'no', 'checked_in_at' => '2026-10-01 20:00:00']));
        self::assertSame(MemberAttendanceSummary::EXCUSED, MemberAttendanceSummary::classify(['status' => 'no', 'absence_reason' => 'sante']));
        self::assertSame(MemberAttendanceSummary::ABSENT, MemberAttendanceSummary::classify(['status' => 'no', 'absence_reason' => 'absence_non_justifiee']));
        self::assertSame(MemberAttendanceSummary::ABSENT, MemberAttendanceSummary::classify(['status' => 'yes', 'checked_in_at' => null]));
        self::assertSame(MemberAttendanceSummary::NO_REPLY, MemberAttendanceSummary::classify(['status' => 'maybe']));
    }

    public function testGridKeepsTheLast26SessionsAfterEngagementOldestFirst(): void
    {
        $events = [];
        $rsvps = [];
        for ($i = 1; $i <= 40; $i++) {
            $events[] = ['id' => $i, 'starts_at' => $this->now->modify('-' . $i . ' days')->format('Y-m-d H:i:s'), 'title' => 'Op ' . $i];
            $rsvps[$i] = ['status' => 'yes', 'checked_in_at' => $i % 2 === 0 ? '2026-01-01 00:00:00' : null];
        }
        // Session future et session antérieure à l’engagement : jamais comptées.
        $events[] = ['id' => 99, 'starts_at' => $this->now->modify('+2 days')->format('Y-m-d H:i:s')];

        $out = MemberAttendanceSummary::summarize($events, $rsvps, $this->now->modify('-30 days')->format('Y-m-d'), $this->now);

        self::assertCount(26, $out['grid']);
        self::assertSame(26, $out['grid'][0]['event_id'], 'la plus ancienne session retenue ouvre le carré');
        self::assertSame(1, $out['grid'][25]['event_id'], 'la plus récente le ferme');
        self::assertSame(13, $out['grid_counts']['present']);
        self::assertSame(13, $out['grid_counts']['absent']);
        self::assertSame(30, $out['sessions_90'], 'les sessions avant l’engagement sont ignorées');
        self::assertSame(15, $out['present_90']);
        self::assertSame(50, $out['rate_90']);
        self::assertSame(15, $out['present_total']);
    }

    public function testRateComparesWithThePreviousQuarter(): void
    {
        $events = [
            ['id' => 1, 'starts_at' => $this->now->modify('-10 days')->format('Y-m-d H:i:s')],
            ['id' => 2, 'starts_at' => $this->now->modify('-20 days')->format('Y-m-d H:i:s')],
            ['id' => 3, 'starts_at' => $this->now->modify('-100 days')->format('Y-m-d H:i:s')],
            ['id' => 4, 'starts_at' => $this->now->modify('-120 days')->format('Y-m-d H:i:s')],
        ];
        $rsvps = [
            1 => ['status' => 'yes', 'checked_in_at' => 'x'],
            2 => ['status' => 'yes', 'checked_in_at' => 'x'],
            3 => ['status' => 'yes', 'checked_in_at' => 'x'],
        ];
        $out = MemberAttendanceSummary::summarize($events, $rsvps, null, $this->now);

        self::assertSame(100, $out['rate_90']);
        self::assertSame(50, $out['rate_prev_90']);
        self::assertSame(50, $out['delta_points']);
    }

    public function testNoSessionGivesNoRate(): void
    {
        $out = MemberAttendanceSummary::summarize([], [], '2026-10-01', $this->now);

        self::assertSame([], $out['grid']);
        self::assertNull($out['rate_90']);
        self::assertNull($out['delta_points']);
    }
}
