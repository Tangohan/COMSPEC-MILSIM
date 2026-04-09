<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\InterteamMissionRepository;

/**
 * Accès au fil forum « coopération » : consentement + code e-mail validés une fois par mission et par utilisateur.
 */
final class InterteamCooperationConsentMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $userId = (int) Session::get('user_id');
        if ($userId <= 0) {
            return $next($request);
        }
        $path = $request->path();
        if (!preg_match('#^forum/coop/([^/]+)/sujet/(\d+)/repondre$#', $path, $m)) {
            return $next($request);
        }
        $slug = $m[1];
        /** @var InterteamMissionRepository $repo */
        $repo = Container::get(InterteamMissionRepository::class);
        if (!$repo->tableExists() || !$repo->consentsTableExists()) {
            return $next($request);
        }
        $mission = $repo->findBySlug($slug);
        if (!$mission) {
            return $next($request);
        }
        $mid = (int) ($mission['id'] ?? 0);
        if ($mid <= 0) {
            return $next($request);
        }
        if ($repo->hasVerifiedConsent($mid, $userId)) {
            return $next($request);
        }
        $return = '/' . ltrim($path, '/');
        Session::flash('warning', 'Pour accéder à cet échange inter-unités, confirmez d’abord votre autorisation de partage (code envoyé par e-mail).');

        return Response::redirect(cooperation_mission_consent_url($mid) . '?return=' . rawurlencode($return));
    }
}
