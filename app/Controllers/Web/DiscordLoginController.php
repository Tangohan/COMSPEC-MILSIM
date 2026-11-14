<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Auth\AuthController;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserDiscordLinkRepository;
use App\Repositories\UserRepository;
use App\Services\Integrations\DiscordLinkedRoles;
use App\Services\Security\FileRateLimiter;
use App\Support\LoginIntendedDestination;

/**
 * « Continuer avec Discord » : connexion (ou départ d'inscription) avec le compte Discord.
 *
 * - Un compte du site est relié à ce Discord (user_discord_links) : connexion par le même chemin que le mot de passe
 *   (AuthController::completeVerifiedLogin : compte actif, double vérification, session neuve).
 * - Visiteur sans compte relié : retour à l'inscription, pseudo Discord (et e-mail seulement s'il est vérifié par
 *   Discord) préremplis. Aucun compte n'est créé ni rattaché d'après l'e-mail : jamais de connexion par e-mail.
 * - Membre déjà connecté : il relie son Discord par le parcours des rôles liés.
 *
 * L'état (state) et le vérificateur PKCE vivent en session, à usage unique. Les codes et jetons ne sont jamais journalisés.
 */
final class DiscordLoginController
{
    private const SESSION = 'discord_login_oauth';
    private const TTL_SEC = 600;

    public function __construct(private ?DiscordLinkedRoles $service = null)
    {
    }

    /** GET /discord/connexion?next=chemin */
    public function start(Request $request, array $params = []): Response
    {
        if ((int) Session::get('user_id', 0) > 0) {
            return Response::redirect(url('discord/roles-lies'));
        }
        if (!$this->service()->configured()) {
            Session::flash('error', 'La connexion avec Discord n’est pas ouverte sur le site.');

            return Response::redirect(url('login'));
        }
        $next = LoginIntendedDestination::suffixFromRedirect((string) $request->query('next', ''));
        if ($next !== null) {
            LoginIntendedDestination::rememberSuffix($next);
        }
        $state = bin2hex(random_bytes(16));
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        Session::set(self::SESSION, ['state' => $state, 'verifier' => $verifier, 'expires_at' => time() + self::TTL_SEC]);

        return Response::redirect($this->service()->loginAuthorizeUrl($state, $challenge));
    }

    /** GET /discord/connexion/retour?code=&state= */
    public function callback(Request $request, array $params = []): Response
    {
        $pending = Session::get(self::SESSION);
        Session::forget(self::SESSION);
        $limiter = new FileRateLimiter();
        $rateKey = 'rl:discord_login:ip:' . $request->ip();
        if ($limiter->tooManyAttempts($rateKey, 30, 300)) {
            return $this->fail('Trop de tentatives. Patientez quelques minutes.');
        }
        $limiter->hit($rateKey, 300);

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');
        if (
            !is_array($pending) || (int) ($pending['expires_at'] ?? 0) < time()
            || (string) ($pending['state'] ?? '') === '' || !hash_equals((string) $pending['state'], $state)
        ) {
            return $this->fail('La connexion avec Discord a expiré. Recommencez.');
        }
        if ($code === '' || $request->query('error') !== null) {
            return $this->fail('Connexion avec Discord annulée.');
        }
        if ((int) Session::get('user_id', 0) > 0) {
            return Response::redirect(url('discord/roles-lies'));
        }
        $me = $this->service()->loginIdentity(mb_substr($code, 0, 200), (string) ($pending['verifier'] ?? ''));
        if ($me === null) {
            return $this->fail('Discord n’a pas confirmé votre compte. Recommencez.');
        }

        $links = new UserDiscordLinkRepository();
        $userId = $links->schemaReady() ? $links->findUserIdByDiscordId($me['id']) : null;
        if ($userId !== null) {
            $user = Container::get(UserRepository::class)->findById($userId, null);
            if ($user !== null) {
                return Container::get(AuthController::class)->completeVerifiedLogin($user, $request);
            }
        }

        // Pas de compte relié : inscription préremplie (l'e-mail n'est repris que s'il est vérifié par Discord).
        $old = ['discord_handle' => $me['username']];
        if ($me['verified'] && $me['email'] !== '') {
            $old['email'] = $me['email'];
        }
        Session::flash('register_old', $old);
        Session::flash('info', 'Aucun compte Athena n’est relié à ce Discord. Créez votre compte ici, ou connectez-vous puis reliez Discord depuis Mon compte.');

        return Response::redirect(url('register'));
    }

    private function fail(string $message): Response
    {
        Session::flash('error', $message);

        return Response::redirect(url('login'));
    }

    private function service(): DiscordLinkedRoles
    {
        return $this->service ??= new DiscordLinkedRoles();
    }
}
