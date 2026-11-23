<?php

declare(strict_types=1);

/**
 * Recalcule la liste « files » (chemin, taille, SHA-256) de config/modpack_items.json à partir des dossiers
 * déjà construits du dépôt (champ « source » + motifs « include »). À relancer après chaque build de mod :
 * php tools/modpack/refresh_items.php. Aucun fichier n'est copié : seul le manifeste change.
 */

$root = dirname(__DIR__, 2);
$path = $root . '/config/modpack_items.json';
$manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

foreach ($manifest['items'] as &$item) {
    $item['files'] = [];
    if ($item['source'] === null) {
        continue;
    }
    $base = $root . '/' . $item['source'];
    $found = [];
    foreach ($item['include'] as $pattern) {
        foreach (glob($base . '/' . $pattern) ?: [] as $file) {
            if (is_file($file) && !is_link($file)) {
                $found[substr($file, strlen($base) + 1)] = $file;
            }
        }
    }
    ksort($found, SORT_STRING);
    foreach ($found as $relative => $file) {
        $item['files'][] = ['path' => $relative, 'size' => filesize($file), 'sha256' => hash_file('sha256', $file)];
    }
    fwrite(STDOUT, sprintf("%s : %d fichiers, %d octets\n", $item['id'], count($item['files']), array_sum(array_column($item['files'], 'size'))));
}
unset($item);

$lines = [];
foreach ($manifest['items'] as $item) {
    $files = $item['files'];
    $item['files'] = '__FILES__';
    $line = '    ' . json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $rows = array_map(
        static fn (array $f): string => '        ' . json_encode($f, JSON_UNESCAPED_SLASHES),
        $files
    );
    $lines[] = str_replace('"__FILES__"', $rows === [] ? '[]' : "[\n" . implode(",\n", $rows) . "\n    ]", $line);
}
file_put_contents($path, "{\n  \"version\": 1,\n  \"items\": [\n" . implode(",\n", $lines) . "\n  ]\n}\n");
