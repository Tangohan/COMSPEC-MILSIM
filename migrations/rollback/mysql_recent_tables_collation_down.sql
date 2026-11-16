-- Retour arrière de l'étape mysql_recent_tables_collation (database/steps/4900_mysql_recent_tables_collation.php).
-- Aucun : remettre une table récente dans la collation par défaut du serveur referait échouer ses jointures avec les
-- tables au format utf8mb4_unicode_ci (erreur 1267 « Illegal mix of collations »), et aucune donnée n'est modifiée par la
-- conversion. La table user_community_membership_periods reste en place : elle est utilisée par l'inscription et les
-- départs ; la supprimer à la main ferait seulement perdre l'historique des séjours :
-- DROP TABLE IF EXISTS user_community_membership_periods;
SELECT 1;
