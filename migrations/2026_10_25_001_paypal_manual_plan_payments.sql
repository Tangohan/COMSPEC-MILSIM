-- Paiement des formules par PayPal (paypal.me) avec activation manuelle par l’administration du site.
-- Remplace le parcours Stripe : la personne choisit sa formule, paie le montant sur la page de paiement
-- d’Athena (bouton PayPal), déclare son identifiant de transaction ; l’admin reçoit un e-mail et active.

-- Prix affichés et demandés (centimes d’euro) : réglés dans Admin > Formules d’accès.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_plans' AND COLUMN_NAME = 'price_monthly_cents');
SET @s := IF(@c = 0, 'ALTER TABLE subscription_plans ADD COLUMN price_monthly_cents INT UNSIGNED NULL DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_plans' AND COLUMN_NAME = 'price_yearly_cents');
SET @s := IF(@c = 0, 'ALTER TABLE subscription_plans ADD COLUMN price_yearly_cents INT UNSIGNED NULL DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS plan_payment_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference VARCHAR(24) NOT NULL,
  kind VARCHAR(16) NOT NULL DEFAULT 'plan' COMMENT 'plan|heart',
  tenant_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  payer_email VARCHAR(190) NULL,
  plan_slug VARCHAR(50) NULL,
  billing_interval VARCHAR(16) NULL COMMENT 'monthly|yearly',
  amount_cents INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  status VARCHAR(16) NOT NULL DEFAULT 'awaiting' COMMENT 'awaiting|declared|activated|rejected|cancelled',
  transaction_ref VARCHAR(80) NULL COMMENT 'Identifiant de transaction PayPal saisi par la personne',
  payer_note VARCHAR(500) NULL,
  admin_note VARCHAR(500) NULL,
  period_ends_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  declared_at DATETIME NULL,
  decided_at DATETIME NULL,
  decided_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_ppr_reference (reference),
  KEY idx_ppr_status (status, created_at),
  KEY idx_ppr_tenant (tenant_id, created_at),
  KEY idx_ppr_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compte simple (usage sans organisation) choisi à la création du compte ou par un fondateur resté seul.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'account_mode');
SET @s := IF(@c = 0, 'ALTER TABLE users ADD COLUMN account_mode VARCHAR(16) NULL DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
