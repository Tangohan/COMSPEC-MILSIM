<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\RateLimitMiddleware;
use App\Services\Security\FileRateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimitMiddlewareTest extends TestCase
{
    private bool $hadPost = false;

    private mixed $savedPost = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Un test précédent peut avoir retiré $_POST (unset) : Request le copie dans une propriété typée array.
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_POST = [];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'], $_SERVER['REMOTE_ADDR']);
        if ($this->hadPost) {
            $_POST = $this->savedPost;
        } else {
            unset($_POST);
        }
        parent::tearDown();
    }

    /** NET-027 : le délai annoncé est le temps qui reste dans la fenêtre du limiteur, identique dans l'en-tête et le corps. */
    public function testApiOverLimitReturns429WithRetryAfter(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/atak/position';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.11';

        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('tooManyAttempts')->willReturn(true);
        $limiter->method('secondsUntilReset')->willReturnCallback(
            static fn (string $key, int $window): int => $key === 'rl:/api/atak/position:POST:203.0.113.11' && $window === 60 ? 37 : 999
        );
        $mw = new RateLimitMiddleware($limiter);
        $response = $mw(new Request(), static fn () => self::fail('next must not run'));

        self::assertSame(429, $response->statusCode());
        self::assertSame('37', $response->headerValue('Retry-After'));
        $payload = json_decode($response->body(), true);
        self::assertIsArray($payload);
        self::assertSame('too_many_requests', $payload['error'] ?? null);
        self::assertSame(37, $payload['retry_after'] ?? null);
    }

    public function testAtakGetIsNotGuestScrapeLimited(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/atak/markers';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.11';

        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->expects($this->never())->method('tooManyAttempts');
        $ok = new Response();
        $mw = new RateLimitMiddleware($limiter);
        $response = $mw(new Request(), static fn () => $ok);
        self::assertSame($ok, $response);
    }

    public function testExpensiveTerrainOverlayIsLimitedByClientIp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/atak/terrain/hillshade?mapId=1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.12';

        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->expects($this->once())
            ->method('tooManyAttempts')
            ->with(
                'rl:atak_terrain_overlay:/api/atak/terrain/hillshade:ip:203.0.113.12',
                30,
                60
            )
            ->willReturn(true);
        $limiter->method('secondsUntilReset')->willReturn(42);
        $mw = new RateLimitMiddleware($limiter);
        $response = $mw(new Request(), static fn () => self::fail('next must not run'));

        self::assertSame(429, $response->statusCode());
        self::assertSame('42', $response->headerValue('Retry-After'));
    }

    public function testReconUploadsAreNotGuestScrapeLimited(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/uploads/recon/bravo.png';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.11';

        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->expects($this->never())->method('tooManyAttempts');
        $ok = new Response();
        $mw = new RateLimitMiddleware($limiter);
        $response = $mw(new Request(), static fn () => $ok);
        self::assertSame($ok, $response);
    }
}
