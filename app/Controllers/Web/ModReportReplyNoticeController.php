<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ForumNotificationRepository;
use App\Services\Monitoring\AtakModReportReplyService;
use App\Support\ModReportReplyNotice;

/**
 * « Compris » sur la fenêtre « réponse à votre signalement » : marque lues les réponses choisies
 * (seulement celles du compte connecté), pour qu'elles ne s'affichent qu'une fois.
 */
final class ModReportReplyNoticeController
{
    public function __construct(private ?ForumNotificationRepository $notifications = null)
    {
    }

    public function dismiss(Request $request, array $params = []): Response
    {
        $userId = (int) (Session::get('user_id') ?? 0);
        $ajax = (string) $request->header('X-Requested-With') === 'fetch';
        $tokenOk = Csrf::validate(is_string($t = $request->input('_csrf_token')) ? $t : null);
        if ($userId < 1 || !$tokenOk) {
            return $ajax ? Response::json(['ok' => false], 403) : Response::redirect(url('/'));
        }

        $raw = $request->input('ids', []);
        $ids = is_array($raw) ? $raw : [$raw];
        $one = (int) $request->input('id', 0);
        if ($one > 0) {
            $ids = [$one];
        }
        $done = 0;
        try {
            $repo = $this->notifications ?? new ForumNotificationRepository();
            $done = $repo->markReadForUser(array_map('intval', $ids), $userId, AtakModReportReplyService::NOTIFICATION_TYPE);
        } catch (\Throwable) {
        }
        // Une réponse arrivée entre-temps s'affiche à la page suivante.
        Session::set(ModReportReplyNotice::SESSION_CHECK_KEY, 0);

        if ($ajax) {
            return Response::json(['ok' => true, 'read' => $done]);
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
