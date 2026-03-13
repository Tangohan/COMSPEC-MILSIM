<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class MaintenanceMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $config = config('maintenance', []);
        $enabled = $config['enabled'] ?? false;

        if (!$enabled) {
            return $next($request);
        }

        $allowedIps = $config['allowed_ips'] ?? [];
        $clientIp = $request->ip();

        if (is_array($allowedIps) && in_array($clientIp, $allowedIps, true)) {
            return $next($request);
        }

        $message = $config['message'] ?? 'Maintenance en cours. Merci de réessayer dans quelques minutes.';
        $viewPath = base_path('views/errors/503.php');
        if (is_file($viewPath)) {
            ob_start();
            (function () use ($message, $viewPath) {
                include $viewPath;
            })();
            $body = ob_get_clean();
        } else {
            $body = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Maintenance</title></head><body><h1>Maintenance</h1><p>' . htmlspecialchars($message) . '</p></body></html>';
        }

        return (new Response())
            ->setStatusCode(503)
            ->header('Retry-After', '300')
            ->setBody($body);
    }
}
