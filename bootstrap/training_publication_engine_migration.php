<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_document_publications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            document_id BIGINT UNSIGNED NULL,
            courrier_template_id BIGINT UNSIGNED NULL,
            status ENUM('draft','review','validated','published','archived') NOT NULL DEFAULT 'draft',
            cover_asset_id BIGINT UNSIGNED NULL,
            overlay_payload_json JSON NULL,
            watermark_payload_json JSON NULL,
            qr_payload_json JSON NULL,
            security_level VARCHAR(32) NOT NULL DEFAULT 'interne',
            version_label VARCHAR(64) NOT NULL DEFAULT 'v1',
            hash_integrity CHAR(64) NULL,
            is_revoked TINYINT(1) NOT NULL DEFAULT 0,
            access_policy_json JSON NULL,
            validation_chain_json JSON NULL,
            publication_targets_json JSON NULL,
            format_payload_json JSON NULL,
            institutional_signature_json JSON NULL,
            diffusion_classification VARCHAR(32) NOT NULL DEFAULT 'interne',
            expires_at DATETIME NULL,
            obsolete_at DATETIME NULL,
            replacement_publication_id BIGINT UNSIGNED NULL,
            compliance_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            published_at DATETIME NULL,
            archived_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_training_doc_pub_tenant_course (tenant_id, course_id),
            KEY idx_training_doc_pub_document_tenant (document_id, tenant_id),
            KEY idx_training_doc_pub_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_document_publication_revisions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            publication_id BIGINT UNSIGNED NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            revision_number INT UNSIGNED NOT NULL,
            change_summary VARCHAR(255) NULL,
            diff_payload_json JSON NULL,
            pdf_snapshot_path VARCHAR(255) NULL,
            compiled_payload_json JSON NULL,
            qr_hash CHAR(64) NULL,
            watermark_hash CHAR(64) NULL,
            integrity_check_passed TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            UNIQUE KEY uq_training_pub_revision (publication_id, revision_number),
            KEY idx_training_pub_revision_tenant (tenant_id, publication_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_document_read_receipts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            publication_id BIGINT UNSIGNED NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            opened_at DATETIME NULL,
            cumulative_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            last_page_reached INT UNSIGNED NOT NULL DEFAULT 0,
            acknowledged_at DATETIME NULL,
            quiz_score SMALLINT UNSIGNED NULL,
            attestation_text VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_training_read_receipt (publication_id, tenant_id, user_id),
            KEY idx_training_read_receipt_tenant_pub (tenant_id, publication_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_document_annexes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            publication_id BIGINT UNSIGNED NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            annex_type VARCHAR(64) NOT NULL,
            title VARCHAR(190) NOT NULL,
            content_json JSON NULL,
            sensitivity ENUM('publique','interne','sensible') NOT NULL DEFAULT 'interne',
            is_publishable TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_training_annex_pub_tenant (publication_id, tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_document_evidence_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            publication_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(128) NOT NULL,
            payload_json JSON NULL,
            created_at DATETIME NOT NULL,
            KEY idx_training_evidence_tenant_pub (tenant_id, publication_id),
            KEY idx_training_evidence_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_document_collab_comments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            publication_id BIGINT UNSIGNED NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            target_ref VARCHAR(128) NOT NULL,
            suggestion_text TEXT NOT NULL,
            resolution_status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_training_collab_pub_tenant (publication_id, tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );


    // Permission RBAC + attribution admin pour le back-office publications.
    $tenants = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $permSel = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $permIns = $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, scope, created_at) VALUES (?, ?, ?, 'training', 'community', NOW())");
    $roleSel = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');

    foreach ($tenants as $tenant) {
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            continue;
        }

        $permSel->execute([$tenantId, 'training.publications.manage']);
        $permId = (int) ($permSel->fetchColumn() ?: 0);
        if ($permId <= 0) {
            $permIns->execute([$tenantId, 'Gérer les publications de formation', 'training.publications.manage']);
            $permId = (int) $pdo->lastInsertId();
        }

        foreach (['tenant_admin', 'community_owner'] as $roleSlug) {
            $roleSel->execute([$tenantId, $roleSlug]);
            $roleId = (int) ($roleSel->fetchColumn() ?: 0);
            if ($roleId > 0 && $permId > 0) {
                $link->execute([$roleId, $permId]);
            }
        }
    }

};
