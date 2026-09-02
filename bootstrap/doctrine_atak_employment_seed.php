<?php

declare(strict_types=1);

/**
 * Doctrine d'emploi ATAK / Overwatch Athena — seed idempotent par tenant.
 */
return function (PDO $pdo): void {
    if (!doctrineAtakTableExists($pdo, 'document_doctrines')) {
        return;
    }

    echo "Doctrine ATAK : seed emploi Overwatch…\n";

    $tenants = $pdo->query("SELECT id FROM tenants WHERE slug != 'default'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tenants as $tid) {
        $tid = (int) $tid;
        if ($tid < 1) {
            continue;
        }
        seedAtakEmploymentDoctrine($pdo, $tid);
    }
};

function doctrineAtakTableExists(PDO $pdo, string $table): bool
{
    try {
        $pdo->query('SELECT 1 FROM `' . str_replace('`', '', $table) . '` LIMIT 1');

        return true;
    } catch (\Throwable) {
        return false;
    }
}

function seedAtakEmploymentDoctrine(PDO $pdo, int $tenantId): void
{
    $referenceCode = 'SIC/ATAK/2026-001';
    $slug = 'sic-atak-2026-001';

    $exists = $pdo->prepare(
        'SELECT dd.id FROM document_doctrines dd
         INNER JOIN documents d ON d.id = dd.document_id
         WHERE dd.tenant_id = ? AND (dd.reference_code = ? OR d.slug = ?)
         LIMIT 1'
    );
    $exists->execute([$tenantId, $referenceCode, $slug]);
    if ((int) ($exists->fetchColumn() ?: 0) > 0) {
        return;
    }

    $cat = $pdo->prepare('SELECT id FROM document_categories WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $cat->execute([$tenantId, 'doctrine']);
    $categoryId = (int) ($cat->fetchColumn() ?: 0);
    if ($categoryId < 1) {
        return;
    }

    $admin = $pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1");
    $admin->execute([$tenantId]);
    $userId = (int) ($admin->fetchColumn() ?: 0);

    $title = 'Doctrine d’emploi d’ATAK / Overwatch Athena';
    $summary = <<<'TXT'
Fixe les règles d’emploi du terminal tactique Overwatch (mod Arma) et du poste de commandement web Athena : prérequis de liaison, emploi carte/marqueurs/ordres/MEDEVAC en mission, responsabilités opérateur et SIC, OPSEC et maintien des compétences. Prise en compte obligatoire pour tous les membres autorisés à l’OP numérique.
TXT;

    $insDoc = $pdo->prepare(
        'INSERT INTO documents (
            tenant_id, scope, title, slug, description, document_category_id,
            status, visibility_scope, classification_level, created_by, created_at, updated_at
         ) VALUES (?, \'tenant\', ?, ?, ?, ?, \'published\', \'organization\', \'interne\', ?, NOW(), NOW())'
    );
    try {
        $insDoc->execute([$tenantId, $title, $slug, $summary, $categoryId, $userId > 0 ? $userId : null]);
    } catch (\Throwable) {
        return;
    }
    $docId = (int) $pdo->lastInsertId();
    if ($docId < 1) {
        return;
    }

    $filePath = 'doctrine/sic-atak-2026-001.md';
    $checksum = is_file(dirname(__DIR__) . '/storage/documents/' . $filePath)
        ? hash_file('sha256', dirname(__DIR__) . '/storage/documents/' . $filePath)
        : hash('sha256', $referenceCode . 'v1.0');

    $insVer = $pdo->prepare(
        'INSERT INTO document_versions (
            document_id, version_number, version_major, version_minor, version_label,
            file_path, checksum, mime_type, is_current, published_at, change_summary, created_at
         ) VALUES (?, 1, 1, 0, ?, ?, ?, ?, 1, NOW(), ?, NOW())'
    );
    $insVer->execute([
        $docId,
        'v1.0',
        $filePath,
        $checksum,
        'text/markdown',
        'Publication initiale — doctrine d’emploi ATAK / Overwatch Athena.',
    ]);

    $domainRow = $pdo->prepare('SELECT id FROM document_reference_domains WHERE tenant_id = ? AND doc_prefix = ? LIMIT 1');
    $domainRow->execute([$tenantId, 'SIC']);
    $domainId = (int) ($domainRow->fetchColumn() ?: 0);

    $deadline = date('Y-m-d H:i:s', strtotime('+14 days'));

    $insDoctrine = $pdo->prepare(
        'INSERT INTO document_doctrines (
            document_id, tenant_id, scope, reference_code, service_prefix, domain_id, domain_code,
            seq_year, seq_number, summary, doctrine_status, requirement_level,
            issuing_label, effective_at, acknowledgment_required, acknowledgment_deadline_at,
            reading_required, include_future_members, published_at
         ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,1,NOW())'
    );
    $insDoctrine->execute([
        $docId,
        $tenantId,
        'tenant',
        $referenceCode,
        'SIC',
        $domainId ?: null,
        'ATAK',
        2026,
        1,
        $summary,
        'published',
        'mandatory',
        'Bureau SIC — Systèmes d’information et commandement',
        1,
        $deadline,
        1,
    ]);

    $aud = $pdo->prepare(
        'INSERT INTO document_audiences (document_id, tenant_id, audience_type, audience_value, include_children)
         VALUES (?, ?, \'all_members\', \'1\', 0)'
    );
    try {
        $aud->execute([$docId, $tenantId]);
    } catch (\Throwable) {
    }

    $seq = $pdo->prepare(
        'INSERT INTO document_reference_sequences (tenant_id, service_prefix, domain_code, year, last_number)
         VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, 1)'
    );
    try {
        $seq->execute([$tenantId, 'SIC', 'ATAK', 2026]);
    } catch (\Throwable) {
    }
}
