<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Security\FileRateLimiter;

/**
 * Throttling par IP sur routes sensibles (auth, recrutement, reset mdp).
 */
final class RateLimitMiddleware
{
    public function __construct(
        private FileRateLimiter $limiter = new FileRateLimiter()
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $path = $request->path();
        $method = $request->method();
        $ip = $this->clientIp();
        $rules = $this->ruleFor($path, $method);
        if ($rules === null) {
            return $next($request);
        }
        [$max, $window] = $rules;
        $key = 'rl:' . $path . ':' . $method . ':' . $ip;
        if ($this->limiter->tooManyAttempts($key, $max, $window)) {
            return Response::view('errors.429', [
                'title' => 'Trop de requêtes',
            ])->setStatusCode(429);
        }

        return $next($request);
    }

    /** @return array{0: int, 1: int}|null [max, windowSeconds] */
    private function ruleFor(string $path, string $method): ?array
    {
        if ($method !== 'POST') {
            return null;
        }
        $routes = [
            '/login' => [30, 300],
            '/login/select-community' => [40, 300],
            '/forgot-password' => [10, 3600],
            '/reset-password' => [20, 3600],
            '/enlistment' => [40, 600],
            '/register' => [10, 3600],
            '/communities/create' => [15, 3600],
            '/invitations/accept' => [30, 600],
            '/community/resolve-code' => [20, 600],
            '/api/forum' => [120, 300],
            '/api/forum-moderation' => [90, 300],
            '/api/forum-upload' => [40, 300],
            '/forum/new-topic' => [25, 600],
        ];
        foreach ($routes as $prefix => $rule) {
            if ($path === $prefix) {
                return $rule;
            }
        }
        if (str_starts_with($path, '/forum/topic/') && str_ends_with($path, '/reply')) {
            return [50, 300];
        }

        return null;
    }

    private function clientIp(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0';
        if (is_string($ip) && str_contains($ip, ',')) {
            return trim(explode(',', $ip)[0]);
        }

        return is_string($ip) ? $ip : '0';
    }
}
