-- Retour arrière de migrations/2026_11_23_100_roleplay_session_app.sql (étape roleplay_session_app). À lancer à la main :
-- efface les codes de session, les réglages de l'app Session et leur journal. Les sessions, leurs participants, les temps
-- retenus et le journal d'audit ne sont pas touchés ; la page de la session fonctionne ensuite sans code ni notification.
DROP TABLE IF EXISTS roleplay_game_session_journal;
DROP TABLE IF EXISTS roleplay_game_session_app;
DROP TABLE IF EXISTS roleplay_game_session_codes;
