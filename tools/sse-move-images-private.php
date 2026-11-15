<?php

declare(strict_types=1);

/**
 * Déplace les images SSE sensibles EXISTANTES (portraits d'identité, signatures, fonds de papiers, copies de la photothèque,
 * signatures tracées au SEEK II) de public/uploads/sse-id-* vers storage/private/sse-images, et met à jour le chemin en base.
 *
 *   php tools/sse-move-images-private.php                      SIMULATION (par défaut) : liste, tailles, collisions ; rien n'est écrit
 *   php tools/sse-move-images-private.php --apply              déplace (copie, contrôle taille + SHA-256, base, puis suppression publique)
 *   php tools/sse-move-images-private.php --apply --limit=50   seulement les 50 premiers fichiers (essai prudent)
 *   php tools/sse-move-images-private.php --tenant=7           une seule communauté
 *   php tools/sse-move-images-private.php --rollback=storage/logs/sse-move-images-AAAAMMJJ-HHMMSS.jsonl
 *                                                              retour arrière d'après un journal (remet public + ancien chemin)
 *
 * Options de test : --public=DIR --private=DIR --log=FICHIER. Le moteur est App\Services\Sse\SseImageMigration.
 * Procédure complète (sauvegarde, vérification, règle nginx, retour arrière) : docs/sse-images-hors-public.md.
 *
 * Code de retour : 0 sans erreur, 1 si une étape a échoué (rien n'est alors supprimé pour ce fichier), 2 si l'appel est invalide.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

$opts = getopt('', ['apply', 'limit:', 'tenant:', 'rollback:', 'public:', 'private:', 'log:', 'help']);
if (isset($opts['help'])) {
    echo "Voir l'en-tête de tools/sse-move-images-private.php et docs/sse-images-hors-public.md\n";
    exit(0);
}
foreach (['limit', 'tenant'] as $k) {
    if (isset($opts[$k]) && (!ctype_digit((string) $opts[$k]) || (int) $opts[$k] < 1)) {
        fwrite(STDERR, "--$k attend un entier positif.\n");
        exit(2);
    }
}
$apply = isset($opts['apply']);
$limit = isset($opts['limit']) ? (int) $opts['limit'] : null;
$tenant = isset($opts['tenant']) ? (int) $opts['tenant'] : null;
$logFile = isset($opts['log']) ? (string) $opts['log'] : $root . '/storage/logs/sse-move-images-' . gmdate('Ymd-His') . '.jsonl';

$mig = new App\Services\Sse\SseImageMigration(
    App\Core\Database::getPdo(),
    isset($opts['public']) ? (string) $opts['public'] : null,
    isset($opts['private']) ? (string) $opts['private'] : null,
    $logFile
);

if (isset($opts['rollback'])) {
    $r = $mig->rollback((string) $opts['rollback']);
    foreach ($r['items'] as $i) {
        echo sprintf("  %-9s %s%s\n", $i['status'], $i['from'], isset($i['detail']) ? ' : ' . $i['detail'] : '');
    }
    echo sprintf("Retour arrière : %d remis, %d erreur(s).\n", $r['restored'], $r['errors']);
    exit($r['errors'] > 0 || isset($r['error']) ? 1 : 0);
}

echo $apply ? "MODE --apply : les fichiers sont déplacés.\n" : "SIMULATION (aucune écriture). Ajoutez --apply pour déplacer.\n";
$report = $mig->run($apply, $tenant, $limit);
foreach ($mig->missingTables() as $m) {
    echo "  (colonne absente, ignorée : $m)\n";
}
foreach ($report['items'] as $i) {
    $rows = array_map(static fn (array $r): string => $r['table'] . '.' . $r['column'] . '#' . $r['id'], $i['rows']);
    echo sprintf("  %-9s %s -> %s  %d o  [%s]%s\n", $i['status'], $i['from'], $i['to'], $i['bytes'], implode(', ', $rows), $i['detail'] !== '' ? '  ' . $i['detail'] : '');
}
echo sprintf(
    "\n%d fichier(s), %s Mo au total. Déplacés : %d, repris : %d, collisions : %d, introuvables : %d, erreurs : %d.\n",
    $report['files'],
    number_format($report['bytes'] / 1048576, 2, '.', ''),
    $report['moved'],
    $report['reused'],
    $report['collisions'],
    $report['missing'],
    $report['errors']
);
if ($apply) {
    echo "Journal : $logFile\n";
}
exit($report['errors'] > 0 ? 1 : 0);
