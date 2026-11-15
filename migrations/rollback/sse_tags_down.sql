-- Retour arrière de l'étape sse_tags (database/steps/4850_sse_tags.php, migrations/2026_11_21_100_sse_tags.sql). À lancer à la
-- main : efface la liaison des fiches à leurs étiquettes, les réglages des types de fiche, les réglages posés sur le catalogue
-- livré et les colonnes ajoutées au catalogue des étiquettes. Les fiches, leurs catégories, leur type, leur urgence et leur
-- cotation ne sont pas touchés ; les étiquettes des rattachements (nom, clé, couleur) restent.
DROP TABLE IF EXISTS sse_tag_links;
DROP TABLE IF EXISTS sse_field_note_settings;
-- Les lignes propres aux fiches (réglages du catalogue livré) n'ont pas de sens pour la fenêtre « Rattacher ».
DELETE FROM sse_attach_tags WHERE scope = 'field_note';
ALTER TABLE sse_attach_tags DROP INDEX idx_sse_attach_tag_family;
ALTER TABLE sse_attach_tags
  DROP COLUMN scope, DROP COLUMN family, DROP COLUMN code, DROP COLUMN name_fr, DROP COLUMN name_en, DROP COLUMN description,
  DROP COLUMN description_en, DROP COLUMN qualifier, DROP COLUMN sort_order, DROP COLUMN origin, DROP COLUMN status,
  DROP COLUMN merged_into, DROP COLUMN updated_by_user_id, DROP COLUMN updated_at;
