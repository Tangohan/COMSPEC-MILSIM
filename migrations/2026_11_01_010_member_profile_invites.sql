-- Liens « compléter son profil » envoyés à un membre (Effectifs > fiche > Invitations).
-- Le jeton n'est jamais stocké en clair : empreinte SHA-256 pour l'ouverture, copie chiffrée (APP_KEY)
-- pour « Copier le lien ». Exécuté par database/steps/3250_member_profile_invites.php ; idempotent.

CREATE TABLE IF NOT EXISTS `member_profile_invites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL COMMENT 'Membre invité à compléter son profil',
  `token_hash` char(64) NOT NULL COMMENT 'SHA-256 du jeton',
  `token_cipher` text DEFAULT NULL COMMENT 'Jeton chiffré, pour recopier le lien',
  `duration_hours` smallint unsigned NOT NULL DEFAULT 48,
  `single_use` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `first_opened_at` datetime DEFAULT NULL,
  `last_opened_at` datetime DEFAULT NULL,
  `open_count` int unsigned NOT NULL DEFAULT 0,
  `account_ready_at` datetime DEFAULT NULL COMMENT 'Compte activé ou connexion faite par le lien',
  `used_at` datetime DEFAULT NULL COMMENT 'Lien à usage unique consommé',
  `completed_at` datetime DEFAULT NULL COMMENT 'Profil complété',
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` int unsigned DEFAULT NULL,
  `last_sent_at` datetime DEFAULT NULL,
  `last_sent_channel` varchar(16) DEFAULT NULL COMMENT 'link | discord | email',
  `sent_count` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mpi_token` (`token_hash`),
  KEY `idx_mpi_tenant_user` (`tenant_id`, `user_id`),
  KEY `idx_mpi_tenant_created` (`tenant_id`, `created_at`),
  CONSTRAINT `fk_mpi_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mpi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
