<?php

declare(strict_types=1);

/**
 * Lance les migrations robmorgan/phinx (fichiers PHP dans /migrations, table phinxlog).
 * Appelé depuis run-migrations.php après l’import du schéma de base.
 */
function run_phinx_migrate(string $root, callable $migrationFlush): void
{
    $autoload = $root . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        echo "  [ATTENTION] Composer non installé (vendor/autoload.php) — étape Phinx ignorée.\n";
        $migrationFlush();

        return;
    }

    require_once $autoload;

    $config = $root . '/config/phinx.php';
    if (!is_file($config)) {
        $dist = $root . '/config/phinx.php.dist';
        if (is_file($dist)) {
            copy($dist, $config);
            echo "  [INFO] Fichier config/phinx.php créé à partir de config/phinx.php.dist\n";
            $migrationFlush();
        } else {
            echo "  [ATTENTION] config/phinx.php introuvable — étape Phinx ignorée.\n";
            $migrationFlush();

            return;
        }
    }

    // Binaire PHP du package (évite les soucis Windows avec .bat).
    $phinxPhp = $root . '/vendor/robmorgan/phinx/bin/phinx';
    if (!is_file($phinxPhp)) {
        $phinxPhp = $root . '/vendor/bin/phinx';
    }
    if (!is_file($phinxPhp)) {
        echo "  [ATTENTION] binaire Phinx introuvable — exécutez composer install. Étape Phinx ignorée.\n";
        $migrationFlush();

        return;
    }

    echo "Migrations Phinx (migrations/*.php, table phinxlog)...\n";
    $migrationFlush();

    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $previousCwd = getcwd();
    try {
        chdir($root);
        $line = escapeshellarg($php) . ' '
            . escapeshellarg($phinxPhp) . ' migrate --configuration='
            . escapeshellarg($config) . ' --environment=default --no-interaction';
        passthru($line, $exitCode);
        if ($exitCode !== 0) {
            echo "[ATTENTION] Phinx migrate a retourné le code {$exitCode}.\n";
        } else {
            echo "[OK] Migrations Phinx appliquées.\n";
        }
    } catch (Throwable $e) {
        echo '[ATTENTION] Phinx migrate : ' . $e->getMessage() . "\n";
    } finally {
        if ($previousCwd !== false) {
            @chdir($previousCwd);
        }
    }
    $migrationFlush();
}
