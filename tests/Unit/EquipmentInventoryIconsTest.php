<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EquipmentInventoryIconsTest extends TestCase
{
    private function render(array $boxes): string
    {
        $h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $inventoryBoxes = $boxes;
        ob_start();
        require dirname(__DIR__, 2) . '/views/equipment/_inventory_line.php';

        return (string) ob_get_clean();
    }

    public function testItemWithIconShowsImageItemWithoutKeepsItsName(): void
    {
        $html = $this->render([[
            'title' => 'Sac',
            'count' => 3,
            'items' => [
                ['name' => 'Pansement <b>', 'qty' => 2, 'class' => 'a', 'icon' => '/equipment/icones/abc.png'],
                ['name' => 'Radio', 'qty' => 1, 'class' => 'b'],
            ],
        ]]);

        self::assertSame(1, substr_count($html, '<img '));
        self::assertStringContainsString('src="/equipment/icones/abc.png"', $html);
        self::assertStringContainsString('onerror="this.remove()"', $html);
        self::assertStringContainsString('Pansement &lt;b&gt; ×2', $html);
        self::assertStringContainsString('</span>', $html);
        self::assertStringContainsString('Radio', $html);
    }

    public function testNoInventoryRendersNothing(): void
    {
        self::assertSame('', trim($this->render([])));
    }
}
