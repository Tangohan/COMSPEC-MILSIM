<?php

declare(strict_types=1);

/**
 * Contrôle d'intégrité du déploiement.
 *
 * À exécuter **sur le serveur, après un envoi FTP** :
 *
 *     php tools/audit-integrite.php
 *
 * Le déploiement se fait par transfert manuel, fichier par fichier. La panne
 * caractéristique n'est donc pas un bug de code mais un fichier oublié — et elle
 * ne se voit pas toujours : une vue absente ne provoque aucune erreur, elle rend
 * une page blanche en HTTP 200. Ce script cherche exactement ces manques.
 *
 * Les quatre contrôles ci-dessous ont chacun attrapé un défaut réel :
 *
 *   1. Route pointant vers une classe ou une méthode absente
 *      → HTTP 500 (déjà vu : AtakOrderWaypoint, ArmaMarkerLabel).
 *   2. Vue référencée mais absente
 *      → page blanche silencieuse, sans trace dans les journaux.
 *   3. Classe qui ne se charge pas
 *      → `php -l` ne le détecte pas : une méthode déclarée deux fois passe
 *        l'analyse syntaxique et échoue à la compilation de la classe
 *        (déjà vu : SystemAuditController::rollback).
 *   4. Méthode déclarée deux fois dans un même fichier
 *      → la cause du point 3, signalée directement et par son nom.
 *
 * ## Isolation
 *
 * Tout contrôle qui doit charger une classe tourne dans un sous-processus. Sans
 * cela, un seul fichier fautif tue l'audit avant qu'il n'ait rien signalé — c'est
 * arrivé au premier essai, la vérification des routes chargeant les contrôleurs
 * dans le processus principal.
 *
 * Sortie : la liste des anomalies, et un code de retour 1 s'il y en a, 0 sinon —
 * de quoi l'enchaîner dans un script de déploiement.
 */

$root = dirname(__DIR__);

// ---------------------------------------------------------------------------
// Sous-processus : vérification des routes (charge les contrôleurs)
// ---------------------------------------------------------------------------
if (($argv[1] ?? '') === '--routes') {
    require $root . '/bootstrap/autoload.php';

    $routesFile = $root . '/routes/web.php';
    if (!is_file($routesFile)) {
        echo "routes/web.php est absent.\n";
        exit(0);
    }
    $src = file_get_contents($routesFile);

    // Les contrôleurs sont importés par des « use » : sans les résoudre, on croit
    // à des centaines de classes manquantes qui existent très bien.
    preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?\s*;/m', $src, $u, PREG_SET_ORDER);
    $alias = [];
    foreach ($u as $x) {
        $alias[$x[2] ?? substr($x[1], strrpos($x[1], '\\') + 1)] = $x[1];
    }

    preg_match_all(
        '/\[\s*(\\\\?[A-Za-z0-9_\\\\]+)::class\s*,\s*[\'"]([A-Za-z0-9_]+)[\'"]\s*\]/',
        $src,
        $m,
        PREG_SET_ORDER
    );

    $seen = [];
    foreach ($m as $x) {
        $ref = ltrim($x[1], '\\');
        $meth = $x[2];
        $cls = str_contains($ref, '\\') ? $ref : ($alias[$ref] ?? $ref);
        if (isset($seen[$cls . '::' . $meth])) {
            continue;
        }
        $seen[$cls . '::' . $meth] = true;

        if (!class_exists($cls)) {
            printf("Route → classe introuvable : %s\n", $cls);
        } elseif (!method_exists($cls, $meth)) {
            printf("Route → méthode absente : %s::%s()\n", $cls, $meth);
        }
    }
    printf("#%d\n", count($seen));
    exit(0);
}

// ---------------------------------------------------------------------------
// Processus principal
// ---------------------------------------------------------------------------
$problems = [];
$counts = ['routes' => 0, 'vues' => 0, 'classes' => 0];

// --- 1. Routes, en sous-processus ---
$out = [];
$code = 0;
exec(sprintf('php %s --routes 2>&1', escapeshellarg(__FILE__)), $out, $code);
if ($code !== 0) {
    $problems[] = 'Le contrôle des routes s\'est interrompu — une classe atteinte '
        . 'depuis les routes est fautive : ' . trim(implode(' ', array_slice($out, 0, 3)));
} else {
    foreach ($out as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if ($line[0] === '#') {
            $counts['routes'] = (int) substr($line, 1);
            continue;
        }
        $problems[] = $line;
    }
}

// --- 2. Vues référencées (texte seul, ne peut pas échouer) ---
$refs = [];
foreach (phpFiles($root . '/app') as $file) {
    if (preg_match_all(
        '/(?:Response::view|portalView|->view)\s*\(\s*[\'"]([a-zA-Z0-9_.\-\/]+)[\'"]/',
        file_get_contents($file),
        $m
    )) {
        foreach ($m[1] as $v) {
            $refs[$v][] = shortPath($file, $root);
        }
    }
}
foreach ($refs as $view => $where) {
    if (!is_file($root . '/views/' . str_replace('.', '/', $view) . '.php')) {
        $problems[] = sprintf(
            'Vue absente (page blanche en HTTP 200) : %s ← %s',
            $view,
            implode(', ', array_unique($where))
        );
    }
}
$counts['vues'] = count($refs);

// --- 3 et 4. Doublons de méthode, puis chargement réel ---
foreach (phpFiles($root . '/app') as $file) {
    $src = file_get_contents($file);

    // Contrôle textuel d'abord : il désigne le fichier et le nom fautifs, là où
    // le message natif de PHP ne dit pas toujours où chercher.
    if (preg_match_all(
        '/^\s*(?:public|private|protected|static|final|abstract|\s)*function\s+([A-Za-z0-9_]+)\s*\(/m',
        $src,
        $fm
    )) {
        foreach (array_count_values($fm[1]) as $name => $n) {
            if ($n > 1 && $name !== '__construct') {
                $problems[] = sprintf(
                    'Méthode déclarée %d fois : %s() dans %s',
                    $n,
                    $name,
                    shortPath($file, $root)
                );
            }
        }
    }

    if (!preg_match('/^\s*namespace\s+([A-Za-z0-9_\\\\]+)\s*;/m', $src, $ns)) {
        continue;
    }
    if (!preg_match('/^\s*(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+([A-Za-z0-9_]+)/m', $src, $cn)) {
        continue;
    }
    $fqcn = $ns[1] . '\\' . $cn[1];
    $counts['classes']++;

    $out = [];
    $code = 0;
    exec(
        sprintf('php -r %s 2>&1', escapeshellarg(sprintf(
            'require %s; $c = %s; if (!class_exists($c) && !interface_exists($c) && !trait_exists($c) && !enum_exists($c)) { exit(2); }',
            var_export($root . '/bootstrap/autoload.php', true),
            var_export($fqcn, true)
        ))),
        $out,
        $code
    );
    if ($code !== 0) {
        $problems[] = sprintf(
            'Classe non chargeable : %s (%s)%s',
            $fqcn,
            shortPath($file, $root),
            $out !== [] ? ' — ' . trim(implode(' ', array_slice($out, 0, 2))) : ''
        );
    }
}

// ---------------------------------------------------------------------------
// Restitution
// ---------------------------------------------------------------------------
printf(
    "Contrôle d'intégrité — %d routes, %d vues référencées, %d classes\n\n",
    $counts['routes'],
    $counts['vues'],
    $counts['classes']
);

$problems = array_values(array_unique($problems));
if ($problems === []) {
    echo "Aucune anomalie.\n";
    exit(0);
}

printf("%d anomalie(s) :\n", count($problems));
foreach ($problems as $p) {
    echo '  - ' . $p . "\n";
}
echo "\nUn fichier manquant vient presque toujours d'un envoi incomplet : "
    . "reprenez DEPLOY.md avant de chercher plus loin.\n";
exit(1);

/**
 * @return list<string>
 */
function phpFiles(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $out = [];
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    ) as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $out[] = $f->getPathname();
        }
    }
    sort($out);

    return $out;
}

function shortPath(string $path, string $root): string
{
    return ltrim(str_replace($root, '', $path), '/');
}
