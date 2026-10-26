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
     * Pages de connexion laissées ouvertes pendant une maintenance qui laisse passer les administrateurs plateforme :
     * sans elles, un administrateur déconnecté ne pourrait plus rentrer. Un membre qui s'y connecte quand même est
     * déconnecté dès la page suivante (enforce).
     */
    public static function isAdminLoginPath(string $requestPath): bool
    {
        $path = '/' . trim($requestPath, '/');

        return in_array($path, [
            '/login',
            '/login/otp',
            '/login/otp/resend',
            '/login/otp/switch',
            '/login/select-community',
            '/logout',
        ], true);
    }

    /** Appel d'API (mod, DLL COMSPEC Link, Overwatch web) : réponse JSON lisible par le client, pas la page HTML. */
    public static function wantsJson(string $requestPath): bool
    {
        $path = '/' . ltrim($requestPath, '/');
        if (str_starts_with($path, '/api/')) {
            return true;
        }
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
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

        // Maintenance globale : tout le monde est déconnecté, sauf les administrateurs plateforme (passés plus haut).
        // Les sessions qui ne se présentent qu'après la réouverture sont fermées par AuthMiddleware.
        $global = (string) ($maintenance['scope'] ?? '') === 'global';
        $loggedOut = false;
        if ($global) {
            MaintenanceSessionEpoch::mark($maintenance);
            if ((int) ($userContext['user_id'] ?? 0) > 0) {
                self::closeSession();
                $loggedOut = true;
            }
        }
        $adminLogin = (int) ($maintenance['allow_admin_bypass'] ?? 0) === 1;
        if (!$loggedOut && $adminLogin && self::isAdminLoginPath($requestPath) && !self::wantsJson($requestPath)) {
            // Formulaire de connexion affiché avec un bandeau « réservé aux administrateurs » (views/auth/login.php).
            $GLOBALS['__maintenance_admin_login'] = [
                'title' => (string) ($maintenance['title'] ?? ''),
                'ends_at' => $maintenance['ends_at'] ?? null,
            ];

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
        header('X-Athena-Maintenance: 1');

        $title = $maintenance['title'] ?: 'Maintenance en cours';
        $message = self::humanMessage($maintenance['message'] ?? null);
        $endsAt = $maintenance['ends_at'] ?? null;
        $code = $maintenance['maintenance_code'] ?? null;
        $appName = function_exists('config') ? (string) config('app.name', 'Athena') : 'Athena';
        $uiVariant = MaintenanceThemes::normalize($maintenance['ui_variant'] ?? null);
        $uiAnimation = (int) ($maintenance['ui_animation'] ?? 1) === 1;

        if (self::wantsJson($requestPath)) {
            // Le mod et la DLL reconnaissent la maintenance (HTTP 503 + error « maintenance ») au lieu d'un
            // « serveur injoignable » : l'app Athena du téléphone l'annonce telle quelle.
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(self::jsonPayload($maintenance, $message), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $adminLoginUrl = $adminLogin && function_exists('url') ? url('login') : '';

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

    /**
     * Corps JSON d'une API en maintenance.
     *
     * @param array<string, mixed> $maintenance
     * @return array<string, mixed>
     */
    public static function jsonPayload(array $maintenance, string $message): array
    {
        $endsAt = trim((string) ($maintenance['ends_at'] ?? ''));
        $endsTs = $endsAt !== '' ? strtotime($endsAt) : false;

        return [
            'ok' => false,
            'error' => 'maintenance',
            'code' => 'MAINTENANCE',
            'maintenance' => true,
            'title' => (string) ($maintenance['title'] ?: 'Maintenance en cours'),
            'message' => $message,
            'ends_at' => $endsTs !== false ? gmdate('c', $endsTs) : null,
            'retry_after' => self::DEFAULT_RETRY_AFTER,
        ];
    }

    /** Ferme la session du visiteur (données et cookie) : il devra se reconnecter après la maintenance. */
    private static function closeSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (!headers_sent()) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 3600,
                    'path' => $params['path'] ?: '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => (bool) ($params['secure'] ?? false),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
        \App\Core\Session::destroy();
        $GLOBALS['__maintenance_logged_out'] = true;
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
                $fr = trim((string) ($decoded['FR'] ?? $decoded['fr'] ?? ''));
                $en = trim((string) ($decoded['EN'] ?? $decoded['en'] ?? ''));
                if ($fr !== '' && $en !== '') {
                    return "FR\n{$fr}\n\n---\n\nEN\n{$en}";
                }
                if ($fr !== '') {
                    return $fr;
                }
                if ($en !== '') {
                    return $en;
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
        $ip = ClientIp::resolve();

        return $ip !== '' ? $ip : '0.0.0.0';
    }
}
