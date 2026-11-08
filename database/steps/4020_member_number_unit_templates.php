<?php

declare(strict_types=1);

/**
 * Étape « Matricules : modèle choisi par unité » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : crée la table
 * tenant_member_number_unit_templates (une ligne par communauté et par unité ayant son propre modèle de
 * matricule). Une unité sans ligne suit le modèle général de la communauté, comme avant : rien d'existant
 * n'est modifié ni supprimé.
 */

$schema4020 = new \App\Support\Migrations\SchemaInspector($pdo);

if ($schema4020->tableExists('tenant_member_number_unit_templates')) {
    echo "  [OK] Table tenant_member_number_unit_templates déjà présente.\n";
} else {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tenant_member_number_unit_templates (
            tenant_id INT UNSIGNED NOT NULL,
            unit_id INT UNSIGNED NOT NULL,
            pattern VARCHAR(120) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (tenant_id, unit_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "  [OK] Table tenant_member_number_unit_templates créée.\n";
}
