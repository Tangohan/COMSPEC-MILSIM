-- Paiements PayPal : remboursement / annulation par l’administration et relance des paiements déclarés non vérifiés.
-- status accepte désormais aussi « refunded » (VARCHAR, aucune contrainte à changer).

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plan_payment_requests' AND COLUMN_NAME = 'admin_reminded_at');
SET @s := IF(@c = 0, 'ALTER TABLE plan_payment_requests ADD COLUMN admin_reminded_at DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plan_payment_requests' AND COLUMN_NAME = 'refunded_at');
SET @s := IF(@c = 0, 'ALTER TABLE plan_payment_requests ADD COLUMN refunded_at DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
