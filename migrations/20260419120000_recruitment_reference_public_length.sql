-- Références d’offres plus longues (style avis / appel à candidatures).
ALTER TABLE recruitment_openings
    MODIFY COLUMN reference_public VARCHAR(280) DEFAULT NULL;
