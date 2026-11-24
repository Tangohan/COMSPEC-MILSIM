<?php

declare(strict_types=1);

/**
 * Étape « Comptes : dernière activité réelle » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la colonne users.last_active_at
 * (DATETIME NULL, UTC) n'est ajoutée que si elle manque. Aucune donnée modifiée, aucun rattrapage : la colonne se
 * remplit à la connexion puis au plus une fois par heure et par session (AuthMiddleware). Sans cette étape, les
 * graphiques d'activité de l'accueil d'administration retombent sur la dernière connexion.
 * Retour arrière : migrations/rollback/user_last_active_down.sql.
 */

$userActiveSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$userActiveSchema->tableExists('users')) {
    echo "  [OK] users absente : rien à faire.\n";
} elseif ($userActiveSchema->columnExists('users', 'last_active_at')) {
    echo "  [OK] users.last_active_at déjà présente.\n";
} else {
    $userActiveSchema->addColumnIfMissing('users', 'last_active_at', 'DATETIME NULL DEFAULT NULL');
    if (!$userActiveSchema->columnExists('users', 'last_active_at')) {
        throw new RuntimeException('Ajout de users.last_active_at impossible.');
    }
    echo "  [OK] Colonne users.last_active_at ajoutée.\n";
}
