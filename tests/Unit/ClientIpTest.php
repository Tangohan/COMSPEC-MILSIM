<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\AntiScraperMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Services\Security\FileRateLimiter;
use App\Support\ClientIp;
use PHPUnit\Framework\TestCase;

/**
 * NET-010 : X-Forwarded-For, Client-IP et CF-Connecting-IP sont écrits par le client. Lus en premier, ils lui
 * laissaient choisir l'adresse vue par la limite de /login, les blocages par IP et le piège anti-miroir.
 */
final class ClientIpTest extends TestCase
{
    private const SERVER_KEYS = [
        'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_CF_CONNECTING_IP',
        'REQUEST_METHOD', 'REQUEST_URI', 'SCRIPT_NAME', 'HTTP_USER_AGENT',
    ];

    /** @var array<string, mixed> */
    private array $savedServer = [];

    private mixed $savedTrusted = null;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::SERVER_KEYS as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $this->savedServer[$key] = $_SERVER[$key];
            }
            unset($_SERVER[$key]);
        }
        $this->savedTrusted = $_ENV['TRUSTED_PROXIES'] ?? null;
        unset($_ENV['TRUSTED_PROXIES']);
        ClientIp::resetTrustedProxies();
    }

    protected function tearDown(): void
    {
        foreach (self::SERVER_KEYS as $key) {
            unset($_SERVER[$key]);
        }
        foreach ($this->savedServer as $key => $value) {
            $_SERVER[$key] = $value;
        }
        if ($this->savedTrusted === null) {
            unset($_ENV['TRUSTED_PROXIES']);
        } else {
            $_ENV['TRUSTED_PROXIES'] = $this->savedTrusted;
        }
        ClientIp::resetTrustedProxies();
        parent::tearDown();
    }

    public function testForwardedHeadersFromADirectClientAreIgnored(): void
    {
        self::assertSame('203.0.113.7', ClientIp::resolve([
            'REMOTE_ADDR' => '203.0.113.7',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.99',
            'HTTP_CLIENT_IP' => '198.51.100.98',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.97',
        ]));
    }

    public function testLoopbackProxyForwardsTheClientAndIgnoresForgedLeftHops(): void
    {
        self::assertSame('203.0.113.9', ClientIp::resolve([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.99, 203.0.113.9',
        ]));
        self::assertSame('203.0.113.9', ClientIp::resolve([
            'REMOTE_ADDR' => '::1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9, 127.0.0.1',
        ]));
        self::assertSame('203.0.113.20', ClientIp::resolve([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.20',
        ]), 'mandataire local sans X-Forwarded-For (tunnel) : CF-Connecting-IP');
        self::assertSame('127.0.0.1', ClientIp::resolve([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => 'pas-une-adresse',
        ]), 'chaîne illisible : l\'adresse du mandataire, jamais une valeur inventée');
    }

    public function testTrustedProxiesFromEnvironmentAcceptCidr(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.0/8, 2001:db8::/32, n\'importe quoi, 192.0.2.1/99';
        ClientIp::resetTrustedProxies();

        self::assertSame('203.0.113.5', ClientIp::resolve([
            'REMOTE_ADDR' => '10.1.2.3',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1, 203.0.113.5, 10.9.9.9',
        ]));
        self::assertSame('198.51.100.77', ClientIp::resolve([
            'REMOTE_ADDR' => '2001:db8::5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.77',
        ]));
        self::assertSame('11.0.0.1', ClientIp::resolve([
            'REMOTE_ADDR' => '11.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
        ]), 'hors de la liste : en-tête ignoré');
        self::assertFalse(ClientIp::isTrustedProxy('192.0.2.1'), 'entrée invalide (/99) ignorée');
    }

    public function testCloudflareAddressesUseTheHeaderCloudflareWrites(): void
    {
        self::assertSame('203.0.113.30', ClientIp::resolve([
            'REMOTE_ADDR' => '172.70.1.2',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.30',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.99',
        ]));
        self::assertSame('203.0.113.31', ClientIp::resolve([
            'REMOTE_ADDR' => '2a06:98c1::5',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.31',
        ]));
        self::assertSame('172.70.1.2', ClientIp::resolve([
            'REMOTE_ADDR' => '172.70.1.2',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.99',
        ]), 'sans CF-Connecting-IP : l\'adresse de Cloudflare, jamais X-Forwarded-For');
        foreach (['172.63.255.255', '172.72.0.1'] as $outside) {
            self::assertSame($outside, ClientIp::resolve([
                'REMOTE_ADDR' => $outside,
                'HTTP_CF_CONNECTING_IP' => '198.51.100.99',
            ]), 'juste hors de 172.64.0.0/13 : en-tête ignoré');
        }
    }

    public function testMissingOrInvalidRemoteAddressAndMappedIpv4(): void
    {
        self::assertSame('', ClientIp::resolve([]));
        self::assertSame('', ClientIp::resolve(['REMOTE_ADDR' => 'inconnue', 'HTTP_X_FORWARDED_FOR' => '203.0.113.1']));
        self::assertSame('203.0.113.8', ClientIp::resolve(['REMOTE_ADDR' => '::ffff:203.0.113.8']));
        self::assertSame('203.0.113.4', ClientIp::resolve([
            'REMOTE_ADDR' => '::ffff:127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.4',
        ]));
    }

    public function testRequestIpUsesTheResolver(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';
        $_SERVER['HTTP_CLIENT_IP'] = '198.51.100.98';

        self::assertSame('203.0.113.10', (new Request())->ip());
    }

    public function testLoginRateLimitKeyCannotBeChosenByTheClient(): void
    {
        $this->bindRequest('POST', '/login');
        $keys = [];
        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('tooManyAttempts')->willReturnCallback(static function (string $key) use (&$keys): bool {
            $keys[] = $key;

            return false;
        });
        $ok = new Response();

        self::assertSame($ok, (new RateLimitMiddleware($limiter))(new Request(), static fn () => $ok));
        self::assertNotEmpty($keys);
        foreach ($keys as $key) {
            self::assertStringContainsString('203.0.113.10', $key);
            self::assertStringNotContainsString('198.51.100.99', $key);
        }
    }

    public function testMirrorTrapBansTheVisitorNotTheAddressItClaims(): void
    {
        $this->bindRequest('GET', AntiScraperMiddleware::TRAP_PATH);
        $hits = [];
        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('hit')->willReturnCallback(static function (string $key) use (&$hits): int {
            $hits[] = $key;

            return 1;
        });
        $limiter->method('attempts')->willReturn(0);

        $response = (new AntiScraperMiddleware($limiter))(new Request(), static fn () => new Response());

        self::assertSame(403, $response->statusCode());
        self::assertSame(['rl:mirror_trap:203.0.113.10'], $hits);
    }

    public function testNoOtherCodeReadsForwardedHeaders(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];
        foreach (['app', 'bootstrap', 'config', 'routes', 'public'] as $dir) {
            if (!is_dir($root . '/' . $dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $path = str_replace('\\', '/', $file->getPathname());
                if (str_ends_with($path, '/app/Support/ClientIp.php')) {
                    continue;
                }
                $src = (string) file_get_contents($path);
                if (preg_match('/HTTP_(?:X_FORWARDED_FOR|CLIENT_IP|CF_CONNECTING_IP|X_REAL_IP)/', $src)) {
                    $offenders[] = substr($path, strlen(str_replace('\\', '/', $root)) + 1);
                }
            }
        }

        self::assertSame([], $offenders, 'Adresse du client : passer par App\Support\ClientIp::resolve()');
    }

    private function bindRequest(string $method, string $uri): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.97';
    }
}
