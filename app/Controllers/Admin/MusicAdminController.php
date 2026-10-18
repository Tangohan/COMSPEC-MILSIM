<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\MusicLibraryRepository;
use App\Services\Auth\AuthService;
use App\Support\MusicTitleSimilarity;
use App\Support\MusicTrackStorage;

/**
 * Administration de la bibliothèque musicale (morceaux et playlists synchronisés avec l'app Musique de l'ATAK natif).
 *
 *  - /admin/musique (administrateur de la plateforme, SystemAdminMiddleware) : toutes les organisations,
 *    statistiques, répartition par organisation, playlist globale, toutes les playlists.
 *  - /back-office/ressources/musique (gestionnaires, TenantResourceAdminMiddleware) : les morceaux et playlists
 *    de son organisation seulement.
 *
 * Actions groupées sur les morceaux cochés : masquer, rétablir, passer en communauté / organisation, supprimer,
 * ajouter à la playlist globale (plateforme seulement).
 */
final class MusicAdminController
{
    private const PER_PAGE = 50;

    public function __construct(
        private ?AuthService $authService = null,
        private ?MusicLibraryRepository $music = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->music ??= new MusicLibraryRepository();
    }

    /** @return array{0: int, 1: int}|Response [tenantId, userId] */
    private function ctx(): array|Response
    {
        $user = $this->authService->user();
        $userId = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? Session::get('tenant_id') ?? 0);
        if ($userId < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return [$tenantId, $userId];
    }

    private static function base(bool $platform): string
    {
        return $platform ? 'admin/musique' : 'back-office/ressources/musique';
    }

    // ----- Pages -----

    public function platform(Request $request, array $params = []): Response
    {
        return $this->render($request, true);
    }

    public function tenant(Request $request, array $params = []): Response
    {
        return $this->render($request, false);
    }

    private function render(Request $request, bool $platform): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $scopeTenant = $platform ? null : $tenantId;
        $filters = [
            'q' => trim((string) ($request->query('q') ?? '')),
            'scope' => (string) ($request->query('portee') ?? ''),
            'status' => (string) ($request->query('etat') ?? ''),
            'tenant' => $platform ? (int) ($request->query('organisation') ?? 0) : 0,
        ];
        $page = max(1, (int) ($request->query('page') ?? 1));
        $tab = (string) ($request->query('onglet') ?? 'morceaux');
        if (!in_array($tab, ['morceaux', 'playlists', 'stats'], true)) {
            $tab = 'morceaux';
        }
        $tracks = $this->music->adminTracks($scopeTenant, $filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $global = $this->music->globalPlaylist();
        $globalIds = array_map(static fn (array $t): int => (int) $t['id'], $this->music->playlistTracks((int) $global['id'], 0, 500));

        $vars = [
            'title' => $platform ? 'Musique · plateforme' : 'Musique · gestion',
            'content' => 'admin.music.index',
            'backOfficePageCss' => ['back-office-music.css'],
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'platform' => $platform,
            'base' => url(self::base($platform)),
            'tab' => $tab,
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'tracks' => $tracks['rows'],
            'total' => $tracks['total'],
            'stats' => $this->music->adminStats($scopeTenant),
            'uploaders' => $this->music->adminTopUploaders($scopeTenant),
            'uploaderDefaultAlias' => (static function (array $u): string {
                $callsign = trim((string) ($u['callsign'] ?? ''));

                return $callsign !== '' ? $callsign : trim((string) ($u['display_name'] ?? ''));
            })((array) ($this->authService->user() ?? [])),
            'tenants' => $platform ? $this->music->adminTenantBreakdown() : [],
            'playlists' => $this->music->adminPlaylists($scopeTenant),
            'globalPlaylist' => $global,
            'globalIds' => $globalIds,
            'quotaBytes' => MusicTrackStorage::USER_QUOTA_BYTES,
            'libraryUrl' => url('back-office/ma-situation/musique'),
        ];
        if ($platform) {
            $vars['isPlatformAdminShell'] = true;
        } else {
            $vars += [
                'isBackOfficeShell' => true,
                'boPageGroup' => 'Communauté',
                'boPageKicker' => 'COMMUNAUTÉ · MUSIQUE',
                'boPageTitle' => 'Musique de l\'organisation',
                'boPageSubtitle' => 'Morceaux et playlists de vos membres, écoutés sur le site et en jeu dans l\'app Musique de l\'ATAK.',
                'boSkipPageHead' => false,
            ];
        }

        return Response::view('layout.main', $vars);
    }

    // ----- Actions -----

    public function platformBulk(Request $request, array $params = []): Response
    {
        return $this->bulk($request, true);
    }

    public function tenantBulk(Request $request, array $params = []): Response
    {
        return $this->bulk($request, false);
    }

    private function bulk(Request $request, bool $platform): Response
    {
        $back = Response::redirect(url(self::base($platform)) . self::backQuery($request));
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return $back;
        }
        if ($platform && !Gate::getInstance()->allows('admin.system')) {
            return $back;
        }
        $action = (string) $request->input('action', '');
        $ids = $request->input('ids', []);
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : [$ids]), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            Session::flash('error', 'Cochez au moins un morceau.');

            return $back;
        }
        $global = $this->music->globalPlaylist();
        $done = 0;
        $skipped = 0;
        foreach (array_slice($ids, 0, 200) as $id) {
            $t = $this->music->findTrack($id);
            // Un gestionnaire n'agit que sur les morceaux de son organisation.
            if ($t === null || (!$platform && (int) $t['tenant_id'] !== $tenantId)) {
                $skipped++;
                continue;
            }
            switch ($action) {
                case 'hide':
                    $this->music->setTrackStatus($id, 'hidden', $userId, (string) $request->input('note', ''));
                    break;
                case 'restore':
                    $this->music->setTrackStatus($id, 'active', $userId);
                    break;
                case 'community':
                case 'org':
                    $this->music->setTrackScope($id, $action);
                    break;
                case 'global_add':
                    if (!$platform || $t['scope'] !== 'community' || $t['status'] !== 'active') {
                        $skipped++;
                        continue 2;
                    }
                    $this->music->addToPlaylist((int) $global['id'], $id, $userId);
                    break;
                case 'global_remove':
                    if (!$platform) {
                        $skipped++;
                        continue 2;
                    }
                    $this->music->removeFromPlaylist((int) $global['id'], $id);
                    break;
                case 'delete':
                    $this->music->deleteTrack($id);
                    MusicTrackStorage::delete((string) $t['file_path']);
                    break;
                default:
                    Session::flash('error', 'Action inconnue.');

                    return $back;
            }
            $done++;
        }
        $labels = [
            'hide' => 'masqué(s)', 'restore' => 'rétabli(s)', 'community' => 'passé(s) dans la bibliothèque communautaire',
            'org' => 'passé(s) dans la bibliothèque de leur organisation', 'global_add' => 'ajouté(s) à la playlist globale',
            'global_remove' => 'retiré(s) de la playlist globale', 'delete' => 'supprimé(s)',
        ];
        Session::flash('success', $done . ' morceau(x) ' . ($labels[$action] ?? 'traité(s)') . '.'
            . ($skipped > 0 ? ' ' . $skipped . ' ignoré(s)' . ($action === 'global_add' ? ' : seuls les morceaux communautaires visibles vont dans la playlist globale.' : '.') : ''));

        return $back;
    }

    public function platformUpload(Request $request, array $params = []): Response
    {
        return $this->upload($request, true);
    }

    public function tenantUpload(Request $request, array $params = []): Response
    {
        return $this->upload($request, false);
    }

    /**
     * Envoi d'un ou plusieurs fichiers (champ audio[]). La page envoie les fichiers un par un en arrière-plan
     * (réponse JSON) pour rester sous les limites du serveur ; sans JavaScript, le formulaire envoie tout d'un coup.
     * Le quota par membre ne s'applique pas à l'administrateur de la plateforme.
     */
    private function upload(Request $request, bool $platform): Response
    {
        $json = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        $finish = function (int $done, array $errors, string $summary) use ($json, $platform): Response {
            if ($json) {
                return Response::json(['ok' => $errors === [], 'done' => $done, 'errors' => $errors], $done > 0 || $errors === [] ? 200 : 422);
            }
            if ($done > 0) {
                Session::flash('success', $summary);
            }
            if ($errors !== []) {
                Session::flash('error', implode(' ', array_slice($errors, 0, 8)) . (count($errors) > 8 ? ' (+' . (count($errors) - 8) . ')' : ''));
            }

            return Response::redirect(url(self::base($platform)));
        };
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $finish(0, ['Session expirée. Rechargez la page.'], '');
        }
        if ($platform && !Gate::getInstance()->allows('admin.system')) {
            return $finish(0, ['Accès refusé.'], '');
        }
        $files = self::uploadedFiles($_FILES['audio'] ?? null);
        if ($files === []) {
            return $finish(0, ['Aucun fichier reçu.'], '');
        }
        $toGlobal = $platform && (string) $request->input('global', '') === '1';
        $scope = $toGlobal ? 'community' : ((string) $request->input('scope', 'org') === 'community' ? 'community' : 'org');
        $playlist = null;
        $plId = (int) $request->input('playlist_id', 0);
        if ($plId > 0) {
            $pl = $this->music->findPlaylist($plId);
            if ($pl !== null && ($pl['visibility'] ?? '') !== 'global' && ($platform || (int) ($pl['tenant_id'] ?? 0) === $tenantId)) {
                $playlist = $pl;
            }
        }
        $global = $toGlobal ? $this->music->globalPlaylist() : null;
        $durations = array_map('floatval', explode(',', (string) $request->input('durations', '')));
        $titleIn = trim((string) $request->input('title', ''));
        $alias = mb_substr(trim((string) $request->input('uploader_alias', '')), 0, 80);
        $forceDuplicates = (string) $request->input('confirm_duplicates', '') === '1';
        $done = 0;
        $errors = [];
        foreach ($files as $i => $file) {
            $label = trim((string) ($file['name'] ?? 'fichier')) . ' : ';
            $candidate = count($files) === 1 && $titleIn !== '' ? $titleIn : MusicTitleSimilarity::titleFromFilename((string) ($file['name'] ?? ''));
            if (!$forceDuplicates) {
                $similar = $this->music->findSimilarTitles($candidate, $platform ? null : $tenantId, 3);
                if ($similar !== []) {
                    $errors[] = $label . 'titre proche de « ' . $similar[0]['title'] . ' » déjà en ligne. Confirmez l’envoi pour le mettre quand même.';
                    continue;
                }
            }
            if (!$platform && $this->music->userUsageBytes($userId) >= MusicTrackStorage::USER_QUOTA_BYTES) {
                $errors[] = $label . 'quota atteint (300 Mo).';
                continue;
            }
            $stored = MusicTrackStorage::storeFromUpload($tenantId, $userId, $file);
            if ($stored['error'] !== null) {
                $errors[] = $label . $stored['error'];
                continue;
            }
            if ($this->music->findDuplicate($userId, (string) $stored['sha1']) !== null) {
                MusicTrackStorage::delete((string) $stored['path']);
                $errors[] = $label . 'déjà en ligne.';
                continue;
            }
            $title = count($files) === 1 && $titleIn !== '' ? $titleIn
                : MusicTitleSimilarity::titleFromFilename((string) $stored['original_name']);
            $id = $this->music->createTrack([
                'tenant_id' => $tenantId,
                'uploader_user_id' => $userId,
                'uploader_alias' => $alias,
                'scope' => $scope,
                'title' => $title !== '' ? $title : 'Morceau sans titre',
                'artist' => $request->input('artist', ''),
                'genre' => $request->input('genre', ''),
                'duration_seconds' => (int) round($durations[$i] ?? 0),
                'file_path' => $stored['path'],
                'file_ext' => $stored['ext'],
                'mime' => $stored['mime'],
                'size_bytes' => $stored['size'],
                'sha1' => $stored['sha1'],
            ]);
            if ($global !== null) {
                $this->music->addToPlaylist((int) $global['id'], $id, $userId);
            }
            if ($playlist !== null) {
                $this->music->addToPlaylist((int) $playlist['id'], $id, $userId);
            }
            $done++;
        }
        $where = $toGlobal ? ' dans la playlist globale' : ($scope === 'community' ? ' dans la bibliothèque communautaire' : ' dans la bibliothèque de l\'organisation');

        return $finish($done, $errors, $done . ' morceau(x) en ligne' . $where . ($playlist !== null ? ' et dans « ' . $playlist['name'] . ' »' : '') . '.');
    }

    public function platformDuplicates(Request $request, array $params = []): Response
    {
        return $this->duplicates($request, true);
    }

    public function tenantDuplicates(Request $request, array $params = []): Response
    {
        return $this->duplicates($request, false);
    }

    /**
     * Pré-contrôle avant envoi : pour chaque titre proposé, les morceaux déjà en ligne qui lui ressemblent.
     * Réponse : {"matches": {"<titre>": [{title, artist, uploader_name, tenant_name, score}]}}.
     */
    private function duplicates(Request $request, bool $platform): Response
    {
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return Response::json(['ok' => false, 'matches' => []], 401);
        }
        [$tenantId] = $ctx;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée. Rechargez la page.', 'matches' => []], 419);
        }
        $titles = $request->input('titles', []);
        $titles = is_array($titles) ? array_slice(array_values(array_filter(array_map(static fn ($t): string => mb_substr(trim((string) $t), 0, 160), $titles))), 0, 50) : [];
        $matches = [];
        foreach ($titles as $title) {
            $found = $this->music->findSimilarTitles($title, $platform ? null : $tenantId, 3);
            if ($found !== []) {
                $matches[$title] = $found;
            }
        }

        return Response::json(['ok' => true, 'matches' => (object) $matches]);
    }

    /**
     * $_FILES['audio'] en liste de fichiers, qu'il soit simple (audio) ou multiple (audio[]).
     *
     * @return list<array<string, mixed>>
     */
    public static function uploadedFiles(mixed $f): array
    {
        if (!is_array($f) || !isset($f['name'])) {
            return [];
        }
        if (!is_array($f['name'])) {
            return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$f];
        }
        $out = [];
        foreach (array_keys($f['name']) as $k) {
            if ((int) ($f['error'][$k] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = ['name' => $f['name'][$k], 'type' => $f['type'][$k] ?? '', 'tmp_name' => $f['tmp_name'][$k] ?? '', 'error' => $f['error'][$k], 'size' => $f['size'][$k] ?? 0];
        }

        return array_slice($out, 0, 50);
    }

    public function platformPlaylistDelete(Request $request, array $params = []): Response
    {
        return $this->playlistDelete($request, $params, true);
    }

    public function tenantPlaylistDelete(Request $request, array $params = []): Response
    {
        return $this->playlistDelete($request, $params, false);
    }

    /** @param array<string, mixed> $params */
    private function playlistDelete(Request $request, array $params, bool $platform): Response
    {
        $back = Response::redirect(url(self::base($platform)) . '?onglet=playlists');
        $ctx = $this->ctx();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return $back;
        }
        $pl = $this->music->findPlaylist((int) ($params['id'] ?? 0));
        if ($pl === null || ($pl['visibility'] ?? '') === 'global' || (!$platform && (int) ($pl['tenant_id'] ?? 0) !== $tenantId)) {
            Session::flash('error', 'Playlist introuvable ou non supprimable.');

            return $back;
        }
        $this->music->deletePlaylist((int) $pl['id']);
        Session::flash('success', 'Playlist « ' . $pl['name'] . ' » supprimée.');

        return $back;
    }

    private static function backQuery(Request $request): string
    {
        $q = trim((string) $request->input('_back', ''));

        return str_starts_with($q, '?') ? $q : '';
    }
}
