-- Workshop des langues de l'ATAK natif (/traduire-atak/workshop) : un pack partagé de l'atelier peut être publié
-- dans le catalogue public. Chaque publication fige une version (v1, v2…) des phrases du pack : c'est elle que les
-- joueurs téléchargent, pendant que l'équipe continue de traduire dans l'atelier.
-- Contrôle des paquets : chaque version est revérifiée contre le modèle de chaînes du mod (audit_fp = empreinte du
-- modèle au dernier contrôle) ; quand une mise à jour du mod ajoute ou change des phrases, le pack passe « à mettre
-- à jour » ou « à revérifier ». App\Repositories\AtakTranslationPackRepository, App\Support\AtakTranslationWorkshop.
SET NAMES utf8mb4;

ALTER TABLE `atak_translation_packs`
  ADD COLUMN `listed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Visible dans le Workshop des langues' AFTER `rev`,
  ADD COLUMN `blocked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Retiré du Workshop par l''administration du site' AFTER `listed`,
  ADD COLUMN `description` varchar(600) NOT NULL DEFAULT '' AFTER `blocked`,
  ADD COLUMN `workshop_id` varchar(20) NOT NULL DEFAULT '' COMMENT 'Identifiant de l''objet Steam Workshop publié par l''auteur' AFTER `description`,
  ADD COLUMN `downloads` int unsigned NOT NULL DEFAULT 0 AFTER `workshop_id`,
  ADD COLUMN `published_at` datetime DEFAULT NULL AFTER `downloads`,
  ADD KEY `idx_atak_tr_packs_listed` (`listed`, `blocked`, `published_at`);

CREATE TABLE IF NOT EXISTS `atak_translation_releases` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pack_id` int unsigned NOT NULL,
  `version` int unsigned NOT NULL,
  `atak_version` varchar(20) NOT NULL DEFAULT '',
  `template_fp` char(16) NOT NULL DEFAULT '',
  `template_total` int unsigned NOT NULL DEFAULT 0,
  `translated` int unsigned NOT NULL DEFAULT 0,
  `note` varchar(300) NOT NULL DEFAULT '',
  `payload` mediumblob NOT NULL COMMENT 'gzcompress(JSON {clé: texte})',
  `audit_fp` varchar(40) NOT NULL DEFAULT '',
  `audit_json` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_atak_tr_rel_version` (`pack_id`, `version`),
  CONSTRAINT `fk_atak_tr_rel_pack` FOREIGN KEY (`pack_id`) REFERENCES `atak_translation_packs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clés des phrases du modèle du mod, une ligne par version du modèle (empreinte) : sert à savoir précisément
-- quelles phrases une mise à jour du mod a ajoutées depuis la version publiée d'un pack.
CREATE TABLE IF NOT EXISTS `atak_translation_template_keys` (
  `fp` char(16) NOT NULL,
  `atak_version` varchar(20) NOT NULL DEFAULT '',
  `total` int unsigned NOT NULL DEFAULT 0,
  `keys_gz` mediumblob NOT NULL COMMENT 'gzcompress(clés de 16 caractères concaténées)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`fp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
