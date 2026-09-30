<?php

declare(strict_types=1);

/**
 * Doctrine d'emploi RH — Recrutement et Avancement — seed idempotent par tenant.
 * Fichier officiel : PDF v1.1 (manuel FM_ATHENA_RH_Doctrine_FR).
 */

require_once dirname(__DIR__) . '/app/Support/SqlText.php';

return function (PDO $pdo): void {
    if (!doctrineRhTableExists($pdo, 'document_doctrines')) {
        return;
    }

    echo "Doctrine RH : seed emploi Recrutement / Avancement…\n";

    $tenants = $pdo->query('SELECT id FROM tenants WHERE ' . \App\Support\SqlText::notEqualsLiteral($pdo, 'slug', 'default'))->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tenants as $tid) {
        $tid = (int) $tid;
        if ($tid < 1) {
            continue;
        }
        seedRhEmploymentDoctrine($pdo, $tid);
    }
};

function doctrineRhTableExists(PDO $pdo, string $table): bool
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
function rhEmploymentOfficialFile(): array
{
    $relative = 'doctrine/drh-pers-2026-001.pdf';
    $full = dirname(__DIR__) . '/storage/documents/' . $relative;
    $checksum = is_file($full)
        ? (hash_file('sha256', $full) ?: '')
        : hash('sha256', 'DRH/PERS/2026-001v1.1');

    return [
        'relative' => $relative,
        'full' => $full,
        'mime' => 'application/pdf',
        'original' => 'FM_ATHENA_RH_Doctrine_FR_v1.1.pdf',
        'label' => 'v1.1',
        'checksum' => $checksum,
        'size' => is_file($full) ? (int) filesize($full) : 0,
    ];
}

function rhEmploymentOfficialSummary(): string
{
    return <<<'TXT'
FM ATHENA RH-01 — Doctrine de recrutement, administration, carrière, instruction et disponibilité. Fixe le cadre RH Athena (besoin, candidature, incorporation, affectation, progression, qualification, disponibilité). Prise en compte obligatoire pour tous les membres de l’organisation.
TXT;
}

function rhEmploymentCurrentLooksLikeOfficialMarkdown(?string $rel, ?string $mime): bool
{
    $rel = str_replace('\\', '/', strtolower(trim((string) $rel)));
    $mime = strtolower(trim((string) $mime));

    return $mime === 'text/markdown'
        || str_ends_with($rel, '.md')
        || str_contains($rel, 'drh-pers-2026-001.md');
}

function seedRhEmploymentDoctrine(PDO $pdo, int $tenantId): void
{
    $referenceCode = 'DRH/PERS/2026-001';
    $slug = 'drh-pers-2026-001';

    $exists = $pdo->prepare(
        'SELECT dd.document_id FROM document_doctrines dd
         INNER JOIN documents d ON d.id = dd.document_id
         WHERE dd.tenant_id = ? AND (' . \App\Support\SqlText::equals($pdo, 'dd.reference_code') . ' OR ' . \App\Support\SqlText::equals($pdo, 'd.slug') . ')
         LIMIT 1'
    );
    $exists->execute([$tenantId, $referenceCode, $slug]);
    $existingId = (int) ($exists->fetchColumn() ?: 0);
    if ($existingId > 0) {
        upgradeRhEmploymentDoctrineIfDemoPlaceholder($pdo, $tenantId, $existingId);
        upgradeRhEmploymentDoctrineToOfficialPdf($pdo, $tenantId, $existingId);
        upgradeRhEmploymentDoctrineOfficialMetadata($pdo, $tenantId, $existingId);
        ensureRhEmploymentBundledFile($pdo, $tenantId, $existingId);
        ensureRhEmploymentMandatoryAudience($pdo, $tenantId, $existingId);

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

    $title = 'Doctrine d’emploi RH — Recrutement et Avancement';
    $summary = rhEmploymentOfficialSummary();
    $file = rhEmploymentOfficialFile();

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
            'Publication du manuel PDF v1.1 — FM ATHENA RH-01 Recrutement / Avancement.',
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
            'Publication du manuel PDF v1.1 — FM ATHENA RH-01 Recrutement / Avancement.',
        ]);
    }

    $domainRow = $pdo->prepare('SELECT id FROM document_reference_domains WHERE tenant_id = ? AND doc_prefix = ? LIMIT 1');
    $domainRow->execute([$tenantId, 'DRH']);
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
        'DRH',
        $domainId ?: null,
        'PERS',
        2026,
        1,
        $summary,
        'published',
        'mandatory',
        'Bureau DRH — Ressources humaines',
        1,
        $deadline,
        1,
    ]);

    ensureRhEmploymentMandatoryAudience($pdo, $tenantId, $docId);

    $seq = $pdo->prepare(
        'INSERT INTO document_reference_sequences (tenant_id, service_prefix, domain_code, year, last_number)
         VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, 1)'
    );
    try {
        $seq->execute([$tenantId, 'DRH', 'PERS', 2026]);
    } catch (\Throwable) {
    }

    ensureRhEmploymentBundledFile($pdo, $tenantId, $docId);
}

/**
 * Diffusion « tous les membres » + lecture / prise en compte obligatoires.
 */
function ensureRhEmploymentMandatoryAudience(PDO $pdo, int $tenantId, int $documentId): void
{
    if ($documentId < 1 || $tenantId < 1) {
        return;
    }

    try {
        $pdo->prepare(
            'UPDATE document_doctrines
             SET requirement_level = \'mandatory\',
                 acknowledgment_required = 1,
                 reading_required = 1,
                 include_future_members = 1,
                 updated_at = NOW()
             WHERE document_id = ? AND tenant_id = ?'
        )->execute([$documentId, $tenantId]);
    } catch (\Throwable) {
    }

    $hasAll = $pdo->prepare(
        'SELECT id FROM document_audiences
         WHERE document_id = ? AND tenant_id = ? AND audience_type = \'all_members\'
         LIMIT 1'
    );
    try {
        $hasAll->execute([$documentId, $tenantId]);
        if ((int) ($hasAll->fetchColumn() ?: 0) > 0) {
            return;
        }
    } catch (\Throwable) {
        return;
    }

    $aud = $pdo->prepare(
        'INSERT INTO document_audiences (document_id, tenant_id, audience_type, audience_value, include_children)
         VALUES (?, ?, \'all_members\', \'1\', 0)'
    );
    try {
        $aud->execute([$documentId, $tenantId]);
    } catch (\Throwable) {
    }
}

/**
 * Si la doctrine RH encore présente est le stub de démonstration
 * (résumé « Document de démonstration » ou fichier demo/), la remplace
 * par le manuel officiel. Ne crée aucune prise en compte, ne change pas
 * le statut publié ni les audiences.
 */
function upgradeRhEmploymentDoctrineIfDemoPlaceholder(PDO $pdo, int $tenantId, int $documentId): void
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

    $officialSummary = rhEmploymentOfficialSummary();
    $file = rhEmploymentOfficialFile();

    try {
        $pdo->prepare(
            'UPDATE documents SET description = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        )->execute([$officialSummary, $documentId, $tenantId]);
        $pdo->prepare(
            'UPDATE document_doctrines SET summary = ?, issuing_label = ?, updated_at = NOW()
             WHERE document_id = ? AND tenant_id = ?'
        )->execute([
            $officialSummary,
            'Bureau DRH — Ressources humaines',
            $documentId,
            $tenantId,
        ]);
        applyRhEmploymentOfficialPdfToCurrentVersion($pdo, $documentId, $file);
    } catch (\Throwable) {
    }
}

/**
 * Remplace le Markdown livré par le manuel PDF v1.1.
 * Ne touche pas à un dépôt déjà fait par un responsable (autre PDF).
 */
function upgradeRhEmploymentDoctrineToOfficialPdf(PDO $pdo, int $tenantId, int $documentId): void
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
    $file = rhEmploymentOfficialFile();
    $alreadyOfficialPdf = $rel === $file['relative'] && strtolower(trim($mime)) === 'application/pdf';
    if ($alreadyOfficialPdf) {
        return;
    }
    if (!rhEmploymentCurrentLooksLikeOfficialMarkdown($rel, $mime)) {
        return;
    }

    applyRhEmploymentOfficialPdfToCurrentVersion($pdo, $documentId, $file);
    try {
        $pdo->prepare('UPDATE documents SET updated_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute([$documentId, $tenantId]);
    } catch (\Throwable) {
    }
}

/**
 * Aligne checksum / libellé / résumé sur le manuel PDF v1.1 si le pointeur
 * officiel est déjà en place (ex. ancienne v1.0 générée).
 */
function upgradeRhEmploymentDoctrineOfficialMetadata(PDO $pdo, int $tenantId, int $documentId): void
{
    if ($documentId < 1 || $tenantId < 1) {
        return;
    }

    $file = rhEmploymentOfficialFile();
    if (!is_file($file['full'])) {
        return;
    }

    $st = $pdo->prepare(
        'SELECT id, file_path, mime_type, checksum, version_label, original_name
         FROM document_versions WHERE document_id = ? AND is_current = 1 LIMIT 1'
    );
    $st->execute([$documentId]);
    $ver = $st->fetch(PDO::FETCH_ASSOC);
    if (!is_array($ver)) {
        return;
    }

    $rel = str_replace('\\', '/', ltrim(trim((string) ($ver['file_path'] ?? '')), '/'));
    $mime = strtolower(trim((string) ($ver['mime_type'] ?? '')));
    $isOfficialPointer = $rel === $file['relative']
        || str_ends_with($rel, '/drh-pers-2026-001.pdf');
    if (!$isOfficialPointer || $mime !== 'application/pdf') {
        return;
    }

    $sameChecksum = trim((string) ($ver['checksum'] ?? '')) === $file['checksum'];
    $sameLabel = trim((string) ($ver['version_label'] ?? '')) === $file['label'];
    $sameName = trim((string) ($ver['original_name'] ?? '')) === $file['original'];
    if ($sameChecksum && $sameLabel && $sameName) {
        return;
    }

    applyRhEmploymentOfficialPdfToCurrentVersion($pdo, $documentId, $file);
    $summary = rhEmploymentOfficialSummary();
    try {
        $pdo->prepare(
            'UPDATE documents SET description = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        )->execute([$summary, $documentId, $tenantId]);
        $pdo->prepare(
            'UPDATE document_doctrines SET summary = ?, updated_at = NOW() WHERE document_id = ? AND tenant_id = ?'
        )->execute([$summary, $documentId, $tenantId]);
    } catch (\Throwable) {
    }
}

/**
 * @param array{relative: string, original: string, checksum: string, mime: string, size: int, label: string} $file
 */
function applyRhEmploymentOfficialPdfToCurrentVersion(PDO $pdo, int $documentId, array $file): void
{
    $change = 'Manuel PDF v1.1 — FM ATHENA RH-01 Recrutement / Avancement.';
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
function ensureRhEmploymentBundledFile(PDO $pdo, int $tenantId, int $documentId): void
{
    if ($documentId < 1 || $tenantId < 1) {
        return;
    }
    $file = rhEmploymentOfficialFile();
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
        || $rel === 'doctrine/drh-pers-2026-001.md'
        || str_ends_with($rel, '/drh-pers-2026-001.md')
        || str_ends_with($rel, '/drh-pers-2026-001.pdf')
        || str_contains($rel, '/documents/demo/');
    if ($rel !== '' && !$isOfficialPointer) {
        return;
    }

    $currentFull = $rel !== '' ? dirname(__DIR__) . '/storage/documents/' . $rel : '';
    if ($currentFull !== '' && is_file($currentFull) && str_ends_with(strtolower($rel), '.pdf')) {
        return;
    }

    if (is_file($file['full'])) {
        applyRhEmploymentOfficialPdfToCurrentVersion($pdo, $documentId, $file);

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
    applyRhEmploymentOfficialPdfToCurrentVersion($pdo, $documentId, $copy);
}
