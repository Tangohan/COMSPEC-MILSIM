<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class JnetCommonStickyTopTest extends TestCase
{
    public function testSalonBarStaysUnderTheMeasuredTopBar(): void
    {
        $root = dirname(__DIR__, 2);
        $css = (string) file_get_contents($root . '/public/assets/css/jnet_commun.css');
        $js = (string) file_get_contents($root . '/public/assets/js/jnet_commun.js');

        self::assertStringNotContainsString('--jc-top: 108px', $css);
        self::assertStringContainsString("setProperty('--jc-top'", $js);
        self::assertStringContainsString("document.querySelector('.ja-hd')", $js);
    }
}
