<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\RoleplayGameSessionRepository;
use App\Services\Atak\AtakGameAdminService;
use App\Services\Atak\AtakMobileAdminService;
use App\Services\Atak\AtakMobileLinkService;
use App\Services\Security\FileRateLimiter;

/**
 * Espace d'administration ouvert par QR code depuis l'app Admin du téléphone en jeu (/m/admin) :
 *   /m/admin                 accueil (un choix à la fois)
 *   /m/admin/joueurs         joueurs et ATAK de la communauté ; /m/admin/joueurs/{id} : fiche, message, blocage…
 *   /m/admin/sessions        sessions de jeu récentes et création d'une session
 *   /m/admin/cr              envoi d'un CR de fin d'opération aux joueurs connectés
 *   POST /m/admin/action     toutes les écritures (CSRF, débit limité, droits relus, journal d'audit)
 * Le lien du QR code doit avoir été demandé pour l'app « admin » par un compte gestionnaire de la communauté ; la session
 * ne sert qu'au navigateur qui a scanné, dure une heure et se coupe dès que le compte perd ses droits (voir
 * AtakMobileLinkService::currentAdmin). Aucune adresse e-mail n'est affichée, ni aucune donnée d'une autre communauté.
 */
final class AtakMobileAdminController
{
    public function __construct(
        private ?AtakMobileLinkService $links = null,
        private ?AtakGameAdminService $game = null,
        private ?AtakMobileAdminService $admin = null,
        private ?FileRateLimiter $limiter = null,
        private ?RoleplayGameSessionRepository $sessions = null,
    ) {
    }

    public function home(Request $request, array $params = []): Response
    {
        $ctx = $this->links()->currentAdmin();
        if ($ctx === null) {
            return $this->expired();
        }

        return $this->page('admin_home', $ctx);
    }

    public function players(Request $request, array $params = []): Response
    {
        $ctx = $this->links()->currentAdmin();
        if ($ctx === null) {
            return $this->expired();
        }
        $q = mb_substr(trim((string) $request->queryString('q')), 0, 80);
        $offset = max(0, (int) $request->queryString('offset'));
        $fleet = $this->game()->fleet((int) $ctx['link']['tenant_id'], $q, $offset);

        return $this->page('admin_players', $ctx, ['q' => $q, 'offset' => $offset, 'fleet' => $fleet, 'pageSize' => AtakGameAdminService::PAGE]);
    }

    public function player(Request $request, array $params = []): Response
    {
        $ctx = $this->links()->currentAdmin();
        if ($ctx === null) {
            return $this->expired();
        }
        $row = $this->game()->one((int) $ctx['link']['tenant_id'], (int) ($params['id'] ?? 0));

        return $this->page('admin_player', $ctx, ['row' => $row], $row === null ? 404 : 200);
    }

    public function sessions(Request $request, array $params = []): Response
    {
        $ctx = $this->links()->currentAdmin();
        if ($ctx === null) {
            return $this->expired();
        }
        $list = [];
        if ($ctx['sessions']) {
            try {
                $repo = $this->sessions ??= new RoleplayGameSessionRepository();
                $list = $repo->schemaReady() ? array_slice($repo->listForTenant((int) $ctx['link']['tenant_id'], 8), 0, 8) : [];
            } catch (\Throwable) {
                $list = [];
            }
        }

        return $this->page('admin_sessions', $ctx, ['list' => $list, 'kinds' => AtakMobileAdminService::SESSION_KINDS], $ctx['sessions'] ? 200 : 403);
    }

    public function report(Request $request, array $params = []): Response
    {
        $ctx = $this->links()->currentAdmin();
        if ($ctx === null) {
            return $this->expired();
        }

        return $this->page('admin_cr', $ctx);
    }

    /** Toutes les écritures : session d'administration, CSRF et débit contrôlés, puis le service relit les droits. */
    public function act(Request $request, array $params = []): Response
    {
        $ctx = $this->links()->currentAdmin();
        if ($ctx === null) {
            return Response::json(['ok' => false, 'error' => 'session', 'message' => 'Session terminée : ouvrez à nouveau l’espace depuis le jeu.'], 401);
        }
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'csrf', 'message' => 'Page expirée : rechargez-la.'], 419);
        }
        $key = 'atak_mobile_admin_post:' . (int) $ctx['link']['id'];
        if ($this->limiter()->tooManyAttempts($key, 30, 60)) {
            return Response::json(['ok' => false, 'error' => 'rate_limited', 'message' => 'Trop d’actions d’un coup : patientez une minute.'], 429);
        }
        $this->limiter()->hit($key, 60);
        $in = $request->all();
        $out = $this->admin()->run($ctx['link'], $ctx['role'], $ctx['sessions'], strtolower(trim((string) ($in['action'] ?? ''))), is_array($in) ? $in : []);

        return Response::json($out, $out['ok'] ? 200 : 422);
    }

    public function logout(Request $request, array $params = []): Response
    {
        $ctx = $this->links()->currentAdmin();
        if ($ctx === null) {
            return Response::json(['ok' => false, 'error' => 'session'], 401);
        }
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'csrf'], 419);
        }
        $this->links()->revoke((int) $ctx['link']['tenant_id'], (int) $ctx['link']['user_id'], (int) $ctx['link']['id']);
        AtakMobileLinkService::forget();

        return Response::json(['ok' => true]);
    }

    /**
     * @param array{link: array<string, mixed>, role: string, sessions: bool} $ctx
     * @param array<string, mixed> $data
     */
    private function page(string $view, array $ctx, array $data = [], int $status = 200): Response
    {
        $response = Response::view('m.' . $view, $data + [
            'title' => 'Administration — Athena',
            'adminView' => $view,
            'adminLink' => $ctx['link'],
            'adminRole' => $ctx['role'],
            'adminSessions' => $ctx['sessions'],
            'csrf' => Csrf::token(),
            'expiresAt' => (string) ($ctx['link']['session_expires_at'] ?? ''),
        ]);
        if ($status !== 200) {
            $response->setStatusCode($status);
        }

        return $response;
    }

    private function expired(): Response
    {
        return Response::view('m.admin_expired', [
            'title' => 'Administration — Athena',
            'why' => 'Cet espace s’ouvre en scannant le QR code de l’app Admin du téléphone en jeu. La session est terminée, a été coupée, ou vos droits ont changé.',
        ])->setStatusCode(403);
    }

    private function links(): AtakMobileLinkService
    {
        return $this->links ??= new AtakMobileLinkService();
    }

    private function game(): AtakGameAdminService
    {
        return $this->game ??= new AtakGameAdminService();
    }

    private function admin(): AtakMobileAdminService
    {
        return $this->admin ??= new AtakMobileAdminService();
    }

    private function limiter(): FileRateLimiter
    {
        return $this->limiter ??= new FileRateLimiter();
    }
}
