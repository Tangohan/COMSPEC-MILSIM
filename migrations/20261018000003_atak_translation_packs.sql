-- Packs de langue partagés de l'atelier public /traduire-atak (traduction communautaire de l'ATAK natif).
-- Sans compte : un pack s'ouvre par un lien secret (jeton propriétaire ou jeton contributeur, seule leur empreinte
-- SHA-256 est gardée). Les contributeurs signent d'un pseudo libre ; aucune donnée personnelle n'est stockée.
-- Une phrase est repérée par sha1(texte français)[:16] (même clé que localisation/template/categories.json).
-- `rev` : compteur du pack, augmenté à chaque écriture ; chaque phrase / proposition garde le `rev` de sa dernière
-- écriture (synchronisation « depuis rev N » et détection des modifications concurrentes).
-- App\Repositories\AtakTranslationPackRepository.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_translation_packs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(12) NOT NULL,
  `lang_code` char(2) NOT NULL,
  `lang_name` varchar(40) NOT NULL DEFAULT '',
  `game_language` varchar(24) NOT NULL DEFAULT '',
  `author_label` varchar(60) NOT NULL DEFAULT '',
  `review_mode` enum('direct','review') NOT NULL DEFAULT 'direct',
  `owner_token_hash` char(64) NOT NULL,
  `edit_token_hash` char(64) NOT NULL,
  `rev` int unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_atak_tr_packs_public` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `atak_translation_strings` (
  `pack_id` int unsigned NOT NULL,
  `key_hash` char(16) NOT NULL,
  `text` text NOT NULL,
  `author` varchar(32) NOT NULL DEFAULT '',
  `rev` int unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pack_id`,`key_hash`),
  KEY `idx_atak_tr_strings_rev` (`pack_id`,`rev`),
  CONSTRAINT `fk_atak_tr_strings_pack` FOREIGN KEY (`pack_id`) REFERENCES `atak_translation_packs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `atak_translation_suggestions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pack_id` int unsigned NOT NULL,
  `key_hash` char(16) NOT NULL,
  `text` text NOT NULL,
  `author` varchar(32) NOT NULL DEFAULT '',
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `rev` int unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_atak_tr_sugg_rev` (`pack_id`,`rev`),
  KEY `idx_atak_tr_sugg_key` (`pack_id`,`key_hash`,`status`),
  CONSTRAINT `fk_atak_tr_sugg_pack` FOREIGN KEY (`pack_id`) REFERENCES `atak_translation_packs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `atak_translation_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pack_id` int unsigned NOT NULL,
  `key_hash` char(16) NOT NULL,
  `old_text` text NOT NULL,
  `new_text` text NOT NULL,
  `author` varchar(32) NOT NULL DEFAULT '',
  `action` enum('edit','accept','revert') NOT NULL DEFAULT 'edit',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_atak_tr_hist_key` (`pack_id`,`key_hash`,`id`),
  CONSTRAINT `fk_atak_tr_hist_pack` FOREIGN KEY (`pack_id`) REFERENCES `atak_translation_packs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
