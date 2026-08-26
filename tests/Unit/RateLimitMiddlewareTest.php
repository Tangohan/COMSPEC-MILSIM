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
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'], $_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    public function testApiOverLimitReturns429WithRetryAfter(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/atak/position';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.11';

        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('tooManyAttempts')->willReturn(true);
        $mw = new RateLimitMiddleware($limiter);
        $response = $mw(new Request(), static fn () => self::fail('next must not run'));

        self::assertSame(429, $response->statusCode());
        self::assertSame('60', $response->headerValue('Retry-After'));
        $payload = json_decode($response->body(), true);
        self::assertIsArray($payload);
        self::assertSame('too_many_requests', $payload['error'] ?? null);
        self::assertArrayHasKey('retry_after', $payload);
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
