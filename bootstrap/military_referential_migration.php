<?php

declare(strict_types=1);

/**
 * Référentiel militaire global (SOF) : DDL + seed + migration affiliations communauté.
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    $root = dirname(__DIR__);
    $hasTable = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $runSqlFile = static function (PDO $pdo, string $path): void {
        if (!is_file($path)) {
            echo "  [ATTENTION] Fichier SQL absent : {$path}\n";

            return;
        }
        $sql = (string) file_get_contents($path);
        $sql = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;
        $sql = preg_replace('/SET NAMES utf8mb4;?/i', '', $sql) ?? $sql;
        $chunks = preg_split('/;\s*[\r\n]+/', trim($sql)) ?: [];
        foreach ($chunks as $stmtSql) {
            $stmtSql = trim($stmtSql);
            if ($stmtSql === '') {
                continue;
            }
            try {
                $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'already exists') === false && stripos($msg, 'Duplicate') === false) {
                    echo '  [ATTENTION] ' . $msg . "\n";
                }
            }
        }
    };

    if (!$hasTable($pdo, 'countries') || !$hasTable($pdo, 'military_units')) {
        echo "  Création tables military_referential...\n";
        $runSqlFile($pdo, $root . '/migrations/military_referential.sql');
    }

    if (!$hasTable($pdo, 'military_units')) {
        echo "  [ATTENTION] military_units introuvable après DDL — seed annulé.\n";

        return;
    }

    $countBefore = (int) $pdo->query('SELECT COUNT(*) FROM military_units')->fetchColumn();
    echo $countBefore === 0
        ? "  Seed référentiel militaire...\n"
        : "  Synchronisation seed référentiel militaire ({$countBefore} entités déjà présentes)…\n";
    if (!function_exists('military_referential_seed')) {
        require $root . '/bootstrap/military_referential_seed.php';
    }
    military_referential_seed($pdo);
    $countAfter = (int) $pdo->query('SELECT COUNT(*) FROM military_units')->fetchColumn();
    echo "  Référentiel militaire : {$countAfter} entités.\n";

    // Migration affiliations JSON → table (idempotent)
    if (!$hasTable($pdo, 'tenant_military_unit_affiliations') || !$hasTable($pdo, 'tenants')) {
        return;
    }

    echo "  Migration affiliations communauté → tenant_military_unit_affiliations...\n";
    $codeToId = [];
    foreach ($pdo->query('SELECT id, code FROM military_units')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $codeToId[(string) $row['code']] = (int) $row['id'];
    }

    $tenants = $pdo->query('SELECT id, settings FROM tenants')->fetchAll(PDO::FETCH_ASSOC);
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO tenant_military_unit_affiliations (tenant_id, military_unit_id, sort_order, created_at)
         VALUES (?, ?, ?, NOW())'
    );
    $updateSettings = $pdo->prepare('UPDATE tenants SET settings = ? WHERE id = ?');
    $migrated = 0;
    $orphans = 0;

    foreach ($tenants as $tenant) {
        $tid = (int) $tenant['id'];
        $settingsRaw = $tenant['settings'] ?? null;
        $settings = is_string($settingsRaw) ? json_decode($settingsRaw, true) : null;
        if (!is_array($settings)) {
            continue;
        }
        $community = $settings['community'] ?? null;
        if (!is_array($community)) {
            continue;
        }
        $aff = $community['unit_affiliation'] ?? null;
        if (!is_array($aff) || empty($aff['is_real'])) {
            continue;
        }
        $unitIds = $aff['unit_ids'] ?? [];
        if (!is_array($unitIds) || $unitIds === []) {
            continue;
        }

        $resolvedCodes = [];
        $resolvedLabels = [];
        $sort = 0;
        foreach ($unitIds as $rawCode) {
            if (!is_string($rawCode) && !is_int($rawCode)) {
                continue;
            }
            $code = trim((string) $rawCode);
            if ($code === '') {
                continue;
            }
            // Accepte aussi un ID numérique déjà migré
            if (ctype_digit($code) && !isset($codeToId[$code])) {
                $mid = (int) $code;
                $chk = $pdo->prepare('SELECT code, display_name FROM military_units WHERE id = ? LIMIT 1');
                $chk->execute([$mid]);
                $found = $chk->fetch(PDO::FETCH_ASSOC);
                if ($found) {
                    $insert->execute([$tid, $mid, $sort++]);
                    $migrated++;
                    $resolvedCodes[] = (string) $found['code'];
                    $resolvedLabels[] = (string) $found['display_name'];
                    continue;
                }
            }
            if (!isset($codeToId[$code])) {
                $orphans++;
                echo "  [ATTENTION] Affiliation orpheline tenant={$tid} code={$code}\n";
                continue;
            }
            $mid = $codeToId[$code];
            $insert->execute([$tid, $mid, $sort++]);
            $migrated++;
            $st = $pdo->prepare('SELECT code, display_name FROM military_units WHERE id = ?');
            $st->execute([$mid]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                $resolvedCodes[] = (string) $u['code'];
                $resolvedLabels[] = (string) $u['display_name'];
            }
        }

        if ($resolvedCodes !== []) {
            $aff['unit_ids'] = $resolvedCodes;
            $aff['unit_labels'] = $resolvedLabels;
            $community['unit_affiliation'] = $aff;
            $settings['community'] = $community;
            $updateSettings->execute([json_encode($settings, JSON_UNESCAPED_UNICODE), $tid]);
        }
    }

    echo "  Affiliations migrées (inserts) : {$migrated}" . ($orphans > 0 ? " ; orphelins : {$orphans}" : '') . "\n";
};
