-- Retour arrière de migrations/2026_11_23_100_sse_detection_rules.sql (étape sse_detection_rules). À lancer à la main :
-- efface les réglages des règles de Cerbère-Lecture (le lexique livré s'applique de nouveau) et les décisions prises sur les
-- propositions (les propositions ignorées ou rejetées réapparaissent). Les fiches, comptes rendus et le journal SSE
-- (sse_audit_log, actions cerbere.lecture.*) ne sont pas touchés.
DROP TABLE IF EXISTS sse_detection_decisions;
DROP TABLE IF EXISTS sse_detection_rule_settings;
