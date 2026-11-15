-- Retour arrière de l'étape sse_member_civil_identity (4700). À lancer à la main.
-- Retire seulement les trois colonnes ajoutées. Les noms alignés restent alignés : les anciennes valeurs sont dans
-- sse_identity_history (décision « civil »), restaurables un champ à la fois depuis la fiche du membre avant ce retrait.
-- La date de naissance du personnage enregistrée sur le profil est perdue (elle se recalcule à l'identique ensuite).
ALTER TABLE sse_identities DROP COLUMN name_override;
ALTER TABLE sse_identities DROP COLUMN civil_synced_at;
ALTER TABLE user_profiles DROP COLUMN character_birth_date;
