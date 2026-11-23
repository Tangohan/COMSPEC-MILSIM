<?php

declare(strict_types=1);

/**
 * Étape « Sessions de jeu : code de session, notifications en jeu et app Session » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : les tables ne sont créées que si elles
 * manquent (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_23_100_roleplay_session_app.sql). Aucune colonne ajoutée,
 * aucune donnée modifiée, aucun droit créé (mêmes droits que la page des sessions). Sans ces tables, la page de la session
 * reste utilisable sans code ni notification. Compatible SQLite et MySQL (unicités dans les CREATE TABLE).
 * Retour arrière : migrations/rollback/roleplay_session_app_down.sql.
 */

$rpSessionAppSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$rpSessionAppTables = ['roleplay_game_session_codes', 'roleplay_game_session_app', 'roleplay_game_session_journal'];
$rpSessionAppMissing = array_values(array_filter($rpSessionAppTables, static fn (string $t): bool => !$rpSessionAppSchema->tableExists($t)));
if ($rpSessionAppMissing === []) {
    echo "  [OK] Tables de l'app Session déjà présentes.\n";
} else {
    $rpSessionAppSchema->runSqlFile($root . '/migrations/2026_11_23_100_roleplay_session_app.sql');
    foreach ($rpSessionAppTables as $rpSessionAppTable) {
        if (!$rpSessionAppSchema->tableExists($rpSessionAppTable)) {
            throw new RuntimeException('Création de la table ' . $rpSessionAppTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $rpSessionAppMissing) . ".\n";
}
