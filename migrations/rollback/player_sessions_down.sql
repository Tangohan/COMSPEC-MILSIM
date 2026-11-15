-- Retour arrière de migrations/2026_11_18_100_player_sessions.sql (étape player_sessions). À lancer à la main : efface
-- l'historique des sessions de jeu. Sans cette table, la disponibilité revient au calcul d'avant (aucune session lue) ;
-- le temps de jeu cumulé (user_arma_playtime) et les sessions de mission ne sont pas touchés.
DROP TABLE IF EXISTS player_sessions;
