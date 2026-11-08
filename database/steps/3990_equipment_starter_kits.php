<?php

declare(strict_types=1);

/**
 * Étape « Kits de départ et favoris de la page Équipement » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente :
 * - game_role_kits.unit_id : unité à laquelle un kit est proposé (NULL = proposé par rôle ou à tous, comme avant) ;
 * - game_role_kits.source : origine du kit (scratch = créé de zéro, game_loadout = tenue remontée du jeu,
 *   assistant = assistant de création) ; « scratch » par défaut, donc les kits existants ne changent pas ;
 * - table arsenal_wardrobe_favorites : tenues mises en favori par chaque membre (une ligne par membre et par tenue).
 * Rien d'existant n'est modifié ni supprimé. Sans la table game_role_kits, ses colonnes sont laissées de côté.
 */

$schema3990 = new \App\Support\Migrations\SchemaInspector($pdo);

if (!$schema3990->tableExists('game_role_kits')) {
    echo "  [OK] Table game_role_kits absente : kits de départ en attente de la table des kits par rôle.\n";
} else {
    $added = false;
    $added = $schema3990->addColumnIfMissing('game_role_kits', 'unit_id', 'INT UNSIGNED DEFAULT NULL') || $added;
    $added = $schema3990->addColumnIfMissing('game_role_kits', 'source', "VARCHAR(24) NOT NULL DEFAULT 'scratch'") || $added;
    $added = $schema3990->addIndexIfMissing('game_role_kits', 'idx_game_role_kits_unit', ['tenant_id', 'unit_id']) || $added;
    echo $added
        ? "  [OK] Kits : colonnes unit_id et source ajoutées.\n"
        : "  [OK] Kits : colonnes unit_id et source déjà présentes.\n";
}

if ($schema3990->tableExists('arsenal_wardrobe_favorites')) {
    echo "  [OK] Table arsenal_wardrobe_favorites déjà présente.\n";
} else {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS arsenal_wardrobe_favorites (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            wardrobe_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_arsenal_wardrobe_fav (tenant_id, user_id, wardrobe_id),
            KEY idx_arsenal_wardrobe_fav_wardrobe (wardrobe_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "  [OK] Table arsenal_wardrobe_favorites créée.\n";
}
