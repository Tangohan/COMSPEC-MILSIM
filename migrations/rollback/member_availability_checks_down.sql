-- Retour arrière de migrations/2026_11_22_100_member_availability_checks.sql (étape member_availability_checks). À lancer à
-- la main : efface l'historique des vérifications de disponibilité. Les sessions de jeu, les absences, le suivi du dossier
-- (y compris les étapes de relance déjà créées) et le journal d'audit ne sont pas touchés.
DROP TABLE IF EXISTS member_availability_checks;
