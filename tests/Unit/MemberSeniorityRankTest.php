<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\MemberSeniorityRank;
use PHPUnit\Framework\TestCase;

/** Rang « membre N / M » : classement par date d’engagement réelle parmi les membres actifs. */
final class MemberSeniorityRankTest extends TestCase
{
    public function testOldestEngagementComesFirstAndTotalCountsAllActiveMembers(): void
    {
        $ids = [10, 11, 12, 13, 14];
        $dates = [10 => '2024-05-01', 11 => '2022-01-15', 12 => '2025-09-30', 13 => '2024-05-01'];

        self::assertSame(['rank' => 1, 'total' => 5], MemberSeniorityRank::rank(11, $ids, $dates));
        self::assertSame(['rank' => 2, 'total' => 5], MemberSeniorityRank::rank(10, $ids, $dates));
        self::assertSame(['rank' => 3, 'total' => 5], MemberSeniorityRank::rank(13, $ids, $dates), 'à date égale, le plus petit numéro de compte passe devant');
        self::assertSame(['rank' => 4, 'total' => 5], MemberSeniorityRank::rank(12, $ids, $dates));
    }

    public function testNoRankWithoutDateOrOutsideTheActiveMembersOfTheCommunity(): void
    {
        $ids = [10, 11];
        $dates = [10 => '2024-05-01', 11 => '2023-01-01', 99 => '2010-01-01'];

        self::assertNull(MemberSeniorityRank::rank(14, [10, 14], $dates), 'sans date d’engagement, pas de rang affiché');
        self::assertNull(MemberSeniorityRank::rank(99, $ids, $dates), 'un membre d’une autre communauté n’est pas classé');
        self::assertSame(['rank' => 2, 'total' => 2], MemberSeniorityRank::rank(10, $ids, $dates), 'les dates hors communauté sont ignorées');
    }

    public function testEngagementDateIsTheOldestKnownDate(): void
    {
        self::assertSame('2019-03-02', MemberSeniorityRank::engagementDate('2024-01-10', '2019-03-02'));
        self::assertSame('2024-01-10', MemberSeniorityRank::engagementDate('2024-01-10 08:00:00', ''));
        self::assertSame('', MemberSeniorityRank::engagementDate('0000-00-00', null));
    }
}
