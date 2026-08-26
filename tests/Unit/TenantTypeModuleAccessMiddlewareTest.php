<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Session;
use App\Middleware\TenantTypeModuleAccessMiddleware;
use App\Repositories\TenantRepository;
use PDOException;
use PHPUnit\Framework\TestCase;

final class TenantTypeModuleAccessMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Session::forget('tenant_id');
        parent::tearDown();
    }

    public function testHubGoneAwayReturnsActionablePageWithoutSqlstate(): void
    {
        $_SESSION['tenant_id'] = 7;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/hub';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $repo = $this->createMock(TenantRepository::class);
        $gone = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        $gone->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
        $repo->method('findById')->willThrowException($gone);

        $middleware = new TenantTypeModuleAccessMiddleware($repo);
        $response = $middleware(new Request(), static fn () => self::fail('next must not run'));

        self::assertSame(503, $response->statusCode());
        self::assertSame('30', $response->headerValue('Retry-After'));
        $body = $response->body();
        self::assertStringNotContainsString('SQLSTATE', $body);
        self::assertStringNotContainsString('PDOException', $body);
        self::assertStringNotContainsString('server has gone away', $body);
        self::assertStringContainsString('réessayez', mb_strtolower($body));
    }

    public function testApiGoneAwayReturnsJsonWithoutSqlstate(): void
    {
        $_SESSION['tenant_id'] = 7;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/atak/units';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $repo = $this->createMock(TenantRepository::class);
        $gone = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        $gone->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
        $repo->method('findById')->willThrowException($gone);

        $middleware = new TenantTypeModuleAccessMiddleware($repo);
        $response = $middleware(new Request(), static fn () => self::fail('next must not run'));

        self::assertSame(503, $response->statusCode());
        $payload = json_decode($response->body(), true);
        self::assertIsArray($payload);
        self::assertSame('database_unavailable', $payload['error'] ?? null);
        self::assertArrayHasKey('message', $payload);
        self::assertStringNotContainsString('SQLSTATE', (string) $payload['message']);
        self::assertStringNotContainsString('PDO', (string) $payload['message']);
    }
}
