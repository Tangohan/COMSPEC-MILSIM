<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Security\FileRateLimiter;

/**
 * Throttling par IP (invité) ou par compte connecté (uid) sur routes sensibles :
 * authentification, recrutement public, forum, hub « Mon activité », modération auto recrutement.
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
        $rules = $this->ruleFor($path, $method, $ip);
        if ($rules === null) {
            return $next($request);
        }
        [$max, $window, $rateKey] = $rules;
        if ($this->limiter->tooManyAttempts($rateKey, $max, $window)) {
            if (str_starts_with($path, '/api/')) {
                return Response::json([
                    'error' => 'too_many_requests',
                    'message' => 'Trop de requêtes. Merci de patienter un instant.',
                ], 429);
            }

            return Response::view('errors.429', [
                'title' => 'Trop de requêtes',
            ])->setStatusCode(429);
        }

        return $next($request);
    }

    /**
     * @return array{0: int, 1: int, 2: string}|null [max, windowSeconds, rateLimiterKey]
     */
    private function ruleFor(string $path, string $method, string $ip): ?array
    {
        if (!in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return null;
        }

        $uid = (int) (Session::get('user_id') ?? 0);
        $actorKey = $uid > 0 ? ('uid:' . $uid) : ('ip:' . $ip);

        if ($path === '/back-office/ressources/recrutement/automod/restore-access') {
            return [25, 600, 'rl:rw_automod_restore:' . $actorKey];
        }
        if ($path === '/back-office/ressources/recrutement/automod/escalate') {
            return [10, 3600, 'rl:rw_automod_escalate:' . $actorKey];
        }
        if (in_array($path, ['/activite/forum/lu', '/activite/courrier/lu', '/activite/messages/lu'], true)) {
            return [45, 300, 'rl:activite_mark:' . $path . ':' . $actorKey];
        }

        $routes = [
            '/login' => [30, 300],
            '/login/select-community' => [40, 300],
            '/forgot-password' => [10, 3600],
            '/reset-password' => [20, 3600],
            '/enlistment' => [40, 600],
            '/register' => [10, 3600],
            '/resend-verification' => [15, 3600],
            '/communities/create' => [15, 3600],
            '/invitations/accept' => [30, 600],
            '/community/resolve-code' => [20, 600],
            '/api/forum' => [120, 300],
            '/api/forum-moderation' => [90, 300],
            '/api/forum-upload' => [40, 300],
            '/forum/new-topic' => [25, 600],
        ];
        foreach ($routes as $routePath => $rule) {
            if ($path === $routePath) {
                [$max, $window] = $rule;

                return [$max, $window, 'rl:' . $path . ':' . $method . ':' . $ip];
            }
        }
        if (str_starts_with($path, '/forum/topic/') && str_ends_with($path, '/reply')) {
            return [50, 300, 'rl:' . $path . ':' . $method . ':' . $ip];
        }

        $prefixRules = [
            '/api/training/' => [300, 300],
            '/api/me/' => [120, 300],
            '/api/admin/' => [200, 300],
            '/api/back-office/' => [200, 300],
        ];
        foreach ($prefixRules as $prefix => $rule) {
            if (str_starts_with($path, $prefix)) {
                [$max, $window] = $rule;

                return [$max, $window, 'rl:prefix:' . $prefix . ':' . $method . ':' . $ip];
            }
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
