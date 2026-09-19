<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersCspWorkerTest extends TestCase
{
    /** @var mixed */
    private $prevCsp;

    protected function setUp(): void
    {
        $this->prevCsp = $_ENV['APP_CSP'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->prevCsp === null) {
            unset($_ENV['APP_CSP']);
        } else {
            $_ENV['APP_CSP'] = $this->prevCsp;
        }
        parent::tearDown();
    }

    public function testDefaultCspAllowsSameOriginAndBlobWorkers(): void
    {
        unset($_ENV['APP_CSP']);
        $csp = $this->headerFromMiddleware();

        self::assertNotNull($csp);
        self::assertMatchesRegularExpression("/worker-src[^;]*'self'[^;]*blob:/i", (string) $csp);
        self::assertStringNotContainsString("script-src blob:", (string) $csp);
    }

    public function testConfiguredCspWithoutWorkerSrcGetsBlobWorkersAppended(): void
    {
        $src = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com";
        self::assertSame(
            $src . "; worker-src 'self' blob:",
            SecurityHeadersMiddleware::ensureWorkerSrc($src)
        );

        $_ENV['APP_CSP'] = $src;
        $csp = $this->headerFromMiddleware();
        self::assertSame($src . "; worker-src 'self' blob:", $csp);
    }

    public function testExistingWorkerSrcGainsBlobWithoutReplacingSelf(): void
    {
        $src = "script-src 'self'; worker-src 'self'";
        $out = SecurityHeadersMiddleware::ensureWorkerSrc($src);
        self::assertMatchesRegularExpression("/worker-src blob: 'self'/i", $out);
        self::assertStringContainsString("script-src 'self'", $out);
    }

    public function testWorkerSrcAsFirstDirectiveKeepsValidHeader(): void
    {
        $src = "worker-src 'self'; script-src 'self'";
        self::assertSame(
            "worker-src blob: 'self'; script-src 'self'",
            SecurityHeadersMiddleware::ensureWorkerSrc($src)
        );
    }

    public function testExplicitWorkerNoneIsLeftAlone(): void
    {
        $src = "script-src 'self'; worker-src 'none'";
        self::assertSame($src, SecurityHeadersMiddleware::ensureWorkerSrc($src));
    }

    public function testBlobAlreadyPresentIsNotDuplicated(): void
    {
        $src = "script-src 'self'; worker-src 'self' blob:";
        self::assertSame($src, SecurityHeadersMiddleware::ensureWorkerSrc($src));
    }

    private function headerFromMiddleware(): ?string
    {
        $mw = new SecurityHeadersMiddleware();
        $response = $mw(new Request(), static fn () => new Response());

        return $response->headerValue('Content-Security-Policy');
    }
}
