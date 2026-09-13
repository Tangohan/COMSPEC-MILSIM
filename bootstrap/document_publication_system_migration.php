<?php

declare(strict_types=1);

/**
 * Publication interne : types de documents, contenu riche, visibilité, relances.
 */
return function (PDO $pdo): void {
    require_once dirname(__DIR__) . '/bootstrap/schema_ensure_column.php';

    $sqlPath = dirname(__DIR__) . '/migrations/20260913140000_document_publication_system.sql';
    if (is_file($sqlPath)) {
        echo "Publication documentaire : tables...\n";
        @flush();
        $sql = file_get_contents($sqlPath);
        if ($sql !== false && $sql !== '') {
            $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
            $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
            foreach ($chunks as $stmtSql) {
                $stmtSql = trim($stmtSql);
                if ($stmtSql === '') {
                    continue;
                }
                $full = $stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';');
                try {
                    $pdo->exec($full);
                } catch (PDOException $e) {
                    $driverCode = (int) ($e->errorInfo[1] ?? 0);
                    $msg = $e->getMessage();
                    $ignorable = in_array($driverCode, [1005, 1007, 1022, 1050, 1060, 1061, 1091, 1826], true)
                        || preg_match('/Duplicate (column|key|foreign key|entry)/i', $msg)
                        || (str_contains($msg, 'already exists') && !str_contains($msg, 'Failed'));
                    if (!$ignorable) {
                        echo '  [ATTENTION] Publication documentaire SQL : ' . $msg . "\n";
                    }
                }
            }
        }
    }

    // Métadonnées de publication sur document_doctrines
    $doctrineCols = [
        'document_type_id' => '`document_type_id` INT UNSIGNED NULL AFTER `document_id`',
        'body_html' => '`body_html` LONGTEXT NULL AFTER `summary`',
        'confirmation_text' => '`confirmation_text` TEXT NULL AFTER `body_html`',
        'visibility_mode' => "`visibility_mode` VARCHAR(32) NOT NULL DEFAULT 'library' AFTER `confirmation_text`",
        'is_permanent' => '`is_permanent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `visibility_mode`',
        'require_validation' => '`require_validation` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_permanent`',
        'reminder_on_publish' => '`reminder_on_publish` TINYINT(1) NOT NULL DEFAULT 1 AFTER `reminder_on_publish`',
        'scope_of_application' => '`scope_of_application` TEXT NULL AFTER `keywords_json`',
        'published_by_user_id' => '`published_by_user_id` INT UNSIGNED NULL AFTER `published_at`',
    ];

    // Fix reminder_on_publish definition (can't AFTER itself)
    $doctrineCols['reminder_on_publish'] = '`reminder_on_publish` TINYINT(1) NOT NULL DEFAULT 1 AFTER `require_validation`';

    foreach ($doctrineCols as $col => $def) {
        try {
            schema_ensure_column($pdo, 'document_doctrines', $col, $def);
        } catch (Throwable $e) {
            echo '  [ATTENTION] document_doctrines.' . $col . ' : ' . $e->getMessage() . "\n";
        }
    }

    // Contenu riche versionné
    try {
        schema_ensure_column(
            $pdo,
            'document_versions',
            'body_html',
            '`body_html` LONGTEXT NULL AFTER `change_summary`'
        );
    } catch (Throwable $e) {
        echo '  [ATTENTION] document_versions.body_html : ' . $e->getMessage() . "\n";
    }

    try {
        schema_ensure_column(
            $pdo,
            'document_versions',
            'published_at',
            '`published_at` DATETIME NULL AFTER `is_current`'
        );
    } catch (Throwable $e) {
        // may already exist from doctrine migration
    }

    // Index FK soft (sans contrainte stricte pour tolérer tenants legacy)
    try {
        $pdo->exec('CREATE INDEX idx_doc_doctrine_type ON document_doctrines (document_type_id)');
    } catch (PDOException $e) {
        // ignore duplicate
    }

    seedDocumentPublicationCatalog($pdo);
};

/**
 * @param PDO $pdo
 */
function seedDocumentPublicationCatalog(PDO $pdo): void
{
    $tenantIds = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN);
    if ($tenantIds === false || $tenantIds === []) {
        return;
    }

    $defaultTypes = [
        ['instruction', 'Instruction', 'Document d’instruction opérationnelle ou administrative.', 'INS', '#1d4ed8', 1, 1, 1, 10],
        ['note_de_service', 'Note de service', 'Note de service interne à l’organisation.', 'NS', '#0f766e', 1, 1, 0, 20],
        ['directive', 'Directive', 'Directive émise par une autorité.', 'DIR', '#7c3aed', 1, 1, 1, 30],
        ['consigne', 'Consigne', 'Consigne ponctuelle ou durable.', 'CSG', '#b45309', 1, 0, 0, 40],
        ['procedure', 'Procédure', 'Procédure interne applicable.', 'PROC', '#475569', 0, 0, 0, 50],
        ['information', 'Information', 'Document à simple information.', 'INFO', '#64748b', 0, 0, 0, 60],
        ['communication', 'Communication', 'Communication importante.', 'COM', '#db2777', 0, 0, 0, 70],
        ['ordre_permanent', 'Ordre permanent', 'Ordre permanent applicable jusqu’à remplacement.', 'OP', '#dc2626', 1, 1, 1, 80],
    ];

    $defaultReminders = [
        ['on_publish', 'Notification à la publication', 'on_publish', null],
        ['before_deadline', 'Rappel avant échéance', 'before_deadline', 48],
        ['after_overdue', 'Rappel après dépassement', 'after_overdue', 24],
    ];

    $insType = $pdo->prepare(
        'INSERT INTO document_types (
            tenant_id, code, label, description, color, code_prefix,
            default_reading_required, default_acknowledgment_required, default_require_validation,
            numbering_pattern, sort_order, is_active
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            label = VALUES(label),
            description = VALUES(description),
            color = VALUES(color),
            code_prefix = VALUES(code_prefix),
            default_reading_required = VALUES(default_reading_required),
            default_acknowledgment_required = VALUES(default_acknowledgment_required),
            default_require_validation = VALUES(default_require_validation),
            numbering_pattern = VALUES(numbering_pattern),
            sort_order = VALUES(sort_order),
            updated_at = NOW()'
    );

    $insReminder = $pdo->prepare(
        'INSERT INTO document_reminder_rules (tenant_id, code, label, trigger_event, offset_hours, is_active)
         VALUES (?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            label = VALUES(label),
            trigger_event = VALUES(trigger_event),
            offset_hours = VALUES(offset_hours),
            is_active = 1'
    );

    foreach ($tenantIds as $tid) {
        $tid = (int) $tid;
        foreach ($defaultTypes as $t) {
            $insType->execute([
                $tid,
                $t[0],
                $t[1],
                $t[2],
                $t[4],
                $t[3],
                $t[5],
                $t[6],
                $t[7],
                '{PREFIX}-{YEAR}-{SEQ}',
                $t[8],
            ]);
        }
        foreach ($defaultReminders as $r) {
            $insReminder->execute([$tid, $r[0], $r[1], $r[2], $r[3]]);
        }
    }

    echo "  Types documentaires et règles de relance initialisés pour " . count($tenantIds) . " communauté(s).\n";
}
