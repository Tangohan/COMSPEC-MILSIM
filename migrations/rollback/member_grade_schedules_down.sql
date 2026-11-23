-- Retour arrière de migrations/2026_11_23_110_member_grade_schedules.sql (étape member_grade_schedules). À lancer à la main :
-- efface les changements de grade planifiés (à venir et historique de planification). Les grades déjà appliqués, l'historique
-- member_grade_changes et les fiches ne sont pas touchés ; le changement de grade immédiat fonctionne ensuite comme avant.
DROP TABLE IF EXISTS member_grade_schedules;
