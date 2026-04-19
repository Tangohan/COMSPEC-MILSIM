<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class RequestTelemetryMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        try {
            $path = $request->path();
            if ($this->shouldSkip($path)) {
                return $response;
            }

            $statusCode = $response->statusCode();
            $tenantId = (int) Session::get('tenant_id', 0);
            $userId = (int) Session::get('user_id', 0);
            $requestId = trim((string) ($response->headerValue('X-Request-Id') ?? ''));

            $pdo = Database::getPdo();
            $stmt = $pdo->prepare(
                'INSERT INTO request_telemetry (tenant_id, user_id, request_id, method, route_path, status_code, duration_ms, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $tenantId > 0 ? $tenantId : null,
                $userId > 0 ? $userId : null,
                $requestId !== '' ? mb_substr($requestId, 0, 36) : null,
                mb_substr($request->method(), 0, 8),
                mb_substr($path, 0, 255),
                max(100, min(599, $statusCode)),
                max(0, $elapsedMs),
            ]);
        } catch (\Throwable) {
            // Ne jamais impacter la réponse utilisateur.
        }

        return $response;
    }

    private function shouldSkip(string $path): bool
    {
        return $path === '/analytics/beacon'
            || $path === '/api/health'
            || str_starts_with($path, '/assets/')
            || str_starts_with($path, '/storage/');
    }
}
