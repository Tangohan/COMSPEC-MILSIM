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
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');

        self::assertDoesNotMatchRegularExpression(
            '/\.atak-map-tools__cluster-label\s*\{[^}]*pointer-events:\s*none/s',
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
        self::assertStringNotContainsString('if (panel) panel.hidden = !open;', $tools);

        self::assertStringNotContainsString('id="atak-tool-group-mark" hidden', $view);
        self::assertStringNotContainsString('id="atak-tool-group-draw" hidden', $view);
        self::assertStringNotContainsString('id="atak-tool-group-analyse" hidden', $view);
        self::assertStringNotContainsString('id="atak-tool-group-view" hidden', $view);
        self::assertStringContainsString('data-tool-group="nav"', $view);
        self::assertStringContainsString('data-tool="goto"', $view);
    }
}
