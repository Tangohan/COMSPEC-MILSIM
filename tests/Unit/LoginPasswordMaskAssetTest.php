<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LoginPasswordMaskAssetTest extends TestCase
{
    public function testLoginPasswordFieldIsMaskedWithoutWaitingForAlpine(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/auth/login.php');

        self::assertStringContainsString('type="password" name="password" id="password"', $view);
        self::assertStringNotContainsString(':type="showPassword', $view);
        self::assertStringContainsString('data-password-toggle="password"', $view);
        self::assertStringContainsString("url('forgot-password')", $view);
        self::assertStringContainsString('assets/js/auth_forms.js', $view);
    }

    public function testPasswordToggleDoesNotDependOnAlpine(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/auth_forms.js');

        self::assertStringContainsString('[data-password-toggle]', $javascript);
        self::assertStringContainsString("setAttribute('type', hide ? 'password' : 'text')", $javascript);
        self::assertStringNotContainsString('Alpine', $javascript);
    }

    public function testRegisterKeepsANativePasswordType(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/auth/register.php');

        self::assertStringContainsString('type="password" :type="showPassword ? \'text\' : \'password\'"', $view);
        self::assertStringContainsString('type="password" :type="showPassword2 ? \'text\' : \'password\'"', $view);
    }
}
