<?php

declare(strict_types=1);

/**
 * Étape « Page publique du joueur (user_public_cv) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * Source unique : UserPublicCvRepository::MIGRATION_FILE (CREATE TABLE IF NOT EXISTS), aussi utilisée à la demande.
 */

$publicCvSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($publicCvSchema->tableExists('user_public_cv')) {
    echo "  [OK] Table user_public_cv déjà présente.\n";
} else {
    $publicCvSchema->runSqlFile($root . '/' . \App\Repositories\UserPublicCvRepository::MIGRATION_FILE);
    echo "  [OK] Table user_public_cv créée (pages publiques désactivées par défaut).\n";
}
