<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakTrailLossBadgeAssetTest extends TestCase
{
    public function testLostLinkCrossIsASmallCornerBadgeNotAFullIcon(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-sse-layers.js');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-motion.css');
        $mapCss = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');

        self::assertStringContainsString("title=\"Perte de liaison\"", $js);
        self::assertStringContainsString('iconSize: [8, 8]', $js);
        self::assertStringContainsString('iconAnchor: [-3, 11]', $js);
        self::assertStringNotContainsString('iconSize: [18, 18]', $js);

        $lossFn = '';
        if (preg_match('/function lossIcon\(\) \{.*?\n  \}/s', $js, $m)) {
            $lossFn = $m[0];
        }
        self::assertNotSame('', $lossFn);
        self::assertStringNotContainsString('S.divIcon', $lossFn);
        self::assertStringNotContainsString('atak-marker-billboard', $lossFn);

        self::assertStringContainsString('font-size: 7px;', $css);
        self::assertStringContainsString('width: 8px;', $css);
        self::assertStringContainsString('height: 8px;', $css);
        self::assertStringNotContainsString('font-size: 15px;', $css);
        self::assertStringNotContainsString('width: 18px;', $css);

        self::assertStringContainsString('#atak-map .leaflet-div-icon.atak-trail-loss', $mapCss);
        self::assertStringNotContainsString('.atak-trail-loss .atak-marker-billboard', $mapCss);
    }
}
