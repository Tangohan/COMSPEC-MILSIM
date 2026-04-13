<?php

declare(strict_types=1);

/**
 * Compare les ensembles de tables déclarées dans deux dumps MySQL/MariaDB (structure).
 * Usage :
 *   php scripts/compare-sql-dump-schemas.php [--verbose] <dump_reference.sql> <dump_a_comparer.sql>
 *   php scripts/compare-sql-dump-schemas.php --list-tables <dump.sql>
 *
 * La référence est typiquement un mysqldump --no-data obtenu après run-migrations.php + phinx migrate sur une base vierge.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$argv = array_values(array_slice($argv, 1));
$verbose = false;
if (($k = array_search('--verbose', $argv, true)) !== false) {
    $verbose = true;
    unset($argv[$k]);
    $argv = array_values($argv);
}

if ($argv[0] ?? '' === '--list-tables') {
    $path = $argv[1] ?? '';
    if ($path === '' || !is_readable($path)) {
        fwrite(STDERR, "Usage: php scripts/compare-sql-dump-schemas.php --list-tables <dump.sql>\n");
        exit(1);
    }
    $tables = extract_create_tables_from_dump($path);
    ksort($tables);
    foreach (array_keys($tables) as $name) {
        echo $name . "\n";
    }
    echo "\nTotal : " . count($tables) . " table(s)\n";
    exit(0);
}

if (count($argv) < 2) {
    fwrite(STDERR, "Usage: php scripts/compare-sql-dump-schemas.php [--verbose] <reference.sql> <autre.sql>\n");
    exit(1);
}

[$refPath, $otherPath] = $argv;
foreach ([$refPath, $otherPath] as $p) {
    if (!is_readable($p)) {
        fwrite(STDERR, "Fichier illisible : {$p}\n");
        exit(1);
    }
}

$refTables = extract_create_tables_from_dump($refPath);
$otherTables = extract_create_tables_from_dump($otherPath);

$onlyRef = array_diff(array_keys($refTables), array_keys($otherTables));
$onlyOther = array_diff(array_keys($otherTables), array_keys($refTables));
$both = array_intersect(array_keys($refTables), array_keys($otherTables));

echo "Référence : {$refPath} (" . count($refTables) . " tables)\n";
echo "Comparé   : {$otherPath} (" . count($otherTables) . " tables)\n\n";

if ($onlyRef !== []) {
    sort($onlyRef);
    echo "Tables présentes uniquement dans la RÉFÉRENCE (" . count($onlyRef) . ") :\n";
    foreach ($onlyRef as $t) {
        echo "  + {$t}\n";
    }
    echo "\n";
}

if ($onlyOther !== []) {
    sort($onlyOther);
    echo "Tables présentes uniquement dans le dump COMPARÉ (" . count($onlyOther) . ") :\n";
    foreach ($onlyOther as $t) {
        echo "  - {$t}\n";
    }
    echo "\n";
}

if ($verbose && $both !== []) {
    echo "Tables communes : " . count($both) . " (détail colonnes non comparé dans cette version)\n\n";
}

if ($onlyRef === [] && $onlyOther === []) {
    echo "Aucune différence d’ensemble de tables entre les deux dumps.\n";
    exit(0);
}

exit(2);

/**
 * @return array<string, string> nom de table => bloc CREATE brut (approximatif, jusqu’à la ligne du closing paren)
 */
function extract_create_tables_from_dump(string $path): array
{
    $content = file_get_contents($path);
    if ($content === false) {
        return [];
    }

    // Normaliser fins de ligne
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    $tables = [];
    $pattern = '/^CREATE TABLE (?:IF NOT EXISTS )?`([^`]+)`\s*\(/mi';

    if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[1] as $idx => $m) {
            $name = $m[0];
            $start = $matches[0][$idx][1];
            $nextStart = $matches[0][$idx + 1][1] ?? strlen($content);
            $block = substr($content, $start, $nextStart - $start);
            $tables[$name] = trim($block);
        }
    }

    return $tables;
}
