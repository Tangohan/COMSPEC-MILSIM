<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakPoMarker;
use PHPUnit\Framework\TestCase;

final class AtakPoMarkerTest extends TestCase
{
    public function testRecognizesObjectiveLabelsAndIgnoresLookalikes(): void
    {
        self::assertTrue(AtakPoMarker::isPoLabel('PO'));
        self::assertTrue(AtakPoMarker::isPoLabel('po'));
        self::assertTrue(AtakPoMarker::isPoLabel('PO1'));
        self::assertTrue(AtakPoMarker::isPoLabel('PO 2'));
        self::assertTrue(AtakPoMarker::isPoLabel('PO-3'));
        self::assertTrue(AtakPoMarker::isPoLabel('PO_04'));
        self::assertTrue(AtakPoMarker::isPoLabel('PO ALPHA'));
        self::assertFalse(AtakPoMarker::isPoLabel(''));
        self::assertFalse(AtakPoMarker::isPoLabel('POI'));
        self::assertFalse(AtakPoMarker::isPoLabel('POSTE'));
        self::assertFalse(AtakPoMarker::isPoLabel('POINT'));
        self::assertFalse(AtakPoMarker::isPoLabel('PORT'));
    }

    public function testReadsWorldPositionAndDefaultRadius(): void
    {
        $fromPos = AtakPoMarker::worldPosition(['pos' => [1840.5, 2210.25]]);
        self::assertNotNull($fromPos);
        self::assertEqualsWithDelta(1840.5, $fromPos['x'], 0.01);
        self::assertEqualsWithDelta(2210.25, $fromPos['y'], 0.01);

        $fromXy = AtakPoMarker::worldPosition(['pos_x' => 100, 'pos_y' => 200]);
        self::assertSame(['x' => 100.0, 'y' => 200.0], $fromXy);

        self::assertNull(AtakPoMarker::worldPosition(['pos' => [0, 0]]));
        self::assertSame(20.0, AtakPoMarker::radiusM([]));
        self::assertEqualsWithDelta(20.0, AtakPoMarker::distanceM(0, 0, 12, 16), 0.01);
        self::assertTrue(AtakPoMarker::distanceM(0, 0, 12, 16) <= AtakPoMarker::RADIUS_M);
        self::assertFalse(AtakPoMarker::distanceM(0, 0, 21, 0) <= AtakPoMarker::RADIUS_M);
    }

    public function testPreserveReachedKeepsConfirmationWhenMarkerIsRepublished(): void
    {
        $previous = AtakPoMarker::withReached(['text' => 'PO 1', 'pos' => [10, 20]], 'RAVEN', 8.4);
        $incoming = ['text' => 'PO 1', 'pos' => [11, 21], 'source' => 'arma'];
        $merged = AtakPoMarker::preserveReached($incoming, $previous);
        self::assertTrue($merged['reached']);
        self::assertSame('RAVEN', $merged['reached_by']);
        self::assertTrue(!empty($merged['po']));
        self::assertSame(20.0, $merged['po_radius_m']);
    }
}
