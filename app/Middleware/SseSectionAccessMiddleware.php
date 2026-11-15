<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessPolicy;
use App\Support\SseAccessCatalog;

/**
 * Droit de lire la section du portail SSE demandée (SseAccessPolicy, niveau « lire »), après la porte d'entrée
 * (SsePortalAccessMiddleware puis SseConfidentialityMiddleware, qui passent avant). La section se déduit de l'adresse
 * (SseAccessCatalog::sectionForPath) ; une page hors section (accueil, recherche, guide, codes d'accès, droits) passe.
 *
 * Sans règle dans la communauté, la réponse est celle de la garde d'origine (lire = être entré au bureau) : rien ne change.
 * Un refus ramène à l'accueil du bureau, sans dire ce que la section contient. Les écritures, versements, suppressions
 * et exports restent jugés par les contrôleurs, avec la même politique.
 */
final class SseSectionAccessMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $section = SseAccessCatalog::sectionForPath($request->path());
        if ($section === null) {
            return $next($request);
        }
        // Session SSE déjà ouverte par les deux intergiciels précédents : on lit ses clés sans rien reconstruire.
        $allowed = SseAccessPolicy::sessionAllows($section, SseAccessCatalog::LEVEL_READ, [
            'guest' => (bool) Session::get(SseAccessCodeService::SESSION_GUEST, false),
            'tenant_id' => (int) Session::get(SseAccessCodeService::SESSION_TENANT, 0),
        ]);
        if (!$allowed) {
            Session::flash('error', 'Votre accès à cette section du bureau SSE est restreint.');

            return Response::redirect(url('atak/sse/accueil'));
        }

        return $next($request);
    }
}
