<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ImagesBadgeAssetTest extends TestCase
{
    private function src(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testBadgeIsWiredOnDirectoryAndShowcase(): void
    {
        self::assertStringContainsString("views/partials/images_badge.php", $this->src('views/community/registry.php'));
        self::assertStringContainsString("views/partials/images_badge.php", $this->src('views/community/show_showcase.php'));
        self::assertStringContainsString("'images-badge.css'", $this->src('views/layout/main.php'));
    }

    public function testShowcaseBadgeSkipsBlurredPhotos(): void
    {
        self::assertMatchesRegularExpression(
            '/parseBlurRegions\(\$gbItem\[\'blur_regions_json\'\] \?\? null\) !== \[\]\)\s*\{\s*continue;/',
            $this->src('views/community/show_showcase.php')
        );
    }

    public function testBadgeRespectsReducedMotionAndKeyboard(): void
    {
        $css = $this->src('public/assets/css/images-badge.css');
        self::assertStringContainsString('prefers-reduced-motion: reduce', $css);
        self::assertStringContainsString('.ib:focus-visible .ib__card', $css);
        self::assertStringContainsString('aria-hidden="true"', $this->src('views/partials/images_badge.php'));
    }
}
