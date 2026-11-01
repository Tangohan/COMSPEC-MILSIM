<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\MemberProfileInviteService;
use App\Services\Security\FileRateLimiter;
use App\Support\LoginIntendedDestination;
use App\Support\RosterOnlyMember;

/**
 * Parcours de la personne qui reçoit un lien « compléter son profil ».
 *
 * 1. /invitation/profil/{jeton} : le lien est vérifié puis retiré de l'adresse (gardé en session sous forme
 *    d'empreinte) pour ne jamais fuiter vers Discord, Steam ou un autre site.
 * 2. /invitation/profil : compte à activer (mot de passe, adresse confirmée) ou connexion habituelle.
 * 3. Une fois connecté sur le bon compte : accueil du profil existant (/account/bienvenue).
 *
 * Un compte déjà utilisé n'est jamais ouvert par le lien seul : il faut son mot de passe (et sa double vérification).
 */
final class ProfileInviteController
{
    public function __construct(
        private ?MemberProfileInviteService $invites = null,
        private ?UserRepository $users = null,
        private ?TenantRepository $tenants = null,
        private ?FileRateLimiter $limiter = null,
    ) {
        $this->invites ??= new MemberProfileInviteService();
        $this->tenants ??= new TenantRepository();
        $this->limiter ??= new FileRateLimiter();
    }

    public function open(Request $request, array $params = []): Response
    {
        $token = trim((string) ($params['token'] ?? ''));
        if ($this->limiter->tooManyAttempts('profile_invite_open|' . $request->ip(), 30, 900)) {
            return $this->invalid('Trop de tentatives depuis cet appareil. Réessayez dans quelques minutes.');
        }
        $invite = $this->invites->available() ? $this->invites->findUsableByToken($token) : null;
        if ($invite === null) {
            Session::forget(MemberProfileInviteService::SESSION_KEY);

            return $this->invalid();
        }
        $this->invites->recordOpen($invite);
        Session::set(MemberProfileInviteService::SESSION_KEY, ['id' => (int) $invite['id'], 'hash' => (string) $invite['token_hash']]);

        return $this->noReferrer(Response::redirect(url('invitation/profil'), 303));
    }

    public function show(Request $request, array $params = []): Response
    {
        $invite = $this->sessionInvite();
        if ($invite === null) {
            return $this->invalid();
        }
        $user = $this->users()->findById((int) $invite['user_id'], (int) $invite['tenant_id']);
        if ($user === null) {
            return $this->invalid();
        }
        $sessionUser = (int) (Session::get('user_id') ?? 0);
        if ($sessionUser > 0) {
            if ($sessionUser === (int) $user['id']) {
                return $this->continueToWelcome($invite);
            }

            return $this->page('wrong_account', $invite, $user);
        }
        if (!self::needsActivation($user)) {
            // Compte déjà en service : connexion habituelle, puis retour ici.
            LoginIntendedDestination::rememberSuffix('invitation/profil');
        }

        return $this->page(self::needsActivation($user) ? 'activate' : 'login', $invite, $user);
    }

    public function activate(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Recommencez.');

            return Response::redirect(url('invitation/profil'));
        }
        $invite = $this->sessionInvite();
        if ($invite === null) {
            return $this->invalid();
        }
        $tenantId = (int) $invite['tenant_id'];
        $user = $this->users()->findById((int) $invite['user_id'], $tenantId);
        if ($user === null || !self::needsActivation($user)) {
            return Response::redirect(url('invitation/profil'));
        }
        if ($this->limiter->tooManyAttempts('profile_invite_activate|' . (int) $invite['id'], 10, 900)
            || $this->limiter->tooManyAttempts('profile_invite_activate_ip|' . $request->ip(), 20, 900)) {
            Session::flash('error', 'Trop de tentatives. Patientez un quart d’heure ou demandez un nouveau lien.');

            return Response::redirect(url('invitation/profil'));
        }

        $currentEmail = strtolower(trim((string) ($user['email'] ?? '')));
        $typedEmail = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $confirm = (string) $request->input('password_confirmation', '');
        $updates = [];
        if (RosterOnlyMember::isRosterEmail($currentEmail)) {
            if ($typedEmail === '' || !filter_var($typedEmail, FILTER_VALIDATE_EMAIL) || RosterOnlyMember::isRosterEmail($typedEmail)) {
                return $this->back('Indiquez une adresse e-mail valide : elle servira à vous connecter.');
            }
            if ($this->users()->emailExistsInTenant($tenantId, $typedEmail, (int) $user['id']) || $this->users()->findFirstByEmailGlobal($typedEmail) !== null) {
                return $this->back('Cette adresse est déjà utilisée par un compte Athena. Indiquez-en une autre, ou demandez à votre gestionnaire de rattacher ce compte.');
            }
            $updates['email'] = $typedEmail;
        } elseif ($typedEmail === '' || !hash_equals($currentEmail, $typedEmail)) {
            // Preuve simple que le lien est entre les bonnes mains (il a pu être posté dans un salon).
            return $this->back('Cette adresse ne correspond pas à celle enregistrée par votre gestionnaire.');
        }
        if (mb_strlen($password) < 8) {
            return $this->back('Choisissez un mot de passe d’au moins 8 caractères.');
        }
        if ($password !== $confirm) {
            return $this->back('Les deux mots de passe ne sont pas identiques.');
        }
        $updates['password_hash'] = password_hash($password, PASSWORD_ARGON2ID);
        if (isset($updates['email'])) {
            // Adresse saisie par la personne elle-même : compte actif, adresse non vérifiée.
            $updates['status'] = 'active';
        }
        $this->users()->update((int) $user['id'], $tenantId, $updates);
        if (!isset($updates['email'])) {
            // Adresse saisie par le gestionnaire et confirmée ici (même règle que les comptes créés par l'administration).
            $this->users()->activatePendingById((int) $user['id']);
        }
        $this->invites->markAccountReady($invite);

        $fresh = $this->users()->findById((int) $user['id'], $tenantId);
        if ($fresh === null) {
            return $this->invalid();
        }
        \App\Core\Container::get(\App\Services\Auth\AuthService::class)->loginUser($fresh);
        try {
            \App\Core\Container::get(\App\Services\Rbac\RbacService::class)->setPermissionsForGateFromUserRow($fresh, $this->users());
        } catch (\Throwable) {
        }
        Session::forget(MemberProfileInviteService::SESSION_KEY);
        Session::flash('success', 'Compte activé. Complétez maintenant votre profil, étape par étape.');

        return $this->noReferrer(Response::redirect(url('account/bienvenue')));
    }

    /**
     * Compte à activer par le lien : en attente, fiche sans adresse réelle, ou jamais utilisé (aucune connexion).
     *
     * @param array<string, mixed> $user
     */
    public static function needsActivation(array $user): bool
    {
        $status = strtolower(trim((string) ($user['status'] ?? '')));
        if (in_array($status, ['inactive', 'suspended', 'banned', 'merged', 'deleted', 'left'], true)) {
            return false;
        }

        return $status === 'pending_verification'
            || RosterOnlyMember::isRosterEmail((string) ($user['email'] ?? ''))
            || trim((string) ($user['last_login_at'] ?? '')) === '';
    }

    /** @param array<string, mixed> $invite */
    private function continueToWelcome(array $invite): Response
    {
        $this->invites->markAccountReady($invite);
        Session::forget(MemberProfileInviteService::SESSION_KEY);

        return $this->noReferrer(Response::redirect(url('account/bienvenue')));
    }

    /** @return array<string, mixed>|null */
    private function sessionInvite(): ?array
    {
        $raw = Session::get(MemberProfileInviteService::SESSION_KEY);
        if (!is_array($raw) || !$this->invites->available()) {
            return null;
        }
        $invite = $this->invites->findUsableByHash((string) ($raw['hash'] ?? ''));
        if ($invite === null || (int) $invite['id'] !== (int) ($raw['id'] ?? 0)) {
            Session::forget(MemberProfileInviteService::SESSION_KEY);

            return null;
        }

        return $invite;
    }

    private function back(string $message): Response
    {
        Session::flash('error', $message);

        return Response::redirect(url('invitation/profil'));
    }

    /**
     * @param array<string, mixed> $invite
     * @param array<string, mixed> $user
     */
    private function page(string $mode, array $invite, array $user): Response
    {
        $tenant = $this->tenants->findById((int) $invite['tenant_id']) ?? [];
        $email = (string) ($user['email'] ?? '');

        return $this->noReferrer(Response::view('layout.main', [
            'title' => 'Compléter mon profil',
            'content' => 'invitations.profile_invite',
            'showPortalFooter' => false,
            'inviteMode' => $mode,
            'inviteCommunity' => (string) ($tenant['name'] ?? 'la communauté'),
            'inviteName' => trim((string) ($user['callsign'] ?? '')) !== '' ? (string) $user['callsign'] : (string) ($user['display_name'] ?? ''),
            'inviteNeedsEmail' => RosterOnlyMember::isRosterEmail($email),
            'inviteEmailHint' => RosterOnlyMember::isRosterEmail($email) ? '' : MemberProfileInviteService::maskEmail($email),
            'inviteExpiresAt' => (string) $invite['expires_at'],
            'inviteSingleUse' => !empty($invite['single_use']),
        ]));
    }

    private function invalid(string $message = ''): Response
    {
        return $this->noReferrer(Response::view('layout.main', [
            'title' => 'Lien expiré',
            'content' => 'invitations.profile_invite',
            'showPortalFooter' => false,
            'inviteMode' => 'invalid',
            'inviteInvalidMessage' => $message,
        ])->setStatusCode(410));
    }

    private function noReferrer(Response $response): Response
    {
        return $response->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    private function users(): UserRepository
    {
        return $this->users ??= \App\Core\Container::get(UserRepository::class);
    }
}
