<?php

declare(strict_types=1);

/**
 * Rétro-remplissage des liens de provenance du bureau SSE : reconstruit dans sse_entity_links les liens que les colonnes
 * existantes portent déjà (identité → personne, papier → identité, contrôle → papier, identité ou personne, dossier d'une fiche,
 * document émis, photo, avis de recherche...), avec la règle « cerbere.backfill.column », la date de l'élément d'origine et le
 * motif de la colonne quand elle en a un. Ne modifie ni ne supprime aucune autre donnée ; un couple déjà lié n'est jamais doublé,
 * donc la commande peut être relancée sans effet.
 *
 *   php tools/sse-backfill-links.php                       SIMULATION (par défaut) : compte ce qui serait créé, n'écrit rien
 *   php tools/sse-backfill-links.php --apply               écrit, par lots, chaque lot dans une transaction
 *   php tools/sse-backfill-links.php --apply --batch=200   taille des lots (500 par défaut, 2000 au plus)
 *   php tools/sse-backfill-links.php --tenant=7            une seule communauté
 *   php tools/sse-backfill-links.php --apply --max-batches=20 --resume=7:3:1520
 *                                                          s'arrête après 20 lots et affiche le curseur ; --resume repart de là
 *
 * Prérequis : l'étape de migration « sse_entity_links » (database/steps/4720_sse_entity_links.php) doit avoir été passée.
 * Code de retour : 0 sans erreur, 1 si un lot a échoué (il est annulé, les autres sont gardés), 2 si l'appel est invalide.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

$opts = getopt('', ['apply', 'tenant:', 'batch:', 'resume:', 'max-batches:', 'help']);
if (isset($opts['help'])) {
    echo "Voir l'en-tête de tools/sse-backfill-links.php\n";
    exit(0);
}
foreach (['tenant', 'batch', 'max-batches'] as $k) {
    if (isset($opts[$k]) && (!ctype_digit((string) $opts[$k]) || (int) $opts[$k] < 1)) {
        fwrite(STDERR, "--$k attend un entier positif.\n");
        exit(2);
    }
}
if (isset($opts['resume']) && preg_match('/^\d+:\d+:\d+$/', (string) $opts['resume']) !== 1) {
    fwrite(STDERR, "--resume attend « communauté:définition:ligne » (le curseur affiché par l'appel précédent).\n");
    exit(2);
}
$apply = isset($opts['apply']);

$job = new App\Services\Sse\Links\SseLinkBackfill(App\Core\Database::getPdo());
echo $apply ? "MODE --apply : les liens manquants sont écrits.\n" : "SIMULATION (aucune écriture). Ajoutez --apply pour écrire.\n";
$report = $job->run(
    $apply,
    isset($opts['tenant']) ? (int) $opts['tenant'] : null,
    isset($opts['batch']) ? (int) $opts['batch'] : 500,
    isset($opts['resume']) ? (string) $opts['resume'] : null,
    isset($opts['max-batches']) ? (int) $opts['max-batches'] : null
);
foreach ($report['messages'] as $m) {
    echo "  ! $m\n";
}
foreach ($report['byDef'] as $name => $c) {
    echo sprintf("  %-70s lues %6d  manquants %6d  %s %6d\n", $name, $c['scanned'], $c['missing'], $apply ? 'créés' : 'à créer', $apply ? $c['created'] : $c['missing']);
}
echo sprintf(
    "\n%d ligne(s) lue(s), %d lien(s) déjà enregistré(s), %d manquant(s), %d créé(s), %d erreur(s).\n",
    $report['scanned'],
    $report['existing'],
    $report['missing'],
    $report['created'],
    $report['errors']
);
if (!$report['ready']) {
    exit(1);
}
if (!$report['done']) {
    echo 'Arrêté avant la fin. Pour reprendre : php tools/sse-backfill-links.php' . ($apply ? ' --apply' : '') . ' --resume=' . $report['cursor'] . "\n";
}
exit($report['errors'] > 0 ? 1 : 0);
