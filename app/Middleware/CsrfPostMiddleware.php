<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Vérification CSRF sur les requêtes POST de formulaire.
 *
 * Le filet global couvre les espaces d'administration. Les routes qui n'y figurent pas
 * doivent valider le jeton elles-mêmes (`Csrf::validate`) — c'est le cas de la majorité
 * des contrôleurs, et la double validation est sans effet de bord puisque le jeton est
 * le même pour toute la session.
 *
 * Les préfixes exemptés reçoivent des requêtes qui, par nature, ne portent pas de jeton
 * de session : webhooks signés, API à clé, abonnement calendrier par token d'URL.
 */
final class CsrfPostMiddleware
{
    /**
     * Espaces dont tous les POST sont contrôlés d'office.
     *
     * @var list<string>
     */
    private const PROTECTED_PREFIXES = [
        '/back-office/',
        // Ajouté après audit : 73 actions POST y vivaient sans filet global. Vérifié avant
        // extension — 66 validaient déjà explicitement, les autres émettent bien un jeton
        // depuis leur formulaire, et aucun appel AJAX ne vise /admin/ (le JS passe par /api/admin/).
        '/admin/',
    ];

    /**
     * Exemptions : authentification propre, sans jeton de session.
     *
     * @var list<string>
     */
    private const EXEMPT_PREFIXES = [
        // Signature Stripe vérifiée côté contrôleur.
        '/api/stripe/webhook',
        // Clé d'intégration (IntegrationsApiAuthMiddleware).
        '/integrations/',
        // Token d'abonnement porté par l'URL.
        '/calendrier/abonnement/',
        // API : jeton validé au cas par cas (clé ATAK, jeton CSRF explicite selon la route).
        '/api/',
    ];

    public function __invoke(Request $request, callable $next): Response
    {
        if ($request->method() !== 'POST') {
            return $next($request);
        }

        $path = $request->path();

        foreach (self::EXEMPT_PREFIXES as $exempt) {
            if (str_starts_with($path, $exempt)) {
                return $next($request);
            }
        }

        $protected = false;
        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $protected = true;
                break;
            }
        }
        if (!$protected) {
            return $next($request);
        }

        if (!self::hasValidToken($request)) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect(Session::get('user_id') ? url('dashboard') : url('login'));
        }

        return $next($request);
    }

    /**
     * Le jeton est accepté sous les noms réellement émis par les pages : « _csrf_token »
     * (Csrf::field), « _csrf » (signalements du mod, fiche unité…) et l'en-tête X-CSRF-Token
     * des appels fetch en JSON. Ne lire que « _csrf_token » renvoyait « Session expirée »
     * à un utilisateur connecté sur ces formulaires.
     */
    private static function hasValidToken(Request $request): bool
    {
        $candidates = [
            $request->input('_csrf_token'),
            $request->input('_csrf'),
            $request->header('X-CSRF-Token'),
        ];
        foreach ($candidates as $token) {
            if (is_string($token) && $token !== '' && Csrf::validate($token)) {
                return true;
            }
        }

        return false;
    }
}
