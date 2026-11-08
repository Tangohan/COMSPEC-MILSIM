<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Repositories\MusicLibraryRepository;
use App\Support\MusicTrackStorage;

/**
 * Bibliothèque musicale de la page /m/musique : mêmes vues et mêmes droits que l'app Musique en jeu
 * (MusicLibraryApiController), avec l'identité du lien mobile. Une playlist hors de portée retombe sur la globale.
 */
final class AtakMobileMusicService
{
    public const LIMIT = 120;

    public function __construct(private MusicLibraryRepository $music)
    {
    }

    /**
     * @return array{vue: string, heading: string, tracks: list<array{id: int, title: string, artist: string, duration: int, url: string, by: string}>, playlists: list<array{id: int, name: string, owner: string, count: int}>}
     */
    public function view(int $tenantId, int $userId, string $vue, string $q): array
    {
        $out = ['vue' => $vue, 'heading' => '', 'tracks' => [], 'playlists' => []];
        if ($vue === 'playlists') {
            $out['heading'] = 'Playlists';
            foreach ($this->music->listPlaylists($tenantId, $userId) as $p) {
                $out['playlists'][] = [
                    'id' => (int) $p['id'],
                    'name' => (string) $p['name'],
                    'owner' => ($p['visibility'] ?? '') === 'global' ? 'Athena' : (string) ($p['owner_name'] ?? ''),
                    'count' => (int) ($p['track_count'] ?? 0),
                ];
            }

            return $out;
        }
        $headings = ['org' => 'Ma communauté', 'community' => 'Partagés', 'mine' => 'Mes envois'];
        if (isset($headings[$vue])) {
            $out['heading'] = $headings[$vue];
            $out['tracks'] = self::rows($this->music->listTracks($tenantId, $vue, $q, self::LIMIT, $userId));

            return $out;
        }
        $playlist = str_starts_with($vue, 'pl:') ? $this->music->findPlaylist((int) substr($vue, 3)) : null;
        if ($playlist === null || !MusicLibraryRepository::playlistVisibleTo($playlist, $tenantId, $userId)) {
            $playlist = $this->music->globalPlaylist();
            $out['vue'] = 'global';
        }
        $out['heading'] = (string) ($playlist['name'] ?? '');
        $out['tracks'] = self::rows($this->music->playlistTracks((int) $playlist['id'], $tenantId, self::LIMIT));

        return $out;
    }

    /**
     * Morceaux jouables : un envoi masqué par la modération n'a pas de flux.
     *
     * @param list<array<string, mixed>> $tracks
     * @return list<array{id: int, title: string, artist: string, duration: int, url: string, by: string}>
     */
    public static function rows(array $tracks, string $base = ''): array
    {
        $base = $base !== '' ? $base : rtrim((string) (function_exists('url') ? url('') : ''), '/');
        $out = [];
        foreach ($tracks as $t) {
            if ((string) ($t['status'] ?? 'active') !== 'active') {
                continue;
            }
            $out[] = [
                'id' => (int) $t['id'],
                'title' => (string) $t['title'],
                'artist' => (string) ($t['artist'] ?? ''),
                'duration' => (int) ($t['duration_seconds'] ?? 0),
                'url' => $base . MusicTrackStorage::signedPath((int) $t['id'], (string) $t['file_ext']),
                'by' => (string) ($t['uploader_name'] ?? ''),
            ];
        }

        return $out;
    }
}
