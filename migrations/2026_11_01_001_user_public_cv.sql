-- Page publique de C.V. du joueur (/joueur/{adresse}) : opt-in, désactivée par défaut.
-- Une ligne par compte ; l’adresse courte est unique sur toute la plateforme.
-- La page ne lit que des données déjà publiques par nature (indicatif, grade, unité, distinctions…) :
-- ni e-mail, ni identité civile, ni données médicales, ni dossier sécurisé.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `user_public_cv` (
  `user_id` int unsigned NOT NULL,
  `tenant_id` int unsigned NOT NULL COMMENT 'Communauté dont la page montre le parcours',
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `slug` varchar(32) DEFAULT NULL COMMENT 'Adresse courte : /joueur/{slug}',
  `bio` varchar(300) DEFAULT NULL COMMENT 'Présentation courte écrite pour la page publique',
  `sections_json` text DEFAULT NULL COMMENT 'Sections visibles (clé => 0/1)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_public_cv_slug_unique` (`slug`),
  KEY `user_public_cv_tenant` (`tenant_id`),
  CONSTRAINT `user_public_cv_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
