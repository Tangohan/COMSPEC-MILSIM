<?php

declare(strict_types=1);

/**
 * Étape « Documents Athena : colonnes et tables manquantes (migration incrémentale) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Documents Athena : colonnes et tables manquantes (migration incrémentale)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_categories' AND COLUMN_NAME = 'color'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout document_categories.color...\n";
    $pdo->exec("ALTER TABLE document_categories ADD COLUMN color varchar(50) DEFAULT NULL AFTER slug");
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'description'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout documents.description...\n";
    $pdo->exec("ALTER TABLE documents ADD COLUMN description text DEFAULT NULL AFTER slug");
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_versions' AND COLUMN_NAME = 'version_number'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout document_versions.version_number...\n";
    $pdo->exec("ALTER TABLE document_versions ADD COLUMN version_number int unsigned NOT NULL DEFAULT 1 AFTER document_id");
}
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment_classes'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table equipment_classes...\n";
    $pdo->exec("CREATE TABLE equipment_classes (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        name varchar(255) NOT NULL,
        slug varchar(100) NOT NULL,
        category varchar(100) DEFAULT NULL,
        description text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tenant_id_slug (tenant_id, slug),
        KEY tenant_id (tenant_id),
        CONSTRAINT equipment_classes_tenant_id_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_links'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table document_links...\n";
    $pdo->exec("CREATE TABLE document_links (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        document_id int unsigned NOT NULL,
        entity_type enum('training','equipment_class','unit','user') NOT NULL,
        entity_id int unsigned NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY document_id (document_id),
        KEY entity_type (entity_type),
        KEY entity_id (entity_id),
        KEY tenant_id (tenant_id),
        CONSTRAINT document_links_tenant_id_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT document_links_document_id_fk FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
