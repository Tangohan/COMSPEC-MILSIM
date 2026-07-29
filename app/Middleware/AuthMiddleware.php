<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Rbac\RbacService;
use App\Support\LoginIntendedDestination;

class AuthMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            $path = $request->path();
            // Invité SSE : hors portail renseignement, renvoyer vers le sas classifié (pas de Tacmap).
            if (str_contains($path, '/atak') && !str_contains($path, '/atak/sse')) {
                $sse = new \App\Services\Sse\SseAccessCodeService();
                if ($sse->hasActiveClearance() && $sse->isGuest()) {
                    return Response::redirect(url('atak/sse/dossiers'));
                }
            }
            if (!LoginIntendedDestination::rememberFromRequest($request)) {
                Session::flash('error', 'Authentification requise.');
            }

            return Response::redirect(url('login'));
        }

        Container::get(\App\Services\Community\DefaultTenantSessionService::class)->leaveDefaultIfOtherMembershipsExist();

        $userId = (int) Session::get('user_id');
        $userRepo = Container::get(UserRepository::class);
        $user = $userRepo->findById($userId, null);
        if ($user === null) {
            $this->clearAuthSession();
            Session::flash('error', 'Ce compte n’existe plus ou la session est invalide.');

            return Response::redirect(url('login'));
        }
        if (($user['status'] ?? '') !== 'active') {
            $this->clearAuthSession();
            Session::flash('error', 'Ce compte n’est plus actif.');

            return Response::redirect(url('login'));
        }
        $sessionTenantId = Session::get('tenant_id');
        if ($sessionTenantId === null || (int) $sessionTenantId !== (int) $user['tenant_id']) {
            $this->clearAuthSession();
            Session::flash('error', 'Session invalide. Merci de vous reconnecter.');

            return Response::redirect(url('login'));
        }

        // Recharger les permissions à chaque requête (union multi-rôles) + aligner role_id session sur la BDD
        Session::set('role_id', $user['role_id'] ? (int) $user['role_id'] : null);
        $rbac = Container::get(RbacService::class);
        $rbac->setPermissionsForGateFromUserRow($user, $userRepo);
        Session::set('rbac_unit_map', json_encode(\App\Core\Gate::getInstance()->getUnitPermissionMap()) ?: '{}');

        $tenantId = (int) $user['tenant_id'];
        try {
            $resolver = Container::get(\App\Services\Moderation\ModerationRestrictionResolver::class);
            if ($resolver->isAccountLocked($tenantId, $userId)) {
                $this->clearAuthSession();
                Session::flash('error', 'Votre accès à cette communauté est restreint (compte verrouillé).');

                return Response::redirect(url('login'));
            }
        } catch (\Throwable) {
            $this->clearAuthSession();
            Session::flash('error', 'Impossible de vérifier votre accès. Réessayez plus tard.');

            return Response::redirect(url('login'));
        }

        // RGPD : suppression de compte en délai de rétractation — accès restreint à la page
        // de gestion des données (annulation) et à la déconnexion, le temps du délai.
        if (!empty($user['deletion_requested_at'])) {
            $allowedPaths = ['/account/donnees', '/account/donnees/export', '/account/donnees/annuler-suppression', '/logout'];
            if (!in_array($request->path(), $allowedPaths, true)) {
                Session::flash('error', 'Votre compte est programmé pour suppression. Annulez la demande pour continuer à utiliser la plateforme.');

                return Response::redirect(url('account/donnees'));
            }
        }

        return $next($request);
    }

    private function clearAuthSession(): void
    {
        Session::forget('user_id');
        Session::forget('tenant_id');
        Session::forget('email');
        Session::forget('display_name');
        Session::forget('callsign');
        Session::forget('role_id');
        Session::forget('rbac_unit_map');
    }
}
