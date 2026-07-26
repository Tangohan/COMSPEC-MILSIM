-- Mode maintenance ATAK / Tacmap par communauté.
-- Préférer : php run-migrations.php (appelle bootstrap/tenant_atak_maintenance_migration.php).
-- Sur MariaDB 10.3+ / 11.x :

ALTER TABLE `tenant_atak_config`
  ADD COLUMN IF NOT EXISTS `maintenance_enabled` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `tenant_atak_config`
  ADD COLUMN IF NOT EXISTS `maintenance_message` TEXT DEFAULT NULL;
