-- 4.6 Événements / Pointage : motifs d'absence normalisés et base analytics.

ALTER TABLE community_event_rsvps
    ADD COLUMN absence_reason VARCHAR(64) NULL AFTER status,
    ADD COLUMN absence_note VARCHAR(255) NULL AFTER absence_reason;

ALTER TABLE community_event_rsvps
    ADD INDEX idx_event_rsvps_absence_reason (absence_reason);
