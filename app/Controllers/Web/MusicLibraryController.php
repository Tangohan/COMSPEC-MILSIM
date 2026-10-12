<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\MusicLibraryRepository;
use App\Services\Auth\AuthService;
use App\Support\MusicTrackStorage;

/**
 * Musique (Mon espace) : bibliothèque musicale partagée avec l'app Musique de l'ATAK natif.
 *
 * Vues : global (playlist globale de la plateforme), community (bibliothèque communautaire : toute la plateforme),
 * org (bibliothèque de mon organisation), playlists, mine (mes envois), moderation (gestionnaires, admin plateforme).
 *
 * Droits :
 *  - tout membre envoie des morceaux (communauté ou organisation), crée des playlists (privée, organisation, publique) ;
 *  - un gestionnaire de l'organisation masque ou supprime les morceaux et playlists de son organisation ;
 *  - l'administrateur de la plateforme (admin.system) tient la playlist globale et modère la bibliothèque communautaire.
 */
final class MusicLibraryController
{
    private const BASE = 'back-office/ma-situation/musique';

    private const VIEWS = ['global', 'community', 'org', 'playlists', 'mine', 'moderation'];

    public function __construct(
        private ?AuthService $authService = null,
        private ?MusicLibraryRepository $music = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->music ??= new MusicLibraryRepository();
    }

    // ----- Contexte et droits -----

    /** @return array{0: array<string, mixed>, 1: int, 2: int}|Response */
    private function ctx(): array|Response
    {
        $user = $this->authService->user();
        if (!$user) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }
        $userId = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? Session::get('tenant_id') ?? 0);
        if ($userId < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return [$user, $tenantId, $userId];
    }

    private static function isPlatformAdmin(): bool
    {
        return Gate::getInstance()->allows('admin.system');
    }

    private static function isOrgManager(): bool
    {
        $g = Gate::getInstance();

        return $g->allows('admin.organization') || $g->allows('admin.access') || $g->allows('admin.members.moderate');
    }

    /** @param array<string, mixed> $track */
    private static function canModerateTrack(array $track, int $tenantId): bool
    {
        if (self::isPlatformAdmin()) {
            return true;
        }

        return self::isOrgManager() && (int) ($track['tenant_id'] ?? 0) === $tenantId;
    }

    /** @param array<string, mixed> $track */
    private static function canEditTrack(array $track, int $tenantId, int $userId): bool
    {
        return (int) ($track['uploader_user_id'] ?? 0) === $userId || self::canModerateTrack($track, $tenantId);
    }

    /** @param array<string, mixed> $pl */
    private static function canEditPlaylist(array $pl, int $tenantId, int $userId): bool
    {
        if (($pl['visibility'] ?? '') === 'global') {
            return self::isPlatformAdmin();
        }
        if ((int) ($pl['owner_user_id'] ?? 0) === $userId) {
            return true;
        }

        return self::isPlatformAdmin() || (self::isOrgManager() && (int) ($pl['tenant_id'] ?? 0) === $tenantId);
    }

    private function back(Request $request, string $fallback = ''): Response
    {
        $to = trim((string) $request->input('_back', ''));
        if ($to === '' || !str_starts_with($to, '?')) {
            $to = $fallback;
        }

        return Response::redirect(url(self::BASE) . $to);
    }

    private function csrfOk(Request $request): bool
    {
        if (Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return true;
        }
        Session::flash('error', 'Session expirée. Réessayez.');

        return false;
    }

    // ----- Page -----

    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$user, $tenantId, $userId] = $ctx;
        $view = (string) ($request->query('vue') ?? 'global');
        if (!in_array($view, self::VIEWS, true)) {
            $view = 'global';
        }
        $isAdmin = self::isPlatformAdmin();
        $isManager = self::isOrgManager();
        if ($view === 'moderation' && !$isAdmin && !$isManager) {
            $view = 'global';
        }
        $q = trim((string) ($request->query('q') ?? ''));
        $global = $this->music->globalPlaylist();
        $playlist = null;
        $tracks = [];
        $plId = (int) ($request->query('liste') ?? 0);
        if ($view === 'global') {
            $playlist = $global;
        } elseif ($plId > 0) {
            $playlist = $this->music->findPlaylist($plId);
            if ($playlist === null || !MusicLibraryRepository::playlistVisibleTo($playlist, $tenantId, $userId)) {
                Session::flash('error', 'Playlist introuvable ou privée.');

                return Response::redirect(url(self::BASE . '?vue=playlists'));
            }
            $view = ($playlist['visibility'] ?? '') === 'global' ? 'global' : 'playlists';
        }
        if ($playlist !== null) {
            $tracks = $this->music->playlistTracks((int) $playlist['id'], $tenantId);
        } elseif (in_array($view, ['community', 'org', 'mine'], true)) {
            $tracks = $this->music->listTracks($tenantId, $view, $q, 200, $userId);
        } elseif ($view === 'moderation') {
            $tracks = $this->music->listHidden($tenantId, $isAdmin);
        }
        $playlists = $this->music->listPlaylists($tenantId, $userId);
        // Playlists où je peux ajouter un morceau : les miennes, plus la globale pour l'admin plateforme.
        $editable = array_values(array_filter($playlists, fn (array $p): bool => self::canEditPlaylist($p, $tenantId, $userId)));

        return Response::view('layout.main', [
            'title' => 'Musique',
            'content' => 'admin.member_situation.musique',
            'boPageTitle' => 'Musique',
            'boPageKicker' => 'OPÉRATEUR · BIBLIOTHÈQUE MUSICALE',
            'boPageSubtitle' => 'Mettez en ligne vos morceaux, composez vos playlists et écoutez-les en jeu dans l\'app Musique de l\'ATAK.',
            'backOfficePageCss' => ['back-office-music.css'],
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérateur',
            'boSkipPageHead' => false,
            'view' => $view,
            'q' => $q,
            'tracks' => $tracks,
            'playlist' => $playlist,
            'playlists' => $playlists,
            'editablePlaylists' => $editable,
            'globalPlaylist' => $global,
            'canEditPlaylist' => $playlist !== null && self::canEditPlaylist($playlist, $tenantId, $userId),
            'isPlatformAdmin' => $isAdmin,
            'isOrgManager' => $isManager,
            'userId' => $userId,
            'tenantId' => $tenantId,
            'tenantName' => (string) ($user['tenant_name'] ?? ''),
            'usageBytes' => $this->music->userUsageBytes($userId),
            'quotaBytes' => MusicTrackStorage::USER_QUOTA_BYTES,
            'maxBytes' => MusicTrackStorage::MAX_BYTES,
            'canEditTrack' => fn (array $t): bool => self::canEditTrack($t, $tenantId, $userId),
            'canModerateTrack' => fn (array $t): bool => self::canModerateTrack($t, $tenantId),
        ]);
    }

    // ----- Morceaux -----

    public function upload(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;
        if (!$this->csrfOk($request)) {
            return $this->back($request, '?vue=mine');
        }
        if ($this->music->userUsageBytes($userId) >= MusicTrackStorage::USER_QUOTA_BYTES) {
            Session::flash('error', 'Quota atteint (300 Mo) : supprimez d\'anciens envois pour en ajouter.');

            return $this->back($request, '?vue=mine');
        }
        $stored = MusicTrackStorage::storeFromUpload($tenantId, $userId, $_FILES['audio'] ?? null);
        if ($stored['error'] !== null) {
            Session::flash('error', $stored['error']);

            return $this->back($request, '?vue=mine');
        }
        $dup = $this->music->findDuplicate($userId, (string) $stored['sha1']);
        if ($dup !== null) {
            MusicTrackStorage::delete((string) $stored['path']);
            Session::flash('error', 'Vous avez déjà mis ce morceau en ligne.');

            return $this->back($request, '?vue=mine');
        }
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $title = trim((string) preg_replace('/[_]+/', ' ', pathinfo((string) $stored['original_name'], PATHINFO_FILENAME)));
        }
        $scope = (string) $request->input('scope', 'org');
        $id = $this->music->createTrack([
            'tenant_id' => $tenantId,
            'uploader_user_id' => $userId,
            'scope' => $scope,
            'title' => $title !== '' ? $title : 'Morceau sans titre',
            'artist' => $request->input('artist', ''),
            'genre' => $request->input('genre', ''),
            'duration_seconds' => (int) round((float) $request->input('duration', 0)),
            'file_path' => $stored['path'],
            'file_ext' => $stored['ext'],
            'mime' => $stored['mime'],
            'size_bytes' => $stored['size'],
            'sha1' => $stored['sha1'],
        ]);
        $plId = (int) $request->input('playlist_id', 0);
        if ($plId > 0) {
            $pl = $this->music->findPlaylist($plId);
            if ($pl !== null && self::canEditPlaylist($pl, $tenantId, $userId)) {
                $this->music->addToPlaylist($plId, $id, $userId);
            }
        }
        Session::flash('success', 'Morceau en ligne' . ($scope === 'community' ? ' dans la bibliothèque communautaire.' : ' dans la bibliothèque de votre organisation.'));

        return $this->back($request, '?vue=mine');
    }

    public function trackUpdate(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;
        if (!$this->csrfOk($request)) {
            return $this->back($request);
        }
        $track = $this->music->findTrack((int) ($params['id'] ?? 0));
        if ($track === null || !self::canEditTrack($track, $tenantId, $userId)) {
            Session::flash('error', 'Morceau introuvable ou non modifiable.');

            return $this->back($request);
        }
        $title = trim((string) $request->input('title', ''));
        $this->music->updateTrack((int) $track['id'], [
            'title' => $title !== '' ? $title : (string) $track['title'],
            'artist' => $request->input('artist', ''),
            'genre' => $request->input('genre', ''),
            'scope' => (string) $request->input('scope', (string) $track['scope']),
        ]);
        Session::flash('success', 'Morceau mis à jour.');

        return $this->back($request);
    }

    public function trackDelete(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;
        if (!$this->csrfOk($request)) {
            return $this->back($request);
        }
        $track = $this->music->findTrack((int) ($params['id'] ?? 0));
        if ($track === null || !self::canEditTrack($track, $tenantId, $userId)) {
            Session::flash('error', 'Morceau introuvable ou non supprimable.');

            return $this->back($request);
        }
        $this->music->deleteTrack((int) $track['id']);
        MusicTrackStorage::delete((string) $track['file_path']);
        Session::flash('success', 'Morceau supprimé.');

        return $this->back($request);
    }

    public function trackModerate(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;
        if (!$this->csrfOk($request)) {
            return $this->back($request);
        }
        $track = $this->music->findTrack((int) ($params['id'] ?? 0));
        if ($track === null || !self::canModerateTrack($track, $tenantId)) {
            Session::flash('error', 'Modération réservée aux gestionnaires de l\'organisation (et à l\'administrateur de la plateforme pour la bibliothèque communautaire).');

            return $this->back($request);
        }
        $hide = (string) $request->input('status', 'hidden') === 'hidden';
        $this->music->setTrackStatus((int) $track['id'], $hide ? 'hidden' : 'active', $userId, (string) $request->input('note', ''));
        Session::flash('success', $hide ? 'Morceau masqué : il n\'apparaît plus ni sur le site ni en jeu.' : 'Morceau rétabli.');

        return $this->back($request);
    }

    /** Durée lue par le navigateur (métadonnées audio) quand l'envoi n'en avait pas : JSON. */
    public function trackDuration(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return Response::json(['ok' => false], 401);
        }
        [, $tenantId] = $ctx;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return Response::json(['ok' => false], 419);
        }
        $track = $this->music->findTrack((int) ($params['id'] ?? 0));
        $sec = (int) round((float) $request->input('duration', 0));
        if ($track === null || !MusicLibraryRepository::trackVisibleTo($track, $tenantId) || $sec < 1 || $sec > 36000) {
            return Response::json(['ok' => false], 404);
        }
        $this->music->setTrackDuration((int) $track['id'], $sec);

        return Response::json(['ok' => true]);
    }

    /** Écoute sur le site (session) : flux avec plages pour la barre de lecture. */
    public function listen(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return (new Response())->setStatusCode(401)->setBody('Connexion requise');
        }
        [, $tenantId, $userId] = $ctx;
        $track = $this->music->findTrack((int) ($params['id'] ?? 0));
        if ($track === null) {
            return (new Response())->setStatusCode(404)->setBody('Morceau introuvable');
        }
        $own = (int) $track['uploader_user_id'] === $userId;
        if (!$own && !MusicLibraryRepository::trackVisibleTo($track, $tenantId) && !self::canModerateTrack($track, $tenantId)) {
            return (new Response())->setStatusCode(404)->setBody('Morceau introuvable');
        }
        $range = $_SERVER['HTTP_RANGE'] ?? null;
        if ($range === null || str_starts_with((string) $range, 'bytes=0-')) {
            $this->music->incrementPlays((int) $track['id']);
        }

        return MusicTrackStorage::streamResponse($track, is_string($range) ? $range : null);
    }

    // ----- Playlists -----

    public function playlistCreate(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;
        if (!$this->csrfOk($request)) {
            return $this->back($request, '?vue=playlists');
        }
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Donnez un nom à la playlist.');

            return $this->back($request, '?vue=playlists');
        }
        $id = $this->music->createPlaylist($tenantId, $userId, $name, (string) $request->input('description', ''), (string) $request->input('visibility', 'private'));
        Session::flash('success', 'Playlist créée.');

        return Response::redirect(url(self::BASE . '?vue=playlists&liste=' . $id));
    }

    public function playlistUpdate(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;
        if (!$this->csrfOk($request)) {
            return $this->back($request);
        }
        $pl = $this->music->findPlaylist((int) ($params['id'] ?? 0));
        if ($pl === null || !self::canEditPlaylist($pl, $tenantId, $userId)) {
            Session::flash('error', 'Playlist non modifiable.');

            return $this->back($request);
        }
        $name = trim((string) $request->input('name', ''));
        $this->music->updatePlaylist(
            (int) $pl['id'],
            $name !== '' ? $name : (string) $pl['name'],
            (string) $request->input('description', ''),
            ($pl['visibility'] ?? '') === 'global' ? null : (string) $request->input('visibility', (string) $pl['visibility'])
        );
        Session::flash('success', 'Playlist mise à jour.');

        return $this->back($request);
    }

    public function playlistDelete(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;
        if (!$this->csrfOk($request)) {
            return $this->back($request, '?vue=playlists');
        }
        $pl = $this->music->findPlaylist((int) ($params['id'] ?? 0));
        if ($pl === null || ($pl['visibility'] ?? '') === 'global' || !self::canEditPlaylist($pl, $tenantId, $userId)) {
            Session::flash('error', 'Playlist non supprimable.');

            return $this->back($request, '?vue=playlists');
        }
        $this->music->deletePlaylist((int) $pl['id']);
        Session::flash('success', 'Playlist supprimée.');

        return Response::redirect(url(self::BASE . '?vue=playlists'));
    }

    public function playlistAdd(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;
        if (!$this->csrfOk($request)) {
            return $this->back($request);
        }
        $pl = $this->music->findPlaylist((int) ($params['id'] ?? 0));
        $track = $this->music->findTrack((int) $request->input('track_id', 0));
        if ($pl === null || !self::canEditPlaylist($pl, $tenantId, $userId)) {
            Session::flash('error', 'Playlist non modifiable.');

            return $this->back($request);
        }
        if ($track === null || !MusicLibraryRepository::trackVisibleTo($track, $tenantId)) {
            Session::flash('error', 'Morceau introuvable.');

            return $this->back($request);
        }
        // La playlist globale s'entend sur toute la plateforme : seulement des morceaux de la bibliothèque communautaire.
        if (($pl['visibility'] ?? '') === 'global' && ($track['scope'] ?? '') !== 'community') {
            Session::flash('error', 'La playlist globale ne prend que des morceaux de la bibliothèque communautaire (un morceau d\'organisation n\'est pas entendu des autres communautés).');

            return $this->back($request);
        }
        $added = $this->music->addToPlaylist((int) $pl['id'], (int) $track['id'], $userId);
        Session::flash('success', $added ? 'Ajouté à « ' . $pl['name'] . ' ».' : 'Déjà dans « ' . $pl['name'] . ' ».');

        return $this->back($request);
    }

    public function playlistRemove(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;
        if (!$this->csrfOk($request)) {
            return $this->back($request);
        }
        $pl = $this->music->findPlaylist((int) ($params['id'] ?? 0));
        if ($pl === null || !self::canEditPlaylist($pl, $tenantId, $userId)) {
            Session::flash('error', 'Playlist non modifiable.');

            return $this->back($request);
        }
        $this->music->removeFromPlaylist((int) $pl['id'], (int) $request->input('track_id', 0));
        Session::flash('success', 'Retiré de la playlist.');

        return $this->back($request);
    }

    public function playlistMove(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;
        if (!$this->csrfOk($request)) {
            return $this->back($request);
        }
        $pl = $this->music->findPlaylist((int) ($params['id'] ?? 0));
        if ($pl === null || !self::canEditPlaylist($pl, $tenantId, $userId)) {
            return $this->back($request);
        }
        $this->music->moveInPlaylist((int) $pl['id'], (int) $request->input('track_id', 0), (int) $request->input('dir', 1));

        return $this->back($request);
    }
}
