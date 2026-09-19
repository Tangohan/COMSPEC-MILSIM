<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * En-têtes de sécurité de base (CSP assoupli pour les vues existantes avec inline).
 *
 * MapLibre / deck.gl (Overwatch Beta Relief 3D) créent des Web Workers via blob: URL.
 * Sans worker-src explicite, le navigateur retombe sur script-src et bloque le worker :
 * la vue 3D reste un écran vide.
 */
final class SecurityHeadersMiddleware
{
    public const DEFAULT_CSP = "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; "
        . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.tailwindcss.com; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; "
        . "img-src 'self' data: blob: https:; font-src 'self' data: https://fonts.gstatic.com; "
        . "connect-src 'self' https: wss:; media-src 'self' blob:; "
        . "worker-src 'self' blob:";

    public function __invoke(Request $request, callable $next): Response
    {
        $response = $next($request);
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Permissions-Policy', 'interest-cohort=()');
        $csp = trim((string) (env('APP_CSP', '') ?: ''));
        if ($csp === '') {
            $csp = self::DEFAULT_CSP;
        }
        $response->header('Content-Security-Policy', self::ensureWorkerSrc($csp));
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if ($https || env('APP_FORCE_HTTPS', false)) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Garantit worker-src 'self' blob: (MapLibre / deck.gl) sans élargir script-src.
     * Si worker-src vaut déjà 'none', on ne touche pas.
     */
    public static function ensureWorkerSrc(string $csp): string
    {
        $csp = trim($csp);
        if ($csp === '') {
            return $csp;
        }
        if (preg_match('/(?:^|;)\s*worker-src\s+([^;]*)/i', $csp, $m) === 1) {
            $value = trim((string) ($m[1] ?? ''));
            if ($value === '' || preg_match("/^'none'$/i", $value) === 1 || preg_match('/\bblob:/i', $value) === 1) {
                return rtrim($csp, '; ');
            }
            $replaced = preg_replace_callback(
                '/((?:^|;)\s*)worker-src\s+[^;]*/i',
                static function (array $match) use ($value): string {
                    return $match[1] . 'worker-src blob: ' . $value;
                },
                $csp,
                1
            );

            return rtrim((string) ($replaced ?? $csp), '; ');
        }

        return rtrim($csp, '; ') . "; worker-src 'self' blob:";
    }
}
