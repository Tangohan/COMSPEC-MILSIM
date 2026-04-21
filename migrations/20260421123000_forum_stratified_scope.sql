-- Forum stratifié (global / tenant / mission) + socle opérationnel.
-- Idempotent: clauses IF [NOT] EXISTS + backfill conditionnel.

ALTER TABLE forum_categories
  MODIFY COLUMN tenant_id INT UNSIGNED NULL,
  MODIFY COLUMN scope VARCHAR(32) NOT NULL DEFAULT 'tenant';

UPDATE forum_categories
SET scope = CASE scope
  WHEN 'platform' THEN 'global'
  WHEN 'organization' THEN 'tenant'
  WHEN 'general' THEN 'tenant'
  WHEN 'moderation' THEN 'tenant'
  ELSE scope
END;

UPDATE forum_categories
SET tenant_id = NULL
WHERE scope = 'global';

ALTER TABLE forum_topics
  MODIFY COLUMN tenant_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS scope VARCHAR(16) NOT NULL DEFAULT 'tenant' AFTER tenant_id,
  ADD COLUMN IF NOT EXISTS thread_mode VARCHAR(16) NOT NULL DEFAULT 'standard' AFTER scope,
  ADD COLUMN IF NOT EXISTS channel_slug VARCHAR(64) DEFAULT NULL AFTER thread_mode,
  ADD COLUMN IF NOT EXISTS operation_mission_id BIGINT UNSIGNED DEFAULT NULL AFTER channel_slug,
  ADD COLUMN IF NOT EXISTS operation_case_ref VARCHAR(128) DEFAULT NULL AFTER operation_mission_id,
  ADD COLUMN IF NOT EXISTS operation_closed_at DATETIME DEFAULT NULL AFTER operation_case_ref,
  ADD COLUMN IF NOT EXISTS auto_summary_text MEDIUMTEXT DEFAULT NULL AFTER operation_closed_at;

ALTER TABLE forum_posts
  MODIFY COLUMN tenant_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS scope VARCHAR(16) NOT NULL DEFAULT 'tenant' AFTER tenant_id,
  ADD COLUMN IF NOT EXISTS message_type VARCHAR(16) NOT NULL DEFAULT 'INFO' AFTER scope,
  ADD COLUMN IF NOT EXISTS atak_payload_json JSON DEFAULT NULL AFTER message_type;

UPDATE forum_topics ft
INNER JOIN forum_categories fc ON fc.id = ft.category_id
SET ft.scope = CASE
    WHEN COALESCE(fc.scope, 'tenant') = 'global' THEN 'global'
    WHEN COALESCE(fc.scope, 'tenant') = 'mission' THEN 'mission'
    ELSE 'tenant'
END
WHERE ft.scope IS NULL OR ft.scope = '' OR ft.scope = 'general' OR ft.scope = 'organization' OR ft.scope = 'platform';

UPDATE forum_posts fp
INNER JOIN forum_topics ft ON ft.id = fp.topic_id
SET fp.scope = CASE
    WHEN COALESCE(ft.scope, 'tenant') = 'global' THEN 'global'
    WHEN COALESCE(ft.scope, 'tenant') = 'mission' THEN 'mission'
    ELSE 'tenant'
END
WHERE fp.scope IS NULL OR fp.scope = '' OR fp.scope = 'general' OR fp.scope = 'organization' OR fp.scope = 'platform';

UPDATE forum_topics
SET tenant_id = NULL
WHERE scope = 'global';

UPDATE forum_posts
SET tenant_id = NULL
WHERE scope = 'global';

CREATE TABLE IF NOT EXISTS forum_message_acks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_id INT UNSIGNED NOT NULL,
  post_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  ack_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_forum_message_acks_post_user (post_id, user_id),
  KEY idx_forum_message_acks_topic (topic_id),
  KEY idx_forum_message_acks_user (user_id),
  CONSTRAINT fk_forum_message_acks_topic FOREIGN KEY (topic_id) REFERENCES forum_topics (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_message_acks_post FOREIGN KEY (post_id) REFERENCES forum_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_message_acks_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_post_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_id INT UNSIGNED NOT NULL,
  post_id INT UNSIGNED NOT NULL,
  actor_user_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(32) NOT NULL,
  before_body MEDIUMTEXT DEFAULT NULL,
  after_body MEDIUMTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_forum_post_audit_post (post_id, created_at),
  CONSTRAINT fk_forum_post_audit_topic FOREIGN KEY (topic_id) REFERENCES forum_topics (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_post_audit_post FOREIGN KEY (post_id) REFERENCES forum_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_post_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_internal_flags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_id INT UNSIGNED NOT NULL,
  post_id INT UNSIGNED NOT NULL,
  flagged_by_user_id INT UNSIGNED NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'open',
  severity VARCHAR(16) NOT NULL DEFAULT 'normal',
  reason VARCHAR(255) DEFAULT NULL,
  escalated_at DATETIME DEFAULT NULL,
  resolved_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_forum_internal_flags_status (status, created_at),
  CONSTRAINT fk_forum_internal_flags_topic FOREIGN KEY (topic_id) REFERENCES forum_topics (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_internal_flags_post FOREIGN KEY (post_id) REFERENCES forum_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_internal_flags_user FOREIGN KEY (flagged_by_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_forum_topics_scope_tenant ON forum_topics (scope, tenant_id, updated_at);
CREATE INDEX IF NOT EXISTS idx_forum_posts_scope_tenant ON forum_posts (scope, tenant_id, created_at);
