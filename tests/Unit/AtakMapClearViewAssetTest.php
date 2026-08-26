<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMapClearViewAssetTest extends TestCase
{
    public function testToolbarOffersAHumanClearViewAction(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $tools = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map-tools.js');

        self::assertStringContainsString('data-tool="clear-view"', $view);
        $button = '';
        if (preg_match('/<button[^>]*data-tool="clear-view"[^>]*>.*?<\/button>/s', $view, $m)) {
            $button = $m[0];
        }
        self::assertStringContainsString('Tout dégager', $button);
        self::assertStringContainsString('Les contacts encore en liaison restent', $button);
        self::assertStringNotContainsString('endpoint', $button);
        self::assertStringNotContainsString('JSON', $button);
        self::assertStringContainsString("{ id: 'clear-view', label: 'Tout dégager' }", $tools);
        self::assertStringContainsString("nav: ['goto', 'follow', 'clear-view']", $tools);
        self::assertStringContainsString('function clearViewOverlays()', $tools);
        self::assertStringContainsString('Les contacts encore en liaison restent affichés.', $tools);
    }

    public function testClearViewRemovesOverlaysWithoutLiveContacts(): void
    {
        $tools = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map-tools.js');
        $sse = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-sse-layers.js');
        $map = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map.js');
        $motion = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-motion-map.js');
        $timeline = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-intel-timeline.js');

        self::assertStringContainsString('ATAKMapShapes.clearAllDrawings', $tools);
        self::assertStringContainsString('ATAKSseLayers.clearTrails', $tools);
        self::assertStringContainsString('ATAKIntelTimeline.clearView', $tools);
        self::assertStringContainsString('ATAKUnitDossier.close', $tools);
        self::assertStringContainsString('ATAKMap.clearTemporaryPings', $tools);
        self::assertStringContainsString('function clearTrails()', $sse);
        self::assertStringContainsString('function clearTemporaryPings()', $map);
        self::assertStringContainsString('function clearTrails()', $motion);
        self::assertStringContainsString('function clearView()', $timeline);
        self::assertStringNotContainsString('setUnitsMarkers([])', $tools);
        self::assertStringNotContainsString('setAirAssets([])', $tools);
    }
}
