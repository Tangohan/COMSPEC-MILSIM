-- Forum v2 : périmètres (global / org), fils, votes, tags, pièces jointes, notifications, modération auto.
--
-- Écart par rapport à migrations/schema.sql (forum « v1 ») :
--   forum_categories : +scope, +owner_tenant_id (FK tenants)
--   forum_topics     : +is_solved, +best_answer_post_id (FK forum_posts)
--   forum_posts      : +parent_post_id (FK self, fils de discussion)
--   forum_reports    : +report_type, +comment
--   Nouvelles tables : forum_post_votes, forum_tags, forum_topic_tags, forum_attachments,
--                      forum_report_events, forum_notifications, user_forum_stats,
--                      forum_moderation_rules, forum_moderation_logs
-- (Les colonnes icon / is_hidden / forum_category_subscriptions viennent d’autres blocs dans run-migrations.php.)
--
-- Exécution : bootstrap/forum_v2_migration.php (appelé par run-migrations.php) — import manuel possible avec mysql.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- forum_categories : scope + propriétaire organisation
-- scope: general (défaut), platform (espace visible « tous »), organization (section dédiée une org)
ALTER TABLE forum_categories
  ADD COLUMN scope varchar(32) NOT NULL DEFAULT 'general' AFTER tenant_id,
  ADD COLUMN owner_tenant_id int unsigned DEFAULT NULL AFTER scope;

ALTER TABLE forum_categories
  ADD KEY forum_categories_scope (scope),
  ADD KEY forum_categories_owner_tenant (owner_tenant_id),
  ADD CONSTRAINT forum_categories_owner_tenant_fk FOREIGN KEY (owner_tenant_id) REFERENCES tenants (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- forum_topics
ALTER TABLE forum_topics
  ADD COLUMN is_solved tinyint(1) NOT NULL DEFAULT 0 AFTER is_archived,
  ADD COLUMN best_answer_post_id int unsigned DEFAULT NULL AFTER is_solved;

ALTER TABLE forum_topics
  ADD KEY forum_topics_best_answer (best_answer_post_id),
  ADD CONSTRAINT forum_topics_best_answer_fk FOREIGN KEY (best_answer_post_id) REFERENCES forum_posts (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- forum_posts : réponses hiérarchiques
ALTER TABLE forum_posts
  ADD COLUMN parent_post_id int unsigned DEFAULT NULL AFTER topic_id;

ALTER TABLE forum_posts
  ADD KEY forum_posts_parent (parent_post_id),
  ADD CONSTRAINT forum_posts_parent_fk FOREIGN KEY (parent_post_id) REFERENCES forum_posts (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- forum_reports étendus
ALTER TABLE forum_reports
  ADD COLUMN report_type varchar(32) NOT NULL DEFAULT 'other' AFTER reason,
  ADD COLUMN comment text AFTER report_type;

-- forum_post_votes
CREATE TABLE IF NOT EXISTS forum_post_votes (
  id int unsigned NOT NULL AUTO_INCREMENT,
  tenant_id int unsigned NOT NULL,
  post_id int unsigned NOT NULL,
  user_id int unsigned NOT NULL,
  value tinyint NOT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY forum_vote_user_post (post_id, user_id),
  KEY tenant_id (tenant_id),
  KEY user_id (user_id),
  CONSTRAINT forum_post_votes_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT forum_post_votes_post_fk FOREIGN KEY (post_id) REFERENCES forum_posts (id) ON DELETE CASCADE,
  CONSTRAINT forum_post_votes_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tags
CREATE TABLE IF NOT EXISTS forum_tags (
  id int unsigned NOT NULL AUTO_INCREMENT,
  tenant_id int unsigned NOT NULL,
  name varchar(80) NOT NULL,
  slug varchar(100) NOT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tenant_slug (tenant_id, slug),
  CONSTRAINT forum_tags_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_topic_tags (
  topic_id int unsigned NOT NULL,
  tag_id int unsigned NOT NULL,
  PRIMARY KEY (topic_id, tag_id),
  CONSTRAINT forum_topic_tags_topic_fk FOREIGN KEY (topic_id) REFERENCES forum_topics (id) ON DELETE CASCADE,
  CONSTRAINT forum_topic_tags_tag_fk FOREIGN KEY (tag_id) REFERENCES forum_tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pièces jointes
CREATE TABLE IF NOT EXISTS forum_attachments (
  id int unsigned NOT NULL AUTO_INCREMENT,
  tenant_id int unsigned NOT NULL,
  post_id int unsigned NOT NULL,
  file_path varchar(500) NOT NULL,
  mime varchar(120) NOT NULL,
  size_bytes int unsigned NOT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tenant_id (tenant_id),
  KEY post_id (post_id),
  CONSTRAINT forum_attachments_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT forum_attachments_post_fk FOREIGN KEY (post_id) REFERENCES forum_posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Historique signalements
CREATE TABLE IF NOT EXISTS forum_report_events (
  id int unsigned NOT NULL AUTO_INCREMENT,
  report_id int unsigned NOT NULL,
  actor_id int unsigned DEFAULT NULL,
  action varchar(64) NOT NULL,
  note text,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY report_id (report_id),
  CONSTRAINT forum_report_events_report_fk FOREIGN KEY (report_id) REFERENCES forum_reports (id) ON DELETE CASCADE,
  CONSTRAINT forum_report_events_actor_fk FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications forum (in-app)
CREATE TABLE IF NOT EXISTS forum_notifications (
  id int unsigned NOT NULL AUTO_INCREMENT,
  tenant_id int unsigned NOT NULL,
  user_id int unsigned NOT NULL,
  type varchar(40) NOT NULL,
  payload_json json DEFAULT NULL,
  read_at datetime DEFAULT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tenant_user (tenant_id, user_id),
  KEY read_at (read_at),
  CONSTRAINT forum_notifications_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT forum_notifications_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stats réputation (par tenant)
CREATE TABLE IF NOT EXISTS user_forum_stats (
  tenant_id int unsigned NOT NULL,
  user_id int unsigned NOT NULL,
  post_count int unsigned NOT NULL DEFAULT 0,
  score int NOT NULL DEFAULT 0,
  reputation int NOT NULL DEFAULT 0,
  updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id, user_id),
  CONSTRAINT user_forum_stats_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT user_forum_stats_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Moteur modération (règles)
CREATE TABLE IF NOT EXISTS forum_moderation_rules (
  id int unsigned NOT NULL AUTO_INCREMENT,
  tenant_id int unsigned NOT NULL,
  rule_type varchar(64) NOT NULL,
  threshold decimal(10,4) DEFAULT NULL,
  action varchar(32) NOT NULL,
  config_json json DEFAULT NULL,
  enabled tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tenant_id (tenant_id),
  CONSTRAINT forum_moderation_rules_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_moderation_logs (
  id int unsigned NOT NULL AUTO_INCREMENT,
  tenant_id int unsigned NOT NULL,
  user_id int unsigned DEFAULT NULL,
  post_id int unsigned DEFAULT NULL,
  rule_type varchar(64) NOT NULL,
  score decimal(10,4) DEFAULT NULL,
  action_taken varchar(64) NOT NULL,
  detail_json json DEFAULT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tenant_id (tenant_id),
  KEY post_id (post_id),
  CONSTRAINT forum_moderation_logs_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT forum_moderation_logs_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT forum_moderation_logs_post_fk FOREIGN KEY (post_id) REFERENCES forum_posts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
