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
     * Arma 3 et l’ATAK web restent ouverts pendant une intervention du portail.
     * Le reste du site (accueil, dossiers, administration, renseignement) reste fermé.
     */
    public static function isOperationalPath(string $requestPath): bool
    {
        $path = '/' . ltrim($requestPath, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        if ($path === '/atak/sse' || str_starts_with($path, '/atak/sse/')) {
            return false;
        }

        $prefixes = [
            '/login',
            '/logout',
            '/connect',
            '/atak',
            '/tacmap',
            '/overwatch',
            '/c2',
            '/operateur/terrain',
            '/map-data',
            '/api/atak',
            '/api/markers',
            '/api/units',
            '/api/chat',
            '/api/pings',
            '/api/nine-line',
            '/api/cas',
            '/api/recon',
            '/api/map-shapes',
            '/api/flight-manifest',
            '/api/intel',
            '/api/fire-support',
            '/api/danger-zones',
            '/api/logistics',
            '/api/replay',
            '/api/iff',
            '/api/tacmap',
            '/api/overwatch',
            '/api/medical-alerts',
            '/api/vehicles',
        ];

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
        if (self::isOperationalPath($requestPath)) {
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
