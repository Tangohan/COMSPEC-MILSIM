<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\ComspecTacticalApiMiddleware;
use App\Support\ComspecApiKeyAuth;
use App\Support\TacticalApiErrorRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TacticalApiErrorRendererTest extends TestCase
{
    protected function tearDown(): void
    {
        ComspecApiKeyAuth::resetForTests();
        unset(
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['SCRIPT_NAME'],
            $_SERVER['HTTP_ACCEPT'],
            $_SERVER['REMOTE_ADDR']
        );
        parent::tearDown();
    }

    public function testPayloadHidesFilePathsAndSqlstate(): void
    {
        $e = new RuntimeException(
            'Database connection failed: SQLSTATE[HY000] [2002] Operation not permitted'
            . ' Vérifiez DB_HOST=127.0.0.1 — app/Core/Database.php:116'
        );
        $payload = TacticalApiErrorRenderer::payload($e, '34b6eb1e6055ca15');

        self::assertFalse($payload['ok']);
        self::assertSame('34b6eb1e6055ca15', $payload['request_id'] ?? null);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        self::assertIsString($body);
        self::assertStringNotContainsString('Database.php', $body);
        self::assertStringNotContainsString('SQLSTATE', $body);
        self::assertStringNotContainsString('127.0.0.1', $body);
        self::assertStringNotContainsString('PDO', $body);
        self::assertStringContainsString('réessayez', mb_strtolower($payload['message']));
    }

    public function testPublicApiAtakPathIsTacticalEvenWithPublicPrefix(): void
    {
        self::assertTrue(TacticalApiErrorRenderer::isTacticalPath('/public/api/atak/mod-report'));
        self::assertTrue(TacticalApiErrorRenderer::isTacticalPath('/api/atak/mod-report'));
        self::assertTrue(TacticalApiErrorRenderer::clientWantsJson('/public/api/atak/mod-report', 'text/html'));
        self::assertFalse(TacticalApiErrorRenderer::isTacticalPath('/hub'));
    }

    public function testMiddlewareReturnsQuiet503JsonWhenDispatchThrows(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/atak/mod-report';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.40';

        $mw = new ComspecTacticalApiMiddleware();
        $response = $mw(new Request(), static function (): never {
            throw new RuntimeException(
                'Database connection failed: SQLSTATE[HY000] [2002] Operation not permitted — app/Core/Database.php:116'
            );
        });

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(503, $response->statusCode());
        self::assertSame('30', $response->headerValue('Retry-After'));
        self::assertStringContainsString('application/json', (string) $response->headerValue('Content-Type'));
        $payload = json_decode($response->body(), true);
        self::assertIsArray($payload);
        self::assertFalse($payload['ok'] ?? true);
        self::assertArrayHasKey('message', $payload);
        self::assertStringNotContainsString('Database.php', $response->body());
        self::assertStringNotContainsString('SQLSTATE', $response->body());
        self::assertStringNotContainsString('Exception non gérée', $response->body());
        self::assertStringNotContainsString('<html', $response->body());
    }
}
