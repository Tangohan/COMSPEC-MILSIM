<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Account\AccountUsageService;
use App\Services\Auth\AuthService;

/**
 * Compte simple : usage d’Athena sans organisation, choisi depuis le tableau de bord.
 */
final class AccountUsageController
{
    private function service(): AccountUsageService
    {
        return Container::get(AccountUsageService::class);
    }

    private function guard(Request $request): ?Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('dashboard'));
        }
        if ((int) (Session::get('user_id') ?? 0) < 1) {
            return Response::redirect(url('login'));
        }

        return null;
    }

    public function chooseSolo(Request $request, array $params = []): Response
    {
        if ($r = $this->guard($request)) {
            return $r;
        }
        if (!$this->service()->setSolo((int) Session::get('user_id'), true)) {
            Session::flash('error', 'Ce choix n’est pas encore disponible : la mise à jour de la base doit être lancée.');

            return Response::redirect(url('dashboard'));
        }
        Session::flash('success', 'C’est noté : vous utilisez Athena seul. Vous pourrez rejoindre ou créer une communauté quand vous voudrez.');

        return Response::redirect(url('dashboard'));
    }

    public function leaveSolo(Request $request, array $params = []): Response
    {
        if ($r = $this->guard($request)) {
            return $r;
        }
        $this->service()->setSolo((int) Session::get('user_id'), false);

        return Response::redirect(url('dashboard') . '#dash-start');
    }

    /** Fondateur seul dans son organisation : l’archive et repasse en compte simple. */
    public function convert(Request $request, array $params = []): Response
    {
        if ($r = $this->guard($request)) {
            return $r;
        }
        $userId = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        $result = $this->service()->convertToSimpleAccount($userId, $tenantId);
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Passage en compte simple impossible.'));

            return Response::redirect(url('dashboard'));
        }

        // L’archivage a relogé le compte (autre communauté ou « Pas d’organisation ») : la session suit.
        $auth = Container::get(AuthService::class);
        $user = Container::get(UserRepository::class)->findById($userId);
        $nextTenant = is_array($user) ? (int) ($user['tenant_id'] ?? 0) : 0;
        if ($nextTenant < 1 || !$auth->switchToTenant($nextTenant)) {
            $auth->logout();
            Session::flash('success', 'Votre organisation est archivée et votre compte est maintenant un compte simple. Reconnectez-vous.');

            return Response::redirect(url('login'));
        }
        Session::flash('success', 'Votre organisation est archivée et votre compte est maintenant un compte simple. L’équipe Athena peut la restaurer si vous changez d’avis.');

        return Response::redirect(url('dashboard'));
    }

    public function dismissOffer(Request $request, array $params = []): Response
    {
        if ($r = $this->guard($request)) {
            return $r;
        }
        $this->service()->dismissOffer((int) Session::get('user_id'), (int) Session::get('tenant_id'));

        return Response::redirect(url('dashboard'));
    }
}
