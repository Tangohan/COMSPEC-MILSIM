<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlanGiftRepository;

/**
 * Fermeture de la fenêtre « formule offerte » (une seule fois par gestionnaire).
 */
final class PlanGiftNoticeController
{
    public function __construct(private ?PlanGiftRepository $gifts = null)
    {
        $this->gifts ??= new PlanGiftRepository();
    }

    public function dismiss(Request $request, array $params = []): Response
    {
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($userId > 0 && Csrf::validate($request->input('_csrf_token'))) {
            try {
                $this->gifts->dismissNotice((int) ($params['id'] ?? 0), $userId);
            } catch (\Throwable) {
            }
            // Une autre offre éventuelle s'affiche à la page suivante.
            Session::set('plan_gift_notice_checked_at', 0);
        }

        if ((string) $request->input('go', '') === 'back-office') {
            return Response::redirect(url('back-office'));
        }

        return Response::redirect(self::sameSiteReferer() ?? url('/'));
    }

    private static function sameSiteReferer(): ?string
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($ref === '' || $host === '') {
            return null;
        }
        $refHost = parse_url($ref, PHP_URL_HOST);
        $hostOnly = strtolower(explode(':', $host)[0]);

        return is_string($refHost) && strtolower($refHost) === $hostOnly ? $ref : null;
    }
}
