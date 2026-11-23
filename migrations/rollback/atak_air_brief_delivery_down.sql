-- Retour arrière de l'étape atak_air_brief_delivery : retire le nom interrogé et le dernier relevé de la boîte du pilote.
-- Le statut des destinataires (envoyé, reçu, lu, WILCO, UNABLE) et ses horodatages restent : ils datent de l'étape 3720.
ALTER TABLE atak_nine_line_recipients DROP COLUMN polled_as, DROP COLUMN last_polled_at;
