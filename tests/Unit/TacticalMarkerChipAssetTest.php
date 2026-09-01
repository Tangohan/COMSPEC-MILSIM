<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TacticalMarkerChipAssetTest extends TestCase
{
    public function testChipFactoryMatchesAceStyleAndIsWiredOnMaps(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/tactical-marker-chip.js');
        $css = (string) file_get_contents($root . '/public/assets/css/tactical-marker-chip.css');
        $arma = (string) file_get_contents($root . '/public/assets/js/arma-map-markers.js');
        $map = (string) file_get_contents($root . '/public/assets/js/atak-map.js');
        $sitrep = (string) file_get_contents($root . '/public/assets/js/atak-sitrep.js');
        $opsMap = (string) file_get_contents($root . '/public/assets/js/comspec-operational-map.js');
        $atakView = (string) file_get_contents($root . '/views/atak.php');
        $tacmap = (string) file_get_contents($root . '/views/tacmap.php');
        $overwatch = (string) file_get_contents($root . '/views/overwatch/index.php');
        $dispatch = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');

        self::assertStringContainsString("SPOTREP: { color: '#e4b429', title: 'SPOTREP', labelFr: 'Observation'", $js);
        self::assertStringContainsString("IMINI: { color: '#e23b3b'", $js);
        self::assertStringContainsString('function formatElapsed', $js);
        self::assertStringContainsString('leafletDivIcon', $js);
        self::assertStringContainsString('.tactical-marker-chip__bar', $css);
        self::assertStringContainsString('.tactical-marker-chip__diamond', $css);
        self::assertStringContainsString('window.TacticalMarkerChip', $arma);
        self::assertStringContainsString('Chip.shouldUseChip', $arma);
        self::assertStringContainsString('pollTacticalReports', $map);
        self::assertStringContainsString('Chip.leafletDivIcon', $sitrep);
        self::assertStringContainsString('renderTacticalReportChips', $opsMap);
        self::assertStringContainsString('tactical-marker-chip.js', $atakView);
        self::assertStringContainsString('tactical-marker-chip.css', $atakView);
        self::assertStringContainsString('tactical-marker-chip.js', $tacmap);
        self::assertStringContainsString('tactical-marker-chip.js', $overwatch);
        self::assertStringContainsString('$pr(280,', $dispatch);
        self::assertStringContainsString('pastilles compactes', $dispatch);
        $chunk = '';
        if (preg_match('/\$pr\(280,.*?\$pr\(279,/s', $dispatch, $m)) {
            $chunk = strtolower($m[0]);
        }
        self::assertNotSame('', $chunk);
        self::assertStringNotContainsString('sqf', $chunk);
        self::assertStringNotContainsString('endpoint', $chunk);
        self::assertStringNotContainsString('json', $chunk);
    }

    public function testChipPreviewFixtureExists(): void
    {
        $root = dirname(__DIR__, 2);
        $fixture = $root . '/tmp-tactical-marker-chips-preview.html';
        self::assertFileExists($fixture);
        $html = (string) file_get_contents($fixture);
        self::assertStringContainsString('SPOTREP', $html);
        self::assertStringContainsString('IMINI', $html);
        self::assertStringContainsString('tactical-marker-chip.css', $html);
        self::assertStringNotContainsString('endpoint', $html);
        self::assertStringNotContainsString('JSON', $html);
    }
}
