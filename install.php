<?php
declare(strict_types=1);

// Afficher toutes les erreurs pour diagnostiquer un 500
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$isWeb = php_sapi_name() !== 'cli';

// Si erreur fatale en mode web, afficher le message (hébergeur peut bloquer display_errors)
if ($isWeb) {
    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }
            echo '<pre style="background:#fdd;padding:1em;">ERREUR FATALE: ' . htmlspecialchars($err['message'] ?? '') . "\nFichier: " . htmlspecialchars($err['file'] ?? '') . "\nLigne: " . ($err['line'] ?? '') . '</pre>';
        }
    });
}

/**
 * Script d'installation Athena — SaaS RH MILSIM Arma 3
 * Utilisation : en CLI « php install.php [--no-composer] [--no-migrate] [--no-seed] » ou par URL (navigateur).
 *
 * Assistant BDD dédié (formulaire hôte / utilisateur / mot de passe, puis migrations) :
 *   https://votre-site/public/install-database-wizard.php
 * (désactivé après création de storage/install.lock)
 */

const PHP_MIN_VERSION = '8.0';
const STORAGE_DIRS = ['logs', 'cache', 'sessions', 'uploads'];
$options = getopt('', ['no-composer', 'no-migrate', 'no-seed']) ?: [];
$root = dirname(__FILE__);

if ($isWeb) {
    header('Content-Type: text/html; charset=utf-8');
    ob_start();
}

try {

function ok(string $msg): void {
    echo "[OK] $msg\n";
}

function warn(string $msg): void {
    echo "[ATTENTION] $msg\n";
}

function err(string $msg): void {
    echo "[ERREUR] $msg\n";
    exit(1);
}

function run(string $cmd, string $cwd): bool {
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $p = proc_open($cmd, $desc, $pipes, $cwd);
    if (!is_resource($p)) {
        return false;
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    return proc_close($p) === 0;
}

echo "\n=== Installation Athena ===\n\n";

// 1. Version PHP
if (version_compare(PHP_VERSION, PHP_MIN_VERSION, '<')) {
    err("PHP " . PHP_MIN_VERSION . " requis. Version actuelle : " . PHP_VERSION);
}
ok("PHP " . PHP_VERSION);

// 2. Répertoires storage
$storageRoot = $root . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($storageRoot)) {
    if (!@mkdir($storageRoot, 0755, true)) {
        err("Impossible de créer le dossier storage.");
    }
    ok("Dossier storage créé.");
}
foreach (STORAGE_DIRS as $dir) {
    $path = $storageRoot . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($path)) {
        if (!@mkdir($path, 0755, true)) {
            err("Impossible de créer storage/$dir.");
        }
        $gitkeep = $path . DIRECTORY_SEPARATOR . '.gitkeep';
        if (!file_exists($gitkeep)) {
            file_put_contents($gitkeep, '');
        }
        ok("storage/$dir créé.");
    }
}

// 3. .env
$envExample = $root . DIRECTORY_SEPARATOR . '.env.example';
$env = $root . DIRECTORY_SEPARATOR . '.env';
if (!is_file($env)) {
    if (!is_file($envExample)) {
        err(".env.example introuvable.");
    }
    if (!@copy($envExample, $env)) {
        err("Impossible de copier .env.example vers .env.");
    }
    ok(".env créé à partir de .env.example — pensez à renseigner DB_* et JWT_SECRET.");
} else {
    ok(".env existe déjà.");
}

// 4. Migrations + seed (point d’entrée unique : setup-database.php)
if (empty($options['no-migrate']) || empty($options['no-seed'])) {
    $setupDb = $root . DIRECTORY_SEPARATOR . 'setup-database.php';
    if (is_file($setupDb)) {
        echo "Lancement : setup-database.php (schéma + migrations + seed) ...\n";
        if (run('php ' . escapeshellarg($setupDb), $root)) {
            ok("Migrations et seed exécutés (admin@athena.local / admin).");
        } else {
            warn("setup-database.php a échoué. Vérifiez .env (DB_*) ou ouvrez public/setup-database.php dans le navigateur.");
        }
    } else {
        warn("setup-database.php introuvable.");
    }
} else {
    ok("Migrations/seed ignorés.");
}

echo "\n=== Installation terminée ===\n";
echo "À faire :\n";
echo "  1. Configurer la BDD : éditer .env (DB_*) ou ouvrir public/install-database-wizard.php dans le navigateur.\n";
echo "  2. Éditer .env (APP_URL, JWT_SECRET, etc.).\n";
echo "  3. Pointer le document root du serveur web sur le dossier public/.\n";
echo "  4. Compte admin par défaut (si seed exécuté) : admin@athena.local / admin\n\n";

} catch (Throwable $e) {
    echo "\n[ERREUR] " . $e->getMessage() . "\n\n" . $e->getTraceAsString();
    if ($isWeb) {
        $out = ob_get_clean();
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Installation Athena</title></head><body><pre>' . htmlspecialchars($out) . '</pre></body></html>';
    }
    exit(1);
}

if ($isWeb) {
    $out = ob_get_clean();
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Installation Athena</title></head><body><pre>' . htmlspecialchars($out) . '</pre></body></html>';
}
