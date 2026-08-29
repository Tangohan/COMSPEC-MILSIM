<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RegisterUxAssetTest extends TestCase
{
    public function testRegisterShowsTermsStepWithoutCloakAndDarkAlertsAreReadable(): void
    {
        $register = (string) file_get_contents(dirname(__DIR__, 2) . '/views/auth/register.php');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/dsfr-service.css');

        self::assertStringContainsString('accept_terms', $register);
        self::assertStringContainsString('auth.register_submit', $register);
        self::assertStringContainsString('reg-terms', $register);
        self::assertStringContainsString("startStep === 2", $register);
        self::assertStringNotContainsString('x-cloak x-transition', $register);
        self::assertStringContainsString('ds-alert--on-dark', $register);
        self::assertStringContainsString('color: #fecaca', $css);
        self::assertStringContainsString('.ds-alert--on-dark .ds-alert__title', $css);
    }
}
