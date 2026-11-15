-- Retour arrière de migrations/2026_11_15_100_sse_access_rules.sql (étape sse_access_rules). À lancer à la main : efface
-- les règles de droits d'accès SSE et leur historique ; les gardes d'origine reprennent seules (aucun membre ne gagne
-- ni ne perd un accès par rapport à une communauté sans règle). Le journal d'audit SSE (sse_audit_log) n'est pas touché.
DROP TABLE IF EXISTS sse_access_rule_history;
DROP TABLE IF EXISTS sse_access_rules;
