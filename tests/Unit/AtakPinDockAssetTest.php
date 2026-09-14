<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakPinDockAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testCommandPostWiresPinnedShortcutDock(): void
    {
        $js = (string) file_get_contents($this->root() . '/public/assets/js/atak-pin-dock.js');
        $nav = (string) file_get_contents($this->root() . '/public/assets/js/atak-section-nav.js');
        $chrome = (string) file_get_contents($this->root() . '/public/assets/js/atak-panel-chrome.js');
        $css = (string) file_get_contents($this->root() . '/public/assets/css/atak-c2-shell.css');
        $view = (string) file_get_contents($this->root() . '/views/atak.php');

        self::assertStringContainsString('window.ATAKPinDock', $js);
        self::assertStringContainsString("STORAGE = 'atak-pin-dock-v1'", $js);
        self::assertStringContainsString("PINNABLE = ['chat', 'radio', 'liaison', 'orders', 'medical', 'pings']", $js);
        self::assertStringContainsString('Épingler', $js);
        self::assertStringContainsString('raccourci', $js);
        self::assertStringContainsString('keepPinnedVisible', $js);

        self::assertStringContainsString('id="atak-pin-dock"', $view);
        self::assertStringContainsString('Fenêtres épinglées en raccourci', $view);
        self::assertStringContainsString('atak-pin-dock.js', $view);

        self::assertStringContainsString('.atak-pin-dock', $css);
        self::assertStringContainsString('.atak-pin-window--chat', $css);
        self::assertStringContainsString('.atak-tab-pin', $css);

        self::assertStringContainsString("c.closest('#atak-pin-dock')", $chrome);
        self::assertStringContainsString('firstUnpinnedIn', $nav);
        self::assertStringContainsString('ATAKPinDock.isPinned', $nav);
    }
}
