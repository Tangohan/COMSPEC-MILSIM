-- Index des lectures fréquentes de la liaison (positions, repères). Idempotent : relançable sans erreur.
-- Voie officielle : étape atak_api_hot_indexes de la file active (php8.4 run-migrations.php), qui constate
-- les index déjà posés. Ce script reste un secours manuel : mysql -u ... athena < scripts/sql/api-hot-indexes.sql

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
