<?php

declare(strict_types=1);

namespace App\Support;

final class MaintenanceGuard
{
    private const DEFAULT_RETRY_AFTER = 900;

    public function __construct(
        private MaintenanceService $maintenanceService
    ) {}

    /**
     * Seuls les points d'entrée indispensables à l'exploitation contournent la maintenance.
     * Les pages publiques, l'authentification et les outils opérationnels font partie du site
     * et doivent donc respecter une règle app_maintenance globale.
     */
    public static function isInfrastructurePath(string $requestPath): bool
    {
        $path = '/' . ltrim($requestPath, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        $exactPaths = [
            '/api/stripe/webhook',
            '/api/health',
            '/api/system/version',
            '/cron/run',
            '/maintenance-toggle.php',
            '/sw.js',
            '/manifest.webmanifest',
        ];
        if (in_array($path, $exactPaths, true)) {
            return true;
        }

        $prefixes = ['/assets', '/uploads', '/admin/system/updates', '/cron'];
        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed>|null $userContext role_slug, etc.
     */
    public function enforce(string $requestPath, ?string $module = null, ?array $userContext = null): void
    {
        if (self::isInfrastructurePath($requestPath)) {
            return;
        }

        $clientIp = self::resolveClientIp();
        $maintenance = $this->maintenanceService->getActiveMaintenance($requestPath, $module);

        if (!$maintenance) {
            return;
        }

        if ($this->maintenanceService->shouldBypass($maintenance, $userContext, $clientIp)) {
            return;
        }

        $status = (int) ($maintenance['http_status'] ?? 503);
        if ($status < 100 || $status > 599) {
            $status = 503;
        }

        $redirectUrl = isset($maintenance['redirect_url']) ? trim((string) $maintenance['redirect_url']) : '';
        if ($redirectUrl !== '' && in_array($status, [301, 302, 303, 307, 308], true)) {
            http_response_code($status);
            header('Location: ' . $redirectUrl);
            exit;
        }

        http_response_code($status);
        header('Retry-After: ' . self::DEFAULT_RETRY_AFTER);

        $title = $maintenance['title'] ?: 'Maintenance en cours';
        $message = self::humanMessage($maintenance['message'] ?? null);
        $endsAt = $maintenance['ends_at'] ?? null;
        $code = $maintenance['maintenance_code'] ?? null;
        $appName = function_exists('config') ? (string) config('app.name', 'Athena') : 'Athena';

        $viewPath = base_path('views/errors/maintenance.php');
        if (is_file($viewPath)) {
            require $viewPath;
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Maintenance</title></head><body>';
            echo '<h1>' . htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') . '</h1>';
            echo '<p>' . nl2br(htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8')) . '</p>';
            echo '</body></html>';
        }
        exit;
    }

    private static function humanMessage(mixed $raw): string
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return 'Le service est momentanément indisponible.';
        }
        if (str_starts_with($text, '{')) {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                foreach (['FR', 'fr', 'EN', 'en'] as $key) {
                    $picked = trim((string) ($decoded[$key] ?? ''));
                    if ($picked !== '') {
                        return $picked;
                    }
                }
                $first = reset($decoded);
                if (is_string($first) && trim($first) !== '') {
                    return trim($first);
                }
            }
        }

        return $text;
    }

    public static function resolveClientIp(): string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $value = trim((string) $_SERVER[$key]);
                if ($key === 'HTTP_X_FORWARDED_FOR' && str_contains($value, ',')) {
                    $parts = explode(',', $value);

                    return trim($parts[0]);
                }

                return $value;
            }
        }

        return '0.0.0.0';
    }
}
