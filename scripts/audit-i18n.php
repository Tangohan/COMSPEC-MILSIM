#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Static, deterministic i18n audit for Athena's presentation layer.
 *
 * Usage: php scripts/audit-i18n.php [--json] [--strict]
 * --strict fails on missing catalogue keys/parity, not on the migration backlog.
 */

$root = dirname(__DIR__);
$json = in_array('--json', $argv, true);
$strict = in_array('--strict', $argv, true);

$run = static function (array $command) use ($root): string {
    $escaped = implode(' ', array_map('escapeshellarg', $command));
    $output = shell_exec('cd ' . escapeshellarg($root) . ' && ' . $escaped);
    return is_string($output) ? $output : '';
};

$tracked = array_values(array_filter(explode("\n", trim($run(['git', 'ls-files'])))));
$isPresentationFile = static function (string $path): bool {
    if (!preg_match('/\.(?:php|js|mjs|html)$/', $path)) {
        return false;
    }
    foreach (['vendor/', 'node_modules/', 'server/node_modules/', 'tcpdf/', 'phpqrcode/', 'tests/',
              'docs/', 'migrations/', 'bootstrap/', 'storage/', 'mod/', '-o/'] as $excluded) {
        if (str_starts_with($path, $excluded)) {
            return false;
        }
    }
    return str_starts_with($path, 'views/')
        || str_starts_with($path, 'public/')
        || str_starts_with($path, 'app/Controllers/')
        || str_starts_with($path, 'app/Support/')
        || str_starts_with($path, 'app/Core/');
};

$markers = '/(?:[éèêëàâçùûôîïÉÈÊËÀÂÇÙÛÔÎÏœŒ]|\b(?:Accueil|Ajouter|Annuler|Aucun(?:e)?|Chargement|Confirmer|Connexion|Déconnexion|Enregistrer|Erreur|Fermer|Modifier|Notification|Paramètres|Précédent|Profil|Recherche|Retour|Suivant|Supprimer|Utilisateur|Actualiser|Envoyer|Étape|Offre|Communauté|Organisation|Renseignement|Compte rendu)\b)/u';
$ignoreLine = '/^\s*(?:\/\/|\/\*|\*|#)|(?:error_log|logger|->log|console\.(?:debug|log))\s*\(/i';
$findings = [];
$filesScanned = 0;
foreach ($tracked as $path) {
    if (!$isPresentationFile($path) || !is_file($root . '/' . $path)) {
        continue;
    }
    ++$filesScanned;
    $lines = file($root . '/' . $path, FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $index => $line) {
        if (!preg_match($markers, $line) || preg_match($ignoreLine, trim($line))) {
            continue;
        }
        // Ignore PHP-only comments and already-localised source/fallback arguments.
        if (str_contains($line, 'i18n_phrase(') || preg_match('/\b(?:__|t)\s*\(/', $line)) {
            continue;
        }
        $findings[$path][] = ['line' => $index + 1, 'text' => trim(mb_substr($line, 0, 240))];
    }
}

$flatten = static function (array $values, string $prefix = '') use (&$flatten): array {
    $result = [];
    foreach ($values as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $result += $flatten($value, $full);
        } elseif (is_string($value)) {
            $result[$full] = $value;
        }
    }
    return $result;
};

$catalogs = [];
$parity = ['missing_en' => [], 'orphan_en' => []];
foreach (glob($root . '/lang/fr/*.php') ?: [] as $frFile) {
    $group = basename($frFile, '.php');
    $enFile = $root . '/lang/en/' . $group . '.php';
    $fr = $flatten((array) require $frFile);
    $en = is_file($enFile) ? $flatten((array) require $enFile) : [];
    $catalogs[$group] = ['fr' => count($fr), 'en' => count($en)];
    foreach (array_diff_key($fr, $en) as $key => $_) {
        $parity['missing_en'][] = $group . '.' . $key;
    }
    foreach (array_diff_key($en, $fr) as $key => $_) {
        // Phrase catalogues intentionally use the French source phrase as their FR side.
        if ($group !== 'nav') {
            $parity['orphan_en'][] = $group . '.' . $key;
        }
    }
}

$usedKeys = [];
foreach ($tracked as $path) {
    if (!preg_match('/\.(?:php|js|mjs)$/', $path) || !is_file($root . '/' . $path)) {
        continue;
    }
    $body = (string) file_get_contents($root . '/' . $path);
    preg_match_all('/(?<![a-z0-9_])(?:__|t)\(\s*[\'\"]([a-z][a-z0-9_-]+\.[a-z0-9_.-]+)[\'\"]/i', $body, $matches);
    foreach ($matches[1] ?? [] as $key) {
        if (str_ends_with($key, '_')) {
            continue; // Static prefix followed by a runtime suffix.
        }
        $usedKeys[$key] = true;
    }
}
$missingUsed = [];
foreach (array_keys($usedKeys) as $key) {
    [$group, $item] = explode('.', $key, 2);
    $file = $root . '/lang/en/' . $group . '.php';
    $entries = is_file($file) ? $flatten((array) require $file) : [];
    if (!array_key_exists($item, $entries)) {
        $missingUsed[] = $key;
    }
}
sort($missingUsed);

$routeSource = (string) @file_get_contents($root . '/routes/web.php');
preg_match_all('/\$router->(get|post|put|patch|delete)\(\s*[\'\"]([^\'\"]+)/i', $routeSource, $routeMatches, PREG_SET_ORDER);
$routes = [];
foreach ($routeMatches as $match) {
    $routes[] = strtoupper($match[1]) . ' /' . ltrim($match[2], '/');
}

$domains = [];
foreach ($findings as $path => $rows) {
    $parts = explode('/', $path);
    $domain = match ($parts[0]) {
        'views' => $parts[1] ?? 'root',
        'public' => 'client-assets',
        default => 'backend-ui',
    };
    $domains[$domain]['files'] = ($domains[$domain]['files'] ?? 0) + 1;
    $domains[$domain]['findings'] = ($domains[$domain]['findings'] ?? 0) + count($rows);
}
ksort($domains);

$result = [
    'generated_at' => gmdate(DATE_ATOM),
    'scope' => ['tracked_files' => count($tracked), 'presentation_files_scanned' => $filesScanned, 'routes' => count($routes)],
    'catalogs' => $catalogs,
    'catalog_parity' => $parity,
    'used_keys' => count($usedKeys),
    'missing_used_keys' => $missingUsed,
    'candidate_summary' => ['files' => count($findings), 'lines' => array_sum(array_map('count', $findings)), 'domains' => $domains],
    'routes' => $routes,
    'candidates' => $findings,
];

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    printf("Athena i18n audit\n=================\nScanned: %d presentation files; %d routes\n", $filesScanned, count($routes));
    printf("Candidate French UI: %d lines in %d files\n", $result['candidate_summary']['lines'], count($findings));
    printf("Missing EN catalogue entries: %d; used keys missing in EN: %d; orphan EN entries: %d\n",
        count($parity['missing_en']), count($missingUsed), count($parity['orphan_en']));
    foreach ($domains as $domain => $counts) {
        printf("  %-24s %5d lines / %4d files\n", $domain, $counts['findings'], $counts['files']);
    }
    echo "Run with --json for routes and line-level findings.\n";
}

if ($strict && ($parity['missing_en'] !== [] || $parity['orphan_en'] !== [] || $missingUsed !== [])) {
    exit(1);
}
