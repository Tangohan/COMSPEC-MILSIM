-- Sessions de jeu (back-office/roleplay/sessions) : code de session, notifications en jeu et app Session du téléphone.
-- TROIS tables NOUVELLES, aucune colonne ajoutée ni donnée modifiée ailleurs ; les unicités sont dans les CREATE TABLE.
--   roleplay_game_session_codes    un code par ligne (actif, révoqué, remplacé ou expiré) ; un code n'est jamais réutilisé
--                                  dans une communauté (unicité tenant + code sur tout l'historique)
--   roleplay_game_session_app      réglages de l'app Session par session (notifications, rappels, consignes)
--   roleplay_game_session_journal  journal : codes émis ou révoqués, joueurs rattachés, refus, notifications envoyées ;
--                                  dedupe_key empêche d'envoyer deux fois le même rappel
-- Sans ces tables : la page de la session reste utilisable sans code ni notification, l'API répond « feature_unavailable ».
-- Retour arrière : migrations/rollback/roleplay_session_app_down.sql.

CREATE TABLE IF NOT EXISTS roleplay_game_session_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NOT NULL COMMENT 'roleplay_game_sessions.id',
  code CHAR(6) NOT NULL COMMENT '6 caractères A-Z (sans I, O) et 2-9, sans tiret',
  status VARCHAR(12) NOT NULL DEFAULT 'active' COMMENT 'active, revoked, replaced, expired',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  ended_at DATETIME NULL COMMENT 'révocation, remplacement ou expiration',
  ended_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rgs_codes_code (tenant_id, code),
  KEY idx_rgs_codes_session (tenant_id, session_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roleplay_game_session_app (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NOT NULL,
  notify_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 : aucune notification en jeu pour cette session',
  remind_15 TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'rappel 15 minutes avant le début prévu',
  remind_5 TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'rappel 5 minutes avant le début prévu',
  briefing VARCHAR(400) NULL COMMENT 'consignes affichées dans l''app Session',
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rgs_app_session (tenant_id, session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roleplay_game_session_journal (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NOT NULL,
  event VARCHAR(24) NOT NULL COMMENT 'code_issued, code_revoked, join, join_refused, notify_<événement>…',
  dedupe_key VARCHAR(64) NULL COMMENT 'clé d''unicité (rappel déjà envoyé, joueur déjà rattaché)',
  actor_user_id INT UNSIGNED NULL,
  detail VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rgs_journal_dedupe (tenant_id, session_id, dedupe_key),
  KEY idx_rgs_journal_session (tenant_id, session_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
