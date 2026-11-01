<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserPublicCvRepository;
use App\Repositories\UserRepository;
use App\Services\Account\ProfileWelcomeJourney;
use App\Services\Account\ProfileWelcomeService;
use App\Services\Auth\AuthService;
use App\Support\PublicCv\PublicCvBannerStorage;
use App\Support\PublicCv\PublicCvSettings;

/**
 * Accueil du profil joueur : /account/bienvenue, une étape à la fois, que l'on peut passer et reprendre.
 *
 * Chaque étape réutilise l'existant : liaison Discord (rôles liés), connexion Steam, téléversement du portrait
 * (AccountController), bannière de la page publique (PublicCvBannerStorage), double vérification (/account/security), page publique (/account/page-publique).
 */
final class ProfileWelcomeController
{
    /** Pages ouvertes depuis une étape ; le retour ramène à l'étape (Discord, Steam, sécurité, page publique). */
    private const GO = [
        ProfileWelcomeService::STEP_DISCORD => 'discord/roles-lies',
        ProfileWelcomeService::STEP_STEAM => 'account/steam/connect',
        ProfileWelcomeService::STEP_SECURITY => 'account/security',
        ProfileWelcomeService::STEP_PUBLIC_PAGE => 'account/page-publique',
    ];

    public function __construct(private ?ProfileWelcomeJourney $journey = null)
    {
    }

    /** GET /account/bienvenue[?etape=…] */
    public function show(Request $request, array $params = []): Response
    {
        $user = $this->user();
        if ($user === null) {
            return Response::redirect(url('login'));
        }
        Session::forget(ProfileWelcomeService::SESSION_RETURN);
        $snap = $this->journey()->snapshot($user);
        $asked = (string) $request->query('etape', '');
        $keys = array_column($snap['steps'], 'key');
        if ($asked !== ProfileWelcomeService::END && !in_array($asked, $keys, true)) {
            return Response::redirect(ProfileWelcomeJourney::stepUrl($snap['resume']));
        }
        if ($snap['state']['current'] !== $asked || $snap['state']['started_at'] === null) {
            $this->journey()->save((int) $user['id'], ProfileWelcomeService::moveTo($snap['state'], $asked));
        }

        $personnel = [];
        try {
            $personnel = Container::get(PersonnelProfileRepository::class)->getByUserId((int) $user['id']) ?? [];
        } catch (\Throwable) {
        }
        $discordLink = null;
        try {
            $links = new \App\Repositories\UserDiscordLinkRepository();
            $discordLink = $links->schemaReady() ? $links->find((int) $user['id']) : null;
        } catch (\Throwable) {
        }

        return Response::view('layout.main', [
            'content' => 'account.welcome',
            'title' => 'Compléter mon profil',
            'accountHubPage' => true,
            'accountPublicCvUrl' => PublicPlayerCvController::activeUrlForUser((int) $user['id']),
            'user' => $user,
            'welcomeStep' => $asked,
            'welcomeSteps' => $snap['steps'],
            'welcomeProgress' => $snap['progress'],
            'welcomeFacts' => $snap['facts'],
            'welcomeNext' => $asked === ProfileWelcomeService::END ? null : ProfileWelcomeService::nextKey($snap['steps'], $asked),
            'welcomePersonnel' => $personnel,
            'welcomeDiscordLink' => $discordLink,
            'welcomeBannerUrl' => $this->bannerUrl((int) $user['id']),
            'welcomeBannerHint' => PublicCvBannerStorage::hintText(),
            'welcomeIsNewMember' => \App\Services\Game\FirstLoginGameLinkGate::isNewMember($user),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    /** POST /account/bienvenue/identite : indicatif (mêmes champs que Profil et préférences). */
    public function saveIdentity(Request $request, array $params = []): Response
    {
        $user = $this->user();
        if ($user === null) {
            return Response::redirect(url('login'));
        }
        $here = ProfileWelcomeJourney::stepUrl(ProfileWelcomeService::STEP_IDENTITY);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($here);
        }
        $callsign = preg_replace('/\s+/u', ' ', trim((string) $request->input('callsign', ''))) ?? '';
        if ($callsign === '') {
            Session::flash('error', 'Indiquez votre indicatif, ou passez l’étape pour le choisir plus tard.');

            return Response::redirect($here);
        }
        if (mb_strlen($callsign) > 50) {
            Session::flash('error', 'L’indicatif tient en 50 caractères au plus.');

            return Response::redirect($here);
        }
        $uid = (int) $user['id'];
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        Container::get(UserRepository::class)->update($uid, $tenantId, ['callsign' => $callsign]);
        try {
            $personnel = Container::get(PersonnelProfileRepository::class);
            $personnel->ensureRecord($uid);
            $personnel->update($uid, ['callsign' => $callsign]);
        } catch (\Throwable) {
        }
        Session::set('callsign', $callsign);
        $user['callsign'] = $callsign;
        Session::flash('success', 'Indicatif enregistré : « ' . $callsign . ' ».');

        return $this->advance($user, ProfileWelcomeService::STEP_IDENTITY);
    }

    /**
     * POST /account/bienvenue/banniere : bannière personnelle de la page publique, même stockage et mêmes contrôles
     * que Mon compte › Ma page publique (PublicCvBannerStorage). Ne touche ni à l'ouverture de la page ni à son adresse.
     */
    public function saveBanner(Request $request, array $params = []): Response
    {
        $user = $this->user();
        if ($user === null) {
            return Response::redirect(url('login'));
        }
        $here = ProfileWelcomeJourney::stepUrl(ProfileWelcomeService::STEP_BANNER);
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            Session::flash('error', 'Image trop lourde : la bannière doit peser ' . (int) floor(PublicCvBannerStorage::effectiveMaxBytes() / (1024 * 1024)) . ' Mo au plus.');

            return Response::redirect($here);
        }
        if (!Csrf::validate(is_string($t = $request->input('_csrf_token')) ? $t : null)) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($here);
        }
        $uid = (int) $user['id'];
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $upload = is_array($_FILES['banner'] ?? null) ? $_FILES['banner'] : [];
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Session::flash('error', 'Choisissez une image, ou passez l’étape.');

            return Response::redirect($here);
        }
        $repo = new UserPublicCvRepository();
        if ($tenantId < 1 || !$repo->ensureTable() || !$repo->ensureBannerColumn()) {
            Session::flash('error', 'Les bannières ne peuvent pas être enregistrées pour le moment. Réessayez plus tard.');

            return Response::redirect($here);
        }
        $storage = new PublicCvBannerStorage();
        $stored = $storage->store($uid, $upload);
        if ($stored['error'] !== null || $stored['path'] === null) {
            Session::flash('error', $stored['error'] ?? 'La bannière n’a pas pu être enregistrée. Réessayez.');

            return Response::redirect($here);
        }
        $row = $repo->findByUserId($uid);
        $previous = isset($row['banner_path']) && PublicCvBannerStorage::isStoredPath((string) $row['banner_path']) ? (string) $row['banner_path'] : null;
        try {
            if ($row === null) {
                // Première fois : réglages par défaut, page fermée (le joueur l'ouvre lui-même à l'étape suivante).
                $repo->save($uid, $tenantId, false, null, '', PublicCvSettings::encodeSections(PublicCvSettings::defaultSections()));
            }
            $repo->setBannerPath($uid, $stored['path']);
            $storage->delete($previous);
        } catch (\Throwable) {
            $storage->delete($stored['path']);
            Session::flash('error', 'La bannière n’a pas pu être enregistrée. Réessayez.');

            return Response::redirect($here);
        }
        Session::flash('success', 'Bannière enregistrée.');

        return $this->advance($user, ProfileWelcomeService::STEP_BANNER);
    }

    /** POST /account/bienvenue/continuer : étape suivante (la page publique compte alors comme vue). */
    public function next(Request $request, array $params = []): Response
    {
        $user = $this->user();
        if ($user === null) {
            return Response::redirect(url('login'));
        }
        $step = (string) $request->input('etape', '');
        if (!Csrf::validate($request->input('_csrf_token')) || !ProfileWelcomeService::isStep($step)) {
            return Response::redirect(url('account/bienvenue'));
        }
        if ($step === ProfileWelcomeService::STEP_PUBLIC_PAGE) {
            $uid = (int) $user['id'];
            $this->journey()->save($uid, ProfileWelcomeService::markSeen($this->journey()->load($uid), $step));
        }

        return $this->advance($user, $step);
    }

    /** POST /account/bienvenue/passer : l'étape reste proposée plus tard, dans le parcours et dans Mon compte. */
    public function skip(Request $request, array $params = []): Response
    {
        $user = $this->user();
        if ($user === null) {
            return Response::redirect(url('login'));
        }
        $step = (string) $request->input('etape', '');
        if (!Csrf::validate($request->input('_csrf_token')) || !ProfileWelcomeService::isStep($step)) {
            return Response::redirect(url('account/bienvenue'));
        }
        $uid = (int) $user['id'];
        $snap = $this->journey()->snapshot($user);
        $next = ProfileWelcomeService::nextKey($snap['steps'], $step);
        $this->journey()->save($uid, ProfileWelcomeService::skip($snap['state'], $step, $next));

        return Response::redirect(ProfileWelcomeJourney::stepUrl($next));
    }

    /** POST /account/bienvenue/terminer */
    public function finish(Request $request, array $params = []): Response
    {
        $user = $this->user();
        if ($user === null) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::redirect(ProfileWelcomeJourney::stepUrl(ProfileWelcomeService::END));
        }
        $uid = (int) $user['id'];
        $snap = $this->journey()->snapshot($user);
        $this->journey()->save($uid, ProfileWelcomeService::finish($snap['state']));
        Session::flash('success', $snap['progress']['done'] === $snap['progress']['total']
            ? 'Profil complet. Bienvenue !'
            : 'Accueil terminé. Les étapes passées restent disponibles ici, dans Mon compte.');

        return Response::redirect(url('account'));
    }

    /** POST /account/bienvenue/reprendre : depuis Mon compte, rouvre le parcours là où il en était. */
    public function resume(Request $request, array $params = []): Response
    {
        $user = $this->user();
        if ($user === null) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::redirect(url('account'));
        }
        $uid = (int) $user['id'];
        $snap = $this->journey()->snapshot($user);
        $state = ProfileWelcomeService::reopen($snap['state']);
        $this->journey()->save($uid, $state);
        $steps = ProfileWelcomeService::steps($snap['facts'], $state);

        return Response::redirect(ProfileWelcomeJourney::stepUrl(ProfileWelcomeService::resumeKey($steps, $state)));
    }

    /** GET /account/bienvenue/aller/{etape} : ouvre la page existante de l'étape et retient où revenir. */
    public function go(Request $request, array $params = []): Response
    {
        $step = (string) ($params['etape'] ?? '');
        if (!isset(self::GO[$step])) {
            return Response::redirect(url('account/bienvenue'));
        }
        Session::set(ProfileWelcomeService::SESSION_RETURN, $step);

        return Response::redirect(url(self::GO[$step]));
    }

    private function bannerUrl(int $userId): ?string
    {
        try {
            $row = (new UserPublicCvRepository())->findByUserId($userId);
        } catch (\Throwable) {
            return null;
        }

        return PublicCvBannerStorage::publicUrl(isset($row['banner_path']) ? (string) $row['banner_path'] : null);
    }

    /** @param array<string, mixed> $user */
    private function advance(array $user, string $from): Response
    {
        $uid = (int) $user['id'];
        $snap = $this->journey()->snapshot($user);
        $next = ProfileWelcomeService::nextKey($snap['steps'], $from);
        $this->journey()->save($uid, ProfileWelcomeService::moveTo($snap['state'], $next));

        return Response::redirect(ProfileWelcomeJourney::stepUrl($next));
    }

    /** @return array<string, mixed>|null ligne users à jour */
    private function user(): ?array
    {
        $user = Container::get(AuthService::class)->user();
        if (!is_array($user) || (int) ($user['id'] ?? 0) < 1) {
            return null;
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        try {
            $fresh = $tenantId > 0 ? Container::get(UserRepository::class)->findById((int) $user['id'], $tenantId) : null;
        } catch (\Throwable) {
            $fresh = null;
        }

        return is_array($fresh) ? array_merge($user, $fresh) : $user;
    }

    private function journey(): ProfileWelcomeJourney
    {
        return $this->journey ??= new ProfileWelcomeJourney();
    }
}
