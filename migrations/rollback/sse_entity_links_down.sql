-- Retour arrière de migrations/2026_11_16_110_sse_entity_links.sql (étape sse_entity_links). À lancer à la main : efface les
-- liens de provenance et les liens manuels posés depuis le widget « Liaisons ». Aucune fiche, identité, papier, dossier ni
-- contrôle n'est touché ; le widget retombe en mode dégradé (liens déduits des colonnes existantes).
DROP TABLE IF EXISTS sse_entity_links;
