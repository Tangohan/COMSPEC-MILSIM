<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakBriefingPlanRepository;
use PHPUnit\Framework\TestCase;

final class AtakBriefingPlanRepositoryTest extends TestCase
{
    public function testNormalizeKeepsKnownKindsAndBoundsValues(): void
    {
        $items = AtakBriefingPlanRepository::normalizeItems([
            ['id' => 's1', 'kind' => 'obj', 'pts' => [[100.04, 200], [300, 400.5]], 'text' => "OBJ\nALPHA", 'color' => 'yellow', 'width' => 9, 'num' => -3],
            ['id' => 's2', 'kind' => 'HACK', 'pts' => [[1, 2]]],
            ['id' => 's3', 'kind' => 'NOTE', 'pts' => [['x', 2]]],
            'pas un élément',
        ]);
        self::assertCount(1, $items);
        self::assertSame('OBJ', $items[0]['kind']);
        self::assertSame([[100.0, 200.0], [300.0, 400.5]], $items[0]['pts']);
        self::assertSame('OBJ ALPHA', $items[0]['text']);
        self::assertSame('YELLOW', $items[0]['color']);
        self::assertSame(3, $items[0]['width']);
        self::assertSame(0, $items[0]['num']);
    }

    public function testUnknownColorFallsBackToWhite(): void
    {
        $items = AtakBriefingPlanRepository::normalizeItems([['kind' => 'PL', 'pts' => [[0, 0], [10, 10]], 'color' => '#ff0000']]);
        self::assertSame('WHITE', $items[0]['color']);
    }
}
