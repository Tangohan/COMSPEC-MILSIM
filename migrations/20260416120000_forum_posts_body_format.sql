-- Rendu forum : messages générés par l’application (ex. avis de recrutement) en HTML de confiance.
ALTER TABLE forum_posts
  ADD COLUMN body_format VARCHAR(20) NOT NULL DEFAULT 'markdown' COMMENT 'markdown|html' AFTER body;

UPDATE forum_posts
SET body_format = 'html'
WHERE body LIKE '%rofa-wrap%';
