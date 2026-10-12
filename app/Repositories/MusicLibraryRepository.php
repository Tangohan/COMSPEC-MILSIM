<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

/**
 * Bibliothèque musicale : morceaux (communauté = toute la plateforme, org = mon organisation),
 * playlists (privée, organisation, publique, globale) et leur contenu.
 *
 * Règle de visibilité d'un morceau pour un lecteur (tenant T) : status = active ET (scope = community OU tenant_id = T).
 */
final class MusicLibraryRepository
{
    public const SCOPES = ['community', 'org'];

    public const VISIBILITIES = ['private', 'org', 'public'];

    private const TRACK_COLS = 't.id, t.tenant_id, t.uploader_user_id, t.scope, t.status, t.title, t.artist, t.genre, t.duration_seconds,
        t.file_path, t.file_ext, t.size_bytes, t.play_count, t.created_at,
        COALESCE(NULLIF(u.callsign, \'\'), NULLIF(u.display_name, \'\'), \'Membre\') AS uploader_name, tn.name AS tenant_name';

    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        SilentSchemaMigration::run(base_path('bootstrap/music_library_migration.php'));
    }

    private static function visibleSql(): string
    {
        return "t.status = 'active' AND (t.scope = 'community' OR t.tenant_id = ?)";
    }

    private static function like(string $q): string
    {
        return '%' . addcslashes($q, '%_\\') . '%';
    }

    // ----- Morceaux -----

    /** @return list<array<string, mixed>> */
    public function listTracks(int $tenantId, string $view, string $q = '', int $limit = 100, int $userId = 0): array
    {
        $where = [];
        $params = [];
        switch ($view) {
            case 'community':
                $where[] = "t.status = 'active' AND t.scope = 'community'";
                break;
            case 'org':
                $where[] = "t.status = 'active' AND t.tenant_id = ?";
                $params[] = $tenantId;
                break;
            case 'mine':
                $where[] = 't.uploader_user_id = ? AND t.tenant_id = ?';
                $params[] = $userId;
                $params[] = $tenantId;
                break;
            case 'all':
            default:
                $where[] = self::visibleSql();
                $params[] = $tenantId;
        }
        $q = trim($q);
        if ($q !== '') {
            $where[] = '(t.title LIKE ? OR t.artist LIKE ? OR t.genre LIKE ?)';
            $like = self::like($q);
            array_push($params, $like, $like, $like);
        }
        $limit = max(1, min(500, $limit));

        return $this->db->fetchAll(
            'SELECT ' . self::TRACK_COLS . ' FROM music_tracks t
             LEFT JOIN users u ON u.id = t.uploader_user_id
             LEFT JOIN tenants tn ON tn.id = t.tenant_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY t.created_at DESC, t.id DESC LIMIT ' . $limit,
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function findTrack(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT ' . self::TRACK_COLS . ', t.sha1, t.mime FROM music_tracks t
             LEFT JOIN users u ON u.id = t.uploader_user_id
             LEFT JOIN tenants tn ON tn.id = t.tenant_id WHERE t.id = ?',
            [$id]
        );
    }

    /** @param array<string, mixed> $track */
    public static function trackVisibleTo(array $track, int $tenantId): bool
    {
        return ($track['status'] ?? '') === 'active'
            && (($track['scope'] ?? '') === 'community' || (int) ($track['tenant_id'] ?? 0) === $tenantId);
    }

    public function userUsageBytes(int $userId): int
    {
        $row = $this->db->fetchOne('SELECT COALESCE(SUM(size_bytes), 0) AS b FROM music_tracks WHERE uploader_user_id = ?', [$userId]);

        return (int) ($row['b'] ?? 0);
    }

    public function findDuplicate(int $userId, string $sha1): ?int
    {
        $row = $this->db->fetchOne('SELECT id FROM music_tracks WHERE uploader_user_id = ? AND sha1 = ? LIMIT 1', [$userId, $sha1]);

        return $row ? (int) $row['id'] : null;
    }

    /** @param array<string, mixed> $data */
    public function createTrack(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO music_tracks (tenant_id, uploader_user_id, scope, status, title, artist, genre, duration_seconds, file_path, file_ext, mime, size_bytes, sha1)
             VALUES (?, ?, ?, \'active\', ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['tenant_id'], (int) $data['uploader_user_id'],
                in_array($data['scope'] ?? '', self::SCOPES, true) ? $data['scope'] : 'org',
                mb_substr((string) $data['title'], 0, 160), self::nullable($data['artist'] ?? null, 120), self::nullable($data['genre'] ?? null, 60),
                max(0, (int) ($data['duration_seconds'] ?? 0)),
                (string) $data['file_path'], (string) $data['file_ext'], (string) $data['mime'], (int) $data['size_bytes'], (string) $data['sha1'],
            ]
        );
    }

    /** @param array<string, mixed> $data */
    public function updateTrack(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE music_tracks SET title = ?, artist = ?, genre = ?, scope = ?, updated_at = NOW() WHERE id = ?',
            [
                mb_substr((string) $data['title'], 0, 160), self::nullable($data['artist'] ?? null, 120), self::nullable($data['genre'] ?? null, 60),
                in_array($data['scope'] ?? '', self::SCOPES, true) ? $data['scope'] : 'org', $id,
            ]
        );
    }

    public function setTrackStatus(int $id, string $status, int $moderatorId, string $note = ''): void
    {
        $this->db->execute(
            'UPDATE music_tracks SET status = ?, moderated_by_user_id = ?, moderation_note = ?, updated_at = NOW() WHERE id = ?',
            [$status === 'hidden' ? 'hidden' : 'active', $moderatorId, self::nullable($note, 255), $id]
        );
    }

    public function setTrackDuration(int $id, int $seconds): void
    {
        $this->db->execute('UPDATE music_tracks SET duration_seconds = ? WHERE id = ? AND duration_seconds = 0', [max(0, $seconds), $id]);
    }

    public function incrementPlays(int $id): void
    {
        $this->db->execute('UPDATE music_tracks SET play_count = play_count + 1 WHERE id = ?', [$id]);
    }

    public function deleteTrack(int $id): void
    {
        $this->db->execute('DELETE FROM music_playlist_tracks WHERE track_id = ?', [$id]);
        $this->db->execute('DELETE FROM music_tracks WHERE id = ?', [$id]);
    }

    /** Morceaux masqués à revoir (modération) : ceux de l'organisation, plus ceux de la communauté pour l'admin plateforme. @return list<array<string, mixed>> */
    public function listHidden(int $tenantId, bool $platformAdmin): array
    {
        $sql = 'SELECT ' . self::TRACK_COLS . ' FROM music_tracks t LEFT JOIN users u ON u.id = t.uploader_user_id LEFT JOIN tenants tn ON tn.id = t.tenant_id
                WHERE t.status = \'hidden\' AND ' . ($platformAdmin ? '(t.tenant_id = ? OR t.scope = \'community\')' : 't.tenant_id = ?') . ' ORDER BY t.updated_at DESC LIMIT 200';

        return $this->db->fetchAll($sql, [$tenantId]);
    }

    // ----- Playlists -----

    private const PL_COLS = 'p.id, p.tenant_id, p.owner_user_id, p.name, p.description, p.visibility, p.created_at, p.updated_at,
        COALESCE(NULLIF(u.callsign, \'\'), NULLIF(u.display_name, \'\'), \'Athena\') AS owner_name, tn.name AS tenant_name,
        (SELECT COUNT(*) FROM music_playlist_tracks pt WHERE pt.playlist_id = p.id) AS track_count';

    /** @return array<string, mixed> la playlist globale (créée au premier appel) */
    public function globalPlaylist(): array
    {
        $row = $this->db->fetchOne(
            'SELECT ' . self::PL_COLS . ' FROM music_playlists p LEFT JOIN users u ON u.id = p.owner_user_id LEFT JOIN tenants tn ON tn.id = p.tenant_id
             WHERE p.visibility = \'global\' ORDER BY p.id ASC LIMIT 1'
        );
        if ($row) {
            return $row;
        }
        $this->db->insert(
            'INSERT INTO music_playlists (tenant_id, owner_user_id, name, description, visibility) VALUES (NULL, NULL, ?, ?, \'global\')',
            ['Playlist globale', 'La sélection de la plateforme, choisie par son administrateur.']
        );

        return $this->globalPlaylist();
    }

    /**
     * Playlists que ce membre peut écouter : globale, publiques (toute la plateforme), de son organisation, les siennes.
     *
     * @return list<array<string, mixed>>
     */
    public function listPlaylists(int $tenantId, int $userId, string $filter = 'all'): array
    {
        $this->globalPlaylist();
        $where = match ($filter) {
            'mine' => ['p.owner_user_id = ? AND p.visibility <> \'global\'', [$userId]],
            'public' => ['p.visibility IN (\'global\', \'public\')', []],
            'org' => ['p.visibility = \'org\' AND p.tenant_id = ?', [$tenantId]],
            default => ['(p.visibility IN (\'global\', \'public\') OR (p.visibility = \'org\' AND p.tenant_id = ?) OR p.owner_user_id = ?)', [$tenantId, $userId]],
        };

        return $this->db->fetchAll(
            'SELECT ' . self::PL_COLS . ' FROM music_playlists p LEFT JOIN users u ON u.id = p.owner_user_id LEFT JOIN tenants tn ON tn.id = p.tenant_id
             WHERE ' . $where[0] . " ORDER BY (p.visibility = 'global') DESC, p.updated_at DESC, p.id DESC LIMIT 200",
            $where[1]
        );
    }

    /** @return array<string, mixed>|null */
    public function findPlaylist(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT ' . self::PL_COLS . ' FROM music_playlists p LEFT JOIN users u ON u.id = p.owner_user_id LEFT JOIN tenants tn ON tn.id = p.tenant_id WHERE p.id = ?',
            [$id]
        );
    }

    /** @param array<string, mixed> $pl */
    public static function playlistVisibleTo(array $pl, int $tenantId, int $userId): bool
    {
        return match ((string) ($pl['visibility'] ?? '')) {
            'global', 'public' => true,
            'org' => (int) ($pl['tenant_id'] ?? 0) === $tenantId,
            default => $userId > 0 && (int) ($pl['owner_user_id'] ?? 0) === $userId,
        };
    }

    public function createPlaylist(int $tenantId, int $userId, string $name, string $description, string $visibility): int
    {
        return $this->db->insert(
            'INSERT INTO music_playlists (tenant_id, owner_user_id, name, description, visibility) VALUES (?, ?, ?, ?, ?)',
            [$tenantId, $userId, mb_substr($name, 0, 120), self::nullable($description, 500), in_array($visibility, self::VISIBILITIES, true) ? $visibility : 'private']
        );
    }

    public function updatePlaylist(int $id, string $name, string $description, ?string $visibility): void
    {
        if ($visibility === null) {
            $this->db->execute('UPDATE music_playlists SET name = ?, description = ?, updated_at = NOW() WHERE id = ?', [mb_substr($name, 0, 120), self::nullable($description, 500), $id]);

            return;
        }
        $this->db->execute(
            'UPDATE music_playlists SET name = ?, description = ?, visibility = ?, updated_at = NOW() WHERE id = ?',
            [mb_substr($name, 0, 120), self::nullable($description, 500), in_array($visibility, self::VISIBILITIES, true) ? $visibility : 'private', $id]
        );
    }

    public function deletePlaylist(int $id): void
    {
        $this->db->execute('DELETE FROM music_playlist_tracks WHERE playlist_id = ?', [$id]);
        $this->db->execute("DELETE FROM music_playlists WHERE id = ? AND visibility <> 'global'", [$id]);
    }

    /**
     * Morceaux d'une playlist, réduits à ceux que ce membre peut écouter.
     *
     * @return list<array<string, mixed>>
     */
    public function playlistTracks(int $playlistId, int $tenantId, int $limit = 300): array
    {
        $limit = max(1, min(500, $limit));

        return $this->db->fetchAll(
            'SELECT ' . self::TRACK_COLS . ', pt.position FROM music_playlist_tracks pt
             JOIN music_tracks t ON t.id = pt.track_id
             LEFT JOIN users u ON u.id = t.uploader_user_id
             LEFT JOIN tenants tn ON tn.id = t.tenant_id
             WHERE pt.playlist_id = ? AND ' . self::visibleSql() . ' ORDER BY pt.position ASC, pt.added_at ASC LIMIT ' . $limit,
            [$playlistId, $tenantId]
        );
    }

    public function addToPlaylist(int $playlistId, int $trackId, int $userId): bool
    {
        $exists = $this->db->fetchOne('SELECT 1 AS x FROM music_playlist_tracks WHERE playlist_id = ? AND track_id = ?', [$playlistId, $trackId]);
        if ($exists) {
            return false;
        }
        $row = $this->db->fetchOne('SELECT COALESCE(MAX(position), 0) AS m FROM music_playlist_tracks WHERE playlist_id = ?', [$playlistId]);
        $this->db->execute(
            'INSERT INTO music_playlist_tracks (playlist_id, track_id, position, added_by_user_id) VALUES (?, ?, ?, ?)',
            [$playlistId, $trackId, (int) ($row['m'] ?? 0) + 1, $userId]
        );
        $this->db->execute('UPDATE music_playlists SET updated_at = NOW() WHERE id = ?', [$playlistId]);

        return true;
    }

    public function removeFromPlaylist(int $playlistId, int $trackId): void
    {
        $this->db->execute('DELETE FROM music_playlist_tracks WHERE playlist_id = ? AND track_id = ?', [$playlistId, $trackId]);
        $this->db->execute('UPDATE music_playlists SET updated_at = NOW() WHERE id = ?', [$playlistId]);
    }

    /** Monte (-1) ou descend (+1) un morceau d'un cran ; renumérote la playlist. */
    public function moveInPlaylist(int $playlistId, int $trackId, int $dir): void
    {
        $rows = $this->db->fetchAll('SELECT track_id FROM music_playlist_tracks WHERE playlist_id = ? ORDER BY position ASC, added_at ASC', [$playlistId]);
        $ids = array_map(static fn (array $r): int => (int) $r['track_id'], $rows);
        $i = array_search($trackId, $ids, true);
        if ($i === false) {
            return;
        }
        $j = $i + ($dir < 0 ? -1 : 1);
        if ($j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $pos => $tid) {
            $this->db->execute('UPDATE music_playlist_tracks SET position = ? WHERE playlist_id = ? AND track_id = ?', [$pos + 1, $playlistId, $tid]);
        }
    }

    /** @return list<int> playlists (que je peux modifier) contenant déjà ce morceau */
    public function playlistIdsContaining(int $trackId): array
    {
        return array_map(static fn (array $r): int => (int) $r['playlist_id'], $this->db->fetchAll('SELECT playlist_id FROM music_playlist_tracks WHERE track_id = ?', [$trackId]));
    }

    private static function nullable(mixed $v, int $max): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : mb_substr($s, 0, $max);
    }
}
