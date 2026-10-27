<?php

declare(strict_types=1);

/**
 * Étape « Migration public_roster_opt_in (user_profile_display_settings) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Fiche publique vitrine : roster opt-in + champs unité
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings' AND COLUMN_NAME = 'public_roster_opt_in'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration public_roster_opt_in (user_profile_display_settings)...\n";
    $pdo->exec("ALTER TABLE user_profile_display_settings ADD COLUMN public_roster_opt_in tinyint(1) NOT NULL DEFAULT 0 AFTER fiche_show_matricule_to_others");
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings' AND COLUMN_NAME = 'hide_personal_info'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration hide_personal_info (user_profile_display_settings)...\n";
    try {
        $pdo->exec("ALTER TABLE user_profile_display_settings ADD COLUMN hide_personal_info tinyint(1) NOT NULL DEFAULT 0 AFTER public_roster_opt_in");
    } catch (Throwable $e) {
        echo '  [ATTENTION] hide_personal_info : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('hide_personal_info', $e);
    }
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings' AND COLUMN_NAME = 'site_photo_priority'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration site_photo_priority (user_profile_display_settings)...\n";
    try {
        $pdo->exec("ALTER TABLE user_profile_display_settings ADD COLUMN site_photo_priority varchar(16) NOT NULL DEFAULT 'operator' COMMENT 'operator|account — photo prioritaire header / portail' AFTER hide_personal_info");
    } catch (Throwable $e) {
        echo '  [ATTENTION] site_photo_priority : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('site_photo_priority', $e);
    }
}

$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'units' AND COLUMN_NAME = 'public_blurb'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration units public_blurb / public_tags / show_on_public_page...\n";
    $pdo->exec("ALTER TABLE units ADD COLUMN public_blurb text DEFAULT NULL AFTER display_order");
    $pdo->exec("ALTER TABLE units ADD COLUMN public_tags json DEFAULT NULL AFTER public_blurb");
    $pdo->exec("ALTER TABLE units ADD COLUMN show_on_public_page tinyint(1) NOT NULL DEFAULT 1 AFTER public_tags");
}
