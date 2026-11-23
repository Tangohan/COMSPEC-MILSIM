-- Retour arrière de l'étape 5080 : dossiers de contestation (références, fil d'échange)
DROP TABLE IF EXISTS platform_ban_appeal_messages;
DROP TABLE IF EXISTS platform_ban_appeal_counters;
ALTER TABLE platform_ban_appeals DROP INDEX uq_pba_reference;
ALTER TABLE platform_ban_appeals DROP INDEX idx_pba_queue;
ALTER TABLE platform_ban_appeals DROP COLUMN reference, DROP COLUMN status, DROP COLUMN assigned_to, DROP COLUMN unread_staff, DROP COLUMN updated_at;
