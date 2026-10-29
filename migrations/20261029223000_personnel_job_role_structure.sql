-- Optional descriptive hierarchy and references, separate from assignments and generated ORBAT origins.
-- Additive and replayable: no existing job, category, role, holder or assignment is changed.
CREATE TABLE IF NOT EXISTS personnel_job_role_structure (
    tenant_id INT UNSIGNED NOT NULL,
    job_role_id INT UNSIGNED NOT NULL,
    parent_job_role_id INT UNSIGNED NULL,
    linked_unit_id INT UNSIGNED NULL,
    grade_id INT UNSIGNED NULL,
    specialty_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL DEFAULT '',
    kind VARCHAR(16) NOT NULL DEFAULT 'employment',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id, job_role_id),
    KEY idx_pjrs_parent (tenant_id, parent_job_role_id),
    KEY idx_pjrs_unit (tenant_id, linked_unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
