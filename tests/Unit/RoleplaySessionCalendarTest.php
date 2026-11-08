<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RoleplaySessionCalendar;
use PHPUnit\Framework\TestCase;

final class RoleplaySessionCalendarTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function sessions(): array
    {
        return [
            ['id' => 5, 'mission_name' => 'Dague', 'planned_starts_at' => '2026-11-10 20:00:00', 'status' => 'planned', 'community_event_id' => null],
            ['id' => 6, 'mission_name' => 'Liée', 'planned_starts_at' => '2026-11-12 20:00:00', 'status' => 'planned', 'community_event_id' => 9],
            ['id' => 7, 'mission_name' => 'Finie', 'started_at' => '2026-11-02 20:00:00', 'status' => 'closed', 'community_event_id' => null],
        ];
    }

    public function testSessionWithoutEventGetsItsOwnEntryAndLinkedOneMarksTheEvent(): void
    {
        $out = RoleplaySessionCalendar::merge(
            [['id' => 9, 'title' => 'Op', 'starts_at' => '2026-11-12 20:00:00']],
            $this->sessions()
        );

        self::assertSame(['Finie', 'Dague', 'Op'], array_column($out, 'title'));
        self::assertSame(6, $out[2]['linked_session_id']);
        self::assertSame(RoleplaySessionCalendar::TYPE, $out[0]['calendar_kind']);
    }

    public function testTypeFilterSeparatesEventsAndSessions(): void
    {
        $events = [['id' => 9, 'title' => 'Op', 'starts_at' => '2026-11-12 20:00:00']];

        self::assertSame(['Finie', 'Dague'], array_column(RoleplaySessionCalendar::merge($events, $this->sessions(), ['type' => 'session']), 'title'));
        self::assertSame(['Op'], array_column(RoleplaySessionCalendar::merge($events, $this->sessions(), ['type' => 'operation']), 'title'));
    }

    public function testStatusAndSearchApplyToSessions(): void
    {
        self::assertSame(['Finie'], array_column(RoleplaySessionCalendar::merge([], $this->sessions(), ['statut' => 'clos']), 'title'));
        self::assertSame([], RoleplaySessionCalendar::merge([], $this->sessions(), ['statut' => 'annule']));
        self::assertSame(['Dague'], array_column(RoleplaySessionCalendar::merge([], $this->sessions(), ['q' => 'dag']), 'title'));
    }
}
