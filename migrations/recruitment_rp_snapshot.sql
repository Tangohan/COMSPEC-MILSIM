-- Snapshot RP / dispo figé au dépôt d'une candidature (profil enrichi)
ALTER TABLE `enlistments`
  ADD COLUMN `recruitment_rp_json` JSON DEFAULT NULL
  AFTER `shared_fields`;
