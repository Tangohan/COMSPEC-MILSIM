-- Index des lectures fréquentes de la liaison (positions, repères). Idempotent : relançable sans erreur.
-- Hors file de migrations : la file active doit rester vide (MigrationRegistryTest). À passer une fois à la main.
-- Usage manuel : mysql -u ... athena < scripts/sql/api-hot-indexes.sql

SET @t := 'atak_pings', @i := 'idx_atak_pings_tenant_map_created';
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t) = 1
  AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND INDEX_NAME = @i) = 0,
  'ALTER TABLE `atak_pings` ADD INDEX `idx_atak_pings_tenant_map_created` (tenant_id, map_id, created_at)',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @t := 'atak_units', @i := 'idx_atak_units_tenant_map_callsign';
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t) = 1
  AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND INDEX_NAME = @i) = 0,
  'ALTER TABLE `atak_units` ADD INDEX `idx_atak_units_tenant_map_callsign` (tenant_id, map_id, call_sign)',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
