<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Accès refusé : on reste sur la page d'où vient le clic, avec le toast d'erreur dans le coin,
 * au lieu d'être renvoyé vers le tableau de bord, le hub ou l'accueil du back-office.
 *
 * Repère, pour toute la plateforme, la réponse « message d'erreur + redirection vers une page
 * d'accueil » (contrôleurs et middlewares de route) et la remplace par :
 *  - un retour à la page précédente (Referer du même site) pour une page ou un formulaire ;
 *  - un JSON 403 pour un appel fetch, que ath-toast.js affiche en toast sans recharger la page.
 * Sans page précédente exploitable, la redirection d'origine est conservée.
 */
final class AccessDeniedStayMiddleware
{
    /** Pages d'accueil vers lesquelles les refus renvoient aujourd'hui. */
    private const LANDING_PATHS = ['', 'dashboard', 'hub', 'back-office', 'admin'];

    /** Pages précédentes vers lesquelles on ne revient jamais (connexion, sortie…). */
    private const NO_RETURN_PREFIXES = ['login', 'logout', 'register', 'auth', 'two-factor', '2fa', 'password'];

    private const FLASH_KEYS = ['error', 'warning'];

    public function __invoke(Request $request, callable $next): Response
    {
        $before = $this->flashSnapshot();
        $response = $next($request);

        $status = $response->statusCode();
        if ($status < 300 || $status >= 400 || str_starts_with($request->path(), '/api/')) {
            return $response;
        }
        $location = (string) ($response->headerValue('Location') ?? '');
        if ($location === '' || !$this->isLanding($location)) {
            return $response;
        }
        [$flashKey, $message] = $this->newFlash($before);
        if ($flashKey === null) {
            return $response;
        }

        if (!$request->isBrowserNavigation()) {
            unset($_SESSION['_flash'][$flashKey]);

            return Response::json([
                'ok' => false,
                'error' => $message,
                'access_denied' => true,
            ], 403)->header('X-Ath-Access-Denied', '1');
        }

        $back = $this->sameSiteReferer($request);
        if ($back === null) {
            return $response;
        }

        return Response::redirect($back, $request->isPost() ? 303 : 302);
    }

    /** @return array<string, string> */
    private function flashSnapshot(): array
    {
        $out = [];
        foreach (self::FLASH_KEYS as $key) {
            $v = $_SESSION['_flash'][$key] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    /**
     * @param array<string, string> $before
     * @return array{0: ?string, 1: string}
     */
    private function newFlash(array $before): array
    {
        foreach ($this->flashSnapshot() as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                return [$key, $value];
            }
        }

        return [null, ''];
    }

    private function isLanding(string $location): bool
    {
        $path = $this->relativePath((string) (parse_url($location, PHP_URL_PATH) ?? ''));
        if (parse_url($location, PHP_URL_QUERY) !== null) {
            return false;
        }

        return in_array($path, self::LANDING_PATHS, true);
    }

    /** Chemin sans le préfixe d'installation (APP_BASE_PATH, /public) ni les barres de bord. */
    private function relativePath(string $path): string
    {
        $prefix = trim((string) (parse_url(url(''), PHP_URL_PATH) ?? ''), '/');
        $path = trim($path, '/');
        if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
            $path = trim(substr($path, strlen($prefix)), '/');
        }

        return strtolower($path);
    }

    private function sameSiteReferer(Request $request): ?string
    {
        $referer = trim((string) ($request->header('Referer') ?? ''));
        if ($referer === '') {
            return null;
        }
        $parts = parse_url($referer);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $refHost = strtolower((string) $parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if ($host === '' || $refHost !== $host) {
            return null;
        }

        $rawPath = (string) ($parts['path'] ?? '/');
        $refPath = $this->relativePath($rawPath);
        // Retour impossible vers la page refusée elle-même (sinon boucle) ou vers une page d'accueil
        // (la redirection d'origine y mène déjà).
        if ($refPath === $this->relativePath($request->path()) || in_array($refPath, self::LANDING_PATHS, true)) {
            return null;
        }
        foreach (self::NO_RETURN_PREFIXES as $prefix) {
            if ($refPath === $prefix || str_starts_with($refPath, $prefix . '/')) {
                return null;
            }
        }

        return '/' . ltrim($rawPath, '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}
