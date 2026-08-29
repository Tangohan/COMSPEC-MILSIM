-- Demandes de suppression définitive (Compte supprimé) émises par un organisateur.
-- Idempotent via bootstrap/account_purge_requests_migration.php.

CREATE TABLE IF NOT EXISTS `account_purge_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `target_user_id` int unsigned NOT NULL,
  `requested_by` int unsigned NOT NULL,
  `note` text,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `resolution_note` text,
  `resolved_by` int unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apr_tenant_status` (`tenant_id`, `status`),
  KEY `idx_apr_target` (`target_user_id`),
  KEY `idx_apr_requester` (`requested_by`),
  KEY `idx_apr_pending` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
