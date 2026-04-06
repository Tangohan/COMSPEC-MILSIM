-- Ressources de leçon LMS : lien vers la bibliothèque documentaire
ALTER TABLE training_resources
  ADD COLUMN library_document_id INT UNSIGNED NULL DEFAULT NULL AFTER file_size,
  ADD KEY idx_training_resources_library_document (library_document_id),
  ADD CONSTRAINT fk_training_resources_library_document
    FOREIGN KEY (library_document_id) REFERENCES documents (id) ON DELETE SET NULL ON UPDATE CASCADE;
