<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DiscordEventVotes;
use PHPUnit\Framework\TestCase;

final class DiscordEventVotesTest extends TestCase
{
    public function testRemovingReactionPreservesWebsiteCorrectionsAndActualAttendance(): void
    {
        $rsvp = ['status' => 'yes', 'absence_note' => null, 'checked_in_at' => null, 'updated_at' => '2026-11-06 20:00:00'];
        $previous = ['reaction_status' => 'yes', 'rsvp_fingerprint' => DiscordEventVotes::fingerprint($rsvp)];
        self::assertTrue(DiscordEventVotes::canClear($rsvp, $previous));
        $corrected = array_merge($rsvp, ['absence_note' => 'Website correction in the same second']);
        self::assertFalse(DiscordEventVotes::canClear($corrected, $previous));
        self::assertFalse(DiscordEventVotes::canClear(array_merge($rsvp, ['checked_in_at' => '2026-11-06 21:00:00']), $previous));
        self::assertFalse(DiscordEventVotes::canClear(null, $previous));
        self::assertSame(DiscordEventVotes::fingerprint($rsvp), DiscordEventVotes::fingerprint(array_reverse($rsvp, true)));
    }

    public function testVotesUseSnowflakesAsStringsAndRejectAmbiguousChoices(): void
    {
        self::assertSame([
            'discord:123456789012345678' => 'yes',
            'discord:223456789012345678' => 'conflict',
            'discord:323456789012345678' => 'maybe',
        ], DiscordEventVotes::resolve([
            'yes' => ['123456789012345678', '123456789012345678', '223456789012345678'],
            'no' => ['223456789012345678'],
            'maybe' => ['223456789012345678', '323456789012345678'],
        ]));
        self::assertSame([], DiscordEventVotes::resolve([]));
    }
}
