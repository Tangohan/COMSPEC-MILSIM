<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakSceneBounds;
use PHPUnit\Framework\TestCase;

final class AtakSceneBoundsTest extends TestCase
{
    public function testSanitizeClampsGiantHitboxesAndKeepsReason(): void
    {
        $box = AtakSceneBounds::sanitize([
            'width' => 320,
            'depth' => 12,
            'height' => 280,
        ]);
        self::assertSame(AtakSceneBounds::MAX_WIDTH, $box['width']);
        self::assertSame(12.0, $box['depth']);
        self::assertSame(AtakSceneBounds::MAX_HEIGHT, $box['height']);
        self::assertTrue($box['clipped']);
        self::assertNotSame([], $box['reasons']);
        self::assertStringContainsString('width:320', implode(',', $box['reasons']));
        self::assertStringContainsString('height:280', implode(',', $box['reasons']));
        self::assertSame(AtakSceneBounds::SHAPE_BUILDING, $box['shape']);
    }

    public function testSanitizeFillsMissingEdges(): void
    {
        $box = AtakSceneBounds::sanitize([]);
        self::assertFalse($box['clipped']);
        self::assertGreaterThanOrEqual(AtakSceneBounds::MIN_EDGE, $box['width']);
        self::assertGreaterThanOrEqual(AtakSceneBounds::MIN_HEIGHT, $box['height']);
        self::assertSame(AtakSceneBounds::SHAPE_BUILDING, $box['shape']);
    }

    public function testSanitizeLowersThinSlabHeights(): void
    {
        $box = AtakSceneBounds::sanitize([
            'width' => 48,
            'depth' => 2.2,
            'height' => 12,
        ]);
        self::assertLessThanOrEqual(2.6, $box['height']);
        self::assertTrue($box['clipped']);
        self::assertContains($box['shape'], [
            AtakSceneBounds::SHAPE_RIBBON,
            AtakSceneBounds::SHAPE_BUILDING,
        ]);
    }

    public function testShapeClassDetectsPolesAndRibbons(): void
    {
        self::assertSame(AtakSceneBounds::SHAPE_POLE, AtakSceneBounds::shapeClass(0.6, 0.6, 8.0));
        self::assertSame(AtakSceneBounds::SHAPE_RIBBON, AtakSceneBounds::shapeClass(12.0, 0.8, 2.5));
        self::assertSame(AtakSceneBounds::SHAPE_PANEL, AtakSceneBounds::shapeClass(1.5, 0.4, 3.5));
        self::assertSame(AtakSceneBounds::SHAPE_BUILDING, AtakSceneBounds::shapeClass(10.0, 8.0, 6.0));
    }

    public function testSanitizeObstacleKeepsWallsLowAndPolesThin(): void
    {
        $wall = AtakSceneBounds::sanitizeObstacle([
            'kind' => 'wall',
            'width' => 40,
            'depth' => 0.5,
            'height' => 9,
        ]);
        self::assertLessThanOrEqual(2.6, $wall['height']);
        self::assertLessThanOrEqual(1.2, $wall['depth']);

        $pole = AtakSceneBounds::sanitizeObstacle([
            'kind' => 'pylon',
            'width' => 0.5,
            'depth' => 0.5,
            'height' => 12,
        ]);
        self::assertSame(AtakSceneBounds::SHAPE_POLE, $pole['shape']);
        self::assertLessThanOrEqual(1.2, $pole['width']);
    }
}
