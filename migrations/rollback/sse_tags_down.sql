-- Retour arrière de migrations/2026_11_21_100_sse_tags.sql (étape sse_tags). À lancer à la main : efface les étiquettes propres
-- des communautés, leurs réglages du catalogue livré, la liaison des fiches à leurs étiquettes et les réglages des types de fiche.
-- Les fiches, leurs catégories, leur type, leur urgence et leur cotation ne sont pas touchés.
-- Attention : sse_tags est partagée (portée « attach » des rattachements) ; ne pas la supprimer si la fenêtre « Rattacher » en dépend.
DROP TABLE IF EXISTS sse_tag_links;
DROP TABLE IF EXISTS sse_field_note_settings;
DROP TABLE IF EXISTS sse_tags;
