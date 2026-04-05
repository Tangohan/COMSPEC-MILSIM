-- Tables moteur modération forum (règles + journal bot / heuristique).
-- Idempotent : CREATE IF NOT EXISTS. Aligné sur migrations/forum_v2.sql.
-- Usage : import manuel ou bootstrap/forum_moderation_bot_migration.php

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS forum_moderation_rules (
  id int unsigned NOT NULL AUTO_INCREMENT,
  tenant_id int unsigned NOT NULL,
  rule_type varchar(64) NOT NULL,
  threshold decimal(10,4) DEFAULT NULL,
  action varchar(32) NOT NULL,
  config_json json DEFAULT NULL,
  enabled tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tenant_id (tenant_id),
  CONSTRAINT forum_moderation_rules_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_moderation_logs (
  id int unsigned NOT NULL AUTO_INCREMENT,
  tenant_id int unsigned NOT NULL,
  user_id int unsigned DEFAULT NULL,
  post_id int unsigned DEFAULT NULL,
  rule_type varchar(64) NOT NULL,
  score decimal(10,4) DEFAULT NULL,
  action_taken varchar(64) NOT NULL,
  detail_json json DEFAULT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tenant_id (tenant_id),
  KEY idx_fml_tenant_created (tenant_id, created_at),
  KEY post_id (post_id),
  CONSTRAINT forum_moderation_logs_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT forum_moderation_logs_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT forum_moderation_logs_post_fk FOREIGN KEY (post_id) REFERENCES forum_posts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
