<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\RateLimitMiddleware;
use App\Services\Security\FileRateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * NET-028 : POST /api/chat (session web) et POST /api/atak/salute n'avaient aucun frein. Plafonds posés dans
 * RateLimitMiddleware : 120 écritures du tchat et 30 SALUTE par minute, par compte pour une session web, par IP sinon.
 * Usage réel bien en dessous : une pression sur Resynch renvoie au plus 17 lignes, une fois par 15 s ; les pages web
 * n'écrivent qu'à chaque envoi d'un joueur.
 */
final class ChatWriteRateLimitTest extends TestCase
{
    private const SERVER_KEYS = ['REQUEST_METHOD', 'REQUEST_URI', 'SCRIPT_NAME', 'REMOTE_ADDR'];

    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    private bool $hadPost = false;

    private mixed $savedPost = null;

    /** @var list<string> */
    private array $keys = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Un test précédent peut avoir retiré $_POST (unset) : Request le copie dans une propriété typée array.
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_POST = [];
        foreach (self::SERVER_KEYS as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $this->savedServer[$key] = $_SERVER[$key];
            }
        }
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $limiter = new FileRateLimiter();
        foreach ($this->keys as $key) {
            $limiter->clear($key);
        }
        foreach (self::SERVER_KEYS as $key) {
            if (array_key_exists($key, $this->savedServer)) {
                $_SERVER[$key] = $this->savedServer[$key];
            } else {
                unset($_SERVER[$key]);
            }
        }
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        if ($this->hadPost) {
            $_POST = $this->savedPost;
        } else {
            unset($_POST);
        }
        parent::tearDown();
    }

    public function testWebSessionWritesAreCountedPerAccount(): void
    {
        $_SESSION = ['user_id' => 42, 'tenant_id' => 7];

        self::assertSame([['rl:chat_write:uid:42', 120, 60]], $this->countedFor('POST', '/api/chat', '203.0.113.21'));
        self::assertSame([['rl:salute:uid:42', 30, 60]], $this->countedFor('POST', '/api/atak/salute', '203.0.113.21'));
    }

    /** Jeu (clé d'accès, sans session web) et invité : par IP, comme /api/atak/position. */
    public function testWithoutWebSessionWritesAreCountedPerIp(): void
    {
        self::assertSame([['rl:chat_write:ip:203.0.113.22', 120, 60]], $this->countedFor('POST', '/api/chat', '203.0.113.22'));
        self::assertSame([['rl:salute:ip:203.0.113.22', 30, 60]], $this->countedFor('POST', '/api/atak/salute', '203.0.113.22'));
    }

    /** Lectures du tchat (relève du jeu, pages web) et autres routes du tchat : aucun nouveau frein. */
    public function testReadsAndOtherChatRoutesAreNotCounted(): void
    {
        self::assertSame([], $this->countedFor('GET', '/api/chat', '203.0.113.23'), 'relève, invité');
        self::assertSame([], $this->countedFor('POST', '/api/chat/channels', '203.0.113.23'));
        $_SESSION = ['user_id' => 42, 'tenant_id' => 7];
        self::assertSame([], $this->countedFor('GET', '/api/chat', '203.0.113.23'), 'relève, session web');
        self::assertSame([], $this->countedFor('GET', '/api/atak/salute', '203.0.113.23'));
    }

    public function testOverTheLimitTheWriteIsRefusedWith429AndTheTimeLeft(): void
    {
        foreach (['/api/chat', '/api/atak/salute'] as $path) {
            $limiter = $this->createMock(FileRateLimiter::class);
            $limiter->method('tooManyAttempts')->willReturn(true);
            $limiter->method('secondsUntilReset')->willReturn(23);

            $response = $this->through('POST', $path, '203.0.113.24', $limiter, static fn () => self::fail('la requête aurait dû être freinée'));

            self::assertSame(429, $response->statusCode(), $path);
            self::assertSame('23', $response->headerValue('Retry-After'), $path);
            $json = json_decode($response->body(), true);
            self::assertIsArray($json, $path);
            self::assertSame('too_many_requests', $json['error'] ?? null, $path);
            self::assertSame(23, $json['retry_after'] ?? null, $path);
        }
    }

    /** Vrai limiteur : 120 écritures passent dans la minute, la 121e est freinée ; 30 SALUTE, puis le 31e. */
    public function testRealLimiterLetsTheThresholdPassAndRefusesTheNextWrite(): void
    {
        $ip = '198.18.' . random_int(1, 254) . '.' . random_int(1, 254);
        foreach (['/api/chat' => [120, 'rl:chat_write:ip:'], '/api/atak/salute' => [30, 'rl:salute:ip:']] as $path => [$max, $prefix]) {
            $this->keys[] = $prefix . $ip;
            $passed = 0;
            for ($i = 0; $i < $max; $i++) {
                $ok = new Response();
                if ($this->through('POST', $path, $ip, new FileRateLimiter(), static fn () => $ok) === $ok) {
                    ++$passed;
                }
            }
            $refused = $this->through('POST', $path, $ip, new FileRateLimiter(), static fn () => new Response());

            self::assertSame($max, $passed, $path . ' : tout passe jusqu’au plafond');
            self::assertSame(429, $refused->statusCode(), $path . ' : l’écriture suivante est freinée');
            self::assertGreaterThanOrEqual(1, (int) $refused->headerValue('Retry-After'));
            self::assertLessThanOrEqual(60, (int) $refused->headerValue('Retry-After'));
        }
    }

    /**
     * Clés et plafonds demandés au limiteur pour cette requête (le limiteur simulé laisse passer).
     *
     * @return list<array{0: string, 1: int, 2: int}>
     */
    private function countedFor(string $method, string $path, string $ip): array
    {
        $seen = [];
        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('tooManyAttempts')->willReturnCallback(
            static function (string $key, int $max, int $window) use (&$seen): bool {
                $seen[] = [$key, $max, $window];

                return false;
            }
        );
        $ok = new Response();
        $response = $this->through($method, $path, $ip, $limiter, static fn () => $ok);
        self::assertSame($ok, $response, $method . ' ' . $path . ' : la requête continue');

        return $seen;
    }

    private function through(string $method, string $path, string $ip, FileRateLimiter $limiter, callable $next): Response
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REMOTE_ADDR'] = $ip;

        return (new RateLimitMiddleware($limiter))(new Request(), $next);
    }
}
