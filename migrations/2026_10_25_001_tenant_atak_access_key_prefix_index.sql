-- Recherche de la clé d’accès Overwatch par préfixe (ComspecApiKeyAuth, chaque requête tactique).
-- Remplace un parcours de table limité à 500 lignes.
-- Index déjà présent (1061) : ignoré par le moteur de migrations.

ALTER TABLE `tenant_atak_config`
  ADD INDEX `idx_tenant_atak_config_access_key_prefix` (`access_key_prefix`);
