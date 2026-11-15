-- Poste TOC web, panneau Rapports : « lu / non lu » par opérateur du poste. Une ligne = un rapport (compte rendu « tr » ou
-- 9-line « nl ») lu par un membre. Aucune donnée n'est ajoutée ni modifiée dans les rapports eux-mêmes.
-- Sans cette table, le panneau fonctionne : tout y est « non lu » et rien n'est conservé (message dans le panneau).
-- Retour arrière : migrations/rollback/atak_toc_report_reads_down.sql.

CREATE TABLE IF NOT EXISTS atak_toc_report_reads (
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  report_kind VARCHAR(2) NOT NULL COMMENT 'tr = compte rendu tactique, nl = 9-line',
  report_id INT UNSIGNED NOT NULL,
  read_at DATETIME NOT NULL,
  PRIMARY KEY (tenant_id, user_id, report_kind, report_id),
  KEY idx_toc_report_reads_report (tenant_id, report_kind, report_id),
  CONSTRAINT fk_toc_report_reads_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_toc_report_reads_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
