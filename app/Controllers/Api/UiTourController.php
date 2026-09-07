<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserUiTourRepository;

final class UiTourController
{
    public function __construct(
        private ?UserUiTourRepository $tours = null,
    ) {
        $this->tours ??= new UserUiTourRepository();
    }

    public function dismiss(Request $request, array $params = []): Response
    {
        $uid = (int) Session::get('user_id');
        if ($uid <= 0) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Session expirée. Rechargez la page.'], 403);
        }
        $key = trim((string) $request->input('tour_key', ''));
        if ($key !== UserUiTourRepository::KEY_DASHBOARD) {
            return Response::json(['success' => false, 'message' => 'Guide inconnu'], 400);
        }
        $completed = (string) $request->input('completed', '0') === '1';
        $this->tours->dismiss($uid, $key, $completed);

        return Response::json(['success' => true]);
    }
}
