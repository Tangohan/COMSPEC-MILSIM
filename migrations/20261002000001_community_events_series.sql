-- Événements récurrents : les occurrences créées ensemble partagent un identifiant de série.
-- (La colonne est aussi ajoutée à la volée par CommunityEventRepository::seriesColumnReady.)

ALTER TABLE community_events
    ADD COLUMN series_id CHAR(32) NULL DEFAULT NULL,
    ADD INDEX idx_community_events_series (tenant_id, series_id);
