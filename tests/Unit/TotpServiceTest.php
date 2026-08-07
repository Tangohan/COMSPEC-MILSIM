<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Auth\TotpSecretCipher;
use App\Services\Auth\TotpService;
use PHPUnit\Framework\TestCase;

final class TotpServiceTest extends TestCase
{
    public function testGenerateSecretIsBase32(): void
    {
        $svc = new TotpService();
        $secret = $svc->generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertGreaterThanOrEqual(16, strlen($secret));
    }

    public function testVerifyAcceptsCurrentCode(): void
    {
        $svc = new TotpService();
        // Secret de test connu (RFC / vectors communs) — "Hello!" en base32 partiel
        $secret = $svc->generateSecret();
        $code = $svc->currentCode($secret, 1_700_000_000);
        $this->assertSame(6, strlen($code));
        $this->assertTrue($svc->verify($secret, $code, 1, 1_700_000_000));
        $this->assertFalse($svc->verify($secret, '000000', 0, 1_700_000_000));
    }

    public function testProvisioningUriContainsIssuerAndSecret(): void
    {
        $svc = new TotpService();
        $uri = $svc->provisioningUri('JBSWY3DPEHPK3PXP', 'ops@example.com', 'Athena');
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=Athena', $uri);
    }

    public function testCipherRoundTrip(): void
    {
        $cipher = new TotpSecretCipher();
        $plain = 'JBSWY3DPEHPK3PXP';
        $enc = $cipher->encrypt($plain);
        $this->assertStringStartsWith('v1:', $enc);
        $this->assertNotSame($plain, $enc);
        $this->assertSame($plain, $cipher->decrypt($enc));
    }

    public function testFormatSecretForDisplay(): void
    {
        $svc = new TotpService();
        $this->assertSame('ABCD EFGH IJKL', $svc->formatSecretForDisplay('ABCDEFGHIJKL'));
    }
}
