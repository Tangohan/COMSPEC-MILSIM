<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * En-têtes de sécurité de base (CSP assoupli pour les vues existantes avec inline).
 */
final class SecurityHeadersMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $response = $next($request);
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Permissions-Policy', 'interest-cohort=()');
        $csp = (string) (env('APP_CSP', '') ?: '');
        if ($csp !== '') {
            $response->header('Content-Security-Policy', $csp);
        } else {
            $response->header(
                'Content-Security-Policy',
                "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; " .
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.tailwindcss.com; " .
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
                "img-src 'self' data: blob: https:; font-src 'self' data: https://fonts.gstatic.com; " .
                "connect-src 'self' https: wss:; media-src 'self' blob:;"
            );
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if ($https || env('APP_FORCE_HTTPS', false)) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
