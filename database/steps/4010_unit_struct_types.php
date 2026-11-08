<?php

declare(strict_types=1);

/**
 * Étape « Types d'entité personnalisés par communauté » (Schéma).
 *
 * Idempotente : crée la table tenant_unit_struct_types (une ligne par type ajouté par une communauté).
 * Rien d'existant n'est modifié : les types de base (organisation, groupe, équipe...) restent dans la configuration.
 */

$schema4000 = new \App\Support\Migrations\SchemaInspector($pdo);

if ($schema4000->tableExists('tenant_unit_struct_types')) {
    echo "  [OK] Table tenant_unit_struct_types déjà présente.\n";
} else {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tenant_unit_struct_types (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            slug VARCHAR(48) NOT NULL,
            label VARCHAR(120) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_tenant_unit_struct_slug (tenant_id, slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "  [OK] Table tenant_unit_struct_types créée.\n";
}
