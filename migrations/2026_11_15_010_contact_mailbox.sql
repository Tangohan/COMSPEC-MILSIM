-- Boîte de contact de la plateforme (Administration du site > Boîte de contact) : fils de discussion, messages reçus et
-- envoyés, pièces jointes et état de la synchronisation IMAP. Aucune donnée n'est ajoutée : tant que CONTACT_MAIL_PASSWORD
-- n'est pas renseigné dans le .env, rien ne s'y écrit. Retour arrière : migrations/rollback/contact_mailbox_down.sql.

CREATE TABLE IF NOT EXISTS contact_mail_threads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject VARCHAR(255) NOT NULL DEFAULT '',
  subject_norm VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'objet sans Re: ni Fwd:, en minuscules (regroupement de repli)',
  participant_email VARCHAR(254) NOT NULL DEFAULT '' COMMENT 'correspondant : expéditeur du premier message, ou destinataire d''un nouveau message',
  participant_name VARCHAR(160) NOT NULL DEFAULT '',
  source VARCHAR(10) NOT NULL DEFAULT 'mail' COMMENT 'mail|form|compose',
  status VARCHAR(10) NOT NULL DEFAULT 'inbox' COMMENT 'inbox|archived|spam|trash|draft',
  todo TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'à traiter : un message reçu attend une réponse',
  starred TINYINT(1) NOT NULL DEFAULT 0,
  message_count INT UNSIGNED NOT NULL DEFAULT 0,
  unread_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_direction VARCHAR(3) NOT NULL DEFAULT 'in' COMMENT 'in|out',
  last_snippet VARCHAR(255) NOT NULL DEFAULT '',
  last_message_at DATETIME DEFAULT NULL COMMENT 'UTC',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cm_threads_list (status, last_message_at),
  KEY idx_cm_threads_participant (participant_email),
  KEY idx_cm_threads_subject (subject_norm, participant_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_mail_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id BIGINT UNSIGNED NOT NULL,
  direction VARCHAR(3) NOT NULL DEFAULT 'in' COMMENT 'in|out',
  state VARCHAR(10) NOT NULL DEFAULT 'received' COMMENT 'received|sent|draft',
  source VARCHAR(10) NOT NULL DEFAULT 'mail' COMMENT 'mail|form|compose|reply',
  message_id VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Message-ID sans chevrons',
  in_reply_to VARCHAR(255) NOT NULL DEFAULT '',
  refs TEXT DEFAULT NULL COMMENT 'References, identifiants séparés par un espace',
  from_name VARCHAR(160) NOT NULL DEFAULT '',
  from_email VARCHAR(254) NOT NULL DEFAULT '',
  reply_to_email VARCHAR(254) NOT NULL DEFAULT '',
  to_json TEXT DEFAULT NULL,
  cc_json TEXT DEFAULT NULL,
  subject VARCHAR(255) NOT NULL DEFAULT '',
  body_text MEDIUMTEXT DEFAULT NULL,
  body_html MEDIUMTEXT DEFAULT NULL COMMENT 'HTML d''origine, jamais affiché sans assainissement',
  snippet VARCHAR(255) NOT NULL DEFAULT '',
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  auto_reply TINYINT(1) NOT NULL DEFAULT 0,
  has_attachments TINYINT(1) NOT NULL DEFAULT 0,
  imap_uidvalidity BIGINT UNSIGNED DEFAULT NULL,
  imap_uid INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cm_messages_imap (imap_uidvalidity, imap_uid),
  KEY idx_cm_messages_thread (thread_id, sent_at),
  KEY idx_cm_messages_msgid (message_id),
  KEY idx_cm_messages_sent (direction, state, created_at),
  CONSTRAINT fk_cm_messages_thread FOREIGN KEY (thread_id) REFERENCES contact_mail_threads (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_mail_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  message_id BIGINT UNSIGNED NOT NULL,
  filename VARCHAR(190) NOT NULL DEFAULT '',
  mime VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
  size INT UNSIGNED NOT NULL DEFAULT 0,
  content_id VARCHAR(200) NOT NULL DEFAULT '',
  is_inline TINYINT(1) NOT NULL DEFAULT 0,
  storage_path VARCHAR(255) DEFAULT NULL COMMENT 'sous storage/private/contact-mail, NULL si le fichier dépassait la taille permise',
  sha256 CHAR(64) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cm_attachments_message (message_id),
  CONSTRAINT fk_cm_attachments_message FOREIGN KEY (message_id) REFERENCES contact_mail_messages (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_mail_sync_state (
  folder VARCHAR(120) NOT NULL,
  uidvalidity BIGINT UNSIGNED NOT NULL DEFAULT 0,
  last_uid INT UNSIGNED NOT NULL DEFAULT 0,
  last_sync_at DATETIME DEFAULT NULL,
  last_ok_at DATETIME DEFAULT NULL,
  last_error VARCHAR(255) NOT NULL DEFAULT '',
  imported_total INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (folder)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
