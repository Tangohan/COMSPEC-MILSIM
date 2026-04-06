-- Signalements étendus (formations, profils, images, aide intégrée…)
ALTER TABLE forum_reports
  ADD COLUMN content_kind VARCHAR(64) NULL DEFAULT NULL;
