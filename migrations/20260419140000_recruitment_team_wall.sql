-- Fil de discussion interne entre recruteurs (par communauté).
CREATE TABLE IF NOT EXISTS recruitment_team_wall_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    actor_user_id INT UNSIGNED NOT NULL,
    body VARCHAR(4000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recruitment_team_wall_tenant_created (tenant_id, created_at),
    CONSTRAINT rtw_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
