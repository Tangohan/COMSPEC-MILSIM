<?php

declare(strict_types=1);

/**
 * Étape « Table atak_intel (logs PING / CHAT / PHOTO depuis le mod Arma) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Table atak_intel (logs PING / CHAT / PHOTO depuis le mod Arma)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_intel'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table atak_intel...\n";
    $pdo->exec("CREATE TABLE atak_intel (
        id int unsigned NOT NULL AUTO_INCREMENT,
        type varchar(20) NOT NULL,
        author varchar(255) NOT NULL,
        pos_x decimal(15,8) DEFAULT NULL,
        pos_y decimal(15,8) DEFAULT NULL,
        content text DEFAULT NULL,
        metadata json DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY type_created (type, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "atak_intel OK.\n";
}
