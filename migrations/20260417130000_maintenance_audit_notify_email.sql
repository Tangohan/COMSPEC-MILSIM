-- Diffusion e-mail depuis l’admin maintenance : trace d’audit dédiée
ALTER TABLE `app_maintenance_audit`
MODIFY COLUMN `action_type` ENUM('create','update','enable','disable','delete','notify_email') NOT NULL;
