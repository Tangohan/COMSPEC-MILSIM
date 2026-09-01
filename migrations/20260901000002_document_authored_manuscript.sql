-- Manuels rédigés dans Athena (page de garde + signatures), sans fichier obligatoire.
-- Idempotent via bootstrap/core_schema_extensions_migration.php (colonnes ajoutées si absentes).

ALTER TABLE documents
  ADD COLUMN origin VARCHAR(20) NOT NULL DEFAULT 'upload' AFTER status;

ALTER TABLE documents
  ADD COLUMN authored_json LONGTEXT NULL AFTER origin;
