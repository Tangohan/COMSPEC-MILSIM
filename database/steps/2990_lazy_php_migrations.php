<?php

declare(strict_types=1);

/**
 * Étape « Migrations PHP auparavant lancées à la demande par l’application » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Migrations PHP jusqu’ici lancées seulement « à la demande » par l’application (première requête
// sur l’écran concerné) : on les applique ici pour que les tables existent avant les fichiers SQL
// qui en dépendent (ex. athena_accounts pour 20260904120000_atak_secure_device_auth.sql).
foreach ([
    'athena_game_auth_migration.php' => 'identité Athena globale, sessions jeu',
    'equipment_catalog_extras_migration.php' => 'catalogue d’équipement',
    'atak_recon_images_actions_migration.php' => 'actions sur les images de reconnaissance',
    'atak_recon_images_photo_meta_migration.php' => 'fiche des clichés de reconnaissance (titre, SSE, confidentiel)',
    'atak_recon_images_confidential_storage_migration.php' => 'clichés confidentiels hors racine web (storage/private/recon)',
    'account_session_epoch_migration.php' => 'Mon compte : déconnexion des autres sessions',
    'atak_mission_key_migration.php' => 'clé de mission ATAK natif (relecture, temps d’écran, comptes rendus) et pertes amies',
    'sse_campaigns_migration.php' => 'campagnes SSE (gestion Athena et partage entre communautés)',
] as $lazyFile => $lazyLabel) {
    $migrationRunner->step($lazyFile, static function () use ($root, $lazyFile, $lazyLabel, $pdo): void {
        echo "Migration {$lazyFile} ({$lazyLabel})...\n";
        $migrate = require $root . '/bootstrap/' . $lazyFile;
        $migrate($pdo);
    });
}
