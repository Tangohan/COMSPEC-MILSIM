<?php

declare(strict_types=1);

/**
 * Autoload : vendor/autoload.php (Composer) si présent, sinon App\ + helpers à la main.
 */
$root = dirname(__DIR__);

require_once $root . '/bootstrap/env.php';
load_env($root);

$vendorAutoload = $root . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    spl_autoload_register(function (string $class): void {
        $prefix = 'App\\';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $rel = substr($class, $len);
        $path = dirname(__DIR__) . '/app/' . str_replace('\\', DIRECTORY_SEPARATOR, $rel) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });

    require $root . '/app/Support/helpers.php';
}
