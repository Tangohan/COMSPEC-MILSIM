<?php

declare(strict_types=1);

/**
 * Étape « Catalogue de base d'Arma 3 » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente, rejouable sans effet :
 * - game_item_specs.source : origine de la ligne (« import » = export fait en jeu par un responsable, « vanilla » =
 *   catalogue de base livré avec le site, database/data/arma3_catalogue_vanilla.json). Une ligne « vanilla » ne
 *   remplace jamais une ligne importée ; un import la remplace ;
 * - game_item_specs.name_en : nom anglais (le nom français reste dans name) ;
 * - table game_item_vanilla_loads : état du chargement du catalogue de base par communauté (version du fichier,
 *   avancement par lots), pour le charger tout seul au premier accès à la page Équipement.
 * Les lignes existantes restent « import » : rien n'est modifié ni supprimé.
 */

$schema4290 = new \App\Support\Migrations\SchemaInspector($pdo);

if (!$schema4290->tableExists('game_item_specs')) {
    echo "  [OK] Table game_item_specs absente : appliquez d’abord l’étape « Configurateur d’équipement ».\n";
} else {
    $added = false;
    $added = $schema4290->addColumnIfMissing('game_item_specs', 'source', "VARCHAR(16) NOT NULL DEFAULT 'import'") || $added;
    $added = $schema4290->addColumnIfMissing('game_item_specs', 'name_en', "VARCHAR(160) NOT NULL DEFAULT ''") || $added;
    $added = $schema4290->addIndexIfMissing('game_item_specs', 'idx_game_item_specs_source', ['tenant_id', 'source']) || $added;
    echo $added
        ? "  [OK] game_item_specs : colonnes source et name_en ajoutées.\n"
        : "  [OK] game_item_specs : colonnes source et name_en déjà présentes.\n";
}

if ($schema4290->tableExists('game_item_vanilla_loads')) {
    echo "  [OK] Table game_item_vanilla_loads déjà présente.\n";
} else {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS game_item_vanilla_loads (
            tenant_id INT UNSIGNED NOT NULL,
            version VARCHAR(40) NOT NULL DEFAULT \'\',
            total INT UNSIGNED NOT NULL DEFAULT 0,
            done INT UNSIGNED NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL,
            finished_at DATETIME DEFAULT NULL,
            PRIMARY KEY (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "  [OK] Table game_item_vanilla_loads créée.\n";
}
