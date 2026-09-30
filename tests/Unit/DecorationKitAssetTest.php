<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DecorationCatalog;
use PHPUnit\Framework\TestCase;

final class DecorationKitAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testCssIsVectorOnlyWithSquareMedalDiscsAndFixedRibbonBox(): void
    {
        $css = (string) file_get_contents($this->root() . '/public/assets/css/decorations-kit.css');
        self::assertStringContainsString('inspired by official U.S. Army / NATO references', $css);
        self::assertStringContainsString('NOT an official reproduction', $css);
        self::assertStringNotContainsString('url(', $css);
        self::assertStringContainsString('.dk-m-disc {', $css);
        self::assertStringContainsString('width: 62px;', $css);
        self::assertStringContainsString('height: 62px;', $css);
        self::assertStringContainsString('border-radius: 50%;', $css);
        self::assertStringContainsString('aspect-ratio: 1 / 1;', $css);
        self::assertStringContainsString('width: 34px;', $css);
        self::assertStringContainsString('height: 34px;', $css);
        self::assertStringContainsString('width: 52px;', $css);
        self::assertStringContainsString('height: 18px;', $css);
        self::assertStringContainsString('width: 46px;', $css);
        self::assertStringContainsString('height: 16px;', $css);
        self::assertStringContainsString('.dk-slot:hover', $css);
        self::assertStringContainsString('outline: 2px solid var(--dk-gold', $css);
        self::assertStringContainsString('.dk-rb-nato', $css);
        self::assertStringContainsString('.dk-disc-nato', $css);
    }

    public function testKitPageListsFifteenEntriesAndPrudenceBanner(): void
    {
        $kit = (string) file_get_contents($this->root() . '/views/personnel/decorations_kit.php');
        $rack = (string) file_get_contents($this->root() . '/views/partials/personnel/decoration_rack.php');
        $file = (string) file_get_contents($this->root() . '/views/personnel/file.php');
        $edit = (string) file_get_contents($this->root() . '/views/personnel/edit.php');
        $layout = (string) file_get_contents($this->root() . '/views/layout/main.php');
        $routes = (string) file_get_contents($this->root() . '/routes/web.php');
        $controller = (string) file_get_contents($this->root() . '/app/Controllers/Web/PersonnelController.php');
        $dispatch = (string) file_get_contents($this->root() . '/app/Support/DevDispatchCatalog.php');

        self::assertStringContainsString('DecorationCatalog::CAUTION', $kit);
        self::assertStringContainsString('DecorationCatalog::FOOTER', $kit);
        self::assertStringContainsString('NATO_INSPIRED', $kit);
        self::assertStringContainsString('isOfficialReference', $kit);
        self::assertStringContainsString('count($all)', $kit);
        self::assertCount(15, DecorationCatalog::all());

        self::assertStringContainsString('data-dk-rack', $rack);
        self::assertStringContainsString('dk-slot', $rack);
        self::assertStringContainsString('isOfficialReference: false', $rack);

        self::assertStringContainsString('decoration_rack.php', $file);
        self::assertStringContainsString('personnel-decorations-title', $file);
        self::assertStringContainsString('kit-rubans-medailles', $file);

        self::assertStringContainsString('medal_rack_catalog[]', $edit);
        self::assertStringContainsString('Voir le pack visuel', $edit);

        self::assertStringContainsString('decorations-kit.css', $layout);
        self::assertStringContainsString('decorations-rack.js', $layout);
        self::assertStringContainsString('/personnel/kit-rubans-medailles', $routes);
        self::assertStringContainsString('function decorationsKit', $controller);
        self::assertStringContainsString('mergeRackInput', $controller);
        self::assertStringContainsString('Rubans et médailles : pack visuel générique', $dispatch);
    }

    public function testJsSelectsRackSlotsWithoutBitmapAssets(): void
    {
        $js = (string) file_get_contents($this->root() . '/public/assets/js/decorations-rack.js');
        self::assertStringContainsString('not an official reproduction', $js);
        self::assertStringContainsString('is-selected', $js);
        self::assertStringContainsString('data-dk-rack', $js);
        self::assertStringNotContainsString('.png', $js);
        self::assertStringNotContainsString('.jpg', $js);
    }
}
