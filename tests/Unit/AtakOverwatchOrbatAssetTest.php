<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakOverwatchOrbatAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testOrbatLivePanelIsWiredOnOverwatchBeta(): void
    {
        $root = $this->root();
        $view = (string) file_get_contents($root . '/views/atak-overwatch-beta.php');
        $js = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-orbat.js');
        $beta = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-beta.js');
        $css = (string) file_get_contents($root . '/public/assets/css/atak-overwatch-orbat.css');

        self::assertStringContainsString('data-view="orbat"', $view);
        self::assertStringContainsString('id="ow-orbat-panel"', $view);
        self::assertStringContainsString('id="ow-orbat-tree"', $view);
        self::assertStringContainsString('id="ow-orbat-ctx"', $view);
        self::assertStringContainsString('atak-overwatch-orbat.js', $view);
        self::assertStringContainsString('atak-overwatch-orbat.css', $view);

        self::assertStringContainsString('window.OverwatchOrbat', $js);
        self::assertStringContainsString('/api/orbat/roster', $js);
        self::assertStringContainsString('ATAK_CALLSIGN_TO_USER', $js);
        self::assertStringContainsString('Écart ORBAT', $js);
        self::assertStringContainsString('atak:entity-selected', $js);
        self::assertStringContainsString('military_id', $js);
        self::assertStringContainsString('strength_authorized', $js);

        self::assertStringContainsString("name === 'orbat'", $beta);
        self::assertStringContainsString('OverwatchOrbat.activate', $beta);
        self::assertStringContainsString('OverwatchOrbat.deactivate', $beta);

        self::assertStringContainsString('.ow-orbat-panel', $css);
        self::assertStringContainsString('.ow-orbat-ctx', $css);
        self::assertStringContainsString('.ow-orbat-gap', $css);
        self::assertStringNotContainsString('purple', strtolower($css));
    }
}
