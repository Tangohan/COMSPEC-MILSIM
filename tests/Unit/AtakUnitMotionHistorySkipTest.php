<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakUnitMotionService;
use PHPUnit\Framework\TestCase;

final class AtakUnitMotionHistorySkipTest extends TestCase
{
    public function testFirstSampleIsNeverSkipped(): void
    {
        self::assertFalse(AtakUnitMotionService::shouldSkipHistorySample(
            null,
            null,
            null,
            100.0,
            200.0,
            ['telemetry_kind' => 'heartbeat'],
            1_700_000_000
        ));
    }

    public function testHeartbeatWithoutMoveIsSkipped(): void
    {
        self::assertTrue(AtakUnitMotionService::shouldSkipHistorySample(
            10500.0,
            15400.0,
            1_700_000_000,
            10500.4,
            15400.6,
            ['telemetry_kind' => 'heartbeat', 'history_sample_min' => 15],
            1_700_000_045
        ));
    }

    public function testSignificantMoveIsRecordedEvenOnHeartbeat(): void
    {
        self::assertFalse(AtakUnitMotionService::shouldSkipHistorySample(
            10500.0,
            15400.0,
            1_700_000_000,
            10520.0,
            15400.0,
            ['telemetry_kind' => 'heartbeat'],
            1_700_000_010
        ));
    }

    public function testDensePositionSamplesAreCoalescedUntilGap(): void
    {
        self::assertTrue(AtakUnitMotionService::shouldSkipHistorySample(
            100.0,
            100.0,
            1_700_000_000,
            102.0,
            100.0,
            ['telemetry_kind' => 'position', 'history_sample_min' => 15],
            1_700_000_005
        ));
        self::assertFalse(AtakUnitMotionService::shouldSkipHistorySample(
            100.0,
            100.0,
            1_700_000_000,
            102.0,
            100.0,
            ['telemetry_kind' => 'position', 'history_sample_min' => 15],
            1_700_000_020
        ));
    }
}
