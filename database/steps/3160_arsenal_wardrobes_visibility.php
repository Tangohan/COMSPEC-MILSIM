<?php

declare(strict_types=1);

/**
 * Étape « Équipement : visibilité d’une tenue (arsenal_wardrobes.visibility) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * Même colonne que bootstrap/arsenal_wardrobe_migration.php : toute la communauté par défaut (comportement historique).
 */

$wardrobeSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$wardrobeSchema->tableExists('arsenal_wardrobes')) {
    echo "  [OK] Table arsenal_wardrobes absente : la colonne sera posée avec la table par le module Équipement.\n";
} elseif ($wardrobeSchema->addColumnIfMissing(
    'arsenal_wardrobes',
    'visibility',
    "ENUM('tenant','personal') NOT NULL DEFAULT 'tenant'" . ($wardrobeSchema->columnExists('arsenal_wardrobes', 'collection_id') ? ' AFTER collection_id' : '')
)) {
    echo "  [OK] Colonne arsenal_wardrobes.visibility ajoutée (les tenues restent visibles par la communauté).\n";
} else {
    echo "  [OK] Colonne arsenal_wardrobes.visibility déjà présente.\n";
}
