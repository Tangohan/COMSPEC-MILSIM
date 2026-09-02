<?php

declare(strict_types=1);

/**
 * Référentiel doctrinal : tables et colonnes (idempotent).
 */
return function (PDO $pdo): void {
    $sqlPath = dirname(__DIR__) . '/migrations/20260902120000_doctrine_referential.sql';
    if (!is_file($sqlPath)) {
        echo "[ATTENTION] 20260902120000_doctrine_referential.sql introuvable — migration ignorée.\n";

        return;
    }

    echo "Doctrine référentiel : exécution SQL...\n";
    @flush();
    @ob_flush();

    $sql = file_get_contents($sqlPath);
    if ($sql === false || $sql === '') {
        return;
    }
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
                echo '  [ATTENTION] Doctrine référentiel SQL : ' . $msg . "\n";
            }
        }
    }

    // tenant_id nullable pour documents plateforme (ALTER séparé — peut échouer si déjà fait)
    try {
        $pdo->exec('ALTER TABLE documents MODIFY tenant_id INT UNSIGNED NULL');
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (!preg_match('/Duplicate|already/i', $msg)) {
            echo '  [ATTENTION] documents.tenant_id nullable : ' . $msg . "\n";
        }
    }

    seedDefaultDoctrineCatalog($pdo);

    $demoSeed = require dirname(__DIR__) . '/bootstrap/doctrine_demo_seed.php';
    if (is_callable($demoSeed)) {
        $demoSeed($pdo);
    }

    $atakSeed = require dirname(__DIR__) . '/bootstrap/doctrine_atak_employment_seed.php';
    if (is_callable($atakSeed)) {
        $atakSeed($pdo);
    }
};

function seedDefaultDoctrineCatalog(PDO $pdo): void
{
    $tenantIds = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN);
    if ($tenantIds === false || $tenantIds === []) {
        return;
    }

    $defaultDomains = [
        ['EM', 'État-major', 'EM', '#6366f1'],
        ['OPS', 'Opérations', 'OPS', '#059669'],
        ['DRH', 'Ressources humaines', 'DRH', '#2563eb'],
        ['FORM', 'Formation', 'FORM', '#d97706'],
        ['LOG', 'Logistique', 'LOG', '#78716c'],
        ['SIC', 'Systèmes d\'information', 'SIC', '#0891b2'],
        ['MED', 'Médical', 'MED', '#dc2626'],
        ['REN', 'Renseignement', 'REN', '#7c3aed'],
        ['COM', 'Communication', 'COM', '#db2777'],
        ['ADM', 'Administration', 'ADM', '#475569'],
    ];

    $defaultSubdomains = [
        'OPS' => [
            ['SEC', 'Sûreté'],
            ['SIC', 'Transmissions'],
            ['MAN', 'Manœuvre'],
        ],
        'DRH' => [
            ['PERS', 'Personnel'],
        ],
        'FORM' => [
            ['INST', 'Instruction'],
        ],
        'EM' => [
            ['DOCTR', 'Doctrine'],
        ],
        'LOG' => [
            ['MAT', 'Matériel'],
        ],
        'MED' => [
            ['SAN', 'Sanitaire'],
        ],
        'REN' => [
            ['PROC', 'Procédures'],
        ],
        'ADM' => [
            ['ORG', 'Organisation'],
        ],
        'SIC' => [
            ['ATAK', 'Système ATAK / C2'],
        ],
        'COM' => [
            ['INFO', 'Information'],
        ],
    ];

    $defaultDiffusion = [
        ['public', 'Public'],
        ['interne', 'Interne'],
        ['restreint', 'Restreint'],
        ['commandement', 'Commandement'],
        ['need_to_know', 'Besoin d\'en connaître'],
    ];

    $insDomain = $pdo->prepare(
        'INSERT IGNORE INTO document_reference_domains (tenant_id, code, label, doc_prefix, color, sort_order, is_active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );
    $findDomain = $pdo->prepare(
        'SELECT id FROM document_reference_domains WHERE tenant_id = ? AND code = ? LIMIT 1'
    );
    $insSub = $pdo->prepare(
        'INSERT IGNORE INTO document_reference_subdomains (tenant_id, domain_id, code, label, sort_order, is_active)
         VALUES (?, ?, ?, ?, ?, 1)'
    );
    $insDiff = $pdo->prepare(
        'INSERT IGNORE INTO document_diffusion_levels (tenant_id, code, label, sort_order, is_active)
         VALUES (?, ?, ?, ?, 1)'
    );

    foreach ($tenantIds as $tid) {
        $tid = (int) $tid;
        if ($tid < 1) {
            continue;
        }
        $order = 0;
        foreach ($defaultDomains as [$code, $label, $prefix, $color]) {
            $insDomain->execute([$tid, $code, $label, $prefix, $color, $order++]);
            $findDomain->execute([$tid, $code]);
            $domainId = (int) ($findDomain->fetchColumn() ?: 0);
            if ($domainId < 1) {
                continue;
            }
            $subOrder = 0;
            foreach ($defaultSubdomains[$code] ?? [] as [$subCode, $subLabel]) {
                $insSub->execute([$tid, $domainId, $subCode, $subLabel, $subOrder++]);
            }
        }
        $dOrder = 0;
        foreach ($defaultDiffusion as [$dCode, $dLabel]) {
            $insDiff->execute([$tid, $dCode, $dLabel, $dOrder++]);
        }
    }
}
