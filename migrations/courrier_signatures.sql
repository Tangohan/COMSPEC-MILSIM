-- Signatures utilisateur et données de signature sur documents courrier
-- Exécution incrémentale via run-migrations.php

SET NAMES utf8mb4;

-- Signatures enregistrées par l'utilisateur (réutilisables)
CREATE TABLE IF NOT EXISTS user_signatures (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL DEFAULT 'Signature principale',
  file_path VARCHAR(500) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY user_id (user_id),
  KEY tenant_id (tenant_id),
  CONSTRAINT user_signatures_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT user_signatures_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extension courrier_documents : signed_at, content_hash, signature_data_json
-- (signed_at peut exister déjà selon schéma)
-- Exécution conditionnelle en PHP pour chaque colonne
