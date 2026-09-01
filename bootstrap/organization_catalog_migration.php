<?php

declare(strict_types=1);

/**
 * Catalogue d’organisation : modèles officiels / privés et historique d’application par tenant.
 */
return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS organization_catalog_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(120) NOT NULL,
  title VARCHAR(180) NOT NULL,
  summary TEXT NULL,
  kind VARCHAR(40) NOT NULL DEFAULT 'organization_kit',
  visibility VARCHAR(20) NOT NULL DEFAULT 'official',
  owner_tenant_id INT UNSIGNED NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  definition_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_org_catalog_code (code),
  KEY idx_org_catalog_owner (owner_tenant_id),
  KEY idx_org_catalog_vis (visibility, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS organization_catalog_installs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  source_version INT UNSIGNED NOT NULL,
  applied_at DATETIME NOT NULL,
  applied_by INT UNSIGNED NULL,
  report_json LONGTEXT NULL,
  KEY idx_org_catalog_install_tenant (tenant_id),
  KEY idx_org_catalog_install_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    try {
        $col = $pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'organization_catalog_items'
               AND COLUMN_NAME = 'archived_at'
             LIMIT 1"
        );
        if ($col === false || !$col->fetchColumn()) {
            $pdo->exec('ALTER TABLE organization_catalog_items ADD COLUMN archived_at DATETIME NULL DEFAULT NULL');
        }
    } catch (Throwable) {
    }

    if (!class_exists(\App\Services\OrganizationCatalog\OrganizationKitDefinitions::class)) {
        return;
    }

    $upsert = $pdo->prepare(
        'INSERT INTO organization_catalog_items
            (code, title, summary, kind, visibility, owner_tenant_id, version, definition_json, created_at, updated_at)
         VALUES (?, ?, ?, \'organization_kit\', \'official\', NULL, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            summary = VALUES(summary),
            version = VALUES(version),
            definition_json = VALUES(definition_json),
            updated_at = NOW()'
    );

    foreach (\App\Services\OrganizationCatalog\OrganizationKitDefinitions::officialKits() as $kit) {
        $code = (string) ($kit['code'] ?? '');
        if ($code === '') {
            continue;
        }
        $upsert->execute([
            $code,
            (string) ($kit['title'] ?? $code),
            (string) ($kit['summary'] ?? ''),
            (int) ($kit['version'] ?? 1),
            json_encode($kit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
};
