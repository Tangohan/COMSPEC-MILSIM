<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Résout la locale UI au début de chaque requête.
 * Tolère un déploiement partiel (classe LocaleService absente).
 */
final class LocaleMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (class_exists(\App\Services\I18n\LocaleService::class)) {
            try {
                (new \App\Services\I18n\LocaleService())->boot();
            } catch (\Throwable) {
                // Ne bloque jamais la requête si les catalogues / deps i18n sont incomplets.
            }
        }

        return $next($request);
    }
}
