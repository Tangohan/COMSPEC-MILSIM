<?php

declare(strict_types=1);

/**
 * Étape « Table categories (rôles, utilisateurs, organisation, métier) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Table categories (rôles, utilisateurs, organisation, métier)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table categories...\n";
    $pdo->exec("CREATE TABLE categories (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        type varchar(50) NOT NULL DEFAULT 'organizational',
        name varchar(255) NOT NULL,
        slug varchar(100) NOT NULL,
        description varchar(500) DEFAULT NULL,
        color varchar(50) DEFAULT NULL,
        display_order int DEFAULT 0,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tenant_id_slug (tenant_id, slug),
        KEY tenant_id (tenant_id),
        CONSTRAINT categories_tenant_id_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
