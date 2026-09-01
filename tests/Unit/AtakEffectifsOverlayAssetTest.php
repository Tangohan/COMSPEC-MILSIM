<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakEffectifsOverlayAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testC2ShellStopsAboveEffectifsDrawer(): void
    {
        $css = (string) file_get_contents($this->root() . '/public/assets/css/atak-map-c2-live.css');

        self::assertStringContainsString('bottom: var(--atak-effectifs-drawer-h, 250px)', $css);
        self::assertStringContainsString('z-index: 40', $css);
        self::assertStringContainsString('.atak-map-wrap > .atak-drawer', $css);
        self::assertStringContainsString('body.atak-drawer-collapsed #atak-c2-live-shell', $css);
        self::assertStringContainsString('bottom: 38px', $css);
        self::assertStringNotContainsString('inset: 0', $css);
    }

    public function testAnalysisJournalStartsCollapsedAndCompact(): void
    {
        $view = (string) file_get_contents($this->root() . '/views/atak.php');
        $css = (string) file_get_contents($this->root() . '/public/assets/css/atak-map-c2-live.css');

        self::assertStringContainsString('<details class="atak-timeline" id="atak-intel-timeline">', $view);
        self::assertStringNotContainsString('id="atak-intel-timeline" open', $view);
        self::assertStringContainsString('max-height: 9.5rem', $css);
        self::assertStringContainsString('.atak-timeline__list', $css);
    }

    public function testUnitesRailRevealsEffectifsDrawerInsteadOfCop(): void
    {
        $bridge = (string) file_get_contents($this->root() . '/public/assets/js/map/atak-c2-bridge.js');
        $view = (string) file_get_contents($this->root() . '/views/atak.php');

        self::assertStringContainsString('data-tool="layers"', $view);
        self::assertStringContainsString('>Unités</span>', $view);
        self::assertStringContainsString('id="atak-effectifs-drawer"', $view);
        self::assertStringContainsString('function revealEffectifsDrawer()', $bridge);
        self::assertStringContainsString("if (tool === 'layers')", $bridge);
        self::assertStringContainsString('setDrawerOpen(true)', $bridge);
        self::assertStringContainsString('layers: null', $bridge);
        self::assertStringNotContainsString("layers: 'cop'", $bridge);
    }
}
