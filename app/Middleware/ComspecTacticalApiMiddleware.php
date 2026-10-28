<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Support\ComspecApiKeyAuth;

/**
 * Exige une clé API pour les routes tactiques en production (config/tactical_api.php).
 */
final class ComspecTacticalApiMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $path = $request->path();
        $block = ComspecApiKeyAuth::enforceForTacticalPath($path);
        if ($block !== null) {
            // Lien d'un cliché ouvert dans un navigateur sans clé ni session (Discord, message, favori) :
            // le lecteur, qui demande la connexion puis y revient, plutôt que le 401 JSON de la clé ATAK.
            $reconId = self::reconImageBrowserLinkId($request, $path);

            return $reconId !== null
                ? Response::redirect(url('recon/images/' . $reconId))
                    ->header('Cache-Control', 'no-store, private, max-age=0')
                    ->header('Referrer-Policy', 'no-referrer')
                : $block;
        }

        try {
            return $next($request);
        } catch (\Throwable $e) {
            if (!\App\Support\TacticalApiErrorRenderer::isTacticalPath($path)) {
                throw $e;
            }

            return \App\Support\TacticalApiErrorRenderer::toResponse($e);
        }
    }

    /** GET /api/recon/images/{id} ou …/{id}/file ouvert comme page (pas un <img> ni un fetch) : l'id, sinon null. */
    public static function reconImageBrowserLinkId(Request $request, string $path): ?int
    {
        if (strtoupper($request->method()) !== 'GET'
            || !preg_match('#^/api/recon/images/([1-9][0-9]{0,9})(?:/file)?/?$#', $path, $m)) {
            return null;
        }
        $dest = strtolower(trim((string) $request->header('Sec-Fetch-Dest')));
        if ($dest !== '') {
            return in_array($dest, ['document', 'iframe'], true) ? (int) $m[1] : null;
        }
        // Navigateur intégré sans Sec-Fetch-Dest : un Accept qui demande du HTML suffit.
        return str_contains(strtolower((string) $request->header('Accept')), 'text/html') ? (int) $m[1] : null;
    }
}
