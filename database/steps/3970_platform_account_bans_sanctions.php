<?php

declare(strict_types=1);

/**
 * Étape « Sanctions du site : bannissements avec date de fin, note de levée, historique par personne » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : table, colonnes
 * `ends_at` et `lift_note` et index `idx_pab_due` ne sont ajoutés que s’ils manquent.
 * Les bannissements existants gardent ends_at = NULL : ils restent sans fin, rien ne change pour eux.
 */

require_once $root . '/bootstrap/platform_account_bans_migration.php';
run_platform_account_bans_migration($pdo);
$migrationEnsurePdo();
