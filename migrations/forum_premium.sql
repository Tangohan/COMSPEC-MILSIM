-- Forum premium : réactions métier par message, badge de publication
ALTER TABLE forum_posts ADD COLUMN publication_badge VARCHAR(32) NULL DEFAULT NULL AFTER body;

CREATE TABLE IF NOT EXISTS forum_post_reactions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  post_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  reaction_key VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_post_user (post_id, user_id),
  KEY idx_tenant_post (tenant_id, post_id),
  CONSTRAINT forum_post_reactions_post_fk FOREIGN KEY (post_id) REFERENCES forum_posts (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT forum_post_reactions_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT forum_post_reactions_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
