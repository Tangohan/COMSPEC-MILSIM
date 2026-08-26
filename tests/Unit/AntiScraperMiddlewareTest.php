<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AntiScraperMiddleware;
use App\Services\Security\FileRateLimiter;
use PHPUnit\Framework\TestCase;

final class AntiScraperMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Session::forget('user_id');
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    public function testTacticalRosterRoutesAreNotClassedAsScraper(): void
    {
        $this->bindRequest('GET', '/api/units', 'Wget/1.21');
        $ok = new Response();
        $mw = new AntiScraperMiddleware($this->trapBannedLimiter());
        $response = $mw(new Request(), static fn () => $ok);
        self::assertSame($ok, $response);
    }

    public function testAtakApiIsNotClassedAsScraperEvenWithBlockedUserAgent(): void
    {
        $this->bindRequest('GET', '/api/atak/position', 'Wget/1.21');
        $ok = new Response();
        $mw = new AntiScraperMiddleware($this->trapBannedLimiter());
        $response = $mw(new Request(), static fn () => $ok);
        self::assertSame($ok, $response);
    }

    public function testApiKeyBypassesIpTrapBan(): void
    {
        $this->bindRequest('POST', '/api/atak/video-feeds', 'Mozilla/5.0');
        $_SERVER['HTTP_X_COMSPEC_KEY'] = 'tenant-key';
        $ok = new Response();
        $mw = new AntiScraperMiddleware($this->trapBannedLimiter());
        $response = $mw(new Request(), static fn () => $ok);
        self::assertSame($ok, $response);
    }

    public function testLoggedInSessionBypassesIpTrapBanIncludingReconUploads(): void
    {
        $_SESSION['user_id'] = 42;
        $this->bindRequest('GET', '/uploads/recon/alpha.png', 'Mozilla/5.0');
        $ok = new Response();
        $mw = new AntiScraperMiddleware($this->trapBannedLimiter());
        $response = $mw(new Request(), static fn () => $ok);
        self::assertSame($ok, $response);
    }

    public function testExtensionUserAgentBypassesScraperBan(): void
    {
        $this->bindRequest('POST', '/api/chat', 'COMSPECExtension/1.17.5');
        $ok = new Response();
        $mw = new AntiScraperMiddleware($this->trapBannedLimiter());
        $response = $mw(new Request(), static fn () => $ok);
        self::assertSame($ok, $response);
    }

    public function testTrapBannedGuestStillGets403OnPortal(): void
    {
        $this->bindRequest('GET', '/forum', 'Mozilla/5.0');
        $mw = new AntiScraperMiddleware($this->trapBannedLimiter());
        $response = $mw(new Request(), static fn () => self::fail('next must not run'));
        self::assertSame(403, $response->statusCode());
    }

    private function trapBannedLimiter(): FileRateLimiter
    {
        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('attempts')->willReturn(3);
        $limiter->method('hit')->willReturn(3);

        return $limiter;
    }

    private function bindRequest(string $method, string $uri, string $ua): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTP_USER_AGENT'] = $ua;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    }
}
