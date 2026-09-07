<?php

declare(strict_types=1);

/**
 * Doctrine d'emploi ATAK / Overwatch Athena — seed idempotent par tenant.
 * Fichier officiel : PDF v1.1 (remplace l’ancien texte Markdown).
 */

require_once dirname(__DIR__) . '/app/Support/SqlText.php';

return function (PDO $pdo): void {
    if (!doctrineAtakTableExists($pdo, 'document_doctrines')) {
        return;
    }

    echo "Doctrine ATAK : seed emploi Overwatch…\n";

    $tenants = $pdo->query('SELECT id FROM tenants WHERE ' . \App\Support\SqlText::notEqualsLiteral($pdo, 'slug', 'default'))->fetchAll(PDO::FETCH_COLUMN);
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

/**
 * @return array{relative: string, full: string, mime: string, original: string, label: string, checksum: string, size: int}
 */
function atakEmploymentOfficialFile(): array
{
    $relative = 'doctrine/sic-atak-2026-001.pdf';
    $full = dirname(__DIR__) . '/storage/documents/' . $relative;
    $checksum = is_file($full)
        ? (hash_file('sha256', $full) ?: '')
        : hash('sha256', 'SIC/ATAK/2026-001v1.1');

    return [
        'relative' => $relative,
        'full' => $full,
        'mime' => 'application/pdf',
        'original' => 'FM_ATHENA_C2_Doctrine_v1.1_FR.pdf',
        'label' => 'v1.1',
        'checksum' => $checksum,
        'size' => is_file($full) ? (int) filesize($full) : 0,
    ];
}

function atakEmploymentOfficialSummary(): string
{
    return <<<'TXT'
Fixe les règles d’emploi du terminal tactique Overwatch (mod Arma) et du poste de commandement web Athena : prérequis de liaison, emploi carte/marqueurs/ordres/MEDEVAC en mission, responsabilités opérateur et SIC, OPSEC et maintien des compétences. Prise en compte obligatoire pour tous les membres autorisés à l’OP numérique.
TXT;
}

function atakEmploymentCurrentLooksLikeOfficialMarkdown(?string $rel, ?string $mime): bool
{
    $rel = str_replace('\\', '/', strtolower(trim((string) $rel)));
    $mime = strtolower(trim((string) $mime));

    return $mime === 'text/markdown'
        || str_ends_with($rel, '.md')
        || str_contains($rel, 'sic-atak-2026-001.md');
}

function seedAtakEmploymentDoctrine(PDO $pdo, int $tenantId): void
{
    $referenceCode = 'SIC/ATAK/2026-001';
    $slug = 'sic-atak-2026-001';

    $exists = $pdo->prepare(
        'SELECT dd.document_id FROM document_doctrines dd
         INNER JOIN documents d ON d.id = dd.document_id
         WHERE dd.tenant_id = ? AND (' . \App\Support\SqlText::equals($pdo, 'dd.reference_code') . ' OR ' . \App\Support\SqlText::equals($pdo, 'd.slug') . ')
         LIMIT 1'
    );
    $exists->execute([$tenantId, $referenceCode, $slug]);
    $existingId = (int) ($exists->fetchColumn() ?: 0);
    if ($existingId > 0) {
        upgradeAtakEmploymentDoctrineIfDemoPlaceholder($pdo, $tenantId, $existingId);
        upgradeAtakEmploymentDoctrineToOfficialPdf($pdo, $tenantId, $existingId);
        ensureAtakEmploymentBundledFile($pdo, $tenantId, $existingId);

        return;
    }

    $cat = $pdo->prepare('SELECT id FROM document_categories WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
    $cat->execute([$tenantId, 'doctrine']);
    $categoryId = (int) ($cat->fetchColumn() ?: 0);
    if ($categoryId < 1) {
        return;
    }

    $admin = $pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1");
    $admin->execute([$tenantId]);
    $userId = (int) ($admin->fetchColumn() ?: 0);

    $title = 'Doctrine d’emploi d’ATAK / Overwatch Athena';
    $summary = atakEmploymentOfficialSummary();
    $file = atakEmploymentOfficialFile();

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

    try {
        $insVer = $pdo->prepare(
            'INSERT INTO document_versions (
                document_id, version_number, version_major, version_minor, version_label,
                file_path, original_name, checksum, mime_type, size, is_current, published_at, change_summary, created_at
             ) VALUES (?, 1, 1, 1, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW())'
        );
        $insVer->execute([
            $docId,
            $file['label'],
            $file['relative'],
            $file['original'],
            $file['checksum'],
            $file['mime'],
            $file['size'] > 0 ? $file['size'] : null,
            'Publication du manuel PDF v1.1 — doctrine d’emploi ATAK / Athena.',
        ]);
    } catch (\Throwable) {
        $insVer = $pdo->prepare(
            'INSERT INTO document_versions (
                document_id, version_number, version_major, version_minor, version_label,
                file_path, checksum, mime_type, is_current, published_at, change_summary, created_at
             ) VALUES (?, 1, 1, 1, ?, ?, ?, ?, 1, NOW(), ?, NOW())'
        );
        $insVer->execute([
            $docId,
            $file['label'],
            $file['relative'],
            $file['checksum'],
            $file['mime'],
            'Publication du manuel PDF v1.1 — doctrine d’emploi ATAK / Athena.',
        ]);
    }

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

    ensureAtakEmploymentBundledFile($pdo, $tenantId, $docId);
}

/**
 * Si la doctrine ATAK encore présente est le stub de démonstration
 * (résumé « Document de démonstration » ou fichier demo/), la remplace
 * par le manuel officiel. Ne crée aucune prise en compte, ne change pas
 * le statut publié ni les audiences.
 */
function upgradeAtakEmploymentDoctrineIfDemoPlaceholder(PDO $pdo, int $tenantId, int $documentId): void
{
    if ($documentId < 1 || $tenantId < 1) {
        return;
    }

    $rowStmt = $pdo->prepare(
        'SELECT dd.summary, dv.file_path
         FROM document_doctrines dd
         LEFT JOIN document_versions dv ON dv.document_id = dd.document_id AND dv.is_current = 1
         WHERE dd.document_id = ? AND dd.tenant_id = ?
         LIMIT 1'
    );
    $rowStmt->execute([$documentId, $tenantId]);
    $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return;
    }

    $summary = trim((string) ($row['summary'] ?? ''));
    $filePath = str_replace('\\', '/', (string) ($row['file_path'] ?? ''));
    $isDemo = str_starts_with($summary, 'Document de démonstration')
        || str_contains($filePath, 'storage/documents/demo/')
        || str_contains($filePath, '/documents/demo/');
    if (!$isDemo) {
        return;
    }

    $officialSummary = atakEmploymentOfficialSummary();
    $file = atakEmploymentOfficialFile();

    try {
        $pdo->prepare(
            'UPDATE documents SET description = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        )->execute([$officialSummary, $documentId, $tenantId]);
        $pdo->prepare(
            'UPDATE document_doctrines SET summary = ?, issuing_label = ?, updated_at = NOW()
             WHERE document_id = ? AND tenant_id = ?'
        )->execute([
            $officialSummary,
            'Bureau SIC — Systèmes d’information et commandement',
            $documentId,
            $tenantId,
        ]);
        applyAtakEmploymentOfficialPdfToCurrentVersion($pdo, $documentId, $file);
    } catch (\Throwable) {
    }
}

/**
 * Remplace le Markdown livré par le manuel PDF v1.1.
 * Ne touche pas à un dépôt déjà fait par un responsable (autre PDF).
 */
function upgradeAtakEmploymentDoctrineToOfficialPdf(PDO $pdo, int $tenantId, int $documentId): void
{
    if ($documentId < 1 || $tenantId < 1) {
        return;
    }

    $st = $pdo->prepare(
        'SELECT id, file_path, mime_type FROM document_versions WHERE document_id = ? AND is_current = 1 LIMIT 1'
    );
    $st->execute([$documentId]);
    $ver = $st->fetch(PDO::FETCH_ASSOC);
    if (!is_array($ver)) {
        return;
    }

    $rel = str_replace('\\', '/', trim((string) ($ver['file_path'] ?? '')));
    $mime = (string) ($ver['mime_type'] ?? '');
    $file = atakEmploymentOfficialFile();
    $alreadyOfficialPdf = $rel === $file['relative'] && strtolower(trim($mime)) === 'application/pdf';
    if ($alreadyOfficialPdf) {
        return;
    }
    if (!atakEmploymentCurrentLooksLikeOfficialMarkdown($rel, $mime)) {
        return;
    }

    applyAtakEmploymentOfficialPdfToCurrentVersion($pdo, $documentId, $file);
    try {
        $pdo->prepare('UPDATE documents SET updated_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute([$documentId, $tenantId]);
    } catch (\Throwable) {
    }
}

/**
 * @param array{relative: string, original: string, checksum: string, mime: string, size: int, label: string} $file
 */
function applyAtakEmploymentOfficialPdfToCurrentVersion(PDO $pdo, int $documentId, array $file): void
{
    $change = 'Manuel PDF v1.1 — doctrine d’emploi ATAK / Athena.';
    try {
        $pdo->prepare(
            'UPDATE document_versions
             SET file_path = ?, original_name = ?, checksum = ?, mime_type = ?, size = ?,
                 version_label = ?, version_minor = 1, change_summary = ?
             WHERE document_id = ? AND is_current = 1'
        )->execute([
            $file['relative'],
            $file['original'],
            $file['checksum'],
            $file['mime'],
            $file['size'] > 0 ? $file['size'] : null,
            $file['label'],
            $change,
            $documentId,
        ]);
    } catch (\Throwable) {
        $pdo->prepare(
            'UPDATE document_versions
             SET file_path = ?, checksum = ?, mime_type = ?, change_summary = ?
             WHERE document_id = ? AND is_current = 1'
        )->execute([
            $file['relative'],
            $file['checksum'],
            $file['mime'],
            $change,
            $documentId,
        ]);
    }
}

/**
 * Recopie le manuel livré dans le dossier de la communauté si le pointeur
 * officiel n’a plus de fichier sur le serveur. Ne touche pas à un dépôt
 * déjà fait par un responsable (autre PDF ou autre version).
 */
function ensureAtakEmploymentBundledFile(PDO $pdo, int $tenantId, int $documentId): void
{
    if ($documentId < 1 || $tenantId < 1) {
        return;
    }
    $file = atakEmploymentOfficialFile();
    if (!is_file($file['full'])) {
        return;
    }

    $st = $pdo->prepare(
        'SELECT id, file_path FROM document_versions WHERE document_id = ? AND is_current = 1 LIMIT 1'
    );
    $st->execute([$documentId]);
    $ver = $st->fetch(PDO::FETCH_ASSOC);
    if (!is_array($ver)) {
        return;
    }

    $rel = str_replace('\\', '/', trim((string) ($ver['file_path'] ?? '')));
    $rel = ltrim($rel, '/');
    $isOfficialPointer = $rel === $file['relative']
        || $rel === 'doctrine/sic-atak-2026-001.md'
        || str_ends_with($rel, '/sic-atak-2026-001.md')
        || str_ends_with($rel, '/sic-atak-2026-001.pdf')
        || str_contains($rel, '/documents/demo/');
    if ($rel !== '' && !$isOfficialPointer) {
        return;
    }

    $currentFull = $rel !== '' ? dirname(__DIR__) . '/storage/documents/' . $rel : '';
    if ($currentFull !== '' && is_file($currentFull) && str_ends_with(strtolower($rel), '.pdf')) {
        return;
    }

    if (is_file($file['full'])) {
        applyAtakEmploymentOfficialPdfToCurrentVersion($pdo, $documentId, $file);

        return;
    }

    $destRel = $tenantId . '/' . $documentId . '/v1.1.pdf';
    $destFull = dirname(__DIR__) . '/storage/documents/' . $destRel;
    $dir = dirname($destFull);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return;
    }
    if (!@copy($file['full'], $destFull) || !is_file($destFull)) {
        return;
    }

    $copy = $file;
    $copy['relative'] = $destRel;
    $copy['checksum'] = hash_file('sha256', $destFull) ?: $file['checksum'];
    $copy['size'] = (int) filesize($destFull);
    applyAtakEmploymentOfficialPdfToCurrentVersion($pdo, $documentId, $copy);
}
