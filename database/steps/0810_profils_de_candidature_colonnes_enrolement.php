<?php

declare(strict_types=1);

/**
 * Étape « Profils de candidature + colonnes enrôlement (compte Athena, consentement) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Profils de candidature + colonnes enrôlement (compte Athena, consentement)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_presets'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table recruitment_presets...\n";
    $pdo->exec("CREATE TABLE `recruitment_presets` (
      `id` int unsigned NOT NULL AUTO_INCREMENT,
      `user_id` int unsigned NOT NULL,
      `label` varchar(120) NOT NULL,
      `payload` json NOT NULL,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `recruitment_presets_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "recruitment_presets OK.\n";
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'submitted_via'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonnes compte / consentement à enlistments...\n";
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN submitter_user_id int unsigned DEFAULT NULL AFTER reviewer_comment");
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN recruitment_preset_id int unsigned DEFAULT NULL AFTER submitter_user_id");
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN submitted_via varchar(20) NOT NULL DEFAULT 'guest' AFTER recruitment_preset_id");
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN consent_sharing_at datetime DEFAULT NULL AFTER submitted_via");
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN shared_fields json DEFAULT NULL AFTER consent_sharing_at");
    $pdo->exec("ALTER TABLE enlistments ADD KEY submitter_user_id (submitter_user_id)");
    $pdo->exec("ALTER TABLE enlistments ADD CONSTRAINT enlistments_submitter_user_fk FOREIGN KEY (submitter_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE");
    $pdo->exec("ALTER TABLE enlistments ADD CONSTRAINT enlistments_recruitment_preset_fk FOREIGN KEY (recruitment_preset_id) REFERENCES recruitment_presets (id) ON DELETE SET NULL ON UPDATE CASCADE");
    echo "Colonnes enrôlement compte OK.\n";
}

$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'recruitment_rp_json'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne enlistments.recruitment_rp_json...\n";
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN recruitment_rp_json JSON DEFAULT NULL AFTER shared_fields");
    echo "recruitment_rp_json OK.\n";
}
