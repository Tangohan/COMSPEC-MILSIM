-- Retour arrière de l'étape user_last_active : retire la colonne de dernière activité ; les graphiques d'accueil retombent sur last_login_at.
ALTER TABLE users DROP COLUMN last_active_at;
