CREATE TABLE IF NOT EXISTS `user_legal_identities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_legal_identities_user` (`user_id`),
  KEY `idx_user_legal_identities_tenant` (`tenant_id`),
  CONSTRAINT `fk_user_legal_identities_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_legal_identities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_legal_identities` (`tenant_id`, `user_id`, `first_name`, `last_name`, `phone`, `birth_date`, `nationality`, `created_at`, `updated_at`)
SELECT
    u.tenant_id,
    up.user_id,
    NULLIF(TRIM(up.first_name), ''),
    NULLIF(TRIM(up.last_name), ''),
    NULLIF(TRIM(up.phone), ''),
    up.birth_date,
    NULLIF(TRIM(up.nationality), ''),
    NOW(),
    NOW()
FROM user_profiles up
INNER JOIN users u ON u.id = up.user_id
LEFT JOIN user_legal_identities uli ON uli.user_id = up.user_id
WHERE uli.user_id IS NULL
  AND (
      NULLIF(TRIM(up.first_name), '') IS NOT NULL
      OR NULLIF(TRIM(up.last_name), '') IS NOT NULL
      OR NULLIF(TRIM(up.phone), '') IS NOT NULL
      OR up.birth_date IS NOT NULL
      OR NULLIF(TRIM(up.nationality), '') IS NOT NULL
  );
