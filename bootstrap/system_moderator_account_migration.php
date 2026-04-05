<?php

declare(strict_types=1);

/**
 * Colonne users.is_service_account (comptes techniques non connectables).
 */
return function (PDO $pdo): void {
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_service_account' LIMIT 1");
    if ($stmt && !$stmt->fetch()) {
        echo "Utilisateurs système : ajout users.is_service_account...\n";
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_service_account TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Compte non connectable (bot / automatique)' AFTER status");
        } catch (PDOException $e) {
            echo '  [ATTENTION] ' . $e->getMessage() . "\n";
        }
    }
};
