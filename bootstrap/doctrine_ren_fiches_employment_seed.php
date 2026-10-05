<?php

declare(strict_types=1);

/**
 * Doctrine d'emploi des fiches de renseignement simplifiées (FRS / FRM) — seed idempotent par tenant.
 * Fichier officiel : PDF FM ATHENA REN-01, imprimé depuis docs/doctrine/fm-athena-ren-01/ (tools/doctrine/build-pdf.mjs).
 */

require_once dirname(__DIR__) . '/app/Support/SqlText.php';

return function (PDO $pdo): void {
    if (!doctrineRenFichesTableExists($pdo, 'document_doctrines')) {
        return;
    }

    echo "Doctrine REN : seed emploi des fiches FRS / FRM…\n";

    $tenants = $pdo->query('SELECT id FROM tenants WHERE ' . \App\Support\SqlText::notEqualsLiteral($pdo, 'slug', 'default'))->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tenants as $tid) {
        $tid = (int) $tid;
        if ($tid < 1) {
            continue;
        }
        seedRenFichesEmploymentDoctrine($pdo, $tid);
    }
};

function doctrineRenFichesTableExists(PDO $pdo, string $table): bool
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
function renFichesEmploymentOfficialFile(): array
{
    $relative = 'doctrine/ren-proc-2026-001.pdf';
    $full = dirname(__DIR__) . '/storage/documents/' . $relative;
    $checksum = is_file($full)
        ? (hash_file('sha256', $full) ?: '')
        : hash('sha256', 'REN/PROC/2026-001v1.0');

    return [
        'relative' => $relative,
        'full' => $full,
        'mime' => 'application/pdf',
        'original' => 'FM_ATHENA_REN-01_Fiches_FRS_FRM_v1.0.pdf',
        'label' => 'v1.0',
        'checksum' => $checksum,
        'size' => is_file($full) ? (int) filesize($full) : 0,
    ];
}

function renFichesEmploymentOfficialSummary(): string
{
    return <<<'TXT'
FM ATHENA REN-01 — Circulaire relative à la rédaction, à la transmission et à l’exploitation des fiches de renseignement simplifiées (FRS), dont la fiche de renseignement de mission (FRM). Fixe l’emploi de l’application FRS / FRM de COMSPEC ATAK et du rédacteur du portail, le mode dégradé, le suivi par le bureau SSE et les responsabilités. Prise en compte obligatoire pour tous les membres de l’organisation.
TXT;
}

function seedRenFichesEmploymentDoctrine(PDO $pdo, int $tenantId): void
{
    $referenceCode = 'REN/PROC/2026-001';
    $slug = 'ren-proc-2026-001';

    $exists = $pdo->prepare(
        'SELECT dd.document_id FROM document_doctrines dd
         INNER JOIN documents d ON d.id = dd.document_id
         WHERE dd.tenant_id = ? AND (' . \App\Support\SqlText::equals($pdo, 'dd.reference_code') . ' OR ' . \App\Support\SqlText::equals($pdo, 'd.slug') . ')
         LIMIT 1'
    );
    $exists->execute([$tenantId, $referenceCode, $slug]);
    $existingId = (int) ($exists->fetchColumn() ?: 0);
    if ($existingId > 0) {
        upgradeRenFichesEmploymentOfficialMetadata($pdo, $tenantId, $existingId);
        ensureRenFichesEmploymentBundledFile($pdo, $tenantId, $existingId);
        ensureRenFichesEmploymentMandatoryAudience($pdo, $tenantId, $existingId);

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

    $title = 'Doctrine d’emploi des fiches de renseignement — FRS / FRM';
    $summary = renFichesEmploymentOfficialSummary();
    $file = renFichesEmploymentOfficialFile();

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

    $change = 'Publication de la circulaire PDF v1.0 — FM ATHENA REN-01 Fiches de renseignement FRS / FRM.';
    try {
        $insVer = $pdo->prepare(
            'INSERT INTO document_versions (
                document_id, version_number, version_major, version_minor, version_label,
                file_path, original_name, checksum, mime_type, size, is_current, published_at, change_summary, created_at
             ) VALUES (?, 1, 1, 0, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW())'
        );
        $insVer->execute([
            $docId,
            $file['label'],
            $file['relative'],
            $file['original'],
            $file['checksum'],
            $file['mime'],
            $file['size'] > 0 ? $file['size'] : null,
            $change,
        ]);
    } catch (\Throwable) {
        $insVer = $pdo->prepare(
            'INSERT INTO document_versions (
                document_id, version_number, version_major, version_minor, version_label,
                file_path, checksum, mime_type, is_current, published_at, change_summary, created_at
             ) VALUES (?, 1, 1, 0, ?, ?, ?, ?, 1, NOW(), ?, NOW())'
        );
        $insVer->execute([
            $docId,
            $file['label'],
            $file['relative'],
            $file['checksum'],
            $file['mime'],
            $change,
        ]);
    }

    $domainRow = $pdo->prepare('SELECT id FROM document_reference_domains WHERE tenant_id = ? AND doc_prefix = ? LIMIT 1');
    $domainRow->execute([$tenantId, 'REN']);
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
        'REN',
        $domainId ?: null,
        'PROC',
        2026,
        1,
        $summary,
        'published',
        'mandatory',
        'Bureau renseignement — Section SSE',
        1,
        $deadline,
        1,
    ]);

    ensureRenFichesEmploymentMandatoryAudience($pdo, $tenantId, $docId);

    $seq = $pdo->prepare(
        'INSERT INTO document_reference_sequences (tenant_id, service_prefix, domain_code, year, last_number)
         VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, 1)'
    );
    try {
        $seq->execute([$tenantId, 'REN', 'PROC', 2026]);
    } catch (\Throwable) {
    }

    ensureRenFichesEmploymentBundledFile($pdo, $tenantId, $docId);
}

/**
 * Diffusion « tous les membres » + lecture / prise en compte obligatoires.
 */
function ensureRenFichesEmploymentMandatoryAudience(PDO $pdo, int $tenantId, int $documentId): void
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
 * Réimpression du PDF livré (même pointeur officiel, nouveau checksum) : aligne la version courante.
 * Ne touche pas à un dépôt fait par un responsable (autre fichier).
 */
function upgradeRenFichesEmploymentOfficialMetadata(PDO $pdo, int $tenantId, int $documentId): void
{
    if ($documentId < 1 || $tenantId < 1) {
        return;
    }

    $file = renFichesEmploymentOfficialFile();
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
    if ($rel !== $file['relative'] || $mime !== 'application/pdf') {
        return;
    }

    $sameChecksum = trim((string) ($ver['checksum'] ?? '')) === $file['checksum'];
    $sameLabel = trim((string) ($ver['version_label'] ?? '')) === $file['label'];
    $sameName = trim((string) ($ver['original_name'] ?? '')) === $file['original'];
    if ($sameChecksum && $sameLabel && $sameName) {
        return;
    }

    applyRenFichesEmploymentOfficialPdfToCurrentVersion($pdo, $documentId, $file);
}

/**
 * Remet le pointeur officiel si la version courante n’a plus de fichier sur le serveur.
 * Ne touche pas à un dépôt déjà fait par un responsable (autre PDF ou autre version).
 */
function ensureRenFichesEmploymentBundledFile(PDO $pdo, int $tenantId, int $documentId): void
{
    if ($documentId < 1 || $tenantId < 1) {
        return;
    }
    $file = renFichesEmploymentOfficialFile();
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

    $rel = ltrim(str_replace('\\', '/', trim((string) ($ver['file_path'] ?? ''))), '/');
    $isOfficialPointer = $rel === '' || $rel === $file['relative'] || str_ends_with($rel, '/ren-proc-2026-001.pdf');
    if (!$isOfficialPointer) {
        return;
    }

    $currentFull = $rel !== '' ? dirname(__DIR__) . '/storage/documents/' . $rel : '';
    if ($currentFull !== '' && is_file($currentFull)) {
        return;
    }

    applyRenFichesEmploymentOfficialPdfToCurrentVersion($pdo, $documentId, $file);
}

/**
 * @param array{relative: string, original: string, checksum: string, mime: string, size: int, label: string} $file
 */
function applyRenFichesEmploymentOfficialPdfToCurrentVersion(PDO $pdo, int $documentId, array $file): void
{
    $change = 'Circulaire PDF v1.0 — FM ATHENA REN-01 Fiches de renseignement FRS / FRM.';
    try {
        $pdo->prepare(
            'UPDATE document_versions
             SET file_path = ?, original_name = ?, checksum = ?, mime_type = ?, size = ?,
                 version_label = ?, change_summary = ?
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
