-- Retour arrière de l'étape enlistment_retro_auto_close (database/steps/4550_enlistment_retro_auto_close.php). À lancer à
-- la main, après avoir désactivé la tâche « recruitment_retro_auto_close ». Les néants posés par Athena sont supprimés
-- d'abord : sans la colonne, ils passeraient pour des bilans rédigés par un membre. Les bilans rédigés ne sont pas touchés
-- (le journal des dossiers garde la trace des néants).
DELETE FROM enlistment_retro_feedbacks WHERE auto_closed_at IS NOT NULL;
ALTER TABLE enlistment_retro_feedbacks DROP COLUMN auto_closed_at;
