<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RegisterFlowAssetTest extends TestCase
{
    public function testRegisterIsSinglePageWithoutWizardSteps(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/auth/register.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/RegisterController.php');

        self::assertStringContainsString('method="post"', $view);
        self::assertStringContainsString('name="accept_terms"', $view);
        self::assertStringContainsString('type="submit"', $view);
        self::assertStringContainsString('ds-page ds-page--split', $view);
        self::assertStringNotContainsString('reg-step-dot', $view);
        self::assertStringNotContainsString('register_step', $view);
        self::assertStringNotContainsString("Session::flash('register_step'", $controller);
        self::assertStringContainsString("'register_old'", $controller);
    }

    public function testRegisterCheckEmailUsesServiceDesign(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/auth/register-check-email.php');

        self::assertStringContainsString('ds-page ds-page--split', $view);
        self::assertStringContainsString('dsfr-service.css', $view);
        self::assertStringContainsString('resend-verification', $view);
        self::assertStringNotContainsString('home-impact.css', $view);
    }

    public function testVerificationTokenExistsBeforeMailCanBeSent(): void
    {
        $register = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/RegisterController.php');
        $resend = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/VerifyEmailController.php');

        self::assertLessThan(strpos($register, 'sendUserRegisterConfirmation'), strpos($register, '$tokenId = $this->emailTokens->create'));
        self::assertLessThan(strpos($resend, 'sendUserRegisterConfirmation'), strpos($resend, '$tokenId = $this->emailTokens->create'));
        self::assertLessThan(strpos($resend, 'markConsumed'), strpos($resend, 'markEmailVerified'));
    }

    public function testOperationsIsNotifiedWhenAccountIsCreatedAndVerified(): void
    {
        $register = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/RegisterController.php');
        $verify = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/VerifyEmailController.php');
        $template = (string) file_get_contents(dirname(__DIR__, 2) . '/views/emails/platform_account_registration_alert.php');

        self::assertStringContainsString("'created'", $register);
        self::assertStringContainsString('sendPlatformAccountRegistrationAlert', $register);
        self::assertStringContainsString("'email_verified'", $verify);
        self::assertStringContainsString('sendPlatformAccountRegistrationAlert', $verify);
        self::assertStringContainsString('Adresse e-mail', $template);
        self::assertLessThan(strpos($verify, 'sendPlatformAccountRegistrationAlert'), strpos($verify, 'markEmailVerified'));
    }
}
