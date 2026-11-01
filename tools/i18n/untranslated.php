<?php

declare(strict_types=1);

/**
 * Rapport des textes français écrits en dur sans traduction anglaise (lang/en/site_auto.php).
 *
 *   php tools/i18n/untranslated.php                     views/ et public/assets/js : « fichier<TAB>texte »
 *   php tools/i18n/untranslated.php views/admin/effectifs_workspace public/assets/js/foo.js
 *   php tools/i18n/untranslated.php --count             nombre de textes par fichier, du plus fourni au moins
 *   php tools/i18n/untranslated.php --tsv=chemin.tsv    écrit « texte<TAB>fichier » (à traduire puis
 *                                                       python3 tools/i18n/site_i18n.py merge)
 *
 * Heuristique (App\Support\I18n\UntranslatedFrenchScanner) : accent ou mot français hors appels __() / t(). Code de
 * sortie 1 s'il reste des textes.
 */

$root = dirname(__DIR__, 2);
require $root . '/app/Support/helpers.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use App\Support\I18n\UntranslatedFrenchScanner;

$count = false;
$tsv = null;
$targets = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--count') {
        $count = true;
    } elseif (str_starts_with($arg, '--tsv=')) {
        $tsv = substr($arg, 6);
    } else {
        $targets[] = $arg;
    }
}
if ($targets === []) {
    $targets = ['views', 'public/assets/js'];
}

$files = [];
foreach ($targets as $t) {
    $path = is_file($t) || is_dir($t) ? $t : $root . '/' . $t;
    if (is_file($path)) {
        $files[] = $path;
        continue;
    }
    if (!is_dir($path)) {
        fwrite(STDERR, "Introuvable : $t\n");
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = str_replace('\\', '/', (string) $f);
        if (preg_match('~\.min\.js$|/vendor/|i18n_en_dict\.js$~', $p)) {
            continue;
        }
        if (str_ends_with($p, '.php') || (str_ends_with($p, '.js') && str_contains($p, 'assets/js'))) {
            $files[] = $p;
        }
    }
}
sort($files);

$total = 0;
$perFile = [];
$rows = [];
foreach ($files as $f) {
    $rel = ltrim(str_replace([str_replace('\\', '/', $root), '\\'], ['', '/'], str_replace('\\', '/', $f)), '/');
    $missing = UntranslatedFrenchScanner::missingInFile($f);
    if ($missing === []) {
        continue;
    }
    $perFile[$rel] = count($missing);
    $total += count($missing);
    foreach ($missing as $s) {
        $rows[] = [$rel, $s];
    }
}

if ($tsv !== null) {
    $seen = [];
    $lines = '';
    foreach ($rows as [$rel, $s]) {
        if (!isset($seen[$s])) {
            $seen[$s] = true;
            $lines .= $s . "\t" . $rel . "\n";
        }
    }
    file_put_contents($tsv, $lines);
}
if ($count) {
    arsort($perFile);
    foreach ($perFile as $rel => $n) {
        echo str_pad((string) $n, 6, ' ', STR_PAD_LEFT), "  $rel\n";
    }
} elseif ($tsv === null) {
    foreach ($rows as [$rel, $s]) {
        echo "$rel\t$s\n";
    }
}
fwrite(STDERR, $total . ' texte(s) sans traduction dans ' . count($perFile) . " fichier(s)\n");
exit($total > 0 ? 1 : 0);
