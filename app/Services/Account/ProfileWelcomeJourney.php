<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Controllers\Web\PublicPlayerCvController;
use App\Core\Session;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserDiscordLinkRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserUiTourRepository;
use App\Services\Auth\LoginSecurityOtpService;
use App\Services\Integrations\DiscordLinkedRoles;

/**
 * Accès aux données de l'accueil du profil : état réel du compte (« faits ») et progression enregistrée.
 *
 * La progression vit dans user_ui_tours (lignes « profile_welcome… », déjà purgées avec le compte) ; si la table
 * manque encore, elle reste en session. Aucune colonne ni migration propre au parcours.
 */
final class ProfileWelcomeJourney
{
    public function __construct(
        private ?UserUiTourRepository $tours = null,
        private ?UserRepository $users = null,
        private ?PersonnelProfileRepository $personnel = null,
    ) {
    }

    /** @return array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string} */
    public function load(int $userId): array
    {
        $rows = null;
        try {
            $rows = $this->tours()->listByPrefix($userId, ProfileWelcomeService::TOUR_PREFIX);
        } catch (\Throwable) {
            $rows = null;
        }
        if ($rows === null) {
            $session = Session::get(ProfileWelcomeService::SESSION_STATE);

            return ProfileWelcomeService::normalizeState(is_array($session) && (int) ($session['user_id'] ?? 0) === $userId ? $session : []);
        }

        return ProfileWelcomeService::stateFromRows($rows);
    }

    /** @param array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string} $state */
    public function save(int $userId, array $state): void
    {
        $saved = false;
        try {
            $saved = $this->tours()->replacePrefix($userId, ProfileWelcomeService::TOUR_PREFIX, ProfileWelcomeService::stateToRows($state));
        } catch (\Throwable) {
            $saved = false;
        }
        if (!$saved) {
            Session::set(ProfileWelcomeService::SESSION_STATE, ['user_id' => $userId] + $state);
        }
    }

    /**
     * État réel du compte, lu à chaque affichage : une étape réglée ailleurs (Préférences, Sécurité…) compte aussi.
     *
     * @param array<string, mixed> $user ligne users à jour
     * @return array<string, bool>
     */
    public function facts(array $user): array
    {
        $uid = (int) ($user['id'] ?? 0);
        $personnel = [];
        try {
            $personnel = $this->personnel()->getByUserId($uid) ?? [];
        } catch (\Throwable) {
        }
        $discordAvailable = false;
        $discordLinked = false;
        try {
            $discordAvailable = (new DiscordLinkedRoles())->configured();
            $links = new UserDiscordLinkRepository();
            $discordLinked = $links->schemaReady() && $links->find($uid) !== null;
        } catch (\Throwable) {
        }
        $secondFactor = false;
        try {
            $otp = \App\Core\Container::get(LoginSecurityOtpService::class);
            $secondFactor = ($this->users()->hasTotpColumns() && $otp->isTotpEnabled($user))
                || ($this->users()->hasEmailLoginOtpEnabledColumn() && (int) ($user['email_login_otp_enabled'] ?? 0) === 1)
                || $otp->isMandatoryForUserId($uid);
        } catch (\Throwable) {
        }
        $bannerAvailable = false;
        try {
            $bannerAvailable = $this->users()->hasProfileBannerUrlColumn();
        } catch (\Throwable) {
        }

        return [
            'callsign' => trim((string) ($user['callsign'] ?? '')) !== '',
            'discord_available' => $discordAvailable,
            'discord_linked' => $discordLinked,
            'steam_linked' => trim((string) ($user['steam_id'] ?? '')) !== '',
            'portrait' => trim((string) ($personnel['character_portrait_path'] ?? '')) !== '',
            'portrait_locked' => !empty($personnel['character_portrait_locked']),
            'banner_available' => $bannerAvailable,
            'banner' => trim((string) ($user['profile_banner_url'] ?? '')) !== '',
            'second_factor' => $secondFactor,
            'public_page' => PublicPlayerCvController::activeUrlForUser($uid) !== null,
        ];
    }

    /**
     * Tout ce qu'il faut pour l'écran du parcours et la carte de Mon compte.
     *
     * @param array<string, mixed> $user
     * @return array{state: array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string}, facts: array<string, bool>, steps: list<array{key: string, title: string, short: string, lead: string, status: string, number: int}>, progress: array{done: int, skipped: int, todo: int, total: int, percent: int}, finished: bool, resume: string}
     */
    public function snapshot(array $user): array
    {
        $state = $this->load((int) ($user['id'] ?? 0));
        $facts = $this->facts($user);
        $steps = ProfileWelcomeService::steps($facts, $state);

        return [
            'state' => $state,
            'facts' => $facts,
            'steps' => $steps,
            'progress' => ProfileWelcomeService::progress($steps),
            'finished' => ProfileWelcomeService::isFinished($steps, $state),
            'resume' => ProfileWelcomeService::resumeKey($steps, $state),
        ];
    }

    /** Adresse de l'étape, ou de l'écran de fin. */
    public static function stepUrl(string $key): string
    {
        return url('account/bienvenue') . '?etape=' . rawurlencode($key);
    }

    /**
     * Retour depuis une page ouverte par le parcours (Discord, Steam…) : adresse de l'étape si le joueur venait
     * bien de cette étape, sinon null (comportement habituel de la page).
     */
    public static function returnUrlFor(string $step): ?string
    {
        return Session::get(ProfileWelcomeService::SESSION_RETURN) === $step ? self::stepUrl($step) : null;
    }

    private function tours(): UserUiTourRepository
    {
        return $this->tours ??= new UserUiTourRepository();
    }

    private function users(): UserRepository
    {
        return $this->users ??= \App\Core\Container::get(UserRepository::class);
    }

    private function personnel(): PersonnelProfileRepository
    {
        return $this->personnel ??= \App\Core\Container::get(PersonnelProfileRepository::class);
    }
}
