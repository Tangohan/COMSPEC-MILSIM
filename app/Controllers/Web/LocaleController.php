<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Services\I18n\LocaleService;

/**
 * Bascule de langue (cookie + session + profil si connecté).
 */
final class LocaleController
{
    public function switch(Request $request, array $params = []): Response
    {
        $locale = (string) ($params['locale'] ?? $request->query('locale', ''));
        if (!LocaleService::isSupported($locale)) {
            return Response::redirect(url(''));
        }

        (new LocaleService())->setUserLocale($locale);

        $redirect = trim((string) $request->query('redirect', ''));
        if ($redirect === '' || !str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            $base = rtrim(url(''), '/');
            if ($referer !== '' && str_starts_with($referer, $base)) {
                $path = substr($referer, strlen($base));
                $redirect = ($path !== '' && $path !== false) ? $path : '/';
            } else {
                $redirect = '/';
            }
        }

        // Sécurité : uniquement chemin relatif interne.
        if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//') || str_contains($redirect, '://')) {
            $redirect = '/';
        }

        if ($redirect === '/') {
            return Response::redirect(url(''));
        }

        return Response::redirect(url(ltrim($redirect, '/')));
    }
}
