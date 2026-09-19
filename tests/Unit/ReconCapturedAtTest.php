<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ReconCapturedAt;
use PHPUnit\Framework\TestCase;

final class ReconCapturedAtTest extends TestCase
{
    public function testMissionElapsedSecondsBecomeNow(): void
    {
        $now = 1_726_747_200;
        self::assertSame($now, ReconCapturedAt::unixFromPosted(298306, $now));
        self::assertSame($now, ReconCapturedAt::unixFromPosted('61306', $now));
        self::assertSame($now, ReconCapturedAt::unixFromPosted(0, $now));
        self::assertSame($now, ReconCapturedAt::unixFromPosted(null, $now));
    }

    public function testValidUnixIsKept(): void
    {
        $now = 1_726_747_200;
        self::assertSame(1_726_740_000, ReconCapturedAt::unixFromPosted(1_726_740_000, $now));
        self::assertSame(1_726_740_000, ReconCapturedAt::unixFromPosted(1_726_740_000_000, $now));
    }

    public function testDisplayFallsBackWhenYearIs1970(): void
    {
        $row = [
            'captured_at' => '1970-01-04 10:51:46',
            'created_at' => '2026-09-19 12:20:00',
        ];
        self::assertTrue(ReconCapturedAt::isEpochEra('1970-01-04 10:51:46'));
        self::assertSame('2026-09-19 12:20:00', ReconCapturedAt::displayFromRow($row));
        self::assertSame('2026-09-19 12:20:00', ReconCapturedAt::displayFromRow([
            'captured_at' => '2026-09-19 12:20:00',
            'created_at' => '2026-09-19 12:00:00',
        ]));
    }

    public function testSqlDateTimeNeverWrites1970FromMissionTime(): void
    {
        $now = 1_726_747_200;
        self::assertSame(date('Y-m-d H:i:s', $now), ReconCapturedAt::sqlDateTime(169695, $now));
    }
}
