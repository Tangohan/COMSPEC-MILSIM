<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMapsSeedTest extends TestCase
{
    public function testKimmirutIsSeededWithHumanLabelAndJetelainTiles(): void
    {
        $seed = (string) file_get_contents(dirname(__DIR__, 2) . '/run-migrations.php');
        $start = strpos($seed, '$atakMapsSeed = [');
        $end = strpos($seed, 'try {', $start !== false ? $start : 0);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $block = substr($seed, $start, $end - $start);

        self::assertStringContainsString("'slug' => 'sze_kimmirut'", $block);
        self::assertStringContainsString("'label' => 'Kimmirut'", $block);
        self::assertStringContainsString("'world_name' => 'sze_kimmirut'", $block);
        self::assertStringContainsString("'worldSize' => 20480", $block);
        self::assertStringContainsString("'tileSize' => 323", $block);
        self::assertStringContainsString("'tile_cdn' => 'https://atlas.plan-ops.fr/data/1'", $block);
        self::assertStringContainsString("'tile_path' => 'maps/107/107/{z}/{x}/{y}.png'", $block);
        self::assertStringContainsString("'center' => [10240, 10240]", $block);
    }

    public function testAtakMapSelectorUsesHumanLabelsNotTechnicalSlugsAlone(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $admin = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/atak-config/index.php');

        self::assertStringContainsString("\$m['label'] ?? \$m['slug'] ?? 'Carte'", $view);
        self::assertStringContainsString('Kimmirut', $admin);
        self::assertStringNotContainsString('sze_kimmirut', $admin);
    }
}
