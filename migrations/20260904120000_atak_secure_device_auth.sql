-- Authentification de secours COMSPEC ATAK (MySQL 8 / MariaDB 10.5+).
CREATE TABLE IF NOT EXISTS atak_device_pairings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, device_code_hash CHAR(64) NOT NULL,
  user_code_hash CHAR(64) NOT NULL, user_code_hint VARCHAR(9) NOT NULL,
  terminal_uid VARCHAR(64) NOT NULL, steam_uid VARCHAR(32) DEFAULT NULL,
  mod_version VARCHAR(32) DEFAULT NULL, status ENUM('pending','approved','denied','expired','consumed') NOT NULL DEFAULT 'pending',
  user_id INT UNSIGNED DEFAULT NULL, tenant_id INT UNSIGNED DEFAULT NULL, approved_by INT UNSIGNED DEFAULT NULL,
  trusted_device_id BIGINT UNSIGNED DEFAULT NULL, request_ip VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME NOT NULL,
  approved_at DATETIME DEFAULT NULL, consumed_at DATETIME DEFAULT NULL, denied_at DATETIME DEFAULT NULL,
  PRIMARY KEY(id), UNIQUE KEY uk_atak_pair_device_code(device_code_hash),
  UNIQUE KEY uk_atak_pair_user_code(user_code_hash), KEY idx_atak_pair_expiry(status, expires_at),
  KEY idx_atak_pair_terminal(terminal_uid, created_at),
  CONSTRAINT fk_atak_pair_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_atak_pair_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_atak_pair_approver FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atak_trusted_devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL, tenant_id INT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL, terminal_uid VARCHAR(64) NOT NULL, steam_uid VARCHAR(32) DEFAULT NULL,
  label VARCHAR(120) DEFAULT NULL, atak_terminal_id BIGINT UNSIGNED DEFAULT NULL, certificate_id BIGINT UNSIGNED DEFAULT NULL,
  enrollment_status ENUM('enrolled','revoked') NOT NULL DEFAULT 'enrolled', approved_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, approved_at DATETIME NOT NULL,
  last_seen_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL,
  revoked_by INT UNSIGNED DEFAULT NULL, last_ip VARCHAR(45) DEFAULT NULL, last_mod_version VARCHAR(32) DEFAULT NULL,
  PRIMARY KEY(id), UNIQUE KEY uk_atak_trusted_tenant_terminal(tenant_id, terminal_uid),
  KEY idx_atak_trusted_user(user_id, revoked_at), KEY idx_atak_trusted_account(account_id, revoked_at),
  CONSTRAINT fk_atak_trusted_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_atak_trusted_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_atak_trusted_account FOREIGN KEY(account_id) REFERENCES athena_accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_atak_trusted_terminal FOREIGN KEY(atak_terminal_id) REFERENCES atak_terminals(id) ON DELETE SET NULL,
  CONSTRAINT fk_atak_trusted_certificate FOREIGN KEY(certificate_id) REFERENCES atak_certificates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE atak_device_pairings ADD CONSTRAINT fk_atak_pair_trusted FOREIGN KEY(trusted_device_id) REFERENCES atak_trusted_devices(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS atak_recovery_code_sets (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL, tenant_id INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, revoked_at DATETIME DEFAULT NULL,
 PRIMARY KEY(id), KEY idx_atak_recovery_set_user(user_id, tenant_id, revoked_at),
 CONSTRAINT fk_atak_rcs_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_atak_rcs_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atak_recovery_codes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, set_id BIGINT UNSIGNED NOT NULL, lookup_hash CHAR(64) NOT NULL,
 code_hash VARCHAR(255) NOT NULL, used_at DATETIME DEFAULT NULL, used_terminal_uid VARCHAR(64) DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), UNIQUE KEY uk_atak_recovery_lookup(lookup_hash),
 KEY idx_atak_recovery_set_unused(set_id, used_at), CONSTRAINT fk_atak_rc_set FOREIGN KEY(set_id) REFERENCES atak_recovery_code_sets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atak_security_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED DEFAULT NULL, tenant_id INT UNSIGNED DEFAULT NULL,
 event_type VARCHAR(64) NOT NULL, subject_type VARCHAR(40) DEFAULT NULL, subject_id BIGINT UNSIGNED DEFAULT NULL,
 ip_address VARCHAR(45) DEFAULT NULL, metadata_json JSON DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_atak_security_user(user_id, created_at), KEY idx_atak_security_tenant(tenant_id, created_at),
 KEY idx_atak_security_retention(created_at), CONSTRAINT fk_atak_security_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_atak_security_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
