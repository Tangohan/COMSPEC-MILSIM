<?php

declare(strict_types=1);

/**
 * Tables des 6 piliers C2 Overwatch (appui-feu, danger zones, logistique, intel, replay, IFF).
 * Fichier SQL migrations/c2_pillars.sql — idempotent via run-migrations.php.
 */
return static function (PDO $pdo): void {
    $path = dirname(__DIR__) . '/migrations/c2_pillars.sql';
    if (!is_file($path)) {
        echo "  [ATTENTION] Fichier absent : migrations/c2_pillars.sql\n";

        return;
    }

    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $expected = [
        'fire_units',
        'fire_tables',
        'danger_zones',
        'asset_logistics_status',
        'asset_logistics_status_history',
        'intel_reports',
        'intel_reports_events',
        'logs_positions',
        'iff_challenges',
        'iff_asset_status',
    ];
    $allPresent = true;
    foreach ($expected as $t) {
        if (!$tableExists($pdo, $t)) {
            $allPresent = false;
            break;
        }
    }
    if ($allPresent) {
        echo "  [SKIP] piliers C2 déjà présents\n";

        return;
    }

    $sql = (string) file_get_contents($path);
    // Retirer SET … et découper.
    $sql = preg_replace('/^\s*SET\s+[^;]+;/mi', '', $sql) ?? $sql;

    $statements = [];
    $buf = '';
    $len = strlen($sql);
    $inString = false;
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($ch === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) {
            $inString = !$inString;
            $buf .= $ch;
            continue;
        }
        if ($ch === ';' && !$inString) {
            $stmt = trim($buf);
            $buf = '';
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            continue;
        }
        $buf .= $ch;
    }
    $tail = trim($buf);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    } catch (Throwable) {
    }

    foreach ($statements as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        try {
            $pdo->exec($stmt);
            if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?(\w+)/i', $stmt, $m)) {
                echo "  [OK] {$m[1]}\n";
            }
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (
                str_contains($msg, 'already exists')
                || str_contains($msg, 'Duplicate')
                || str_contains($msg, '1060')
                || str_contains($msg, '1061')
                || str_contains($msg, '1050')
            ) {
                continue;
            }
            echo '  [ATTENTION] c2_pillars : ' . $msg . "\n";
        }
    }

    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable) {
    }

    $missing = [];
    foreach ($expected as $t) {
        if (!$tableExists($pdo, $t)) {
            $missing[] = $t;
        }
    }
    if ($missing === []) {
        echo "  [OK] toutes les tables piliers C2 sont présentes\n";
    } else {
        echo '  [ATTENTION] tables C2 encore absentes : ' . implode(', ', $missing) . "\n";
    }
};
