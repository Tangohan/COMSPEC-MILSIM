<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakGeoRoadLabelAssetTest extends TestCase
{
    public function testGeoNetworkSupportsOperatorRoadLabels(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/atak-geo-network.js');
        $css = (string) file_get_contents($root . '/public/assets/css/atak.css');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/AtakGeoRoadRepository.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Api/AtakGeoNetworkApiController.php');

        self::assertStringContainsString('Nom de cette route', $js);
        self::assertStringContainsString('/atak/geo/roads/label', $js);
        self::assertStringContainsString('roadStyle', $js);
        self::assertStringContainsString('HIGHWAY', $js);
        self::assertStringContainsString('ATAKContextMenu.openPrompt', $js);
        self::assertStringContainsString('atak-geo-road-label', $css);
        self::assertStringContainsString('atak-geo-place-label', $css);
        self::assertStringContainsString('/api/atak/geo/roads/label', $routes);
        self::assertStringContainsString('roadsUpdateLabel', $routes);
        self::assertStringContainsString('operator_label', $repo);
        self::assertStringContainsString('updateOperatorLabel', $repo);
        self::assertStringContainsString('Ne jamais écraser operator_label', $repo);
        self::assertStringContainsString('function roadsUpdateLabel', $ctrl);
    }
}
