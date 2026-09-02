<?php

declare(strict_types=1);

/**
 * Données de démonstration réalistes pour le référentiel doctrinal (idempotent).
 */
return function (PDO $pdo): void {
    if (!tableExists($pdo, 'document_doctrines')) {
        return;
    }

    echo "Doctrine référentiel : données de démonstration…\n";

    $tenants = $pdo->query('SELECT id FROM tenants WHERE slug != \'default\' LIMIT 5')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tenants as $tid) {
        $tid = (int) $tid;
        if ($tid < 1) {
            continue;
        }
        seedTenantDemo($pdo, $tid);
    }
};

function tableExists(PDO $pdo, string $table): bool
{
    try {
        $pdo->query('SELECT 1 FROM `' . str_replace('`', '', $table) . '` LIMIT 1');

        return true;
    } catch (\Throwable) {
        return false;
    }
}

function seedTenantDemo(PDO $pdo, int $tenantId): void
{
    $chk = $pdo->prepare('SELECT COUNT(*) FROM document_doctrines WHERE tenant_id = ?');
    $chk->execute([$tenantId]);
    if ((int) $chk->fetchColumn() > 0) {
        return;
    }

    $cat = $pdo->prepare('SELECT id FROM document_categories WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $cat->execute([$tenantId, 'doctrine']);
    $categoryId = (int) ($cat->fetchColumn() ?: 0);
    if ($categoryId < 1) {
        return;
    }

    $admin = $pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND status = \'active\' ORDER BY id ASC LIMIT 1');
    $admin->execute([$tenantId]);
    $userId = (int) ($admin->fetchColumn() ?: 0);

    $demos = [
        ['EM/DOCTR/2026-001', 'EM', 'DOCTR', 'Doctrine générale d’emploi de l’unité', 'mandatory', 1, 0],
        ['OPS/SEC/2026-014', 'OPS', 'SEC', 'Mesures de sûreté applicables aux opérations extérieures', 'mandatory', 2, 1],
        ['OPS/SIC/2026-018', 'OPS', 'SIC', 'Emploi des moyens de transmission et procédures radio', 'mandatory', 2, 1],
        ['DRH/PERS/2026-004', 'DRH', 'PERS', 'Disponibilité, permissions et obligations du personnel', 'mandatory', 1, 0],
        ['FORM/INST/2026-021', 'FORM', 'INST', 'Instruction relative au maintien des compétences individuelles', 'recommended', 3, 0],
        ['LOG/MAT/2026-009', 'LOG', 'MAT', 'Perception, emploi et restitution des matériels sensibles', 'informative', 1, 0],
        ['MED/SAN/2026-006', 'MED', 'SAN', 'Conduite à tenir en cas de blessé au combat', 'mandatory', 1, 0],
        ['REN/PROC/2026-011', 'REN', 'PROC', 'Recueil, qualification et transmission du renseignement terrain', 'recommended', 1, 0],
        ['SIC/ATAK/2026-001', 'SIC', 'ATAK', 'Doctrine d’emploi d’ATAK / Overwatch Athena', 'mandatory', 1, 0],
    ];

    foreach ($demos as [$ref, $svc, $dom, $title, $req, $major, $minor]) {
        $slug = strtolower(str_replace(['/', ' '], '-', $ref));
        $insDoc = $pdo->prepare(
            'INSERT INTO documents (tenant_id, scope, title, slug, description, document_category_id, status, created_by, created_at, updated_at)
             VALUES (?, \'tenant\', ?, ?, ?, ?, \'published\', ?, NOW(), NOW())'
        );
        try {
            $insDoc->execute([$tenantId, $title, $slug, $title, $categoryId, $userId > 0 ? $userId : null]);
        } catch (\Throwable) {
            continue;
        }
        $docId = (int) $pdo->lastInsertId();
        if ($docId < 1) {
            continue;
        }

        $vLabel = 'v' . $major . '.' . $minor;
        $insVer = $pdo->prepare(
            'INSERT INTO document_versions (document_id, version_number, version_major, version_minor, version_label, file_path, checksum, is_current, published_at, created_at)
             VALUES (?, 1, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
        );
        $insVer->execute([$docId, $major, $minor, $vLabel, 'storage/documents/demo/' . $slug . '.pdf', hash('sha256', $ref . $vLabel)]);

        $domainRow = $pdo->prepare('SELECT id FROM document_reference_domains WHERE tenant_id = ? AND doc_prefix = ? LIMIT 1');
        $domainRow->execute([$tenantId, $svc]);
        $domainId = (int) ($domainRow->fetchColumn() ?: 0);

        $ack = in_array($req, ['mandatory', 'recommended'], true) ? 1 : 0;
        $deadline = $ack ? date('Y-m-d H:i:s', strtotime('+14 days')) : null;

        $insDoctrine = $pdo->prepare(
            'INSERT INTO document_doctrines (
                document_id, tenant_id, scope, reference_code, service_prefix, domain_id, domain_code,
                seq_year, seq_number, summary, doctrine_status, requirement_level,
                issuing_label, effective_at, acknowledgment_required, acknowledgment_deadline_at,
                reading_required, include_future_members, published_at
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,1,NOW())'
        );
        preg_match('/(\d{4})-(\d+)/', $ref, $m);
        $insDoctrine->execute([
            $docId, $tenantId, 'tenant', $ref, $svc, $domainId ?: null, $dom,
            (int) ($m[1] ?? 2026), (int) ($m[2] ?? 1),
            'Document de démonstration — ' . $title,
            'published', $req,
            'État-major — ' . $svc,
            $ack, $deadline, $ack,
        ]);

        $aud = $pdo->prepare(
            'INSERT INTO document_audiences (document_id, tenant_id, audience_type, audience_value, include_children)
             VALUES (?, ?, \'all_members\', \'1\', 0)'
        );
        try {
            $aud->execute([$docId, $tenantId]);
        } catch (\Throwable) {
        }
    }
}
