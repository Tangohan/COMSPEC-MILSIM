<?php

declare(strict_types=1);

/**
 * Étape « Configurateur : favoris d'objets et pré-kits » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente, rejouable sans effet :
 * - table game_item_favorites : objets (armes, accessoires, objets) mis en favori par un membre dans le configurateur,
 *   une ligne par communauté, membre et classe Arma ; même principe que arsenal_wardrobe_favorites pour les tenues ;
 * - table game_item_prekits : pré-kits réutilisables (ex. « Équipement obligatoire » : garrot, pansements, radio,
 *   carte) insérés en un clic dans l'uniforme, le gilet ou le sac. Personnels, ou partagés à la communauté par un
 *   responsable des kits. Contenu : liste JSON [[classe, nombre]…] contrôlée par PrekitDocument.
 * Les tendances (objets et tenues les plus utilisés) sont calculées à la volée à partir des tables existantes
 * (game_item_usage, arsenal_wardrobes, favoris) : aucune table de plus. Rien d'existant n'est modifié ni supprimé.
 */

$schema4300 = new \App\Support\Migrations\SchemaInspector($pdo);

if ($schema4300->tableExists('game_item_favorites')) {
    echo "  [OK] Table game_item_favorites déjà présente.\n";
} else {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS game_item_favorites (
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            class_name VARCHAR(128) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (tenant_id, user_id, class_name),
            KEY idx_game_item_favorites_recent (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "  [OK] Table game_item_favorites créée.\n";
}

if ($schema4300->tableExists('game_item_prekits')) {
    echo "  [OK] Table game_item_prekits déjà présente.\n";
} else {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS game_item_prekits (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            name VARCHAR(90) NOT NULL,
            description VARCHAR(300) NOT NULL DEFAULT \'\',
            items_json TEXT NOT NULL,
            shared TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_game_item_prekits_owner (tenant_id, user_id),
            KEY idx_game_item_prekits_shared (tenant_id, shared)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "  [OK] Table game_item_prekits créée.\n";
}
