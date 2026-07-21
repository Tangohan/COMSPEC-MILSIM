-- Liaison Arma ↔ compte Athena (codes courts, TTL 15 min).
-- À exécuter en prod si POST /atak/game-link renvoie 503.
-- Préférable : php run-migrations.php (idempotent via bootstrap/tactical_game_link_migration.php).

CREATE TABLE IF NOT EXISTS tactical_game_link_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  code VARCHAR(8) NOT NULL,
  expires_at DATETIME NOT NULL,
  redeemed_at DATETIME DEFAULT NULL,
  redeemed_steam_uid VARCHAR(32) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tgl_code_expires (code, expires_at),
  KEY idx_tgl_tenant_user (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
