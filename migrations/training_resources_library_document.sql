-- Ressources de leçon LMS : document du centre (document_id) + type library_document
ALTER TABLE training_resources
  MODIFY COLUMN resource_type ENUM('pdf','image','video','audio','zip','attachment','link','library_document') NOT NULL;

-- Si la colonne s’appelait encore library_document_id, exécuter plutôt le bootstrap PHP (run-migrations).
