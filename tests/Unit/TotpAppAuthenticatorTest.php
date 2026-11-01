<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Auth\RecoveryCodeService;
use App\Services\Auth\TotpReplayGuard;
use App\Services\Auth\TotpService;
use PDO;
use PHPUnit\Framework\TestCase;

/** Application d'authentification : vecteurs RFC 6238, fenêtre, anti-rejeu, codes de secours, e-mail en secours seulement si choisi. */
final class TotpAppAuthenticatorTest extends TestCase
{
    private function sqlite(): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite absent');
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    public function testRfc6238Sha1Vectors(): void
    {
        // RFC 6238 annexe B : clé ASCII « 12345678901234567890 », 8 chiffres tronqués à 6 ici.
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $svc = new TotpService();
        self::assertSame('287082', $svc->currentCode($secret, 59));
        self::assertSame('081804', $svc->currentCode($secret, 1111111109));
        self::assertSame('005924', $svc->currentCode($secret, 1234567890));
    }

    public function testSecretIsCryptographicBase32AndUriCarriesIssuer(): void
    {
        $svc = new TotpService();
        $a = $svc->generateSecret();
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $a);
        self::assertNotSame($a, $svc->generateSecret());
        $uri = $svc->provisioningUri($a, 'joueur@example.com', 'ATHENA');
        self::assertStringStartsWith('otpauth://totp/ATHENA%3Ajoueur%40example.com?secret=' . $a, $uri);
        self::assertStringContainsString('&issuer=ATHENA', $uri);
    }

    public function testMatchStepReturnsTheStepWithinTheWindowOnly(): void
    {
        $svc = new TotpService();
        $secret = $svc->generateSecret();
        $now = 1_800_000_015;
        $step = intdiv($now, 30);
        self::assertSame($step, $svc->matchStep($secret, $svc->currentCode($secret, $now), 1, $now));
        self::assertSame($step - 1, $svc->matchStep($secret, $svc->currentCode($secret, $now - 30), 1, $now));
        self::assertSame($step + 1, $svc->matchStep($secret, $svc->currentCode($secret, $now + 30), 1, $now));
        self::assertNull($svc->matchStep($secret, $svc->currentCode($secret, $now - 60), 1, $now));
        self::assertNull($svc->matchStep($secret, 'abc', 1, $now));
    }

    public function testACodeIsAcceptedOnlyOnce(): void
    {
        $guard = new TotpReplayGuard($this->sqlite());
        self::assertTrue($guard->claim(7, 1000));
        self::assertFalse($guard->claim(7, 1000), 'même code rejoué');
        self::assertFalse($guard->claim(7, 999), 'code plus ancien encore dans la fenêtre');
        self::assertTrue($guard->claim(7, 1001));
        self::assertTrue($guard->claim(8, 1000), 'autre membre indépendant');
    }

    public function testRecoveryCodesAreTenHashedAndSingleUse(): void
    {
        $pdo = $this->sqlite();
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->exec('CREATE TABLE user_recovery_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, code_hash VARCHAR(64) NOT NULL, created_at DATETIME, used_at DATETIME NULL)');
        $svc = new RecoveryCodeService($pdo);
        $codes = $svc->regenerate(5);
        self::assertCount(10, $codes);
        self::assertCount(10, array_unique($codes));
        $stored = $pdo->query('SELECT code_hash FROM user_recovery_codes')->fetchAll(PDO::FETCH_COLUMN);
        self::assertNotContains($codes[0], $stored, 'jamais en clair en base');
        self::assertContains(RecoveryCodeService::hashCode($codes[0]), $stored);

        self::assertTrue($svc->consume(5, strtolower($codes[0])));
        self::assertFalse($svc->consume(5, $codes[0]), 'usage unique');
        self::assertFalse($svc->consume(6, $codes[1]), 'code d’un autre membre');
        self::assertSame(9, $svc->remaining(5));
    }

    public function testNoEmailCodeWhenTheAppIsActiveUnlessThePlayerKeptIt(): void
    {
        $svc = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Auth/LoginSecurityOtpService.php');
        self::assertStringContainsString('if ($this->isTotpEnabled($user) && !$this->isEmailOtpEnabled($user)) {', $svc);
        self::assertMatchesRegularExpression('/function canUseEmailFallback\(array \$user\): bool\s*\{\s*return \$this->isEmailOtpEnabled\(\$user\);/', $svc);
        foreach (['app/Controllers/Web/AccountController.php', 'app/Services/Ops/OpsConsoleStepUp.php', 'app/Services/Auth/LoginSecurityOtpService.php'] as $file) {
            self::assertStringNotContainsString('totpService()->verify(', (string) file_get_contents(dirname(__DIR__, 2) . '/' . $file), $file . ' doit passer par verifyTotpCode (anti-rejeu)');
        }
    }
}
