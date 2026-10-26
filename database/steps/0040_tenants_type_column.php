<?php

declare(strict_types=1);

/**
 * Étape « Profil de communauté (colonne tenants.tenant_type) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Profil communauté (Complet / Effectifs / ATAK) — critique pour CREATE communauté.
// CREATE TABLE IF NOT EXISTS ne met jamais à jour une table déjà présente : combler tôt.
$ensureTenantType = schema_ensure_columns($pdo, 'tenants', [
    'tenant_type' => "`tenant_type` VARCHAR(32) NOT NULL DEFAULT 'full' COMMENT 'Profil communauté : full | effectifs | atak' AFTER `slug`",
]);
if ($ensureTenantType === 0) {
    echo "  [OK] tenants : tenant_type déjà présente\n";
} else {
    echo "  [OK] tenants : tenant_type comblée ({$ensureTenantType})\n";
}
