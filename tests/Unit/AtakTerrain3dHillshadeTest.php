<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakTerrain3dHillshadeTest extends TestCase
{
    public function testWebglShadingTemporarilyHidesTheRasterHillshade(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain-3d.js');

        self::assertStringContainsString("map.getPane('atakHillshadePane')", $javascript);
        self::assertStringContainsString(
            "state.enabled && stage.classList.contains('atak-terrain-mesh-ready') ? '0' : ''",
            $javascript
        );
        self::assertStringContainsString("stage.classList.remove('atak-terrain-mesh-ready')", $javascript);
    }
}
