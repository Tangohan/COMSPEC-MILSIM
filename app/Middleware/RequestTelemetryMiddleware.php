<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class RequestTelemetryMiddleware
{
    private const DEFAULT_SUCCESS_SAMPLE_RATE = 0.05;

    private const SLOW_REQUEST_THRESHOLD_MS = 1000;

    private readonly float $successSampleRate;

    public function __construct(?float $successSampleRate = null)
    {
        $configuredRate = $successSampleRate ?? (float) env(
            'REQUEST_TELEMETRY_SAMPLE_RATE',
            self::DEFAULT_SUCCESS_SAMPLE_RATE
        );
        $this->successSampleRate = max(0.0, min(1.0, $configuredRate));
    }

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
            if (!$this->shouldRecord($statusCode, $elapsedMs)) {
                return $response;
            }

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

    private function shouldRecord(int $statusCode, int $elapsedMs): bool
    {
        // Les erreurs et requêtes lentes restent exhaustives pour ne masquer aucun incident.
        if ($statusCode >= 400 || $elapsedMs >= self::SLOW_REQUEST_THRESHOLD_MS) {
            return true;
        }

        if ($this->successSampleRate <= 0.0) {
            return false;
        }
        if ($this->successSampleRate >= 1.0) {
            return true;
        }

        return random_int(1, 10_000) <= (int) round($this->successSampleRate * 10_000);
    }

    private function shouldSkip(string $path): bool
    {
        return $path === '/analytics/beacon'
            || $path === '/api/health'
            || $path === '/api/atak/ping'
            || str_starts_with($path, '/assets/')
            || str_starts_with($path, '/storage/');
    }
}
