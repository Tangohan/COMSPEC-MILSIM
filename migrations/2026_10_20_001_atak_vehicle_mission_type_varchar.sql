-- Suivi véhicules : mission_type ENUM → VARCHAR(40).
-- Le mod envoie GPS_BEACON (balises GPS) et des types libres (COMSPEC_MissionType) absents de l'ENUM :
-- en mode SQL strict l'upsert échouait (ticket #710, vehicle_exception sur /api/atak/telemetry/batch).
ALTER TABLE atak_vehicle_tracking MODIFY mission_type VARCHAR(40) NULL;
