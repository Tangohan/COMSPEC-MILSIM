<?php

declare(strict_types=1);

/**
 * Bibliothèque musicale (site Athena + app Musique de l'ATAK natif, COMSPEC Link 2.0.72).
 *
 * - music_tracks : morceaux mis en ligne par les membres. scope = community (toute la plateforme)
 *   ou org (seulement l'organisation de l'envoyeur) ; status = active | hidden (masqué par la modération).
 * - music_playlists : playlists. visibility = private | org | public | global
 *   (global : une seule, tenue par l'administrateur de la plateforme, tenant_id NULL).
 * - music_playlist_tracks : contenu ordonné d'une playlist.
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('music_tracks')) {
        $pdo->exec(
            "CREATE TABLE music_tracks (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                uploader_user_id INT UNSIGNED NOT NULL,
                scope VARCHAR(16) NOT NULL DEFAULT 'org',
                status VARCHAR(16) NOT NULL DEFAULT 'active',
                title VARCHAR(160) NOT NULL,
                artist VARCHAR(120) DEFAULT NULL,
                genre VARCHAR(60) DEFAULT NULL,
                duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                file_path VARCHAR(255) NOT NULL,
                file_ext VARCHAR(8) NOT NULL,
                mime VARCHAR(64) NOT NULL,
                size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
                sha1 CHAR(40) NOT NULL,
                play_count INT UNSIGNED NOT NULL DEFAULT 0,
                moderated_by_user_id INT UNSIGNED DEFAULT NULL,
                moderation_note VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_music_tracks_tenant (tenant_id, status, scope),
                KEY idx_music_tracks_scope (scope, status, created_at),
                KEY idx_music_tracks_uploader (uploader_user_id),
                KEY idx_music_tracks_sha1 (sha1),
                CONSTRAINT fk_music_tracks_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists('music_playlists')) {
        $pdo->exec(
            "CREATE TABLE music_playlists (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED DEFAULT NULL,
                owner_user_id INT UNSIGNED DEFAULT NULL,
                name VARCHAR(120) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                visibility VARCHAR(16) NOT NULL DEFAULT 'private',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_music_playlists_tenant (tenant_id, visibility),
                KEY idx_music_playlists_owner (owner_user_id),
                KEY idx_music_playlists_visibility (visibility)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists('music_playlist_tracks')) {
        $pdo->exec(
            "CREATE TABLE music_playlist_tracks (
                playlist_id INT UNSIGNED NOT NULL,
                track_id INT UNSIGNED NOT NULL,
                position INT UNSIGNED NOT NULL DEFAULT 0,
                added_by_user_id INT UNSIGNED DEFAULT NULL,
                added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (playlist_id, track_id),
                KEY idx_music_pl_tracks_order (playlist_id, position),
                KEY idx_music_pl_tracks_track (track_id),
                CONSTRAINT fk_music_pl_tracks_playlist FOREIGN KEY (playlist_id) REFERENCES music_playlists (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_music_pl_tracks_track FOREIGN KEY (track_id) REFERENCES music_tracks (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
};
