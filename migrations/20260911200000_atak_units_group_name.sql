-- Groupe BFT indexable (libellé Arma / Zeus), en plus de extra.group_name.
SET @has_group_name := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_units' AND COLUMN_NAME = 'group_name'
);
SET @sql_add := IF(
  @has_group_name = 0,
  'ALTER TABLE atak_units ADD COLUMN group_name VARCHAR(96) NULL DEFAULT NULL AFTER role, ADD KEY idx_atak_units_tenant_group (tenant_id, group_name)',
  'SELECT 1'
);
PREPARE stmt_add FROM @sql_add;
EXECUTE stmt_add;
DEALLOCATE PREPARE stmt_add;

UPDATE atak_units
SET group_name = NULLIF(TRIM(BOTH '"' FROM JSON_UNQUOTE(JSON_EXTRACT(extra, '$.group_name'))), '')
WHERE group_name IS NULL
  AND extra IS NOT NULL
  AND JSON_EXTRACT(extra, '$.group_name') IS NOT NULL
  AND JSON_UNQUOTE(JSON_EXTRACT(extra, '$.group_name')) NOT IN ('', 'null');
