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
        self::assertStringContainsString('data-preview-modal', $register);
        self::assertStringContainsString('Preview ouverte', $register);
        self::assertStringContainsString('PortalHost::feedbackEmail()', $register);
        self::assertStringContainsString('.ds-preview-modal', $css);

        // Refonte : sections, partie facultative repliable, erreurs sous les champs, force du mot de passe, mode nuit.
        $registerCss = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/auth-register.css');
        $registerJs = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/auth_register.js');
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/RegisterController.php');
        self::assertStringContainsString('auth-register.css', $register);
        self::assertStringContainsString('assets/js/auth_register.js', $register);
        self::assertStringContainsString('data-inline-validation', $register);
        self::assertStringContainsString('class="rg-optional"', $register);
        self::assertStringContainsString('data-strength-meter', $register);
        self::assertStringContainsString('data-error-for="email"', $register);
        self::assertStringContainsString('athena.bo.theme', $register);
        self::assertStringContainsString('html[data-bo-theme="dark"] .ds-page--register', $registerCss);
        self::assertStringContainsString('.rg-error:empty', $registerCss);
        self::assertStringContainsString('aria-invalid', $registerJs);
        self::assertStringContainsString("'register_error_field'", $controller);
        // Plus de lien texte « Afficher le mot de passe » dans le champ : bouton œil avec aria-pressed.
        self::assertStringContainsString('aria-pressed="false"', $register);
        self::assertStringNotContainsString('data-password-toggle-label', $register);

        $email = (string) file_get_contents(dirname(__DIR__, 2) . '/views/emails/user_register_confirmation.php');
        self::assertStringContainsString('preview ouverte', $email);
        self::assertStringContainsString('PortalHost::feedbackEmail()', $email);
    }
}
