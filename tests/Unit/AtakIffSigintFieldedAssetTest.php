<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakIffSigintFieldedAssetTest extends TestCase
{
    public function testIffAndSigintAreWiredOnTheCommandPost(): void
    {
        $root = dirname(__DIR__, 2);
        $iff = (string) file_get_contents($root . '/public/assets/js/atak-iff.js');
        $sigint = (string) file_get_contents($root . '/public/assets/js/atak-sigint.js');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');

        self::assertStringContainsString('/api/atak/sigint?mapId=', $sigint);
        self::assertStringContainsString('function sigintIndex', $ctrl);
        self::assertStringContainsString('getSigintReports', $ctrl);
        self::assertStringContainsString("'sigintIndex'", $routes);
        self::assertStringContainsString('return !isNaN(t) && t > 0 ? t : 0;', $iff);
        self::assertFileExists($root . '/docs/bugs/2026-09-13-iff-communaute-par-defaut.md');
    }
}
