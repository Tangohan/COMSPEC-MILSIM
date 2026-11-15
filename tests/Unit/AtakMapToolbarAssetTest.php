<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMapToolbarAssetTest extends TestCase
{
    public function testClusterLabelsRemainClickableInC2Chrome(): void
    {
        $shell = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-c2-shell.css');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');
        $tools = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map-tools.js');

        self::assertDoesNotMatchRegularExpression(
            '/button\.atak-map-tools__cluster-label\s*\{[^}]*pointer-events:\s*none/s',
            $shell
        );
        self::assertStringContainsString('button.atak-map-tools__cluster-label {', $shell);
        self::assertStringContainsString('pointer-events: auto;', $shell);
        self::assertStringContainsString('span.atak-map-tools__cluster-label {', $shell);

        self::assertStringContainsString('.atak-map-tools__cluster-btns[hidden]', $shell);
        self::assertStringContainsString('position: static !important;', $shell);
        self::assertStringContainsString('display: flex !important;', $shell);
        self::assertStringContainsString('overflow: visible;', $shell);

        self::assertStringContainsString('position: static;', $css);
        self::assertStringContainsString('pointer-events: auto;', $css);
        self::assertStringNotContainsString('top: calc(100% + 7px);', $css);

        self::assertStringContainsString('panel.hidden = false', $tools);
        self::assertStringContainsString("panel.removeAttribute('hidden')", $tools);
        self::assertStringContainsString('/* Toujours visibles : le chrome C2 les aligne sous le libellé. */', $tools);
    }

    public function testC2KeepsToolsInFlowAndBeatsCompactDropdown(): void
    {
        $shell = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-c2-shell.css');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');
        $cop = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-cop.css');

        self::assertDoesNotMatchRegularExpression(
            '/\.atak-map-tools__cluster-btns\s*\{[^}]*position:\s*absolute/s',
            $css
        );
        self::assertDoesNotMatchRegularExpression(
            '/button\.atak-map-tools__cluster-label\s*\{[^}]*pointer-events:\s*none/s',
            $shell
        );
        self::assertMatchesRegularExpression(
            '/button\.atak-map-tools__cluster-label\s*\{[^}]*pointer-events:\s*auto/s',
            $shell
        );
        self::assertStringContainsString('flex-direction: column !important;', $shell);
        self::assertStringContainsString('flex-wrap: wrap !important;', $shell);
        self::assertStringContainsString('overflow: visible !important;', $shell);
        self::assertStringContainsString('cluster--chrome > .atak-map-tools__cluster-label', $shell);
        self::assertStringContainsString('flex-direction: row !important;', $shell);

        self::assertStringContainsString('justify-content: space-between;', $shell);
        self::assertStringContainsString('.atak-terrain-inventory__row', $shell);
        self::assertStringContainsString('justify-content: space-between;', $cop);
        self::assertStringContainsString('line-height: 1.5;', $cop);
    }
}
