-- Retour arrière de migrations/2026_11_15_010_contact_mailbox.sql (étape contact_mailbox). À lancer à la main : efface la
-- copie locale de la boîte de contact (fils, messages, pièces jointes, état de synchronisation). Les messages restent dans
-- la boîte e-mail d'origine. Les fichiers de pièces jointes de storage/private/contact-mail se suppriment à part.
DROP TABLE IF EXISTS contact_mail_attachments;
DROP TABLE IF EXISTS contact_mail_messages;
DROP TABLE IF EXISTS contact_mail_threads;
DROP TABLE IF EXISTS contact_mail_sync_state;
