<?php

declare(strict_types=1);

use App\Core\Database;

function migratePlatformAdminTenantIntervention(?PDO $connection = null): void
{
    $pdo = $connection ?? Database::getPdo();
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_admin_tenant_sessions (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_id INT UNSIGNED NOT NULL, tenant_id INT UNSIGNED NOT NULL,
      started_at DATETIME NOT NULL, ended_at DATETIME NULL, reason VARCHAR(500) NULL,
      status ENUM('active','ended') NOT NULL DEFAULT 'active', ip_address VARCHAR(64) NULL, user_agent VARCHAR(500) NULL,
      KEY idx_pats_tenant_active (tenant_id,status,started_at), KEY idx_pats_admin (admin_id,started_at),
      CONSTRAINT fk_pats_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
      CONSTRAINT fk_pats_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_admin_tenant_actions (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, session_id BIGINT UNSIGNED NOT NULL, tenant_id INT UNSIGNED NOT NULL,
      admin_id INT UNSIGNED NOT NULL, request_id VARCHAR(64) NOT NULL, action_type VARCHAR(32) NOT NULL,
      module VARCHAR(100) NULL, route VARCHAR(500) NULL, http_method VARCHAR(10) NULL, entity_type VARCHAR(100) NULL,
      entity_id VARCHAR(191) NULL, description TEXT NULL, before_state JSON NULL, after_state JSON NULL, metadata JSON NULL,
      is_reversible TINYINT(1) NOT NULL DEFAULT 0, rollback_status VARCHAR(32) NOT NULL DEFAULT 'not_requested',
      rollback_of_action_id BIGINT UNSIGNED NULL, rolled_back_by_action_id BIGINT UNSIGNED NULL,
      checkpoint_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL,
      KEY idx_pata_timeline (session_id,created_at,id), KEY idx_pata_entity (tenant_id,entity_type,entity_id),
      CONSTRAINT fk_pata_session FOREIGN KEY(session_id) REFERENCES platform_admin_tenant_sessions(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_admin_tenant_errors (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, session_id BIGINT UNSIGNED NOT NULL, tenant_id INT UNSIGNED NOT NULL,
      admin_id INT UNSIGNED NOT NULL, request_id VARCHAR(64) NOT NULL, severity VARCHAR(20) NOT NULL,
      module VARCHAR(100) NULL, route VARCHAR(500) NULL, exception_class VARCHAR(255) NULL, message TEXT NOT NULL,
      stack_trace MEDIUMTEXT NULL, context_json JSON NULL, created_at DATETIME NOT NULL,
      KEY idx_pate_timeline(session_id,created_at,id),
      CONSTRAINT fk_pate_session FOREIGN KEY(session_id) REFERENCES platform_admin_tenant_sessions(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_admin_tenant_checkpoints (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, session_id BIGINT UNSIGNED NOT NULL, tenant_id INT UNSIGNED NOT NULL,
      admin_id INT UNSIGNED NOT NULL, label VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL,
      KEY idx_patc_session(session_id,created_at),
      CONSTRAINT fk_patc_session FOREIGN KEY(session_id) REFERENCES platform_admin_tenant_sessions(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
