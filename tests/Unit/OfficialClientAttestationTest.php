<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ComspecApiKeyAuth;
use App\Support\OfficialClientAttestation;
use PHPUnit\Framework\TestCase;

final class OfficialClientAttestationTest extends TestCase
{
    private const SECRET = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    /** @var array<string, array{in_env: bool, env: mixed, getenv: string|false}> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['COMSPEC_CLIENT_ATTEST_SECRET', 'ATAK_DEFAULT_TENANT_ID', 'APP_ATAK_DEFAULT_TENANT_ID', 'TACTICAL_API_ALLOW_SESSION', 'APP_ENV', 'TACTICAL_API_STRICT', 'X_COMSPEC_KEY', 'ATAK_INTEL_SECRET'] as $key) {
            $this->savedEnv[$key] = [
                'in_env' => array_key_exists($key, $_ENV),
                'env' => $_ENV[$key] ?? null,
                'getenv' => getenv($key),
            ];
        }
        $this->setEnv('COMSPEC_CLIENT_ATTEST_SECRET', self::SECRET);
        $this->setEnv('ATAK_DEFAULT_TENANT_ID', null);
        $this->setEnv('APP_ATAK_DEFAULT_TENANT_ID', null);
        $this->setEnv('TACTICAL_API_ALLOW_SESSION', 'false');
        $this->setEnv('APP_ENV', 'testing');
        $this->setEnv('TACTICAL_API_STRICT', null);
        $this->setEnv('X_COMSPEC_KEY', null);
        $this->setEnv('ATAK_INTEL_SECRET', null);
        OfficialClientAttestation::setNowForTests(1_700_000_000);
        unset(
            $_SERVER['HTTP_X_COMSPEC_CLIENT_KEY'],
            $_SERVER['HTTP_X_COMSPEC_CLIENT_TS'],
            $_SERVER['HTTP_X_COMSPEC_CLIENT_NONCE'],
            $_SERVER['HTTP_X_COMSPEC_CLIENT_PROOF'],
            $_SERVER['HTTP_X_COMSPEC_KEY'],
            $_SERVER['HTTP_AUTHORIZATION']
        );
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/public/api/atak/client-init';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        ComspecApiKeyAuth::resetForTests();
    }

    protected function tearDown(): void
    {
        OfficialClientAttestation::setNowForTests(null);
        ComspecApiKeyAuth::resetForTests();
        unset(
            $_SERVER['HTTP_X_COMSPEC_CLIENT_KEY'],
            $_SERVER['HTTP_X_COMSPEC_CLIENT_TS'],
            $_SERVER['HTTP_X_COMSPEC_CLIENT_NONCE'],
            $_SERVER['HTTP_X_COMSPEC_CLIENT_PROOF']
        );
        foreach ($this->savedEnv as $key => $saved) {
            if ($saved['in_env']) {
                $_ENV[$key] = $saved['env'];
            } else {
                unset($_ENV[$key]);
            }
            putenv($saved['getenv'] === false ? $key : $key . '=' . $saved['getenv']);
        }
        parent::tearDown();
    }

    public function testSecretOffLeavesCurrentClientsAlone(): void
    {
        $this->setEnv('COMSPEC_CLIENT_ATTEST_SECRET', null);
        self::assertSame('off', OfficialClientAttestation::secretState());
        self::assertFalse(OfficialClientAttestation::pathRequiresProof('/api/atak/client-init'));
        self::assertNull(ComspecApiKeyAuth::enforceForTacticalPath('/api/atak/client-init'));
    }

    public function testCopiedClientIsRejected(): void
    {
        $response = ComspecApiKeyAuth::enforceForTacticalPath('/api/atak/client-init');
        self::assertNotNull($response);
        self::assertSame(401, $response->statusCode());
        self::assertStringContainsString('client_untrusted', $response->body());
        self::assertStringNotContainsString('unauthorized', $response->body());
    }

    public function testOfficialProofIsAccepted(): void
    {
        $this->presentProof('POST', '/api/atak/client-init');
        self::assertSame('valid', OfficialClientAttestation::presentedStatus());
        self::assertNull(ComspecApiKeyAuth::enforceForTacticalPath('/api/atak/client-init'));
    }

    public function testProofDoesNotTravelToAnotherPath(): void
    {
        $this->presentProof('POST', '/api/atak/client-init');
        $_SERVER['REQUEST_URI'] = '/public/api/atak/position';
        self::assertSame('invalid', OfficialClientAttestation::presentedStatus());
        $response = ComspecApiKeyAuth::enforceForTacticalPath('/api/atak/position');
        self::assertSame(401, $response?->statusCode());
        self::assertStringContainsString('client_untrusted', (string) $response?->body());
    }

    public function testExpiredProofIsRejected(): void
    {
        $this->presentProof('POST', '/api/atak/client-init', 1_700_000_000 - 181);
        self::assertSame('invalid', OfficialClientAttestation::presentedStatus());
    }

    public function testBrokenSecretFailsClosed(): void
    {
        $this->setEnv('COMSPEC_CLIENT_ATTEST_SECRET', 'pas-un-secret');
        self::assertSame('broken', OfficialClientAttestation::secretState());
        $this->presentProof('POST', '/api/atak/client-init');
        self::assertSame('invalid', OfficialClientAttestation::presentedStatus());
    }

    public function testPublicPagesStayOpen(): void
    {
        foreach (['/api/atak/version', '/api/atak/ping', '/api/atak/tiles', '/api/atak/map-photo/upload'] as $path) {
            self::assertFalse(OfficialClientAttestation::pathRequiresProof($path), $path);
            self::assertNull(ComspecApiKeyAuth::enforceForTacticalPath($path));
        }
    }

    public function testLinkAndAccountRoutesRequireProof(): void
    {
        foreach ([
            '/api/atak/game-link/redeem',
            '/api/atak/pair/start',
            '/api/game/v1/auth/password',
            '/api/game/v1/session/restore',
            '/api/atak/startup-check',
        ] as $path) {
            self::assertTrue(OfficialClientAttestation::pathRequiresProof($path), $path);
        }
    }

    public function testCanonicalKeepsRawQueryAfterPublicPrefix(): void
    {
        $_SERVER['REQUEST_URI'] = '/public/api/atak/markers?mapId=Altis';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        self::assertSame('/api/atak/markers?mapId=Altis', OfficialClientAttestation::canonicalPathAndQuery());
    }

    public function testMaskMatchesTheModule(): void
    {
        $cs = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/OfficialClientProof.cs');
        self::assertStringContainsString('COMSPEC-CLIENT-V1\n', $cs);
        foreach (OfficialClientAttestation::clientMask() as $byte) {
            self::assertStringContainsString('0x' . strtoupper(sprintf('%02X', $byte)), $cs);
        }
    }

    public function testXorPackRoundTrip(): void
    {
        $secret = hex2bin(self::SECRET);
        self::assertIsString($secret);
        $packed = OfficialClientAttestation::xorPack($secret);
        $mask = OfficialClientAttestation::clientMask();
        $back = '';
        foreach ($packed as $i => $byte) {
            $back .= chr($byte ^ $mask[$i % count($mask)]);
        }
        self::assertSame($secret, $back);
    }

    private function presentProof(string $method, string $path, int $ts = 1_700_000_000): void
    {
        $nonce = 'abc123def456abc123def456abc123de';
        $pathAndQuery = $path;
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = '/public' . $path;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $canonical = OfficialClientAttestation::canonical($method, $pathAndQuery, OfficialClientAttestation::KEY_ID, (string) $ts, $nonce);
        $secret = hex2bin(self::SECRET);
        self::assertIsString($secret);
        $_SERVER['HTTP_X_COMSPEC_CLIENT_KEY'] = OfficialClientAttestation::KEY_ID;
        $_SERVER['HTTP_X_COMSPEC_CLIENT_TS'] = (string) $ts;
        $_SERVER['HTTP_X_COMSPEC_CLIENT_NONCE'] = $nonce;
        $_SERVER['HTTP_X_COMSPEC_CLIENT_PROOF'] = hash_hmac('sha256', $canonical, $secret);
    }

    private function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key]);
            putenv($key);
            return;
        }
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}
