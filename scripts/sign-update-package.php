<?php

declare(strict_types=1);

/**
 * Calcule le checksum payload et (optionnel) la signature HMAC d’un dossier package déjà assemblé.
 *
 * Usage :
 *   php scripts/sign-update-package.php /chemin/vers/package
 *   UPDATE_PACKAGE_HMAC_SECRET=... php scripts/sign-update-package.php /chemin/vers/package
 */

$root = dirname(__DIR__);
require_once $root . '/bootstrap/env.php';
load_env($root);
require_once $root . '/bootstrap/autoload.php';

$packageRoot = $argv[1] ?? '';
if ($packageRoot === '' || !is_dir($packageRoot)) {
    fwrite(STDERR, "Usage: php scripts/sign-update-package.php <dossier-package>\n");
    exit(1);
}

$packageRoot = rtrim($packageRoot, '/\\');
$manifestPath = $packageRoot . DIRECTORY_SEPARATOR . 'manifest.json';
if (!is_file($manifestPath)) {
    fwrite(STDERR, "manifest.json manquant.\n");
    exit(1);
}

$compute = static function (string $packageRoot): string {
    $parts = [];
    foreach (['files', 'migrations', 'scripts'] as $dir) {
        $base = $packageRoot . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );
        $baseLen = strlen(rtrim($base, '/\\')) + 1;
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($file->getPathname(), $baseLen));
            $parts[] = $dir . '/' . $rel . ':' . hash_file('sha256', $file->getPathname());
        }
    }
    sort($parts, SORT_STRING);

    return hash('sha256', implode("\n", $parts));
};

$checksum = $compute($packageRoot);
$manifest = json_decode((string) file_get_contents($manifestPath), true);
if (!is_array($manifest)) {
    fwrite(STDERR, "manifest.json invalide.\n");
    exit(1);
}

$manifest['checksum'] = $checksum;
$verifier = new \App\Services\Deployment\PackageSignatureVerifier();
if ($verifier->isEnforced()) {
    $manifest['signature'] = $verifier->sign(
        (string) ($manifest['version'] ?? ''),
        (string) ($manifest['minimum_version'] ?? ''),
        $checksum
    );
}

file_put_contents(
    $manifestPath,
    json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
);

echo "checksum={$checksum}\n";
if (isset($manifest['signature'])) {
    echo "signature={$manifest['signature']}\n";
}
echo "manifest.json mis à jour.\n";
