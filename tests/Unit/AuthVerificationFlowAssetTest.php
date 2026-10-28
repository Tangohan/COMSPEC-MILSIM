<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AuthVerificationFlowAssetTest extends TestCase
{
    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testVerificationActivatesCommunityProfilesToo(): void
    {
        $repo = self::read('app/Repositories/UserRepository.php');
        $migration = self::read('bootstrap/user_community_identity_migration.php');

        self::assertStringContainsString('function activatePendingCommunityProfiles', $repo);
        self::assertGreaterThanOrEqual(4, substr_count($repo, 'activatePendingCommunityProfiles('));
        self::assertStringContainsString("SET p.status = 'active'", $migration);
    }

    public function testVerificationLinkLastsADayEverywhere(): void
    {
        foreach ([
            'app/Controllers/Web/RegisterController.php',
            'app/Controllers/Web/VerifyEmailController.php',
            'app/Controllers/Admin/Organization/UserAdminController.php',
        ] as $path) {
            $src = self::read($path);
            self::assertStringNotContainsString("'+15 minutes'", $src, $path);
            self::assertStringContainsString('REGISTER_CONFIRM_TTL_MINUTES', $src, $path);
        }
        self::assertStringContainsString('REGISTER_CONFIRM_TTL_MINUTES = 1440', self::read('app/Services/Email/EmailTokenPurpose.php'));
    }

    public function testAdminResendStoresTokenBeforeSending(): void
    {
        $admin = self::read('app/Controllers/Admin/Organization/UserAdminController.php');
        $start = strpos($admin, 'public function resendVerificationEmail');
        self::assertNotFalse($start);
        $body = substr($admin, (int) $start);

        self::assertLessThan(strpos($body, 'sendUserRegisterConfirmation'), strpos($body, '$tokenId = $this->emailTokenRepository->create'));
    }

    public function testUsedOrExpiredLinksAreExplained(): void
    {
        $verify = self::read('app/Controllers/Web/VerifyEmailController.php');
        $routes = self::read('routes/web.php');

        self::assertStringContainsString('findAnyByHash', $verify);
        self::assertStringContainsString('déjà confirmée', $verify);
        self::assertStringContainsString("\$router->get('/verify-email', [VerifyEmailController::class, 'verify']);", $routes);
    }

    public function testOtherAccountLinksLeaveThePendingStep(): void
    {
        self::assertStringContainsString("?autre-compte=1", self::read('views/auth/login-otp.php'));
        self::assertStringContainsString("?autre-compte=1", self::read('views/auth/select-community.php'));
        self::assertStringContainsString("query('autre-compte'", self::read('app/Controllers/Auth/AuthController.php'));
    }

    public function testTimeCriticalMailsSkipTheQueue(): void
    {
        $mail = self::read('app/Services/EmailService.php');

        foreach (["'password_reset']", "'register']", "'login_security_otp']"] as $purpose) {
            self::assertStringContainsString($purpose . ",\n            true\n", $mail, $purpose);
        }
    }
}
