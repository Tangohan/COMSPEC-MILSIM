<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakNineLineNatoAssetTest extends TestCase
{
    public function testJtacFormUsesNatoNineLineAndLaserCodes(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/atak-jtac.js');
        $laser = (string) file_get_contents($root . '/public/assets/js/atak-laser-codes.js');

        self::assertStringContainsString("getApiBase() + '/api/cas'", $js);
        self::assertStringContainsString('ATAKLaserCodes', $js);
        self::assertStringContainsString('buildLine7', $js);
        self::assertStringContainsString('credentials: \'include\'', $laser);
        self::assertStringContainsString('fillLaserSelect', $laser);
    }
}
