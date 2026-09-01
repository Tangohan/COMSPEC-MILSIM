<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMapToolbarPersistAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testC2LiveDoesNotAutoHideClassicToolbar(): void
    {
        $bridge = (string) file_get_contents($this->root() . '/public/assets/js/map/atak-c2-bridge.js');
        $liveCss = (string) file_get_contents($this->root() . '/public/assets/css/atak-map-c2-live.css');
        $v2Css = (string) file_get_contents($this->root() . '/public/assets/css/atak-map-c2-v2.css');
        $view = (string) file_get_contents($this->root() . '/views/atak.php');

        self::assertStringNotContainsString('hideLegacyToolbarChrome', $bridge);
        self::assertStringContainsString('keepLegacyToolbarChrome', $bridge);
        self::assertStringNotContainsString("classList.add('atak-map-tools--c2-legacy')", $bridge);
        self::assertStringContainsString("classList.remove('atak-map-tools--c2-legacy')", $bridge);
        self::assertStringNotContainsString('fab.hidden = true', $bridge);

        self::assertStringNotContainsString('clip-path: inset(50%)', $liveCss);
        self::assertStringNotContainsString('.atak-map-tools--c2-legacy', $liveCss);
        self::assertStringContainsString('.atak-map-tools:not(.is-collapsed)', $liveCss);
        self::assertStringContainsString('pointer-events: auto', $liveCss);

        self::assertStringNotContainsString('atak-map-tools--c2-legacy', $v2Css);
        self::assertStringNotContainsString('id="atak-map-tools" hidden', $view);
        self::assertStringNotContainsString('atak-map-tools--c2-legacy', $view);
        self::assertStringContainsString('id="atak-map-tools"', $view);
        self::assertStringContainsString('data-tool-ui="collapse"', $view);
    }

    public function testCollapseIsUserChosenAndPersisted(): void
    {
        $tools = (string) file_get_contents($this->root() . '/public/assets/js/atak-map-tools.js');
        $css = (string) file_get_contents($this->root() . '/public/assets/css/atak.css');
        $shell = (string) file_get_contents($this->root() . '/public/assets/css/atak-c2-shell.css');

        self::assertStringContainsString("var LS_COLLAPSED = 'atak_map_tools_collapsed'", $tools);
        self::assertStringContainsString("localStorage.setItem(LS_COLLAPSED, collapsed ? '1' : '0')", $tools);
        self::assertStringContainsString("return localStorage.getItem(LS_COLLAPSED) === '1'", $tools);
        self::assertStringContainsString("if (action === 'collapse') setToolbarCollapsed(true)", $tools);
        self::assertStringContainsString('setToolbarCollapsed(isToolbarCollapsed())', $tools);
        self::assertStringContainsString('setToolbarCollapsed(false)', $tools);

        self::assertStringContainsString('.atak-map-tools.is-collapsed', $css);
        self::assertStringContainsString('display: none !important', $css);
        self::assertStringContainsString('.atak-map-tools.is-collapsed', $shell);
    }
}
