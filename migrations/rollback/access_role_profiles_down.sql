-- Retour arrière de migrations/2026_11_23_100_access_role_profiles.sql (étape access_role_profiles). À lancer à la main :
-- efface les fiches des niveaux d'accès (abrégés, descriptions détaillées, icônes, logos, étiquettes posées, qualifications et
-- emplois associés, ordre, archivage). Les niveaux eux-mêmes, leurs droits, leurs titulaires, leur nom, leur description courte
-- et leur couleur (table roles) ne sont pas touchés, ni le catalogue des étiquettes partagé avec les fonctions.
-- Les fichiers de logo restent dans storage/private/access-role-logos : les supprimer à la main si besoin.
DROP TABLE IF EXISTS access_role_jobs;
DROP TABLE IF EXISTS access_role_qualifications;
DROP TABLE IF EXISTS access_role_tag_links;
DROP TABLE IF EXISTS access_role_profiles;
