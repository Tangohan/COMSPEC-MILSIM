<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Auth\RecoveryCodeService;
use App\Services\Auth\TrustedDeviceService;
use App\Support\DbTime;
use PHPUnit\Framework\TestCase;

/**
 * Double vérification : codes de secours, appareils de confiance, heure des jetons e-mail.
 */
final class TwoFactorRecoveryTrustedDeviceTest extends TestCase
{
    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testRecoveryCodesAreReadableAndUnambiguous(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = RecoveryCodeService::generateCode();
            self::assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{4}-[A-HJKMNP-Z2-9]{4}$/', $code);
        }
    }

    public function testRecoveryCodeHashIgnoresCaseSpacesAndDash(): void
    {
        $ref = RecoveryCodeService::hashCode('ABCD-EFGH');
        self::assertSame($ref, RecoveryCodeService::hashCode('abcd efgh'));
        self::assertSame($ref, RecoveryCodeService::hashCode(' abcdefgh '));
        self::assertNotSame($ref, RecoveryCodeService::hashCode('ABCD-EFGJ'));
        self::assertSame('ABCDEFGH', RecoveryCodeService::normalize('ab-cd ef.gh'));
    }

    public function testTrustedDeviceIsBoundToThePasswordHash(): void
    {
        $a = TrustedDeviceService::passwordFingerprint(password_hash('one', PASSWORD_DEFAULT));
        $b = TrustedDeviceService::passwordFingerprint(password_hash('one', PASSWORD_DEFAULT));
        self::assertNotSame($a, $b, 'Un nouveau mot de passe (nouveau hash) doit invalider les appareils mémorisés.');
        self::assertSame(64, strlen($a));
    }

    public function testTrustedDeviceLabelsAreHuman(): void
    {
        self::assertSame('Firefox sur Windows', TrustedDeviceService::labelFromUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0'));
        self::assertSame('Chrome sur Android', TrustedDeviceService::labelFromUserAgent('Mozilla/5.0 (Linux; Android 14; SM-S911B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36'));
        self::assertSame('Safari sur iPhone', TrustedDeviceService::labelFromUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1'));
        self::assertSame('Navigateur', TrustedDeviceService::labelFromUserAgent(''));
    }

    public function testTokenExpiryIsWrittenInUtcLikeMysqlNow(): void
    {
        $paris = new \DateTimeImmutable('2026-07-01 12:00:00', new \DateTimeZone('Europe/Paris'));
        self::assertSame('2026-07-01 10:00:00', DbTime::utc($paris));
        self::assertSame($paris->getTimestamp(), DbTime::utcToTimestamp('2026-07-01 10:00:00'));

        foreach (['app/Repositories/EmailTokenRepository.php', 'app/Repositories/PasswordResetRepository.php'] as $repo) {
            self::assertStringContainsString('DbTime::utc($expiresAt)', self::read($repo), $repo);
        }
        self::assertStringContainsString("new \\DateTimeZone('UTC')", self::read('app/Repositories/EmailTokenRepository.php'));
        self::assertStringContainsString('DbTime::utcToTimestamp', self::read('app/Controllers/Web/VerifyEmailController.php'));
    }

    public function testLoginSkipsTheCodeOnlyOnATrustedDevice(): void
    {
        $auth = self::read('app/Controllers/Auth/AuthController.php');
        self::assertStringNotContainsString('isLoginEmailOtpRequired(', $auth);
        self::assertGreaterThanOrEqual(3, substr_count($auth, 'needsChallengeNow($user)'));
        self::assertStringContainsString("input('use_recovery', '') === '1'", $auth);
        self::assertStringContainsString("input('remember_device', '') === '1' && \$this->loginSecurityOtpService->canTrustDevice(\$user)", $auth);

        $otp = self::read('app/Services/Auth/LoginSecurityOtpService.php');
        // Jamais d'appareil de confiance pour un administrateur de la plateforme.
        self::assertStringContainsString("is_platform_admin", $otp);
        self::assertStringContainsString('emailHasPlatformAdmin', $otp);
        // Les codes de secours comptent dans le même plafond de tentatives.
        $start = (int) strpos($otp, 'function verifyPendingRecoveryCode');
        self::assertStringContainsString('MAX_TOTP_ATTEMPTS', substr($otp, $start, 1500));

        $trusted = self::read('app/Services/Auth/TrustedDeviceService.php');
        self::assertStringContainsString("'httponly' => true", $trusted);
        self::assertStringContainsString('hash_equals', $trusted);
    }

    public function testDisablingTheAppOrClosingSessionsDropsCodesAndDevices(): void
    {
        $account = self::read('app/Controllers/Web/AccountController.php');
        $disable = substr($account, (int) strpos($account, "\$section === 'totp_disable'"), 2500);
        self::assertStringContainsString('recoveryCodes()->deleteAll($uid)', $disable);
        self::assertStringContainsString('trustedDevices()->revokeAll($uid)', $disable);
        $revoke = substr($account, (int) strpos($account, 'bumpSessionEpoch'), 800);
        self::assertStringContainsString('trustedDevices()->revokeAll($uid)', $revoke);
        // Codes montrés une seule fois, puis retirés de la session.
        self::assertStringContainsString("Session::forget('fresh_recovery_codes')", $account);
    }

    public function testMigrationAndScreensAreWired(): void
    {
        self::assertStringContainsString('3120_auth_recovery_trusted_devices.php', self::read('database/steps/registry.php'));
        $migration = self::read('bootstrap/auth_recovery_trusted_devices_migration.php');
        self::assertStringContainsString('CREATE TABLE user_recovery_codes', $migration);
        self::assertStringContainsString('CREATE TABLE user_trusted_devices', $migration);

        $otpView = self::read('views/auth/login-otp.php');
        self::assertStringContainsString('name="remember_device"', $otpView);
        self::assertStringContainsString('name="use_recovery" value="1"', $otpView);
        self::assertStringContainsString('id="secours"', $otpView);
        // Garde-fou du double envoi (#762) conservé.
        self::assertStringContainsString('function submitOnce()', $otpView);

        $security = self::read('views/account/security.php');
        self::assertStringContainsString('id="codes-secours"', $security);
        self::assertStringContainsString('id="appareils-confiance"', $security);
        self::assertStringContainsString('recovery_regenerate', $security);
        self::assertStringContainsString('trusted_revoke_all', $security);

        foreach (['login', 'login-otp', 'forgot-password', 'reset-password', 'select-community'] as $page) {
            $view = self::read("views/auth/{$page}.php");
            self::assertStringContainsString('ds-page--auth', $view, $page);
            self::assertStringContainsString('views/partials/auth/head.php', $view, $page);
        }
        self::assertStringContainsString('athena.bo.theme', self::read('views/partials/auth/head.php'));
    }
}
