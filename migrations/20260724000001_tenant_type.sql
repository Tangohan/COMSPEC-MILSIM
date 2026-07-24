-- Ajout du champ tenant_type pour différencier les types de communautés
-- Type par défaut : 'full' (complet)
-- Types simplifiés : 'effectifs' (gestion simple d'effectifs) et 'atak' (ATAK uniquement)

ALTER TABLE `tenants` 
ADD COLUMN `tenant_type` VARCHAR(32) NOT NULL DEFAULT 'full' AFTER `slug`,
ADD INDEX `idx_tenants_type` (`tenant_type`);

-- Mise à jour des tenants existants : tout passe en 'full' (comportement actuel)
UPDATE `tenants` SET `tenant_type` = 'full' WHERE `tenant_type` = 'full';
