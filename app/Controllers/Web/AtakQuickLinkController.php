<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Game\GameAtakPairingService;
use App\Support\LoginIntendedDestination;

/**
 * Page courte /lier : le joueur valide, depuis son navigateur connecté à Athena,
 * le code affiché par le mod dans Arma. Le jeu reçoit alors sa session (compte,
 * communauté, Steam) sans clé de communauté ni identifiant à saisir.
 */
final class AtakQuickLinkController
{
    private const PENDING_CODE_KEY = 'atak_quick_link_code';
    private const WATCH_KEY = 'atak_quick_link_watch';

    public function __construct(private ?GameAtakPairingService $pairing = null)
    {
    }

    public function show(Request $request, array $params = []): Response
    {
        $code = self::cleanCode((string) $request->query('code', ''));
        if (!Session::get('user_id')) {
            // Le code de l’adresse est perdu par la redirection de connexion : on le garde en session.
            if ($code !== '') {
                Session::set(self::PENDING_CODE_KEY, $code);
            }
            LoginIntendedDestination::rememberFromRequest($request);

            return Response::redirect(url('login'));
        }
        if ($code === '') {
            $code = self::cleanCode((string) Session::get(self::PENDING_CODE_KEY, ''));
        }
        Session::forget(self::PENDING_CODE_KEY);
        $linked = (bool) Session::getFlash('atak_quick_link_done');
        $gameRequest = null;
        if (!$linked && $code !== '') {
            $this->pairing ??= Container::get(GameAtakPairingService::class);
            $gameRequest = $this->pairing->describePending($code, (int) Session::get('user_id'), (int) Session::get('tenant_id'));
        }

        return Response::view('layout.main', [
            'title' => 'Lier le jeu',
            'content' => 'account.game_link',
            'accountHubPage' => true,
            'code' => $code,
            'gameRequest' => $gameRequest,
            'watch' => $linked ? (int) Session::get(self::WATCH_KEY, 0) : 0,
            'steamMoved' => $linked ? (int) Session::getFlash('atak_quick_link_moved') : 0,
            'linked' => $linked,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function confirm(Request $request, array $params = []): Response
    {
        $userId = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        $code = self::cleanCode((string) $request->input('code', ''));
        $back = url('lier') . ($code !== '' ? '?code=' . rawurlencode($code) : '');
        if ($userId < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $this->pairing ??= Container::get(GameAtakPairingService::class);
        $result = $this->pairing->approveFromWeb($code, $userId, $tenantId);
        if (!$result['ok']) {
            $message = (string) ($result['payload']['message'] ?? 'Ce code ne peut pas être validé.');
            Session::flash('error', $message);

            return Response::redirect($back);
        }
        Session::flash('atak_quick_link_done', '1');
        Session::set(self::WATCH_KEY, (int) ($result['payload']['challenge_id'] ?? 0));
        Session::flash('atak_quick_link_moved', (string) count((array) ($result['payload']['steam_moved_from'] ?? [])));
        Session::flash('success', 'C’est fait. Retournez dans Arma : votre téléphone se connecte et se synchronise tout seul.');

        return Response::redirect(url('lier'));
    }

    /**
     * GET /lier/etat : la page attend que le jeu reprenne sa session après l'autorisation.
     * Réponse : {"state": "approved" | "consumed" | "expired" | "unknown"}.
     */
    public function state(Request $request, array $params = []): Response
    {
        $userId = (int) Session::get('user_id');
        $watch = (int) Session::get(self::WATCH_KEY, 0);
        if ($userId < 1 || $watch < 1) {
            return Response::json(['state' => 'unknown']);
        }
        $this->pairing ??= Container::get(GameAtakPairingService::class);
        $state = $this->pairing->approvalProgress($watch, $userId);
        if ($state !== 'approved') {
            Session::forget(self::WATCH_KEY);
        }

        return Response::json(['state' => $state]);
    }

    /** Code affiché par le jeu (XXXX-XXXX) : lettres et chiffres seulement, 16 au plus. */
    private static function cleanCode(string $raw): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
        $code = substr($code, 0, 16);
        if (strlen($code) === 8) {
            return substr($code, 0, 4) . '-' . substr($code, 4);
        }

        return $code;
    }
}
