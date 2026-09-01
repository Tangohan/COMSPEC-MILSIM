<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelPublicLayoutCspAssetTest extends TestCase
{
    public function testPortalPersonnelLayoutDoesNotLoadJsdelivrIconOrAnimationLibs(): void
    {
        $root = dirname(__DIR__, 2);
        $layout = (string) file_get_contents($root . '/views/layout/main.php');
        $file = (string) file_get_contents($root . '/views/personnel/file.php');
        $partial = (string) file_get_contents($root . '/views/partials/cdn_media_libs.php');
        $cfg = require $root . '/config/cdn_libraries.php';

        self::assertIsArray($cfg);
        self::assertSame([], $cfg['defaults'] ?? null);

        foreach (['icons', 'animation'] as $pack) {
            $assets = $cfg['packs'][$pack]['assets'] ?? [];
            self::assertIsArray($assets);
            foreach ($assets as $asset) {
                $url = (string) ($asset['href'] ?? $asset['src'] ?? '');
                self::assertStringNotContainsString('cdn.jsdelivr.net', $url);
                self::assertStringNotContainsString('animate.css', $url);
                self::assertStringNotContainsString('aos@', $url);
                self::assertStringNotContainsString('lucide@', $url);
                self::assertStringNotContainsString('iconify-icon@', $url);
            }
        }

        foreach ([$layout, $file] as $source) {
            self::assertStringNotContainsString('cdn.jsdelivr.net', $source);
            self::assertStringNotContainsString('animate.min.css', $source);
            self::assertStringNotContainsString('aos.css', $source);
            self::assertStringNotContainsString('lucide.min.js', $source);
            self::assertStringNotContainsString('iconify-icon.min.js', $source);
            self::assertStringNotContainsString('aos.js', $source);
        }

        self::assertStringContainsString('needsJsdelivrPreconnect', $partial);
        self::assertStringContainsString("str_contains(\$cdnAssetBlob, 'cdn.jsdelivr.net')", $partial);

        $packs = cdn_resolve_packs(null, 'portal');
        self::assertSame([], $packs);
        foreach (['head', 'body'] as $phase) {
            foreach (cdn_collect_assets($packs, $phase) as $asset) {
                $url = (string) ($asset['href'] ?? $asset['src'] ?? '');
                self::assertStringNotContainsString('cdn.jsdelivr.net', $url);
            }
        }

        self::assertFileExists($root . '/public/assets/vendor/alpinejs/alpine.min.js');
        self::assertStringContainsString('assets/vendor/alpinejs/alpine.min.js', $layout);
        self::assertGreaterThan(1000, filesize($root . '/public/assets/vendor/alpinejs/alpine.min.js'));
    }
}
