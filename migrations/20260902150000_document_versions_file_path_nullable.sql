-- Pièce jointe retirable : un document sans fichier est un état légitime (NULL).
ALTER TABLE document_versions
  MODIFY file_path VARCHAR(500) NULL;
