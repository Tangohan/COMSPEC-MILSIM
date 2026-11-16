<?php

declare(strict_types=1);

/**
 * Re-détection Cerbère-Lecture : rejoue le nouveau moteur (mots entiers, exclusions, négations) sur les remontées encore
 * ouvertes et retire, avec une trace, les propositions que l'ancienne lecture par sous-chaîne affichait à tort
 * (« otage » trouvé dans « sabotage »…).
 *
 *   php tools/sse-redetect.php                  SIMULATION (par défaut) : liste ce qui serait retiré ; rien n'est écrit
 *   php tools/sse-redetect.php --apply          écrit le retrait (ligne « withdrawn » + journal SSE cerbere.lecture.withdrawn)
 *   php tools/sse-redetect.php --tenant=7       une seule communauté
 *   php tools/sse-redetect.php --limit=500      au plus 500 remontées ouvertes par communauté
 *
 * Jamais modifié : les données des remontées (type, thèmes, urgence), les propositions déjà appliquées, ignorées ou rejetées.
 * Idempotent : une proposition retirée ne l'est pas deux fois ; relancer ne change rien. --apply demande l'étape
 * « sse_detection_rules » (sinon : simulation seule). Le moteur est App\Services\Sse\Detection\SseRedetection.
 *
 * Code de retour : 0 sans erreur, 2 si l'appel est invalide.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

$opts = getopt('', ['apply', 'limit:', 'tenant:', 'help']);
if (isset($opts['help'])) {
    echo "Voir l'en-tête de tools/sse-redetect.php\n";
    exit(0);
}
foreach (['limit', 'tenant'] as $k) {
    if (isset($opts[$k]) && (!ctype_digit((string) $opts[$k]) || (int) $opts[$k] < 1)) {
        fwrite(STDERR, "--$k attend un entier positif.\n");
        exit(2);
    }
}
$apply = isset($opts['apply']);
$tenant = isset($opts['tenant']) ? (int) $opts['tenant'] : null;
$limit = isset($opts['limit']) ? (int) $opts['limit'] : 2000;

echo $apply ? "MODE --apply : les propositions injustifiées sont retirées avec une trace (aucune remontée modifiée).\n" : "SIMULATION (aucune écriture). Ajoutez --apply pour retirer.\n";
$report = (new App\Services\Sse\Detection\SseRedetection(App\Core\Database::getPdo()))->run($apply, $tenant, $limit);
if ($apply && !$report['ready']) {
    echo "  Étape « sse_detection_rules » absente : rien n'a pu être écrit, passez d'abord la migration.\n";
}
echo sprintf("Communautés : %d, remontées ouvertes lues : %d.\n", $report['tenants'], $report['items']);
echo sprintf("Anciennes propositions : %d, toujours justifiées : %d, déjà décidées (non touchées) : %d.\n", $report['legacy'], $report['kept'], $report['skipped']);
echo sprintf("%s : %d.\n", $apply ? 'Retirées' : 'À retirer', $report['withdrawn']);
foreach (array_slice($report['changes'], 0, 60) as $c) {
    echo sprintf("  communauté %d · %s %s · %s %s · ancienne lecture : %s%s\n", $c['tenant'], $c['source'], $c['ref'] !== '' ? $c['ref'] : '#' . $c['id'], $c['target'], $c['value'], implode(', ', $c['words']), $apply && !$c['done'] ? ' (non écrit)' : '');
}
if (count($report['changes']) > 60) {
    echo sprintf("  … et %d autre(s).\n", count($report['changes']) - 60);
}
exit(0);
