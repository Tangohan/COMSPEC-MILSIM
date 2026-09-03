<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\RequestTelemetryMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class RequestTelemetryMiddlewareTest extends TestCase
{
    #[DataProvider('importantRequestProvider')]
    public function testErrorsAndSlowRequestsAreAlwaysRecorded(int $statusCode, int $elapsedMs): void
    {
        $middleware = new RequestTelemetryMiddleware(0.0);

        self::assertTrue($this->shouldRecord($middleware, $statusCode, $elapsedMs));
    }

    /** @return iterable<string, array{int, int}> */
    public static function importantRequestProvider(): iterable
    {
        yield 'client error' => [404, 10];
        yield 'server error' => [500, 10];
        yield 'slow success' => [200, 1000];
    }

    public function testSuccessfulFastRequestsCanBeDisabled(): void
    {
        $middleware = new RequestTelemetryMiddleware(0.0);

        self::assertFalse($this->shouldRecord($middleware, 200, 50));
    }

    public function testSampleRateIsClampedToOne(): void
    {
        $middleware = new RequestTelemetryMiddleware(2.0);

        self::assertTrue($this->shouldRecord($middleware, 204, 5));
    }

    private function shouldRecord(RequestTelemetryMiddleware $middleware, int $statusCode, int $elapsedMs): bool
    {
        $method = new ReflectionMethod($middleware, 'shouldRecord');

        return (bool) $method->invoke($middleware, $statusCode, $elapsedMs);
    }
}
