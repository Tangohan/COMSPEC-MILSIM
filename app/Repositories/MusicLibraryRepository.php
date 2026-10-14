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
        t.uploader_alias,
        COALESCE(NULLIF(t.uploader_alias, \'\'), NULLIF(u.callsign, \'\'), NULLIF(u.display_name, \'\'), \'Membre\') AS uploader_name, tn.name AS tenant_name';

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
            'INSERT INTO music_tracks (tenant_id, uploader_user_id, uploader_alias, scope, status, title, artist, genre, duration_seconds, file_path, file_ext, mime, size_bytes, sha1)
             VALUES (?, ?, ?, ?, \'active\', ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['tenant_id'], (int) $data['uploader_user_id'], self::nullable($data['uploader_alias'] ?? null, 80),
                in_array($data['scope'] ?? '', self::SCOPES, true) ? $data['scope'] : 'org',
                mb_substr((string) $data['title'], 0, 160), self::nullable($data['artist'] ?? null, 120), self::nullable($data['genre'] ?? null, 60),
                max(0, (int) ($data['duration_seconds'] ?? 0)),
                (string) $data['file_path'], (string) $data['file_ext'], (string) $data['mime'], (int) $data['size_bytes'], (string) $data['sha1'],
            ]
        );
    }

    /**
     * Morceaux déjà en ligne dont le titre ressemble à celui proposé (doublons probables).
     * Plateforme : tous les morceaux ; organisation : ceux qu'elle voit (les siens et la bibliothèque communautaire).
     *
     * @return list<array{id: int, title: string, artist: ?string, uploader_name: string, tenant_name: ?string, score: float}>
     */
    public function findSimilarTitles(string $title, ?int $visibleToTenantId, int $limit = 5): array
    {
        if (\App\Support\MusicTitleSimilarity::normalize($title) === '') {
            return [];
        }
        $where = 't.status = \'active\'';
        $params = [];
        if ($visibleToTenantId !== null) {
            $where .= ' AND (t.scope = \'community\' OR t.tenant_id = ?)';
            $params[] = $visibleToTenantId;
        }
        $rows = $this->db->fetchAll(
            'SELECT t.id, t.title, t.artist, tn.name AS tenant_name,
                COALESCE(NULLIF(t.uploader_alias, \'\'), NULLIF(u.callsign, \'\'), NULLIF(u.display_name, \'\'), \'Membre\') AS uploader_name
             FROM music_tracks t LEFT JOIN users u ON u.id = t.uploader_user_id LEFT JOIN tenants tn ON tn.id = t.tenant_id
             WHERE ' . $where . ' ORDER BY t.created_at DESC LIMIT 5000',
            $params
        );
        $out = [];
        foreach ($rows as $r) {
            $score = \App\Support\MusicTitleSimilarity::score($title, (string) $r['title']);
            if ($score >= \App\Support\MusicTitleSimilarity::THRESHOLD) {
                $out[] = [
                    'id' => (int) $r['id'],
                    'title' => (string) $r['title'],
                    'artist' => $r['artist'] !== null ? (string) $r['artist'] : null,
                    'uploader_name' => (string) $r['uploader_name'],
                    'tenant_name' => $r['tenant_name'] !== null ? (string) $r['tenant_name'] : null,
                    'score' => $score,
                ];
            }
        }
        usort($out, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($out, 0, $limit);
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
        if (($data['scope'] ?? '') !== 'community') {
            $this->setTrackScope($id, 'org');
        }
        if (array_key_exists('uploader_alias', $data)) {
            $this->db->execute('UPDATE music_tracks SET uploader_alias = ? WHERE id = ?', [self::nullable($data['uploader_alias'], 80), $id]);
        }
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

    // ----- Administration (plateforme : $tenantId null ; gestion d'une organisation : son id) -----

    /**
     * @param array{q?: string, scope?: string, status?: string, tenant?: int} $f
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function adminTracks(?int $tenantId, array $f, int $limit = 50, int $offset = 0): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($tenantId !== null) {
            $where[] = 't.tenant_id = ?';
            $params[] = $tenantId;
        } elseif ((int) ($f['tenant'] ?? 0) > 0) {
            $where[] = 't.tenant_id = ?';
            $params[] = (int) $f['tenant'];
        }
        if (in_array($f['scope'] ?? '', self::SCOPES, true)) {
            $where[] = 't.scope = ?';
            $params[] = $f['scope'];
        }
        if (in_array($f['status'] ?? '', ['active', 'hidden'], true)) {
            $where[] = 't.status = ?';
            $params[] = $f['status'];
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(t.title LIKE ? OR t.artist LIKE ? OR t.genre LIKE ? OR u.callsign LIKE ? OR u.display_name LIKE ?)';
            $like = self::like($q);
            array_push($params, $like, $like, $like, $like, $like);
        }
        $from = ' FROM music_tracks t LEFT JOIN users u ON u.id = t.uploader_user_id LEFT JOIN tenants tn ON tn.id = t.tenant_id WHERE ' . implode(' AND ', $where);
        $total = (int) (($this->db->fetchOne('SELECT COUNT(*) AS n' . $from, $params))['n'] ?? 0);
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $rows = $this->db->fetchAll(
            'SELECT ' . self::TRACK_COLS . ', t.moderation_note, t.updated_at,
                (SELECT COUNT(*) FROM music_playlist_tracks pt WHERE pt.track_id = t.id) AS playlist_count' . $from
            . ' ORDER BY t.created_at DESC, t.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string, int> */
    public function adminStats(?int $tenantId): array
    {
        $w = $tenantId !== null ? ' WHERE tenant_id = ?' : '';
        $p = $tenantId !== null ? [$tenantId] : [];
        $t = $this->db->fetchOne(
            "SELECT COUNT(*) AS tracks, COALESCE(SUM(status = 'hidden'), 0) AS hidden, COALESCE(SUM(scope = 'community'), 0) AS community,
                    COALESCE(SUM(size_bytes), 0) AS bytes, COALESCE(SUM(play_count), 0) AS plays, COUNT(DISTINCT uploader_user_id) AS uploaders,
                    COALESCE(SUM(created_at >= NOW() - INTERVAL 7 DAY), 0) AS week
             FROM music_tracks" . $w,
            $p
        ) ?? [];
        $pl = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM music_playlists" . ($tenantId !== null ? ' WHERE tenant_id = ?' : " WHERE visibility <> 'global'"),
            $p
        ) ?? [];

        return array_map('intval', $t + ['playlists' => (int) ($pl['n'] ?? 0)]);
    }

    /** Par organisation (plateforme). @return list<array<string, mixed>> */
    public function adminTenantBreakdown(): array
    {
        return $this->db->fetchAll(
            "SELECT t.tenant_id, tn.name AS tenant_name, COUNT(*) AS tracks, COALESCE(SUM(t.status = 'hidden'), 0) AS hidden,
                    COALESCE(SUM(t.scope = 'community'), 0) AS community, COALESCE(SUM(t.size_bytes), 0) AS bytes, COALESCE(SUM(t.play_count), 0) AS plays
             FROM music_tracks t LEFT JOIN tenants tn ON tn.id = t.tenant_id GROUP BY t.tenant_id, tn.name ORDER BY bytes DESC LIMIT 100"
        );
    }

    /** @return list<array<string, mixed>> */
    public function adminTopUploaders(?int $tenantId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT t.uploader_user_id, COALESCE(NULLIF(u.callsign, ''), NULLIF(u.display_name, ''), 'Membre') AS name, tn.name AS tenant_name,
                    COUNT(*) AS tracks, COALESCE(SUM(t.size_bytes), 0) AS bytes, COALESCE(SUM(t.play_count), 0) AS plays
             FROM music_tracks t LEFT JOIN users u ON u.id = t.uploader_user_id LEFT JOIN tenants tn ON tn.id = t.tenant_id"
            . ($tenantId !== null ? ' WHERE t.tenant_id = ?' : '')
            . ' GROUP BY t.uploader_user_id, u.callsign, u.display_name, tn.name ORDER BY bytes DESC LIMIT ' . max(1, min(50, $limit)),
            $tenantId !== null ? [$tenantId] : []
        );
    }

    /** Toutes les playlists (plateforme) ou celles d'une organisation. @return list<array<string, mixed>> */
    public function adminPlaylists(?int $tenantId): array
    {
        $this->globalPlaylist();

        return $this->db->fetchAll(
            'SELECT ' . self::PL_COLS . ' FROM music_playlists p LEFT JOIN users u ON u.id = p.owner_user_id LEFT JOIN tenants tn ON tn.id = p.tenant_id'
            . ($tenantId !== null ? ' WHERE p.tenant_id = ?' : '')
            . " ORDER BY (p.visibility = 'global') DESC, p.updated_at DESC, p.id DESC LIMIT 300",
            $tenantId !== null ? [$tenantId] : []
        );
    }

    public function setTrackScope(int $id, string $scope): void
    {
        $this->db->execute('UPDATE music_tracks SET scope = ?, updated_at = NOW() WHERE id = ?', [in_array($scope, self::SCOPES, true) ? $scope : 'org', $id]);
        // Un morceau qui redevient « organisation » ne peut plus rester dans la playlist globale.
        if ($scope === 'org') {
            $this->db->execute(
                "DELETE pt FROM music_playlist_tracks pt JOIN music_playlists p ON p.id = pt.playlist_id WHERE pt.track_id = ? AND p.visibility = 'global'",
                [$id]
            );
        }
    }

    private static function nullable(mixed $v, int $max): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : mb_substr($s, 0, $max);
    }
}
