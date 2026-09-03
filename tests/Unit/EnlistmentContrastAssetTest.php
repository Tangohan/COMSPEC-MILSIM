<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EnlistmentContrastAssetTest extends TestCase
{
    public function testGateHintsDoNotDeriveTextColorFromTenantAccent(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/community-enlistment.css');

        self::assertStringContainsString('.ce-gate__hints li', $css);
        self::assertStringContainsString('color: #f1f5f9', $css);
        self::assertStringNotContainsString('color: color-mix(in srgb, var(--ce-accent)', $css);
        self::assertStringContainsString('--ce-muted: #c5d0de', $css);
    }

    public function testEmptyAiScanHintsRemainHidden(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/community-enlistment.css');

        self::assertMatchesRegularExpression(
            '/\.ce-label-hint\[data-ai-hint\]\[hidden\]\s*\{\s*display:\s*none;\s*\}/',
            $css
        );
    }

    public function testStaleEnlistmentPagesDropJnetChrome(): void
    {
        $success = (string) file_get_contents(dirname(__DIR__, 2) . '/views/enlistment/success.php');
        $error = (string) file_get_contents(dirname(__DIR__, 2) . '/views/enlistment/error.php');
        $noCommunity = (string) file_get_contents(dirname(__DIR__, 2) . '/views/enlistment/no_community.php');

        foreach ([$success, $error, $noCommunity] as $view) {
            self::assertStringNotContainsString('JNET v2.4.0', $view);
            self::assertStringContainsString('dsfr-service.css', $view);
            self::assertStringContainsString('ds-page', $view);
        }
    }
}
