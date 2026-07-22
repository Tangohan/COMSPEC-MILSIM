<?php

/**
 * Smoke test génération QR (sans bootstrap app).
 *
 * Usage : php scripts/smoke-qr-png.php [texte]
 * Écrit storage/tmp/smoke-qr.png si OK.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/Services/Qr/QrPngGenerator.php';

// Autoload Endroid si vendor présent.
$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$payload = $argv[1] ?? 'https://athena.ttrd.fr/atak/connect/smoke-test';
$gen = new \App\Services\Qr\QrPngGenerator();
$out = $gen->png($payload, 400, 12, true);

if ($out === null || strncmp($out['body'], "\x89PNG", 4) !== 0) {
    fwrite(STDERR, "FAIL: QR unavailable (PNG only)\n");
    fwrite(STDERR, 'attempts: ' . implode(' | ', $gen->attempts()) . "\n");
    exit(1);
}

$dir = $root . '/storage/tmp';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$path = $dir . '/smoke-qr.png';
file_put_contents($path, $out['body']);

fwrite(STDOUT, "OK mime={$out['mime']} bytes=" . strlen($out['body']) . " file={$path}\n");
fwrite(STDOUT, 'gd=' . (extension_loaded('gd') ? 'yes' : 'no')
    . ' zlib=' . (function_exists('gzcompress') ? 'yes' : 'no')
    . ' endroid=' . (class_exists(\Endroid\QrCode\Builder\Builder::class) ? 'yes' : 'no')
    . "\n");
exit(0);
