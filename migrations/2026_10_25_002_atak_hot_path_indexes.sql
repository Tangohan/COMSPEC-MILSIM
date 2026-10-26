-- Index des chemins sondés par le jeu (revue d'optimisation, axe base de données).
-- Index déjà présent (1061) ou déjà supprimé (1091) : ignoré par le moteur de migrations.

-- Expiration des commandes du poste (AtakWebCommandRepository::expireStale, sans filtre carte).
ALTER TABLE `atak_web_commands`
  ADD INDEX `idx_web_cmd_status_created` (`tenant_id`, `status`, `created_at`);

-- Alertes médicales / tactiques tirées du tchat (ORDER BY created_at DESC LIMIT n) : plus de tri complet.
ALTER TABLE `atak_chat_messages`
  ADD INDEX `idx_atak_chat_created` (`tenant_id`, `map_id`, `created_at`);

-- Marqueurs synchronisés depuis le jeu (recherche par arma_name à chaque marqueur).
ALTER TABLE `atak_markers`
  ADD INDEX `idx_atak_markers_arma_name` (`tenant_id`, `map_id`, `arma_name`(191));

-- Dernier point d'un véhicule (filtre de doublons d'équipage) et relecture par période.
ALTER TABLE `atak_vehicle_position_history`
  ADD INDEX `idx_vph_vehicle_recorded` (`vehicle_tracking_id`, `recorded_at`);

-- Index jamais utilisés, réécrits à chaque mise à jour de position :
-- idx_position (aucune requête par coordonnées), idx_vehicle_callsign (couvert par uk_tenant_context_callsign).
ALTER TABLE `atak_vehicle_tracking` DROP INDEX `idx_position`;
ALTER TABLE `atak_vehicle_tracking` DROP INDEX `idx_vehicle_callsign`;
