<?php

declare(strict_types=1);

/**
 * Étape « Dotation : lien entre un article du référentiel et un objet vu en jeu » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien. Colonne facultative (NULL = article
 * sans équivalent en jeu) : aucune donnée existante n’est modifiée.
 */

$dotationSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$dotationSchema->tableExists('equipment_item_definitions')) {
    echo "  [OK] Table equipment_item_definitions absente : rien à faire.\n";
} else {
    $added = $dotationSchema->addColumnIfMissing(
        'equipment_item_definitions',
        'game_class_name',
        'VARCHAR(128) DEFAULT NULL'
    );
    echo $added ? "  [OK] Colonne game_class_name ajoutée.\n" : "  [OK] Colonne game_class_name déjà présente.\n";
    $indexed = $dotationSchema->addIndexIfMissing(
        'equipment_item_definitions',
        'idx_equip_def_game_class',
        ['tenant_id', 'game_class_name']
    );
    echo $indexed ? "  [OK] Index idx_equip_def_game_class posé.\n" : "  [OK] Index idx_equip_def_game_class déjà posé.\n";
}
