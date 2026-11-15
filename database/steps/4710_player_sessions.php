<?php

declare(strict_types=1);

/**
 * Étape « Sessions de jeu des joueurs » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table player_sessions n'est créée que
 * si elle manque (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_18_100_player_sessions.sql). Aucune donnée ajoutée
 * ni modifiée : sans elle, la disponibilité garde son calcul d'avant. Le remplissage du passé est un outil à part
 * (tools/rebuild-player-sessions.php, essai à blanc par défaut). Retour arrière : migrations/rollback/player_sessions_down.sql.
 */

$playerSessionsSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($playerSessionsSchema->tableExists('player_sessions')) {
    echo "  [OK] Table player_sessions déjà présente.\n";
} else {
    $playerSessionsSchema->runSqlFile($root . '/migrations/2026_11_18_100_player_sessions.sql');
    if (!$playerSessionsSchema->tableExists('player_sessions')) {
        throw new RuntimeException('Création de la table player_sessions impossible.');
    }
    echo "  [OK] Table player_sessions créée.\n";
}
