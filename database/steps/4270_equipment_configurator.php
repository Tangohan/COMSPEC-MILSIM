<?php

declare(strict_types=1);

/**
 * Étape « Configurateur d'équipement » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente, rejouable sans effet :
 * - table game_item_specs : configuration des objets Arma importée depuis le jeu (export hors ligne
 *   comspec_sse_fnc_loadoutCatalog ou tools/arma/export_catalogue_equipement.sqf) : masse, charge maximale des
 *   conteneurs, emplacements autorisés, accessoires et chargeurs compatibles de chaque arme. Une ligne par
 *   communauté et par classe ; complète game_item_catalog (objets vus en jeu) sans le remplacer ;
 * - table game_role_kit_versions : historique des versions de chaque kit de départ (restauration) ;
 * - game_role_kits.member_user_id et qualification_id : kit attribué à un membre précis ou aux titulaires d'une
 *   qualification, en plus des destinataires existants (tous, une unité, un rôle).
 * Rien d'existant n'est modifié ni supprimé.
 */

$schema4270 = new \App\Support\Migrations\SchemaInspector($pdo);

if ($schema4270->tableExists('game_item_specs')) {
    echo "  [OK] Table game_item_specs déjà présente.\n";
} else {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS game_item_specs (
            tenant_id INT UNSIGNED NOT NULL,
            class_name VARCHAR(128) NOT NULL,
            root CHAR(1) NOT NULL DEFAULT 'w',
            type_main VARCHAR(24) NOT NULL DEFAULT '',
            type_sub VARCHAR(32) NOT NULL DEFAULT '',
            name VARCHAR(160) NOT NULL DEFAULT '',
            mass DECIMAL(10,3) DEFAULT NULL,
            max_load INT UNSIGNED DEFAULT NULL,
            slot_type INT DEFAULT NULL,
            ammo_count INT UNSIGNED DEFAULT NULL,
            allowed_slots VARCHAR(64) NOT NULL DEFAULT '',
            compat_json MEDIUMTEXT DEFAULT NULL,
            mags_json MEDIUMTEXT DEFAULT NULL,
            mod_name VARCHAR(160) NOT NULL DEFAULT '',
            imported_by INT UNSIGNED DEFAULT NULL,
            imported_at DATETIME NOT NULL,
            PRIMARY KEY (tenant_id, class_name),
            KEY idx_game_item_specs_type (tenant_id, type_main, type_sub)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "  [OK] Table game_item_specs créée.\n";
}

if ($schema4270->tableExists('game_role_kit_versions')) {
    echo "  [OK] Table game_role_kit_versions déjà présente.\n";
} else {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS game_role_kit_versions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            kit_id BIGINT UNSIGNED NOT NULL,
            version_no INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            payload_text MEDIUMTEXT NOT NULL,
            note VARCHAR(190) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_game_role_kit_versions (tenant_id, kit_id, version_no),
            KEY idx_game_role_kit_versions_kit (kit_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "  [OK] Table game_role_kit_versions créée.\n";
}

if (!$schema4270->tableExists('game_role_kits')) {
    echo "  [OK] Table game_role_kits absente : attribution à un membre ou à une qualification en attente.\n";
} else {
    $added = false;
    $added = $schema4270->addColumnIfMissing('game_role_kits', 'member_user_id', 'INT UNSIGNED DEFAULT NULL') || $added;
    $added = $schema4270->addColumnIfMissing('game_role_kits', 'qualification_id', 'INT UNSIGNED DEFAULT NULL') || $added;
    $added = $schema4270->addIndexIfMissing('game_role_kits', 'idx_game_role_kits_member', ['tenant_id', 'member_user_id']) || $added;
    $added = $schema4270->addIndexIfMissing('game_role_kits', 'idx_game_role_kits_qualif', ['tenant_id', 'qualification_id']) || $added;
    echo $added
        ? "  [OK] Kits : colonnes member_user_id et qualification_id ajoutées.\n"
        : "  [OK] Kits : colonnes member_user_id et qualification_id déjà présentes.\n";
}
