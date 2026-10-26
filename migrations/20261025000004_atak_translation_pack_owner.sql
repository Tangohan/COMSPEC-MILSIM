-- Rattache un pack de l'atelier /traduire-atak au compte qui le reprend sur un autre appareil.
-- Les packs déjà créés n'ont pas de compte (user_id vide) : ils s'ouvrent toujours par leur lien secret.
-- Aucune ligne existante n'est supprimée ni réécrite.
SET NAMES utf8mb4;

ALTER TABLE `atak_translation_packs`
  ADD COLUMN `user_id` int unsigned DEFAULT NULL COMMENT 'Compte qui reprend cette traduction. NULL : pack historique ouvert par son lien.' AFTER `edit_token_hash`,
  ADD KEY `idx_atak_tr_packs_user` (`user_id`, `lang_code`);
