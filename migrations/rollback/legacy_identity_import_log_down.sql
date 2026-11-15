-- Retour arrière de migrations/2026_11_15_100_legacy_identity_import_log.sql (étape legacy_identity_import_log). À lancer à
-- la main : efface seulement le journal des reprises d'anciens noms (et donc le marqueur « à vérifier »). Les prénoms et
-- noms déjà copiés dans les fiches restent ; pour les retirer, utiliser d'abord « Annuler la reprise » dans Qualité des données.
DROP TABLE IF EXISTS legacy_identity_import_log;
