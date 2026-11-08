-- Exporter les projets et sauvegarder storage/patch-studio AVANT ce rollback destructif.
DROP TABLE IF EXISTS patch_studio_releases;
DROP TABLE IF EXISTS patch_studio_asset_refs;
DROP TABLE IF EXISTS patch_studio_shares;
DROP TABLE IF EXISTS patch_studio_versions;
DROP TABLE IF EXISTS patch_studio_assets;
DROP TABLE IF EXISTS patch_studio_projects;
