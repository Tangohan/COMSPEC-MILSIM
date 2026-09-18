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
    }

    public function testSanitizeFillsMissingEdges(): void
    {
        $box = AtakSceneBounds::sanitize([]);
        self::assertFalse($box['clipped']);
        self::assertGreaterThanOrEqual(AtakSceneBounds::MIN_EDGE, $box['width']);
        self::assertGreaterThanOrEqual(AtakSceneBounds::MIN_HEIGHT, $box['height']);
    }
}
