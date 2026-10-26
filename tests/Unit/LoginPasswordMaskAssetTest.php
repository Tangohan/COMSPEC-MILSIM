<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LoginPasswordMaskAssetTest extends TestCase
{
    public function testLoginPasswordFieldIsMaskedWithoutWaitingForAlpine(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/auth/login.php');
        $field = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/auth/password_input.php');

        // Champ commun des pages d'accès : type password natif, bouton œil, alerte Verr. Maj.
        self::assertStringContainsString('<input type="password" name="', $field);
        self::assertStringContainsString('data-password-toggle="<?= $pwIdSafe ?>"', $field);
        self::assertStringContainsString('aria-pressed="false"', $field);
        self::assertStringContainsString('data-caps-watch', $field);
        self::assertStringContainsString("\$pwId = 'password'; \$pwName = 'password'; \$pwAutocomplete = 'current-password'", $view);
        self::assertStringContainsString("views/partials/auth/password_input.php", $view);
        self::assertStringNotContainsString(':type="showPassword', $view);
        self::assertStringContainsString("url('forgot-password')", $view);
        self::assertStringContainsString('assets/js/auth_forms.js', $view);
        self::assertStringNotContainsString('alpinejs', $view);
        self::assertStringNotContainsString('x-show="view', $view);
        self::assertStringNotContainsString('Access Control', $view);
    }

    public function testForgotPasswordPageIsAFullGuestScreen(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/auth/forgot-password.php');
        self::assertStringContainsString("url('forgot-password')", $view);
        self::assertStringContainsString('name="email"', $view);
        self::assertStringContainsString("views/partials/auth/head.php", $view);
        self::assertStringContainsString('dsfr-service.css', (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/auth/head.php'));
        self::assertStringContainsString('athena_header_guest.php', $view);
        self::assertStringContainsString('auth.forgot_lead', $view);
        self::assertStringContainsString('assets/js/auth_forms.js', $view);
        self::assertStringNotContainsString('Access Control', $view);
        self::assertStringNotContainsString('Retour au terminal', $view);
        self::assertStringNotContainsString('alpinejs', $view);
    }

    public function testResetPasswordPageUsesMaskedFieldsAndConfirm(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/auth/reset-password.php');
        self::assertStringContainsString("\$pwId = 'password'; \$pwName = 'password'; \$pwAutocomplete = 'new-password'", $view);
        self::assertStringContainsString("\$pwId = 'password_confirmation'; \$pwName = 'password_confirmation'", $view);
        self::assertStringContainsString('data-password-confirm-of="password"', $view);
        self::assertStringContainsString('data-strength-meter', $view);
        self::assertStringContainsString("url('reset-password')", $view);
        self::assertStringContainsString('name="token"', $view);
        self::assertStringContainsString("views/partials/auth/head.php", $view);
        self::assertStringContainsString('assets/js/auth_forms.js', $view);
        self::assertStringNotContainsString('alpinejs', $view);
    }

    public function testPasswordToggleDoesNotDependOnAlpine(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/auth_forms.js');

        self::assertStringContainsString('[data-password-toggle]', $javascript);
        self::assertStringContainsString("setAttribute('type', hide ? 'password' : 'text')", $javascript);
        self::assertStringContainsString('data-password-confirm-of', $javascript);
        self::assertStringContainsString('data-register-form', $javascript);
        self::assertStringNotContainsString('Alpine', $javascript);
    }

    public function testRegisterKeepsANativePasswordType(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/auth/register.php');

        self::assertStringContainsString('type="password" id="password" name="password"', $view);
        self::assertStringContainsString('type="password" id="password_confirmation" name="password_confirmation"', $view);
        self::assertStringNotContainsString(':type="showPassword', $view);
        self::assertStringContainsString('data-password-toggle="password"', $view);
        self::assertStringContainsString('data-password-toggle="password_confirmation"', $view);
        self::assertStringContainsString('data-register-form', $view);
        self::assertStringContainsString('name="accept_terms"', $view);
        self::assertStringContainsString('name="first_name"', $view);
        self::assertStringContainsString('name="last_name"', $view);
        self::assertStringNotContainsString('x-data=', $view);
        self::assertStringNotContainsString('x-show="step', $view);
        self::assertStringNotContainsString('alpinejs', $view);
        self::assertStringContainsString('assets/js/auth_forms.js', $view);
        self::assertStringContainsString('dsfr-service.css', $view);
    }
}
