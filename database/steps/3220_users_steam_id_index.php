<?php

declare(strict_types=1);

/**
 * Étape « Index sur users.steam_id (détection des Steam en double à chaque page) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * Aucun index ajouté si un index existant commence déjà par steam_id, quel que soit son nom.
 */

$steamIndexSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$steamIndexSchema->columnExists('users', 'steam_id')) {
    echo "  [OK] Colonne users.steam_id absente : rien à indexer.\n";
} elseif ($steamIndexSchema->columnIsIndexed('users', 'steam_id')) {
    echo "  [OK] users.steam_id déjà indexée.\n";
} else {
    $steamIndexSchema->addIndexIfMissing('users', 'idx_users_steam_id', ['steam_id']);
    echo "  [OK] Index idx_users_steam_id posé.\n";
}
