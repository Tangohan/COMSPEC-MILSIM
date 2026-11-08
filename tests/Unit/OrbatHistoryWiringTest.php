<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OrbatHistoryWiringTest extends TestCase
{
    public function testHistoryActionAndNoEmailFallback(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/OrbatApiController.php');
        self::assertStringContainsString("'unit_history' => \$this->unitHistory(", $src);
        self::assertStringContainsString('recordUnitFieldChanges', $src);
        self::assertStringNotContainsString("\$actor['email']", $src);
    }

    public function testDrawerResetsCacheAndStaysHiddenWhenClosed(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/orbat-app.js');
        $css = (string) file_get_contents($root . '/public/assets/css/orbat-app.css');
        self::assertStringContainsString('dr._sig = null', $js);
        self::assertStringContainsString('.orb-dr:not(.on) { visibility: hidden', $css);
    }
}
