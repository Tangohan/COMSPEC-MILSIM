-- Ajout du champ tenant_type pour différencier les types de communautés
-- Type par défaut : 'full' (complet)
-- Types simplifiés : 'effectifs' (effectifs + ATAK) et 'atak' (ATAK uniquement)
--
-- Préférer le runner idempotent : bootstrap/tenant_type_migration.php via php run-migrations.php
-- (ce fichier SQL brut n’est pas exécuté automatiquement).

-- ALTER TABLE `tenants`
-- ADD COLUMN `tenant_type` VARCHAR(32) NOT NULL DEFAULT 'full' AFTER `slug`,
-- ADD INDEX `idx_tenants_type` (`tenant_type`);
