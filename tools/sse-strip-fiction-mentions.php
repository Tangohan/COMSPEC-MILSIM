<?php

declare(strict_types=1);

/**
 * Rattrapage des mentions de jeu enregistrées en base avant leur retrait des documents SSE (« usage RP », « preuve judiciaire »,
 * « — RP », « (fictif) » sur un nom livré).
 *
 *   php tools/sse-strip-fiction-mentions.php               SIMULATION (par défaut) : liste chaque valeur à corriger ; rien n'est écrit
 *   php tools/sse-strip-fiction-mentions.php --apply       écrit les corrections
 *   php tools/sse-strip-fiction-mentions.php --tenant=7    une seule communauté
 *   php tools/sse-strip-fiction-mentions.php --quiet       seulement les totaux (sans le détail ligne par ligne)
 *
 * Tables : sse_document_prefabs (pied de page et description des modèles de présentation), sse_wanted_settings (autorité et
 * service par défaut de la notice), sse_wanted_notices (service demandeur et autorité des avis), sse_wanted_notice_meta
 * (instantané du service). Seule la valeur EXACTE livrée par défaut est reconnue : un pied de page, une autorité ou un service
 * écrits par une communauté ne sont jamais touchés. Aucune ligne n'est supprimée.
 * Idempotent : une valeur corrigée n'est plus reconnue, relancer ne fait rien. Chaque correction est journalisée sur la sortie
 * standard (table, identifiant, communauté, colonne, avant, après) : redirigez-la vers un fichier pour la garder.
 * Le site n'attend pas cet outil : la lecture retire déjà ces mentions. Le moteur est App\Services\Sse\SseFictionMentionCleanup.
 *
 * Code de retour : 0 sans erreur, 1 si une écriture a échoué, 2 si l'appel est invalide.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

$opts = getopt('', ['apply', 'tenant:', 'quiet', 'help']);
if (isset($opts['help'])) {
    echo "Voir l'en-tête de tools/sse-strip-fiction-mentions.php\n";
    exit(0);
}
if (isset($opts['tenant']) && (!ctype_digit((string) $opts['tenant']) || (int) $opts['tenant'] < 1)) {
    fwrite(STDERR, "--tenant attend un entier positif.\n");
    exit(2);
}
$apply = isset($opts['apply']);
$tenant = isset($opts['tenant']) ? (int) $opts['tenant'] : null;

echo $apply ? "MODE --apply : les mentions livrées par défaut sont retirées.\n" : "SIMULATION (aucune écriture). Ajoutez --apply pour corriger.\n";
$report = (new App\Services\Sse\SseFictionMentionCleanup(App\Core\Database::getPdo()))->run($apply, $tenant);

$short = static fn (string $s): string => mb_strlen($s) > 140 ? mb_substr($s, 0, 137) . '…' : $s;
if (!isset($opts['quiet'])) {
    foreach ($report['changes'] as $c) {
        echo sprintf("  %s #%s (communauté %d) %s : « %s » => « %s »\n", $c['table'], $c['id'], $c['tenant'], $c['column'], $short($c['before']), $short($c['after']));
    }
}
foreach ($report['by_table'] as $table => $n) {
    echo sprintf("  %6d  %s\n", $n, $table);
}
echo sprintf("Valeurs à corriger : %d.\n", $report['found']);
if ($apply) {
    echo sprintf("Écrites : %d, en échec : %d.\n", $report['written'], $report['failed']);
    foreach ($report['errors'] as $e) {
        fwrite(STDERR, "  ERREUR $e\n");
    }
}
exit($report['failed'] > 0 ? 1 : 0);
