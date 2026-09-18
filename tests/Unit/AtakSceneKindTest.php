<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakSceneKind;
use PHPUnit\Framework\TestCase;

final class AtakSceneKindTest extends TestCase
{
    public function testNormalizesLodZoomAliases(): void
    {
        self::assertSame(AtakSceneKind::LOD0, AtakSceneKind::normalizeLod('far'));
        self::assertSame(AtakSceneKind::LOD1, AtakSceneKind::normalizeLod('mid'));
        self::assertSame(AtakSceneKind::LOD2, AtakSceneKind::normalizeLod('near'));
        self::assertSame(AtakSceneKind::LOD3, AtakSceneKind::normalizeLod('proximity'));
    }

    public function testHumanNameHidesClassname(): void
    {
        self::assertSame('Maison 04', AtakSceneKind::humanName('Land_i_House_Small_04_V1_F', 'building', 'b:1'));
        self::assertSame('Mur', AtakSceneKind::humanName('Land_City_Wall_F', 'wall', 'o:1'));
        self::assertSame(3, AtakSceneKind::floors(8.9));
        self::assertSame('Masqué par un bâtiment', AtakSceneKind::losCause('building'));
        self::assertTrue(AtakSceneKind::isObstacle('fence'));
        $floors = AtakSceneKind::floorLabels(3);
        self::assertSame('RDC', $floors[0]['label']);
        self::assertSame('N+1', $floors[1]['label']);
        self::assertSame('N+2', $floors[2]['label']);
        self::assertSame('Toit', $floors[3]['label']);
    }
}
