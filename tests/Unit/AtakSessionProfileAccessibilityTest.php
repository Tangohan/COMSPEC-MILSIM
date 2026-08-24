<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AtakSessionProfileAccessibilityTest extends TestCase
{
    public function testTabGatingReliesOnHiddenWithoutMutatingAriaHidden(): void
    {
        $script = file_get_contents(__DIR__ . '/../../public/assets/js/atak-session-profile.js');

        self::assertIsString($script);
        self::assertStringContainsString('btn.hidden = !ok;', $script);
        self::assertStringNotContainsString("btn.setAttribute('aria-hidden'", $script);
    }
}
