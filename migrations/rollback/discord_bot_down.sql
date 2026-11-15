-- Retour arrière de l'étape discord_bot (migrations/2026_11_17_100_discord_bot.sql). À lancer à la main : efface les réglages du
-- bot par communauté (salons, rôles, rappels), la liste des rappels déjà envoyés, le journal d'audit des commandes et les rappels
-- refermés du tableau de bord. Le rattachement serveur <-> communauté (discord_companion_guilds) et les comptes Discord liés ne
-- sont pas touchés.
DROP TABLE IF EXISTS discord_bot_dismissals;
DROP TABLE IF EXISTS discord_bot_audit;
DROP TABLE IF EXISTS discord_bot_reminders;
DROP TABLE IF EXISTS discord_bot_settings;
