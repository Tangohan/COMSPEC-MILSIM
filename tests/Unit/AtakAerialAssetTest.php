<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakAerialAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testAltisAerialIsWiredOnCommandMaps(): void
    {
        $js = (string) file_get_contents($this->root() . '/public/assets/js/atak-aerial.js');
        $map = (string) file_get_contents($this->root() . '/public/assets/js/atak-map.js');
        $ops = (string) file_get_contents($this->root() . '/public/assets/js/comspec-operational-map.js');
        $helpers = (string) file_get_contents($this->root() . '/app/Support/helpers.php');
        $atakView = (string) file_get_contents($this->root() . '/views/atak.php');
        $tacmap = (string) file_get_contents($this->root() . '/views/tacmap.php');
        $overwatch = (string) file_get_contents($this->root() . '/views/overwatch/index.php');
        $controller = (string) file_get_contents($this->root() . '/app/Controllers/Web/AtakController.php');
        $home = (string) file_get_contents($this->root() . '/app/Controllers/Web/HomeController.php');

        self::assertStringContainsString('function atak_aerial_layer_config', $helpers);
        self::assertStringContainsString('maps/3/295/{z}/{x}/{y}.webp', $helpers);
        self::assertStringContainsString('function atak_with_aerial_layer', $helpers);

        self::assertStringContainsString('window.ATAKAerial', $js);
        self::assertStringContainsString('maps/3/295/{z}/{x}/{y}.webp', $js);
        self::assertStringContainsString("STORAGE_KEY = 'athena:atak-fond'", $js);
        self::assertStringContainsString("tileSize: 381", $js);
        self::assertStringNotContainsString('crossOrigin', $js);
        self::assertStringContainsString('Photo aérienne', $atakView);

        self::assertStringContainsString('ATAKAerial.attach', $map);
        self::assertStringContainsString('ATAKAerial.detach', $map);
        self::assertStringContainsString('ATAKAerial.attach', $ops);
        self::assertStringContainsString('atak-aerial.js', $atakView);
        self::assertStringContainsString('atak-aerial.js', $tacmap);
        self::assertStringContainsString('atak-aerial.js', $overwatch);
        self::assertStringContainsString('data-atak-aerial-fond', $atakView);
        self::assertStringContainsString('data-atak-aerial-fond', $tacmap);
        self::assertStringContainsString('data-atak-aerial-fond', $overwatch);

        self::assertStringContainsString('atak_with_aerial_layer', $controller);
        self::assertStringContainsString('atak_with_aerial_layer', $home);
        self::assertStringContainsString('atak_with_aerial_layer', $atakView);
    }

    public function testAerialBulletinDescribesOperatorFacingBehaviour(): void
    {
        $catalog = (string) file_get_contents($this->root() . '/app/Support/DevDispatchCatalog.php');
        self::assertStringContainsString("\$pr(568, '2026-09-14'", $catalog);
        self::assertStringContainsString('photo aérienne', strtolower($catalog));
        self::assertStringNotContainsString('webp', strtolower(explode('$pr(567,', $catalog)[0]));
    }
}
