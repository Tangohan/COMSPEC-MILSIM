<?php

declare(strict_types=1);

/**
 * SSE — Laboratoire numérique (ATH-SSE-LABNUM).
 * Idempotent — appelée depuis run-migrations.php et ensureSchema().
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $name) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$name]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('sse_digital_devices')) {
        $pdo->exec("CREATE TABLE sse_digital_devices (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            reference_code VARCHAR(40) NOT NULL,
            device_type VARCHAR(40) NOT NULL DEFAULT 'telephone',
            manufacturer VARCHAR(120) DEFAULT NULL,
            model VARCHAR(160) DEFAULT NULL,
            serial_number VARCHAR(120) DEFAULT NULL,
            color VARCHAR(64) DEFAULT NULL,
            capacity_label VARCHAR(64) DEFAULT NULL,
            apparent_condition VARCHAR(80) DEFAULT NULL,
            lock_state VARCHAR(40) DEFAULT NULL,
            has_sim TINYINT(1) NOT NULL DEFAULT 0,
            has_memory_card TINYINT(1) NOT NULL DEFAULT 0,
            has_battery TINYINT(1) NOT NULL DEFAULT 1,
            discovery_place VARCHAR(200) DEFAULT NULL,
            person_id INT UNSIGNED DEFAULT NULL,
            site_id INT UNSIGNED DEFAULT NULL,
            interest_case_id INT UNSIGNED DEFAULT NULL,
            mission_label VARCHAR(160) DEFAULT NULL,
            seized_by_label VARCHAR(160) DEFAULT NULL,
            power_state VARCHAR(40) DEFAULT NULL,
            locked TINYINT(1) NOT NULL DEFAULT 0,
            airplane_mode TINYINT(1) NOT NULL DEFAULT 0,
            network_connected TINYINT(1) NOT NULL DEFAULT 0,
            encryption_detected TINYINT(1) NOT NULL DEFAULT 0,
            presumed_os VARCHAR(80) DEFAULT NULL,
            displayed_time VARCHAR(40) DEFAULT NULL,
            language_label VARCHAR(64) DEFAULT NULL,
            damage_notes TEXT NULL,
            accessories_notes TEXT NULL,
            discovered_at DATETIME DEFAULT NULL,
            seized_at DATETIME DEFAULT NULL,
            packaging_notes TEXT NULL,
            observations TEXT NULL,
            data_profile VARCHAR(80) DEFAULT NULL,
            arma_object_id VARCHAR(80) DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'discovered',
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sse_dig_dev_ref (tenant_id, reference_code),
            KEY idx_sse_dig_dev_queue (tenant_id, status, updated_at),
            KEY idx_sse_dig_dev_case (tenant_id, case_id),
            KEY idx_sse_dig_dev_type (tenant_id, device_type),
            CONSTRAINT fk_sse_dig_dev_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_device_components')) {
        $pdo->exec("CREATE TABLE sse_digital_device_components (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            component_type VARCHAR(40) NOT NULL,
            label VARCHAR(160) NOT NULL,
            serial_or_imsi VARCHAR(120) DEFAULT NULL,
            notes TEXT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_comp_dev (tenant_id, device_id),
            CONSTRAINT fk_sse_dig_comp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_sse_dig_comp_dev FOREIGN KEY (device_id) REFERENCES sse_digital_devices (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_seizures')) {
        $pdo->exec("CREATE TABLE sse_digital_seizures (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            reference_code VARCHAR(40) NOT NULL,
            seal_label VARCHAR(120) DEFAULT NULL,
            packaging TEXT NULL,
            photo_notes TEXT NULL,
            handlers_json MEDIUMTEXT NULL,
            discovered_at DATETIME DEFAULT NULL,
            seized_at DATETIME DEFAULT NULL,
            transmitted_at DATETIME DEFAULT NULL,
            received_lab_at DATETIME DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'seized',
            observations TEXT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sse_dig_seiz_ref (tenant_id, reference_code),
            KEY idx_sse_dig_seiz_dev (tenant_id, device_id),
            CONSTRAINT fk_sse_dig_seiz_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_sse_dig_seiz_dev FOREIGN KEY (device_id) REFERENCES sse_digital_devices (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_acquisitions')) {
        $pdo->exec("CREATE TABLE sse_digital_acquisitions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            seizure_id INT UNSIGNED DEFAULT NULL,
            reference_code VARCHAR(40) NOT NULL,
            method VARCHAR(40) NOT NULL DEFAULT 'logical',
            operator_label VARCHAR(160) DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            ended_at DATETIME DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'planned',
            volume_bytes BIGINT UNSIGNED DEFAULT NULL,
            file_count INT UNSIGNED NOT NULL DEFAULT 0,
            artifact_count INT UNSIGNED NOT NULL DEFAULT 0,
            integrity_algo VARCHAR(32) DEFAULT 'SHA-256',
            integrity_hash VARCHAR(128) DEFAULT NULL,
            tool_name VARCHAR(120) DEFAULT NULL,
            tool_version VARCHAR(64) DEFAULT NULL,
            is_partial TINYINT(1) NOT NULL DEFAULT 0,
            reserves TEXT NULL,
            errors_text TEXT NULL,
            data_profile VARCHAR(80) DEFAULT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sse_dig_acq_ref (tenant_id, reference_code),
            KEY idx_sse_dig_acq_dev (tenant_id, device_id, status),
            CONSTRAINT fk_sse_dig_acq_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_sse_dig_acq_dev FOREIGN KEY (device_id) REFERENCES sse_digital_devices (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_acquisition_logs')) {
        $pdo->exec("CREATE TABLE sse_digital_acquisition_logs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            acquisition_id INT UNSIGNED NOT NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_acqlog (tenant_id, acquisition_id, logged_at),
            CONSTRAINT fk_sse_dig_acqlog_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_sse_dig_acqlog_acq FOREIGN KEY (acquisition_id) REFERENCES sse_digital_acquisitions (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_integrity_checks')) {
        $pdo->exec("CREATE TABLE sse_digital_integrity_checks (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            acquisition_id INT UNSIGNED NOT NULL,
            algo VARCHAR(32) NOT NULL DEFAULT 'SHA-256',
            hash_value VARCHAR(128) NOT NULL,
            result_status VARCHAR(24) NOT NULL DEFAULT 'ok',
            notes TEXT NULL,
            checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_integ (tenant_id, acquisition_id),
            CONSTRAINT fk_sse_dig_integ_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_sse_dig_integ_acq FOREIGN KEY (acquisition_id) REFERENCES sse_digital_acquisitions (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_artifacts')) {
        $pdo->exec("CREATE TABLE sse_digital_artifacts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            acquisition_id INT UNSIGNED NOT NULL,
            reference_code VARCHAR(40) DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            path VARCHAR(500) DEFAULT NULL,
            category VARCHAR(40) NOT NULL DEFAULT 'document',
            mime_label VARCHAR(80) DEFAULT NULL,
            size_bytes BIGINT UNSIGNED DEFAULT NULL,
            created_at_device DATETIME DEFAULT NULL,
            modified_at_device DATETIME DEFAULT NULL,
            accessed_at_device DATETIME DEFAULT NULL,
            presumed_author VARCHAR(160) DEFAULT NULL,
            account_label VARCHAR(160) DEFAULT NULL,
            source_app VARCHAR(120) DEFAULT NULL,
            geo_lat DECIMAL(10,7) DEFAULT NULL,
            geo_lng DECIMAL(10,7) DEFAULT NULL,
            detected_persons TEXT NULL,
            associated_identifiers TEXT NULL,
            analyst_comment TEXT NULL,
            interest_level VARCHAR(24) NOT NULL DEFAULT 'courant',
            status VARCHAR(40) NOT NULL DEFAULT 'unexamined',
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            is_hidden TINYINT(1) NOT NULL DEFAULT 0,
            is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
            is_favorite TINYINT(1) NOT NULL DEFAULT 0,
            payload_json MEDIUMTEXT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_sse_dig_art_acq (tenant_id, acquisition_id, category),
            KEY idx_sse_dig_art_dev (tenant_id, device_id, status),
            KEY idx_sse_dig_art_interest (tenant_id, interest_level, status),
            CONSTRAINT fk_sse_dig_art_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_sse_dig_art_dev FOREIGN KEY (device_id) REFERENCES sse_digital_devices (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_sse_dig_art_acq FOREIGN KEY (acquisition_id) REFERENCES sse_digital_acquisitions (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_contacts')) {
        $pdo->exec("CREATE TABLE sse_digital_contacts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            acquisition_id INT UNSIGNED NOT NULL,
            display_name VARCHAR(160) NOT NULL,
            phone_number VARCHAR(64) DEFAULT NULL,
            email VARCHAR(160) DEFAULT NULL,
            alias_label VARCHAR(160) DEFAULT NULL,
            notes TEXT NULL,
            linked_person_id INT UNSIGNED DEFAULT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_contact (tenant_id, device_id),
            CONSTRAINT fk_sse_dig_contact_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_messages')) {
        $pdo->exec("CREATE TABLE sse_digital_messages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            acquisition_id INT UNSIGNED NOT NULL,
            thread_key VARCHAR(80) DEFAULT NULL,
            direction VARCHAR(16) DEFAULT 'inbound',
            sender_label VARCHAR(160) DEFAULT NULL,
            recipient_label VARCHAR(160) DEFAULT NULL,
            body TEXT NULL,
            sent_at DATETIME DEFAULT NULL,
            app_label VARCHAR(80) DEFAULT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            has_attachment TINYINT(1) NOT NULL DEFAULT 0,
            linked_person_id INT UNSIGNED DEFAULT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_msg (tenant_id, device_id, sent_at),
            KEY idx_sse_dig_msg_thread (tenant_id, thread_key),
            CONSTRAINT fk_sse_dig_msg_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_calls')) {
        $pdo->exec("CREATE TABLE sse_digital_calls (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            acquisition_id INT UNSIGNED NOT NULL,
            direction VARCHAR(16) DEFAULT 'inbound',
            peer_label VARCHAR(160) DEFAULT NULL,
            peer_number VARCHAR(64) DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            duration_sec INT UNSIGNED DEFAULT 0,
            linked_person_id INT UNSIGNED DEFAULT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_call (tenant_id, device_id, started_at),
            CONSTRAINT fk_sse_dig_call_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_accounts')) {
        $pdo->exec("CREATE TABLE sse_digital_accounts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            acquisition_id INT UNSIGNED NOT NULL,
            service_label VARCHAR(120) NOT NULL,
            username VARCHAR(160) DEFAULT NULL,
            email VARCHAR(160) DEFAULT NULL,
            notes TEXT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_acct (tenant_id, device_id),
            CONSTRAINT fk_sse_dig_acct_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_locations')) {
        $pdo->exec("CREATE TABLE sse_digital_locations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            acquisition_id INT UNSIGNED NOT NULL,
            label VARCHAR(160) DEFAULT NULL,
            lat DECIMAL(10,7) DEFAULT NULL,
            lng DECIMAL(10,7) DEFAULT NULL,
            observed_at DATETIME DEFAULT NULL,
            source_label VARCHAR(80) DEFAULT NULL,
            linked_site_id INT UNSIGNED DEFAULT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_loc (tenant_id, device_id, observed_at),
            CONSTRAINT fk_sse_dig_loc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_networks')) {
        $pdo->exec("CREATE TABLE sse_digital_networks (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            acquisition_id INT UNSIGNED NOT NULL,
            network_type VARCHAR(24) NOT NULL DEFAULT 'wifi',
            ssid_or_name VARCHAR(160) NOT NULL,
            observed_at DATETIME DEFAULT NULL,
            notes TEXT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_net (tenant_id, device_id),
            CONSTRAINT fk_sse_dig_net_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_applications')) {
        $pdo->exec("CREATE TABLE sse_digital_applications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            acquisition_id INT UNSIGNED NOT NULL,
            app_name VARCHAR(160) NOT NULL,
            package_or_path VARCHAR(255) DEFAULT NULL,
            version_label VARCHAR(64) DEFAULT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_app (tenant_id, device_id),
            CONSTRAINT fk_sse_dig_app_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_media')) {
        $pdo->exec("CREATE TABLE sse_digital_media (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            acquisition_id INT UNSIGNED NOT NULL,
            artifact_id INT UNSIGNED DEFAULT NULL,
            media_type VARCHAR(24) NOT NULL DEFAULT 'image',
            name VARCHAR(255) NOT NULL,
            captured_at DATETIME DEFAULT NULL,
            geo_lat DECIMAL(10,7) DEFAULT NULL,
            geo_lng DECIMAL(10,7) DEFAULT NULL,
            integrity_hash VARCHAR(128) DEFAULT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_media (tenant_id, device_id),
            CONSTRAINT fk_sse_dig_media_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_timelines')) {
        $pdo->exec("CREATE TABLE sse_digital_timelines (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED DEFAULT NULL,
            acquisition_id INT UNSIGNED DEFAULT NULL,
            artifact_id INT UNSIGNED DEFAULT NULL,
            event_type VARCHAR(40) NOT NULL,
            event_at DATETIME NOT NULL,
            title VARCHAR(255) NOT NULL,
            detail TEXT NULL,
            interest_level VARCHAR(24) NOT NULL DEFAULT 'courant',
            is_validated TINYINT(1) NOT NULL DEFAULT 0,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_tl (tenant_id, event_at),
            KEY idx_sse_dig_tl_dev (tenant_id, device_id, event_at),
            CONSTRAINT fk_sse_dig_tl_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_findings')) {
        $pdo->exec("CREATE TABLE sse_digital_findings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED DEFAULT NULL,
            acquisition_id INT UNSIGNED DEFAULT NULL,
            artifact_id INT UNSIGNED DEFAULT NULL,
            finding_type VARCHAR(40) NOT NULL,
            title VARCHAR(255) NOT NULL,
            detail TEXT NULL,
            confidence_level VARCHAR(24) NOT NULL DEFAULT 'modere',
            score_pct TINYINT UNSIGNED DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'to_review',
            factors_json MEDIUMTEXT NULL,
            proposed_relation_json MEDIUMTEXT NULL,
            reviewed_by INT UNSIGNED DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            review_comment TEXT NULL,
            algorithm_version VARCHAR(32) NOT NULL DEFAULT 'labnum-1.0',
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_find (tenant_id, status, created_at),
            KEY idx_sse_dig_find_dev (tenant_id, device_id),
            CONSTRAINT fk_sse_dig_find_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_exports')) {
        $pdo->exec("CREATE TABLE sse_digital_exports (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED DEFAULT NULL,
            acquisition_id INT UNSIGNED DEFAULT NULL,
            export_type VARCHAR(40) NOT NULL,
            title VARCHAR(255) NOT NULL,
            reason TEXT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_exp (tenant_id, created_at),
            CONSTRAINT fk_sse_dig_exp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sse_digital_files')) {
        $pdo->exec("CREATE TABLE sse_digital_files (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED NOT NULL,
            acquisition_id INT UNSIGNED NOT NULL,
            parent_path VARCHAR(500) DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            is_directory TINYINT(1) NOT NULL DEFAULT 0,
            size_bytes BIGINT UNSIGNED DEFAULT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            is_hidden TINYINT(1) NOT NULL DEFAULT 0,
            is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
            artifact_id INT UNSIGNED DEFAULT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_dig_files (tenant_id, acquisition_id, parent_path(191)),
            CONSTRAINT fk_sse_dig_files_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
