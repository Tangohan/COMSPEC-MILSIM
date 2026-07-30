<?php

declare(strict_types=1);

/**
 * Autoload : vendor/autoload.php (Composer) si présent, sinon App\ + helpers à la main.
 */
$root = dirname(__DIR__);

require_once $root . '/bootstrap/env.php';
load_env($root);

/**
 * Repli PSR-4 pour App\ — enregistré en fin de chaîne (Composer garde la priorité).
 * Utile en production : le déploiement se fait par upload de fichiers, sans
 * `composer dump-autoload`. Un classmap optimisé périmé ne doit pas faire disparaître
 * une classe dont le fichier est bien présent.
 */
$registerAppFallbackAutoloader = static function () use ($root): void {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'App\\';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $rel = substr($class, $len);
        if (str_contains($rel, '..')) {
            return;
        }
        $path = $root . '/app/' . str_replace('\\', DIRECTORY_SEPARATOR, $rel) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
};

$vendorAutoload = $root . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
    $registerAppFallbackAutoloader();
} else {
    $registerAppFallbackAutoloader();

    require $root . '/app/Support/helpers.php';
}

require_once __DIR__ . '/load_phpmailer.php';
