<?php

declare(strict_types=1);

/**
 * Rapport de vérification en fin de pipeline (tables, .env, données de démonstration).
 * L’application des fichiers migrations/*.sql est faite par bootstrap/migration_runner.php.
 */

/**
 * @return list<string>
 */
function comspec_expected_tables_from_schema_sql(string $schemaPath): array
{
    $raw = @file_get_contents($schemaPath);
    if ($raw === false || $raw === '') {
        return [];
    }

    if (!preg_match_all(
        '/^\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`([^`]+)`|([a-zA-Z0-9_]+))\s*\(/im',
        $raw,
        $m,
        PREG_SET_ORDER
    )) {
        return [];
    }

    $names = [];
    foreach ($m as $row) {
        $t = trim((string) ($row[1] ?? ''));
        if ($t === '') {
            $t = trim((string) ($row[2] ?? ''));
        }
        if ($t !== '') {
            $names[] = $t;
        }
    }

    return array_values(array_unique($names));
}

/**
 * @param callable():void $flush
 */
function comspec_print_post_migration_report(PDO $pdo, string $root, callable $flush): void
{
    echo "\n=== Rapport de vérification (post-migration) ===\n";
    $flush();

    // --- Tables réelles ---
    $actual = [];
    try {
        $st = $pdo->query(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\' ORDER BY TABLE_NAME'
        );
        if ($st) {
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $n = (string) ($row['TABLE_NAME'] ?? '');
                if ($n !== '') {
                    $actual[] = $n;
                }
            }
            $st->closeCursor();
        }
    } catch (PDOException $e) {
        echo '[ATTENTION] Lecture information_schema : ' . $e->getMessage() . "\n";
        $flush();
    }

    echo 'Tables en base (BASE TABLE) : ' . count($actual) . "\n";
    $flush();

    $schemaPath = $root . '/migrations/schema.sql';
    $expected = comspec_expected_tables_from_schema_sql($schemaPath);
    if ($expected === []) {
        echo "[ATTENTION] Aucune table extraite de migrations/schema.sql (fichier absent ou vide ?).\n";
        $flush();
    } else {
        $missing = array_values(array_diff($expected, $actual));
        $extra = array_values(array_diff($actual, $expected));

        echo 'Tables attendues (CREATE TABLE dans schema.sql) : ' . count($expected) . "\n";
        if ($missing !== []) {
            echo 'Manquantes par rapport au schéma de référence : ' . count($missing) . "\n";
            foreach (array_slice($missing, 0, 50) as $t) {
                echo "  - {$t}\n";
            }
            if (count($missing) > 50) {
                echo '  … +' . (count($missing) - 50) . " autre(s)\n";
            }
        } else {
            echo "Aucune table du schéma de référence signalée comme manquante.\n";
        }
        if ($extra !== []) {
            echo 'Tables en base non listées dans schema.sql : ' . count($extra) . " (souvent normal : modules SQL / extensions DDL).\n";
            foreach (array_slice($extra, 0, 30) as $t) {
                echo "  + {$t}\n";
            }
            if (count($extra) > 30) {
                echo '  … +' . (count($extra) - 30) . " autre(s)\n";
            }
        }
        $flush();
    }

    // --- Ancienne table phinxlog (outil retiré) : information seulement ---
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'phinxlog'");
        if ($chk) {
            $has = (bool) $chk->fetch();
            $chk->closeCursor();
            if ($has) {
                echo "Table phinxlog : encore présente en base (héritage ; le projet n’utilise plus Phinx).\n";
            }
        }
    } catch (PDOException $e) {
        echo '[ATTENTION] Lecture phinxlog : ' . $e->getMessage() . "\n";
    }
    $flush();

    // --- Variables application (.env / environnement) ---
    $get = static function (string $k): string {
        $v = $_ENV[$k] ?? getenv($k);

        return is_string($v) ? $v : '';
    };

    echo "\nVariables APP (diagnostic, non secrets) :\n";
    foreach (['APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_BASE_PATH', 'APP_LOCALE', 'APP_TIMEZONE'] as $k) {
        $v = $get($k);
        echo '  ' . $k . ' = ' . ($v === '' ? '(vide)' : $v) . "\n";
    }
    foreach (['JWT_SECRET', 'APP_KEY'] as $k) {
        $v = $get($k);
        echo '  ' . $k . ' = ' . ($v === '' ? 'absent' : 'présent (valeur masquée)') . "\n";
    }
    $flush();

    // --- Données de démonstration interdites en production ---
    try {
        $demoUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(email) LIKE '%@demo.local'")->fetchColumn();
        $demoTenants = (int) $pdo->query("SELECT COUNT(*) FROM tenants WHERE slug = 'demo-comspec'")->fetchColumn();
        if ($demoUsers === 0 && $demoTenants === 0) {
            echo "[OK] Aucun compte ni communauté de démonstration.\n";
        } else {
            echo "[ERREUR] Données de démonstration restantes : {$demoUsers} compte(s), {$demoTenants} communauté(s).\n";
        }
    } catch (PDOException $e) {
        echo '[ERREUR] Vérification des données de démonstration impossible : ' . $e->getMessage() . "\n";
    }

    echo "\n=== Fin du rapport ===\n";
    $flush();
}
