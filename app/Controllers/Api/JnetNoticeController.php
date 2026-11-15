<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserUiTourRepository;
use App\Support\JnetAnnouncement;

/**
 * Mémoire par membre de l'annonce de JNET : la fenêtre « JNET arrive » (vue une fois) et le bandeau du tableau de bord
 * (masqué, ou réaffiché). Même table que les guides du tableau de bord (user_ui_tours) ; deux éléments connus seulement.
 */
final class JnetNoticeController
{
    /** @var array<string, string> élément de l'adresse => clé enregistrée */
    private const KEYS = [
        'fenetre' => JnetAnnouncement::MODAL_KEY,
        'bandeau' => JnetAnnouncement::BANNER_KEY,
    ];

    public function __construct(private ?UserUiTourRepository $tours = null)
    {
        $this->tours ??= new UserUiTourRepository();
    }

    /** POST /api/jnet/annonce/{item}/vu : la fenêtre est fermée, ou le bandeau masqué. */
    public function dismiss(Request $request, array $params = []): Response
    {
        $check = $this->check($request, $params);
        if ($check instanceof Response) {
            return $check;
        }
        [$userId, $item, $key] = $check;
        $completed = $request->inputString('completed', '0') === '1';
        $this->tours->dismiss($userId, $key, $completed);
        if ($item === 'fenetre') {
            Session::set(JnetAnnouncement::SESSION_MODAL_SEEN, 1);
        }

        return Response::json(['success' => true]);
    }

    /** POST /api/jnet/annonce/{item}/rappel : le bandeau masqué redevient visible. */
    public function restore(Request $request, array $params = []): Response
    {
        $check = $this->check($request, $params);
        if ($check instanceof Response) {
            return $check;
        }
        [$userId, , $key] = $check;
        $this->tours->restore($userId, $key);

        return Response::json(['success' => true]);
    }

    /** @return array{0: int, 1: string, 2: string}|Response */
    private function check(Request $request, array $params): array|Response
    {
        $userId = (int) Session::get('user_id');
        if ($userId <= 0) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }
        if (!Csrf::validate($request->inputString('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Session expirée. Rechargez la page.'], 403);
        }
        $item = (string) ($params['item'] ?? '');
        if (!isset(self::KEYS[$item])) {
            return Response::json(['success' => false, 'message' => 'Annonce inconnue'], 404);
        }

        return [$userId, $item, self::KEYS[$item]];
    }
}
