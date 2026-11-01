<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AthenaAccountRepository;
use App\Repositories\EmailTokenRepository;
use App\Repositories\TenantBrandingRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\LoginSecurityOtpService;
use App\Services\Auth\RecoveryCodeService;
use App\Services\Auth\TotpReplayGuard;
use App\Services\Auth\TotpSecretCipher;
use App\Services\Auth\TotpService;
use App\Services\Auth\TrustedDeviceService;
use App\Services\EmailService;
use App\Services\Game\GameAuthService;
use App\Services\Game\GameOverwatchExperienceService;
use App\Services\Game\GameSecondFactorGate;
use App\Services\Security\FileRateLimiter;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Connexion en jeu d'un compte protégé par l'application d'authentification : le code e-mail, le mot de passe
 * seul, Steam seul et les sessions ouvertes avant le correctif ne suffisent plus ; le code de l'application
 * (une seule fois) ou un code de secours ouvrent la session.
 *
 * Une session acceptée s'arrête ici sur NO_TENANT (aucune communauté simulée) : le contrôle a été franchi.
 */
final class GameAuthSecondFactorTest extends TestCase
{
    private const EMAIL = 'joueur@example.com';

    private const STEAM = '76561198000000001';

    private string $secret = '';

    private PDO $pdo;

    /** @var array<string, int> */
    private array $hits = [];

    private AthenaAccountRepository&MockObject $accounts;

    private EmailService&MockObject $email;

    private ?RecoveryCodeService $recovery = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite absent');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $this->pdo->exec('CREATE TABLE user_recovery_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, code_hash VARCHAR(64) NOT NULL, created_at DATETIME, used_at DATETIME NULL)');
        $this->secret = (new TotpService())->generateSecret();
        $this->hits = [];
        $this->accounts = $this->createMock(AthenaAccountRepository::class);
        $this->accounts->method('findByEmail')->willReturn(['id' => 1, 'email' => self::EMAIL, 'status' => 'active', 'steam_id' => self::STEAM, 'password_hash' => password_hash('motdepasse', PASSWORD_BCRYPT, ['cost' => 4])]);
        $this->accounts->method('findById')->willReturn(['id' => 1, 'email' => self::EMAIL, 'status' => 'active', 'steam_id' => self::STEAM]);
        $this->accounts->method('listActiveMemberships')->willReturn([]);
        $this->email = $this->createMock(EmailService::class);
    }

    /** @param array<string, mixed> $userOverrides */
    private function service(bool $totp = true, array $userOverrides = []): GameAuthService
    {
        $user = array_merge([
            'id' => 42,
            'tenant_id' => 3,
            'email' => self::EMAIL,
            'status' => 'active',
            'password_hash' => password_hash('motdepasse', PASSWORD_BCRYPT, ['cost' => 4]),
            'totp_enabled' => $totp ? 1 : 0,
            'totp_secret' => $totp ? $this->secret : null,
            'totp_confirmed_at' => '2026-10-01 12:00:00',
        ], $userOverrides);
        $users = $this->createMock(UserRepository::class);
        $users->method('hasTotpColumns')->willReturn(true);
        $users->method('listUsersForLoginByEmail')->willReturn([$user]);
        $users->method('findById')->willReturn($user);
        $users->method('findBySteamId')->willReturn($user);

        $this->recovery = new RecoveryCodeService($this->pdo);
        $otp = new LoginSecurityOtpService(
            $users,
            $this->createMock(EmailTokenRepository::class),
            $this->createMock(TenantRepository::class),
            $this->email,
            new TotpService(),
            new TotpSecretCipher(),
            $this->recovery,
            new TrustedDeviceService($this->pdo),
            new TotpReplayGuard($this->pdo),
        );
        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('attempts')->willReturnCallback(fn (string $k): int => $this->hits[$k] ?? 0);
        $limiter->method('hit')->willReturnCallback(function (string $k): int {
            $this->hits[$k] = ($this->hits[$k] ?? 0) + 1;

            return $this->hits[$k];
        });
        $limiter->method('clear')->willReturnCallback(function (string $k): bool {
            unset($this->hits[$k]);

            return true;
        });

        return new GameAuthService(
            $this->accounts,
            $users,
            $this->createMock(TenantRepository::class),
            $this->email,
            $this->createMock(GameOverwatchExperienceService::class),
            $this->createMock(UserProfileRepository::class),
            $this->createMock(TenantBrandingRepository::class),
            $this->createMock(UserNotificationPreferencesRepository::class),
            new GameSecondFactorGate($users, $otp, $limiter),
        );
    }

    private function appCode(int $offsetSeconds = 0): string
    {
        return (new TotpService())->currentCode($this->secret, time() + $offsetSeconds);
    }

    public function testRequestOtpSendsNoEmailForAnAppProtectedAccountAndAnswersAsUsual(): void
    {
        $this->email->expects(self::never())->method('send');
        $this->accounts->expects(self::never())->method('insertOtp');

        $res = $this->service()->requestOtp(['email' => self::EMAIL]);

        self::assertSame(['ok' => true, 'status' => 200, 'payload' => ['sent' => true]], $res, 'même réponse que pour tout e-mail (rien n’est révélé)');
    }

    public function testRequestOtpStillSendsTheEmailCodeWithoutTheApp(): void
    {
        $this->email->expects(self::once())->method('send');
        $this->accounts->expects(self::once())->method('insertOtp');

        $res = $this->service(false)->requestOtp(['email' => self::EMAIL]);

        self::assertTrue($res['ok']);
    }

    public function testVerifyOtpIgnoresEmailCodesAndAcceptsTheAppCodeOnlyOnce(): void
    {
        // Un code e-mail encore en base (demandé avant le correctif) n'est même plus consulté.
        $this->accounts->expects(self::never())->method('findLatestOtp');
        $svc = $this->service();

        $wrong = $svc->verifyOtp(['email' => self::EMAIL, 'code' => $this->appCode() === '000000' ? '111111' : '000000']);
        self::assertSame('INVALID_CREDENTIALS', $wrong['payload']['error']);
        self::assertSame('totp_required', $wrong['payload']['reason']);

        $code = $this->appCode();
        $ok = $svc->verifyOtp(['email' => self::EMAIL, 'code' => $code]);
        self::assertSame('NO_TENANT', $ok['payload']['error'], 'code de l’application accepté');

        $replay = $svc->verifyOtp(['email' => self::EMAIL, 'code' => $code]);
        self::assertSame('INVALID_CREDENTIALS', $replay['payload']['error'], 'anti-rejeu');
    }

    public function testVerifyOtpAcceptsARecoveryCodeOnce(): void
    {
        $svc = $this->service();
        $codes = $this->recovery?->regenerate(42) ?? [];

        self::assertSame('NO_TENANT', $svc->verifyOtp(['email' => self::EMAIL, 'code' => $codes[0]])['payload']['error']);
        self::assertSame('INVALID_CREDENTIALS', $svc->verifyOtp(['email' => self::EMAIL, 'code' => $codes[0]])['payload']['error']);
    }

    public function testVerifyOtpLocksAfterTooManyWrongCodes(): void
    {
        $svc = $this->service();
        $bad = $this->appCode() === '000000' ? '111111' : '000000';
        for ($i = 0; $i < GameSecondFactorGate::MAX_FAILED_ATTEMPTS; $i++) {
            self::assertSame(401, $svc->verifyOtp(['email' => self::EMAIL, 'code' => $bad])['status']);
        }

        $locked = $svc->verifyOtp(['email' => self::EMAIL, 'code' => $this->appCode()]);

        self::assertSame(429, $locked['status']);
        self::assertSame('INVALID_CREDENTIALS', $locked['payload']['error'], 'code connu de la DLL');
    }

    public function testVerifyOtpKeepsTheEmailCodeWithoutTheApp(): void
    {
        $this->accounts->method('findLatestOtp')->willReturn([
            'id' => 9, 'code_hash' => hash('sha256', '123456'), 'attempts' => 0,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
        ]);
        $this->accounts->expects(self::once())->method('consumeOtp')->with(9);

        $res = $this->service(false)->verifyOtp(['email' => self::EMAIL, 'code' => '123456']);

        self::assertSame('NO_TENANT', $res['payload']['error']);
    }

    public function testPasswordAloneIsRefusedWhenTheAppIsActive(): void
    {

        $res = $this->service()->authPassword(['email' => self::EMAIL, 'password' => 'motdepasse']);

        self::assertSame(401, $res['status']);
        self::assertSame('INVALID_CREDENTIALS', $res['payload']['error']);
        self::assertSame('totp_required', $res['payload']['reason']);
    }

    public function testPasswordStillWorksWithoutTheApp(): void
    {

        $res = $this->service(false)->authPassword(['email' => self::EMAIL, 'password' => 'motdepasse']);

        self::assertSame('NO_TENANT', $res['payload']['error']);
    }

    public function testDemoAccountsAreNeverAskedForTheAppCode(): void
    {
        $this->email->expects(self::once())->method('send');

        $this->service(true, ['email' => 'pilote@demo.local'])->requestOtp(['email' => 'pilote@demo.local']);
    }

    public function testSteamAloneIsRefusedWhenTheAppIsActive(): void
    {
        $res = $this->service()->steamExchange(['device_id' => '0123456789abcdef', 'steam_id' => self::STEAM]);

        self::assertSame('STEAM_NOT_LINKED', $res['payload']['error']);
        self::assertSame('totp_required', $res['payload']['reason']);
    }

    public function testSteamWithThisDevicePairingFromAVerifiedSessionIsAccepted(): void
    {
        $token = str_repeat('a', 64);
        $this->accounts->method('findPairing')->willReturn(['id' => 5, 'pairing_token_hash' => hash('sha256', $token)]);
        $this->accounts->method('findPairingSessionCreatedAt')->willReturn(gmdate('Y-m-d H:i:s'));

        $res = $this->service()->steamExchange(['device_id' => '0123456789abcdef', 'steam_id' => self::STEAM, 'pairing_token' => $token]);

        self::assertSame('NO_TENANT', $res['payload']['error']);
    }

    public function testSteamPairingFromASessionOpenedBeforeTheFixIsRefused(): void
    {
        $token = str_repeat('a', 64);
        $this->accounts->method('findPairing')->willReturn(['id' => 5, 'pairing_token_hash' => hash('sha256', $token)]);
        $this->accounts->method('findPairingSessionCreatedAt')->willReturn('2026-10-15 08:00:00');

        $res = $this->service()->steamExchange(['device_id' => '0123456789abcdef', 'steam_id' => self::STEAM, 'pairing_token' => $token]);

        self::assertSame('STEAM_NOT_LINKED', $res['payload']['error']);
    }

    public function testSteamAloneStillWorksWithoutTheApp(): void
    {
        $res = $this->service(false)->steamExchange(['device_id' => '0123456789abcdef', 'steam_id' => self::STEAM]);

        self::assertSame('NO_TENANT', $res['payload']['error']);
    }

    public function testRestoreRevokesASessionOpenedBeforeTheFix(): void
    {
        $this->accounts->method('findSessionByRefreshHash')->willReturn([
            'id' => 77, 'account_id' => 1, 'tenant_id' => 3, 'device_id' => '0123456789abcdef',
            'created_at' => '2026-10-20 10:00:00', 'refresh_expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
        ]);
        $this->accounts->expects(self::once())->method('revokeSession')->with(77);

        $res = $this->service()->restore(['refresh_token' => str_repeat('b', 64)]);

        self::assertSame(401, $res['status']);
        self::assertSame('SESSION_EXPIRED', $res['payload']['error'], 'la DLL efface le jeton et redemande la connexion');
    }

    public function testRestoreKeepsASessionOpenedAfterTheFix(): void
    {
        $this->accounts->method('findSessionByRefreshHash')->willReturn([
            'id' => 78, 'account_id' => 1, 'tenant_id' => 3, 'device_id' => '0123456789abcdef',
            'created_at' => gmdate('Y-m-d H:i:s'), 'refresh_expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
        ]);
        $this->accounts->expects(self::never())->method('revokeSession');

        $res = $this->service()->restore(['refresh_token' => str_repeat('b', 64)]);

        self::assertSame('NO_TENANT', $res['payload']['error']);
    }

    public function testASessionOlderThanTheAppActivationIsNotTrusted(): void
    {
        $users = $this->createMock(UserRepository::class);
        $gate = new GameSecondFactorGate($users, $this->createMock(LoginSecurityOtpService::class), $this->createMock(FileRateLimiter::class));
        $activatedLocal = date('Y-m-d H:i:s', time() - 600);

        self::assertFalse($gate->sessionOpenedWithFactor(gmdate('Y-m-d H:i:s', time() - 3600), [['totp_confirmed_at' => $activatedLocal]]));
        self::assertTrue($gate->sessionOpenedWithFactor(gmdate('Y-m-d H:i:s', time() - 60), [['totp_confirmed_at' => $activatedLocal]]));
        self::assertFalse($gate->sessionOpenedWithFactor(null, [['totp_confirmed_at' => $activatedLocal]]));
    }
}
