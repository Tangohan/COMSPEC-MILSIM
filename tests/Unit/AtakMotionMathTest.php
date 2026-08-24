<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakMotionMath;
use PHPUnit\Framework\TestCase;

final class AtakMotionMathTest extends TestCase
{
    public function testCircularMeanWrapsAroundNorth(): void
    {
        $mean = AtakMotionMath::circularMeanDeg([359.0, 1.0, 0.0]);
        self::assertNotNull($mean);
        self::assertEqualsWithDelta(0.0, $mean, 2.0);
    }

    public function testCircularDeltaNeverReadsAsFullTurn(): void
    {
        $delta = AtakMotionMath::circularDeltaDeg(359.0, 1.0);
        self::assertEqualsWithDelta(2.0, $delta, 0.01);
        self::assertEqualsWithDelta(-2.0, AtakMotionMath::circularDeltaDeg(1.0, 359.0), 0.01);
    }

    public function testLerpHeadingCrossesNorth(): void
    {
        $smoothed = AtakMotionMath::lerpHeading(350.0, 10.0, 0.5);
        self::assertEqualsWithDelta(0.0, $smoothed, 1.0);
    }

    public function testHeadingFromEastwardDelta(): void
    {
        self::assertEqualsWithDelta(90.0, AtakMotionMath::headingFromDelta(10.0, 0.0), 0.5);
        self::assertEqualsWithDelta(0.0, AtakMotionMath::headingFromDelta(0.0, 10.0), 0.5);
    }

    public function testInfantryStaticVsVehicleFast(): void
    {
        self::assertSame(AtakMotionMath::STATUS_STATIC, AtakMotionMath::classifyStatus(AtakMotionMath::CAT_INFANTRY, 0.05));
        self::assertSame(AtakMotionMath::STATUS_MOVING, AtakMotionMath::classifyStatus(AtakMotionMath::CAT_INFANTRY, 1.2));
        self::assertSame(AtakMotionMath::STATUS_FAST, AtakMotionMath::classifyStatus(AtakMotionMath::CAT_GROUND_VEHICLE, 20.0));
        self::assertSame(AtakMotionMath::STATUS_MOVING, AtakMotionMath::classifyStatus(AtakMotionMath::CAT_FIXED_WING, 50.0));
        self::assertSame(AtakMotionMath::STATUS_FAST, AtakMotionMath::classifyStatus(AtakMotionMath::CAT_FIXED_WING, 200.0));
    }

    public function testPathUsesSeveralSamplesNotJustLastTwo(): void
    {
        $base = 1_700_000_000;
        $samples = [
            ['x' => 1000.0, 'y' => 2000.0, 't' => $base + 0],
            ['x' => 1000.4, 'y' => 2000.2, 't' => $base + 3],
            ['x' => 1008.0, 'y' => 2002.5, 't' => $base + 6],
            ['x' => 1016.0, 'y' => 2005.0, 't' => $base + 9],
            ['x' => 1024.0, 'y' => 2007.5, 't' => $base + 12],
        ];
        $out = AtakMotionMath::compute($samples, ['platform' => 'INFANTRY']);
        self::assertNotNull($out['movement_heading']);
        self::assertGreaterThan(0.4, $out['confidence']);
        self::assertSame(AtakMotionMath::STATUS_MOVING, $out['motion_status']);
        self::assertGreaterThan(1.0, $out['speed_ms'] ?? 0);
    }

    public function testEtaDirectSmoothsJumps(): void
    {
        $first = AtakMotionMath::etaDirect(3420.0, 12.8, AtakMotionMath::CAT_GROUND_VEHICLE);
        self::assertFalse($first['arrived']);
        self::assertNotNull($first['seconds']);
        $jump = AtakMotionMath::etaDirect(3420.0, 30.0, AtakMotionMath::CAT_GROUND_VEHICLE, (float) $first['seconds']);
        self::assertNotNull($jump['seconds']);
        self::assertGreaterThan((int) $first['seconds'] * 0.5, $jump['seconds']);
        self::assertLessThan((int) $first['seconds'] + 5, $jump['seconds']);
    }

    public function testArrivedWhenInsideRadius(): void
    {
        $eta = AtakMotionMath::etaDirect(20.0, 2.0, AtakMotionMath::CAT_INFANTRY);
        self::assertTrue($eta['arrived']);
        self::assertSame(0, $eta['seconds']);
        self::assertSame(
            AtakMotionMath::COURSE_ARRIVED,
            AtakMotionMath::courseStatus(90.0, 90.0, 2.0, 20.0, AtakMotionMath::CAT_INFANTRY)
        );
    }

    public function testCourseOnCourseVersusDiverging(): void
    {
        self::assertSame(
            AtakMotionMath::COURSE_ON_COURSE,
            AtakMotionMath::courseStatus(270.0, 275.0, 8.0, 400.0, AtakMotionMath::CAT_GROUND_VEHICLE)
        );
        self::assertSame(
            AtakMotionMath::COURSE_DIVERGING,
            AtakMotionMath::courseStatus(90.0, 275.0, 8.0, 400.0, AtakMotionMath::CAT_GROUND_VEHICLE)
        );
    }

    public function testInterceptPointIsReachable(): void
    {
        $hit = AtakMotionMath::interceptPoint(0.0, 0.0, 90.0, 10.0, 0.0, 200.0, 20.0);
        self::assertNotNull($hit);
        self::assertArrayHasKey('x', $hit);
        self::assertArrayHasKey('t', $hit);
        self::assertGreaterThan(0.0, $hit['t']);
    }

    public function testEtaKindIsDirectByDefault(): void
    {
        $eta = AtakMotionMath::etaDirect(1000.0, 10.0, AtakMotionMath::CAT_INFANTRY);
        self::assertSame(AtakMotionMath::ETA_DIRECT, $eta['kind']);
    }

    public function testReachGrowsWithSilenceThenCaps(): void
    {
        $phoneWalk = AtakMotionMath::reachRadiusM(AtakMotionMath::CAT_INFANTRY, 60.0, null, true);
        self::assertGreaterThan(80.0, $phoneWalk);
        self::assertLessThan(700.0, $phoneWalk);

        $tooSoon = AtakMotionMath::reachRadiusM(AtakMotionMath::CAT_INFANTRY, 3.0, null, true);
        self::assertSame(0.0, $tooSoon);

        $vehicle = AtakMotionMath::reachRadiusM(AtakMotionMath::CAT_GROUND_VEHICLE, 120.0, 20.0, false);
        self::assertGreaterThan($phoneWalk, $vehicle);
        self::assertLessThanOrEqual(3500.0, $vehicle);
    }

    public function testTrailMarksUncertainGaps(): void
    {
        $base = 1_700_000_000;
        $samples = [
            ['x' => 1000.0, 'y' => 2000.0, 't' => $base + 0],
            ['x' => 1010.0, 'y' => 2000.0, 't' => $base + 5],
            ['x' => 1300.0, 'y' => 2000.0, 't' => $base + 40],
        ];
        $out = AtakMotionMath::compute($samples, ['platform' => 'GROUND_VEHICLE', 'speed_ms' => 8]);
        $trail = $out['trail'];
        self::assertGreaterThanOrEqual(2, count($trail));
        $last = $trail[count($trail) - 1];
        self::assertTrue(!empty($last['gap']) || !empty($last['uncertain']));
    }
}
