<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\MusicLibraryRepository;
use App\Support\ComspecApiKeyAuth;
use App\Support\MusicTrackStorage;

/**
 * Bibliothèque musicale pour l'app Musique de l'ATAK natif.
 *
 *  GET /api/atak/music?view=global|community|org|mine|playlists|pl:<id>&q=&limit=
 *      (protégé comme toute route /api/atak : jeton de jeu ou clé de communauté) → {"items":[…]}
 *      piste    : {type:"T", id, title, artist, duration, url (absolue, signée), by}
 *      playlist : {type:"P", id, name, owner, count, kind (global|public|org|private), description}
 *  GET /api/music/stream/{id}.{ext}?exp=&sig= : fichier audio par URL signée (le lecteur de la DLL n'envoie
 *      aucun en-tête Athena ; les joueurs proches en haut-parleur lisent la même URL).
 */
final class MusicLibraryApiController
{
    public function __construct(private ?MusicLibraryRepository $music = null)
    {
        $this->music ??= new MusicLibraryRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (ComspecApiKeyAuth::matchedTenantId() ?? 0);
        $userId = (int) (ComspecApiKeyAuth::matchedUserId() ?? 0);
        if ($tenantId < 1) {
            return Response::json(['error' => 'tenant_context_required'], 403);
        }
        $view = trim((string) ($request->query('view') ?? 'global'));
        $q = mb_substr(trim((string) ($request->query('q') ?? '')), 0, 80);
        $limit = max(1, min(120, (int) ($request->query('limit') ?? 80)));
        try {
            if ($view === 'playlists') {
                $items = [];
                foreach ($this->music->listPlaylists($tenantId, $userId) as $p) {
                    $items[] = [
                        'type' => 'P',
                        'id' => (int) $p['id'],
                        'name' => (string) $p['name'],
                        'owner' => ($p['visibility'] ?? '') === 'global' ? 'Athena' : (string) ($p['owner_name'] ?? ''),
                        'count' => (int) ($p['track_count'] ?? 0),
                        'kind' => (string) $p['visibility'],
                        'description' => (string) ($p['description'] ?? ''),
                    ];
                }

                return Response::json(['items' => array_slice($items, 0, $limit)]);
            }
            if ($view === 'global' || str_starts_with($view, 'pl:')) {
                $pl = $view === 'global' ? $this->music->globalPlaylist() : $this->music->findPlaylist((int) substr($view, 3));
                if ($pl === null || !MusicLibraryRepository::playlistVisibleTo($pl, $tenantId, $userId)) {
                    return Response::json(['error' => 'not_found'], 404);
                }
                $tracks = $this->music->playlistTracks((int) $pl['id'], $tenantId, $limit);
            } elseif (in_array($view, ['community', 'org', 'mine'], true)) {
                if ($view === 'mine' && $userId < 1) {
                    return Response::json(['items' => [], 'note' => 'account_link_required']);
                }
                $tracks = $this->music->listTracks($tenantId, $view, $q, $limit, $userId);
            } else {
                return Response::json(['error' => 'unknown_view'], 400);
            }
        } catch (\PDOException) {
            return Response::json(['error' => 'migration_required'], 503);
        }
        $base = rtrim((string) (function_exists('url') ? url('') : ''), '/');

        return Response::json(['items' => array_map(static fn (array $t): array => [
            'type' => 'T',
            'id' => (int) $t['id'],
            'title' => (string) $t['title'],
            'artist' => (string) ($t['artist'] ?? ''),
            'duration' => (int) ($t['duration_seconds'] ?? 0),
            'url' => $base . MusicTrackStorage::signedPath((int) $t['id'], (string) $t['file_ext']),
            'by' => (string) ($t['uploader_name'] ?? ''),
        ], $tracks)]);
    }

    public function stream(Request $request, array $params = []): Response
    {
        $raw = (string) ($params['file'] ?? '');
        if (!preg_match('/^(\d+)\.(mp3|m4a|aac|wav)$/', $raw, $m)) {
            return (new Response())->setStatusCode(404)->setBody('');
        }
        $id = (int) $m[1];
        if (!MusicTrackStorage::verify($id, (int) ($request->query('exp') ?? 0), (string) ($request->query('sig') ?? ''))) {
            return (new Response())->setStatusCode(403)->setBody('Lien expiré');
        }
        $track = $this->music->findTrack($id);
        if ($track === null || ($track['status'] ?? '') !== 'active') {
            return (new Response())->setStatusCode(404)->setBody('');
        }
        $range = $_SERVER['HTTP_RANGE'] ?? null;
        if ($range === null) {
            $this->music->incrementPlays($id);
        }

        return MusicTrackStorage::streamResponse($track, is_string($range) ? $range : null);
    }
}
