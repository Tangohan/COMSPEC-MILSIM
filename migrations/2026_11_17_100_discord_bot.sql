-- Bot Discord Athena : réglages par communauté (salons, rôles, rappels), rappels déjà envoyés, journal d'audit des
-- commandes et rappel refermable du tableau de bord. Le rattachement serveur <-> communauté reste dans
-- discord_companion_guilds (un serveur pour une communauté). Aucune donnée n'est ajoutée ni modifiée.
-- Retour arrière : migrations/rollback/discord_bot_down.sql.

CREATE TABLE IF NOT EXISTS discord_bot_settings (
  tenant_id INT UNSIGNED NOT NULL,
  announce_channel_id VARCHAR(24) DEFAULT NULL,
  applications_channel_id VARCHAR(24) DEFAULT NULL,
  events_channel_id VARCHAR(24) DEFAULT NULL,
  log_channel_id VARCHAR(24) DEFAULT NULL,
  member_role_id VARCHAR(24) DEFAULT NULL,
  manager_role_id VARCHAR(24) DEFAULT NULL,
  roles_json TEXT DEFAULT NULL,
  sync_roles TINYINT(1) NOT NULL DEFAULT 0,
  reminders_enabled TINYINT(1) NOT NULL DEFAULT 1,
  welcome_enabled TINYINT(1) NOT NULL DEFAULT 0,
  bot_seen_at DATETIME DEFAULT NULL,
  test_requested_at DATETIME DEFAULT NULL,
  linked_by_user_id INT UNSIGNED DEFAULT NULL,
  linked_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (tenant_id),
  CONSTRAINT fk_discord_bot_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discord_bot_reminders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  kind VARCHAR(24) NOT NULL,
  ref VARCHAR(80) NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_discord_bot_reminder (tenant_id, kind, ref),
  CONSTRAINT fk_discord_bot_reminders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discord_bot_audit (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  guild_id VARCHAR(24) NOT NULL,
  discord_user_id VARCHAR(24) NOT NULL DEFAULT '',
  command VARCHAR(40) NOT NULL,
  outcome VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_discord_bot_audit_tenant (tenant_id, created_at),
  KEY idx_discord_bot_audit_actor (discord_user_id, created_at),
  CONSTRAINT fk_discord_bot_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discord_bot_dismissals (
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  dismissed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id, user_id),
  CONSTRAINT fk_discord_bot_dismissals_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
