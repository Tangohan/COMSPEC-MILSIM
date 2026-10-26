-- Contributeurs du site et traduction communautaire du site (/contribuer, /traduire-site).
-- Rôles de contributeur (traducteur, testeur, créateur de contenu, développeur, support) : un membre en fait la
-- demande, l'administration du site valide ou refuse, puis peut retirer le rôle. Le traducteur l'est pour une ou
-- plusieurs langues. Le pseudo Discord sert à donner le rôle équivalent sur le serveur Discord.
-- Traduction : le site est écrit en français ; chaque langue de la communauté (et « en » pour corriger l'anglais) a
-- ses traductions validées et les propositions en attente. Une langue publiée apparaît dans le sélecteur de langue.
-- App\Repositories\SiteContributorRepository, App\Services\I18n\CommunityLanguages.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `site_contributor_roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `role` varchar(24) NOT NULL COMMENT 'translator, tester, content, developer, support',
  `languages` varchar(120) NOT NULL DEFAULT '' COMMENT 'Codes de langue séparés par des virgules (traducteur)',
  `status` varchar(12) NOT NULL DEFAULT 'pending' COMMENT 'pending, active, rejected, revoked',
  `motivation` varchar(1500) NOT NULL DEFAULT '',
  `discord_handle` varchar(64) NOT NULL DEFAULT '',
  `public_credit` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Nom affiché dans la liste publique des contributeurs',
  `admin_note` varchar(500) NOT NULL DEFAULT '',
  `decided_by` int unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_contrib_user_role` (`user_id`, `role`),
  KEY `idx_site_contrib_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_translation_languages` (
  `code` varchar(8) NOT NULL,
  `name` varchar(60) NOT NULL COMMENT 'Nom dans la langue elle-même (Español)',
  `status` varchar(12) NOT NULL DEFAULT 'draft' COMMENT 'draft, published',
  `created_by` int unsigned DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Traduction validée d'un texte du site dans une langue (une seule par texte).
CREATE TABLE IF NOT EXISTS `site_translations` (
  `lang` varchar(8) NOT NULL,
  `src_hash` char(16) NOT NULL COMMENT 'sha1(texte source)[:16], voir CommunityLanguages::hash',
  `text` text NOT NULL,
  `user_id` int unsigned DEFAULT NULL COMMENT 'Dernier auteur',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lang`, `src_hash`),
  KEY `idx_site_tr_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Propositions des membres sans rôle de traducteur pour la langue : un traducteur ou l'administration les accepte
-- (elles deviennent la traduction validée) ou les refuse.
CREATE TABLE IF NOT EXISTS `site_translation_suggestions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `lang` varchar(8) NOT NULL,
  `src_hash` char(16) NOT NULL,
  `text` text NOT NULL,
  `user_id` int unsigned NOT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'pending' COMMENT 'pending, accepted, rejected',
  `decided_by` int unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site_tr_sugg_lang` (`lang`, `status`, `src_hash`),
  KEY `idx_site_tr_sugg_user` (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
