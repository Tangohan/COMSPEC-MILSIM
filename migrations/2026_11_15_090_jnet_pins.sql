-- JNET : éléments épinglés de l'espace commun (annonces et ordres du jour, renseignements, cibles, unités, pages). Un
-- épinglage ne donne jamais de droit de plus : chaque élément est revérifié à l'affichage avec les droits du lecteur, et
-- reste « Élément masqué » (sans titre) s'il ne le voit pas. Plafond de 25 épingles par communauté, posé par le serveur.
-- Retour arrière : migrations/rollback/jnet_pins_down.sql.

CREATE TABLE IF NOT EXISTS jnet_pins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  scope VARCHAR(12) NOT NULL DEFAULT 'commun' COMMENT 'espace qui porte l''épingle (aujourd''hui : commun)',
  target_type VARCHAR(14) NOT NULL COMMENT 'echange|renseignement|cible|unite|page',
  target_id VARCHAR(40) NOT NULL,
  `position` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'rang d''affichage, du plus haut au plus bas',
  pinned_by INT UNSIGNED NOT NULL,
  pinned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jnet_pins_target (tenant_id, scope, target_type, target_id),
  KEY idx_jnet_pins_order (tenant_id, scope, `position`),
  CONSTRAINT fk_jnet_pins_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
