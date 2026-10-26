<?php

declare(strict_types=1);

/**
 * Étape « Migration music_tracks / music_playlists / music_playlist_tracks (bibliothèque musicale) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Files d'envoi COMSPEC Link : dernier bilan par joueur ou serveur et historique 48 h (AtakSendQueueRepository).
// Bibliothèque musicale : morceaux, playlists, playlist globale (site + app Musique de l'ATAK natif, COMSPEC Link 2.0.72).
$musicLibraryMigrate = require $root . '/bootstrap/music_library_migration.php';
$migrationRunner->step('music_library', static function () use ($musicLibraryMigrate, $pdo): void {
    echo "Migration music_tracks / music_playlists / music_playlist_tracks (bibliothèque musicale)...\n";
    $musicLibraryMigrate($pdo);
});
