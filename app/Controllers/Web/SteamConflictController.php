<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Account\SteamConflictService;

/**
 * Page de choix imposée quand le Steam du joueur est aussi sur le compte Athena d'une autre personne
 * (AuthMiddleware y renvoie toute page du site tant que rien n'est réglé).
 */
class SteamConflictController
{
    /** Session : Steam en double que le joueur veut garder ; la connexion Steam qui suit doit prouver celui-là. */
    public const SESSION_KEEP = 'steam_conflict_keep';

    private SteamConflictService $conflicts;
    private UserRepository $users;

    public function __construct(?SteamConflictService $conflicts = null, ?UserRepository $users = null)
    {
        $this->conflicts = $conflicts ?? new SteamConflictService();
        $this->users = $users ?? new UserRepository();
    }

    public function show(Request $request, array $params = []): Response
    {
        $user = $this->user();
        if ($user === null) {
            return Response::redirect(url('login'));
        }
        $conflict = $this->conflicts->conflictFor($user);
        if ($conflict === null) {
            return Response::redirect(url('dashboard'));
        }
        $canKeep = true;
        foreach ($conflict['others'] as $o) {
            $canKeep = $canKeep && strtolower($o['status']) === 'active';
        }

        return Response::view('layout.main', [
            'content' => 'account.steam_conflict',
            'title' => 'Steam relié à deux comptes',
            'accountHubPage' => true,
            'user' => $user,
            'steamEnd' => substr($conflict['steam_id'], -4),
            'otherHints' => array_map([SteamConflictService::class, 'maskedHint'], $conflict['others']),
            'canKeep' => $canKeep,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    /** Choix a : la preuve passe par la connexion Steam existante (AccountController::steamCallback). */
    public function keep(Request $request, array $params = []): Response
    {
        $user = $this->user();
        $conflict = $user !== null ? $this->conflicts->conflictFor($user) : null;
        if ($conflict === null) {
            return Response::redirect(url('dashboard'));
        }
        Session::set(self::SESSION_KEEP, $conflict['steam_id']);

        return Response::redirect(url('account/steam/connect'));
    }

    /** Choix b : Steam retiré de ce compte (le sien), puis connexion à l'autre compte. */
    public function release(Request $request, array $params = []): Response
    {
        $user = $this->user();
        if ($user === null || $this->conflicts->conflictFor($user) === null) {
            return Response::redirect(url('dashboard'));
        }
        $this->conflicts->release($user);
        // Même fin de session que « Se déconnecter », puis un message sur la page de connexion.
        (new \App\Services\Audit\AuditService())->log(
            \App\Services\Audit\AuditAction::AUTH_LOGOUT,
            (int) ($user['tenant_id'] ?? 0),
            (int) $user['id'],
            'auth',
            (int) $user['id']
        );
        Session::destroy();
        Session::start();
        Session::regenerate();
        Session::flash('info', 'Steam a été retiré de ce compte. Connectez-vous maintenant avec votre autre compte Athena : le jeu le reconnaîtra par Steam.');

        return Response::redirect(url('login'));
    }

    /** Choix c : Steam mis de côté sur ce compte, l'administration vérifie. */
    public function report(Request $request, array $params = []): Response
    {
        $user = $this->user();
        if ($user === null || $this->conflicts->conflictFor($user) === null) {
            return Response::redirect(url('dashboard'));
        }
        try {
            $this->conflicts->report($user);
        } catch (\Throwable $e) {
            error_log('[steam-conflict] signalement impossible : ' . $e->getMessage());
            Session::flash('error', 'Le signalement n’a pas pu être enregistré. Réessayez dans un instant.');

            return Response::redirect(url('account/steam-doublon'));
        }
        Session::flash('success', 'L’administration est prévenue. Steam est mis de côté sur ce compte le temps de la vérification : rien n’a été supprimé.');

        return Response::redirect(url('dashboard'));
    }

    /** @return array<string, mixed>|null */
    private function user(): ?array
    {
        $id = (int) Session::get('user_id', 0);

        return $id > 0 ? $this->users->findById($id, (int) Session::get('tenant_id', 0) ?: null) : null;
    }
}
