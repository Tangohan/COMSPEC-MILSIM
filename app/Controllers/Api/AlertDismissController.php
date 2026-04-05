<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserAlertDismissalRepository;

final class AlertDismissController
{
    public function __construct(
        private ?UserAlertDismissalRepository $dismissals = null
    ) {
        $this->dismissals ??= new UserAlertDismissalRepository();
    }

    public function handle(Request $request, array $params = []): Response
    {
        $uid = Session::get('user_id') ? (int) Session::get('user_id') : 0;
        if ($uid <= 0) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }
        if (! Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Jeton CSRF invalide'], 403);
        }

        $scope = trim((string) $request->input('scope', ''));
        if ($scope !== 'platform' && $scope !== 'tenant') {
            return Response::json(['success' => false, 'message' => 'Scope invalide'], 400);
        }
        $alertId = (int) $request->input('alert_id', 0);
        if ($alertId <= 0) {
            return Response::json(['success' => false, 'message' => 'Alerte invalide'], 400);
        }

        $this->dismissals->dismiss($uid, $scope, $alertId);

        return Response::json(['success' => true]);
    }
}
