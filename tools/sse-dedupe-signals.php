<?php

declare(strict_types=1);

/**
 * Rattrapage des signaux du moteur analytique SSE créés en double (un nouveau signal chaque nuit pour la même lacune du même dossier).
 *
 *   php tools/sse-dedupe-signals.php                      SIMULATION (par défaut) : groupes de doublons, nombre de lignes à fusionner ; rien n'est écrit
 *   php tools/sse-dedupe-signals.php --apply              fusionne : le signal le plus ancien de chaque groupe reste la source, les autres passent au statut « merged »
 *   php tools/sse-dedupe-signals.php --apply --limit=20   seulement les 20 premiers groupes (essai prudent ; relancer reprend la suite)
 *   php tools/sse-dedupe-signals.php --tenant=7           une seule communauté
 *
 * Aucune ligne n'est supprimée. Un groupe est fusionné dans une transaction : en cas d'erreur il est annulé en entier et les autres continuent.
 * Idempotent : un signal fusionné n'est plus un doublon, relancer ne refait rien. Les colonnes de suivi de l'étape 4690 sont utilisées si elles
 * existent (merged_into, date et auteur de la fusion) ; sans elles seul le statut change. Compatible sqlite (tests).
 * Le moteur est App\Services\Sse\SseSignalDeduper ; la procédure complète est dans la description de la demande de fusion.
 *
 * Code de retour : 0 sans erreur, 1 si un groupe a échoué (rien n'est alors modifié pour ce groupe), 2 si l'appel est invalide.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

$opts = getopt('', ['apply', 'limit:', 'tenant:', 'help']);
if (isset($opts['help'])) {
    echo "Voir l'en-tête de tools/sse-dedupe-signals.php\n";
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

echo $apply ? "MODE --apply : les doublons sont fusionnés (rien n'est supprimé).\n" : "SIMULATION (aucune écriture). Ajoutez --apply pour fusionner.\n";
$report = (new App\Services\Sse\SseSignalDeduper(App\Core\Database::getPdo()))->run($apply, $tenant, $limit);

if (!$report['columns']['dedup_key']) {
    echo "  (colonnes de suivi absentes : étape 4690 pas encore passée, seul le statut changera)\n";
}
echo sprintf("Signaux ouverts ou en veille : %d, communautés : %d.\n", $report['signals'], $report['tenants']);
echo sprintf("Groupes de doublons : %d, lignes à fusionner : %d.\n", $report['groups'], $report['duplicates']);
foreach (array_slice($report['by_type'], 0, 15, true) as $type => $n) {
    echo sprintf("  %6d  %s\n", $n, $type);
}
foreach ($report['samples'] as $s) {
    echo sprintf("  exemple : communauté %d, dossier %s, signal %d « %s » + %d doublon(s)\n", $s['tenant'], $s['case_id'] ?? '-', $s['source_id'], $s['title'], $s['duplicates']);
}
if ($apply) {
    echo sprintf("Fusionnés : %d, groupes en échec : %d, groupes restants : %d.\n", $report['merged'], $report['failed'], $report['remaining']);
    foreach ($report['errors'] as $e) {
        fwrite(STDERR, "  ERREUR $e\n");
    }
}
exit($report['failed'] > 0 ? 1 : 0);
