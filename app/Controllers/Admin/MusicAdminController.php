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
