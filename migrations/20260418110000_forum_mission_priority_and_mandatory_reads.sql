ALTER TABLE forum_topics
  ADD COLUMN mission_priority_level varchar(16) DEFAULT NULL AFTER is_official,
  ADD COLUMN mission_priority_role varchar(64) DEFAULT NULL AFTER mission_priority_level,
  ADD COLUMN mandatory_read tinyint(1) NOT NULL DEFAULT 0 AFTER mission_priority_role,
  ADD COLUMN mandatory_read_due_at datetime DEFAULT NULL AFTER mandatory_read;

ALTER TABLE forum_topics
  ADD KEY idx_forum_topics_mission_priority (tenant_id, mission_priority_level, mandatory_read),
  ADD KEY idx_forum_topics_mandatory_due_at (tenant_id, mandatory_read_due_at);

CREATE TABLE IF NOT EXISTS forum_topic_mandatory_reads (
  id int unsigned NOT NULL AUTO_INCREMENT,
  tenant_id int unsigned NOT NULL,
  topic_id int unsigned NOT NULL,
  user_id int unsigned NOT NULL,
  status enum('unseen','seen','acknowledged','overdue') NOT NULL DEFAULT 'unseen',
  seen_at datetime DEFAULT NULL,
  acknowledged_at datetime DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_forum_topic_mandatory_reads_target (tenant_id, topic_id, user_id),
  KEY idx_forum_topic_mandatory_reads_status (tenant_id, status),
  KEY idx_forum_topic_mandatory_reads_user (tenant_id, user_id),
  CONSTRAINT fk_forum_topic_mandatory_reads_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_topic_mandatory_reads_topic FOREIGN KEY (topic_id) REFERENCES forum_topics (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_topic_mandatory_reads_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
