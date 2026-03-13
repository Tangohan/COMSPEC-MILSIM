#!/usr/bin/env php
<?php

/**
 * Lance composer install (dépendances PHP).
 * CLI : php composer.php
 * Web : https://votre-site.fr/composer.php (via public/composer.php)
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$root = dirname(__FILE__);
$isWeb = php_sapi_name() !== 'cli';

if ($isWeb) {
    header('Content-Type: text/html; charset=utf-8');
    ob_start();
}

echo "=== Composer install ===\n\n";

$composerPhar = $root . DIRECTORY_SEPARATOR . 'composer.phar';
$composerCmd = is_file($composerPhar) ? 'php composer.phar' : 'composer';
$cmd = $composerCmd . ' install --no-interaction';

$desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$p = proc_open($cmd, $desc, $pipes, $root);

if (!is_resource($p)) {
    echo "[ERREUR] Impossible d'exécuter composer (proc_open désactivé ?).\n";
    echo "Exécutez en ligne de commande : cd " . $root . " && composer install\n";
    if ($isWeb) {
        $out = ob_get_clean();
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Composer</title></head><body><pre>' . htmlspecialchars($out) . '</pre></body></html>';
    }
    exit(1);
}

// Afficher la sortie de composer
while (!feof($pipes[1])) {
    $line = fgets($pipes[1]);
    if ($line !== false) {
        echo $line;
    }
}
while (!feof($pipes[2])) {
    $line = fgets($pipes[2]);
    if ($line !== false) {
        echo $line;
    }
}
fclose($pipes[1]);
fclose($pipes[2]);

$code = proc_close($p);

if ($code !== 0) {
    echo "\n[ATTENTION] Composer a quitté avec le code " . $code . ".\n";
    if ($isWeb) {
        $out = ob_get_clean();
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Composer</title></head><body><pre>' . htmlspecialchars($out) . '</pre></body></html>';
    }
    exit(1);
}

echo "\n[OK] Composer install terminé.\n";

if ($isWeb) {
    $out = ob_get_clean();
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Composer</title></head><body><pre>' . htmlspecialchars($out) . '</pre></body></html>';
}
