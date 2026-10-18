<?php

declare(strict_types=1);

/**
 * Nouvelles éditions officielles des doctrines livrées (seed idempotent par tenant).
 *
 * Publie une nouvelle version (nouvelle ligne document_versions, version courante) et remet la
 * prise en compte signée à zéro : chaque membre doit signer la nouvelle version, avec une échéance
 * de 14 jours. Les versions précédentes restent dans l’historique.
 *
 * Ne publie rien si la version courante a été déposée par un responsable (fichier hors doctrine/),
 * ni si l’édition existe déjà pour le document.
 */

require_once dirname(__DIR__) . '/app/Support/SqlText.php';

return function (PDO $pdo): void {
    if (!doctrineEditionsTableExists($pdo, 'document_doctrines')) {
        return;
    }

    echo "Doctrine : nouvelles éditions officielles…\n";

    $tenants = $pdo->query('SELECT id FROM tenants WHERE ' . \App\Support\SqlText::notEqualsLiteral($pdo, 'slug', 'default'))->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tenants as $tid) {
        $tid = (int) $tid;
        if ($tid < 1) {
            continue;
        }
        foreach (doctrineOfficialEditions() as $edition) {
            publishDoctrineOfficialEdition($pdo, $tid, $edition);
        }
    }
};

function doctrineEditionsTableExists(PDO $pdo, string $table): bool
{
    try {
        $pdo->query('SELECT 1 FROM `' . str_replace('`', '', $table) . '` LIMIT 1');

        return true;
    } catch (\Throwable) {
        return false;
    }
}

/**
 * @return list<array{reference: string, slug: string, relative: string, original: string, major: int, minor: int, change: string, summary: string|null, previous_files: list<string>}>
 */
function doctrineOfficialEditions(): array
{
    return [
        [
            'reference' => 'REN/PROC/2026-001',
            'slug' => 'ren-proc-2026-001',
            'relative' => 'doctrine/ren-proc-2026-001-v1.1.pdf',
            'original' => 'FM_ATHENA_REN-01_Fiches_FRS_FRM_v1.1.pdf',
            'major' => 1,
            'minor' => 1,
            'change' => 'Édition 1.1 du 18 octobre 2026 — terminal ATAK natif, rédacteur Overwatch gelé, relecture avant envoi, fiches rapides SSE, annexe des sigles ; rédaction reprise.',
            'summary' => 'FM ATHENA REN-01 — Circulaire relative à la rédaction, à la transmission et à l’exploitation des fiches de renseignement simplifiées (FRS), notamment de la fiche de renseignement de mission (FRM). Fixe l’emploi de l’application FRS / FRM du terminal ATAK natif et du rédacteur du portail, le mode dégradé, le suivi par le bureau SSE et les responsabilités. Prise en compte obligatoire pour tous les membres de l’organisation.',
            'previous_files' => ['doctrine/ren-proc-2026-001.pdf'],
        ],
        [
            'reference' => 'SIC/ATAK/2026-001',
            'slug' => 'sic-atak-2026-001',
            'relative' => 'doctrine/sic-atak-2026-001-v1.3.pdf',
            'original' => 'FM_ATHENA_C2-01_v1.3_FR.pdf',
            'major' => 1,
            'minor' => 3,
            'change' => 'Édition 1.3 du 18 octobre 2026 — doctrine refondue pour le terminal ATAK natif (éditions 1.2 et 1.3) : liaison au compte, emploi du terminal, appui-feu, comptes rendus, résilience ; surcouche Overwatch gelée.',
            'summary' => 'FM ATHENA C2-01 — Doctrine de mise en place et d’emploi d’ATHENA et du terminal ATAK natif : installation et liaison au compte, emploi du terminal et de la Tacmap, appui-feu, renseignement, sécurité des communautés, plan PACE, versions et dépannage. Prise en compte obligatoire pour tous les membres autorisés à l’OP numérique.',
            'previous_files' => ['doctrine/sic-atak-2026-001.pdf', 'doctrine/sic-atak-2026-001.md'],
        ],
    ];
}

/**
 * @param array{reference: string, slug: string, relative: string, original: string, major: int, minor: int, change: string, summary: string|null, previous_files: list<string>} $edition
 */
function publishDoctrineOfficialEdition(PDO $pdo, int $tenantId, array $edition): void
{
    $full = dirname(__DIR__) . '/storage/documents/' . $edition['relative'];
    if (!is_file($full)) {
        return;
    }

    $find = $pdo->prepare(
        'SELECT dd.document_id FROM document_doctrines dd
         INNER JOIN documents d ON d.id = dd.document_id
         WHERE dd.tenant_id = ? AND (' . \App\Support\SqlText::equals($pdo, 'dd.reference_code') . ' OR ' . \App\Support\SqlText::equals($pdo, 'd.slug') . ')
         LIMIT 1'
    );
    $find->execute([$tenantId, $edition['reference'], $edition['slug']]);
    $documentId = (int) ($find->fetchColumn() ?: 0);
    if ($documentId < 1) {
        return;
    }

    $label = 'v' . $edition['major'] . '.' . $edition['minor'];

    $versions = $pdo->prepare('SELECT id, file_path, version_label, original_name, is_current FROM document_versions WHERE document_id = ?');
    $versions->execute([$documentId]);
    $current = null;
    foreach ($versions->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $rel = ltrim(str_replace('\\', '/', trim((string) ($v['file_path'] ?? ''))), '/');
        if ($rel === $edition['relative']
            || trim((string) ($v['original_name'] ?? '')) === $edition['original']
            || trim((string) ($v['version_label'] ?? '')) === $label) {
            return;
        }
        if (!empty($v['is_current'])) {
            $current = $v;
        }
    }

    if ($current !== null && !doctrineEditionIsOfficialPointer((string) ($current['file_path'] ?? ''), $edition['previous_files'])) {
        return;
    }

    $next = $pdo->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 FROM document_versions WHERE document_id = ?');
    $next->execute([$documentId]);
    $number = (int) $next->fetchColumn();
    $checksum = hash_file('sha256', $full) ?: '';
    $size = (int) filesize($full);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE document_versions SET is_current = 0 WHERE document_id = ?')->execute([$documentId]);
        $pdo->prepare(
            'INSERT INTO document_versions (
                document_id, version_number, version_major, version_minor, version_label,
                file_path, original_name, checksum, mime_type, size, change_summary,
                acknowledgment_reset, is_current, published_at, created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'application/pdf\', ?, ?, 1, 1, NOW(), NOW())'
        )->execute([
            $documentId,
            $number,
            $edition['major'],
            $edition['minor'],
            $label,
            $edition['relative'],
            $edition['original'],
            $checksum,
            $size,
            $edition['change'],
        ]);

        $fields = 'requirement_level = \'mandatory\', acknowledgment_required = 1, reading_required = 1,
                   acknowledgment_deadline_at = ?, published_at = NOW(), updated_at = NOW()';
        $params = [date('Y-m-d H:i:s', strtotime('+14 days'))];
        if ($edition['summary'] !== null) {
            $fields .= ', summary = ?';
            $params[] = $edition['summary'];
        }
        $params[] = $documentId;
        $params[] = $tenantId;
        $pdo->prepare('UPDATE document_doctrines SET ' . $fields . ' WHERE document_id = ? AND tenant_id = ?')->execute($params);

        if ($edition['summary'] !== null) {
            $pdo->prepare('UPDATE documents SET description = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
                ->execute([$edition['summary'], $documentId, $tenantId]);
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo '  [ATTENTION] ' . $edition['reference'] . ' ' . $label . ' : ' . $e->getMessage() . "\n";

        return;
    }

    echo '  ' . $edition['reference'] . ' ' . $label . ' publiée (tenant ' . $tenantId . ").\n";
}

/**
 * Vrai si la version courante est un fichier livré par le dépôt (ou une copie par communauté),
 * faux si un responsable a déposé son propre fichier.
 *
 * @param list<string> $previousFiles
 */
function doctrineEditionIsOfficialPointer(string $filePath, array $previousFiles): bool
{
    $rel = ltrim(str_replace('\\', '/', trim($filePath)), '/');
    if ($rel === '' || str_contains($rel, '/documents/demo/')) {
        return true;
    }
    foreach ($previousFiles as $prev) {
        if ($rel === $prev || str_ends_with($rel, '/' . basename($prev))) {
            return true;
        }
    }

    return (bool) preg_match('#^\d+/\d+/v1\.\d+\.pdf$#', $rel);
}
