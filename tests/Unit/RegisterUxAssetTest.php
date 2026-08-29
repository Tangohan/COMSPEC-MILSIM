<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RegisterUxAssetTest extends TestCase
{
    public function testRegisterIsSinglePageWithVisibleTermsAndReadableAlerts(): void
    {
        $register = (string) file_get_contents(dirname(__DIR__, 2) . '/views/auth/register.php');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/dsfr-service.css');

        self::assertStringContainsString('accept_terms', $register);
        self::assertStringContainsString('auth.register_submit', $register);
        self::assertStringContainsString('ds-check', $register);
        self::assertStringContainsString('ds-page ds-page--split', $register);
        self::assertStringContainsString('type="submit"', $register);
        self::assertStringNotContainsString('x-cloak', $register);
        self::assertStringNotContainsString('x-show="step', $register);
        self::assertStringNotContainsString('startStep', $register);
        self::assertStringNotContainsString('reg-step-dot', $register);
        // Surface claire : alertes DSFR standards (pas de thème sombre illisible).
        self::assertStringNotContainsString('ds-alert--on-dark', $register);
        self::assertStringContainsString('ds-alert-stack', $register);
        self::assertStringContainsString('.ds-check', $css);
        self::assertStringContainsString('.ds-main__inner--register', $css);
    }
}
