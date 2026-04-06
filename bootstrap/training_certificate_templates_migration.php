<?php

declare(strict_types=1);

/**
 * Gabarits d’attestation (logo, fond, couleurs) + traçabilité issued_by sur training_certificates.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_certificates' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_certificate_templates : table training_certificates absente — ignoré.\n";

        return;
    }

    $col = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_certificates' AND COLUMN_NAME = 'issued_by_user_id' LIMIT 1");
    if (!$col || !$col->fetch()) {
        echo "training_certificates : ajout colonne issued_by_user_id...\n";
        try {
            $pdo->exec(
                'ALTER TABLE training_certificates ADD COLUMN issued_by_user_id INT UNSIGNED NULL DEFAULT NULL AFTER enrollment_id'
            );
        } catch (PDOException $e) {
            echo '  [ATTENTION] issued_by_user_id colonne : ' . $e->getMessage() . "\n";
        }
        try {
            $pdo->exec(
                'ALTER TABLE training_certificates ADD KEY idx_training_certificates_issued_by (issued_by_user_id)'
            );
        } catch (PDOException) {
        }
        try {
            $pdo->exec(
                'ALTER TABLE training_certificates ADD CONSTRAINT fk_training_certificates_issued_by FOREIGN KEY (issued_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        } catch (PDOException) {
        }
    }

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `training_certificate_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL DEFAULT 'Modèle par défaut',
  `headline` VARCHAR(255) NOT NULL DEFAULT 'Attestation de formation',
  `subtitle` VARCHAR(255) NULL DEFAULT NULL,
  `footer_legal` TEXT NULL DEFAULT NULL,
  `primary_hex` VARCHAR(7) NOT NULL DEFAULT '#0f172a',
  `accent_hex` VARCHAR(7) NOT NULL DEFAULT '#059669',
  `logo_relative_path` VARCHAR(500) NULL DEFAULT NULL,
  `background_relative_path` VARCHAR(500) NULL DEFAULT NULL,
  `layout_json` JSON NULL DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_training_cert_tpl_tenant` (`tenant_id`),
  CONSTRAINT `fk_training_cert_tpl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
};
