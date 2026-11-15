<?php

declare(strict_types=1);

/**
 * Aligne l'identité OFFICIELLE (civile) des membres sur leur état civil (prénom et nom du profil, date de naissance du personnage,
 * lieu de naissance du dossier du personnage) et fait porter la nouvelle valeur à leurs papiers officiels.
 *
 *   php tools/sse-sync-member-identities.php                         SIMULATION (par défaut) : liste les écarts (membre, valeur du registre,
 *                                                                    valeur du profil, papiers concernés) ; rien n'est écrit
 *   php tools/sse-sync-member-identities.php --apply                 aligne (une transaction par membre, anciennes valeurs dans l'historique
 *                                                                    restaurable et dans le journal)
 *   php tools/sse-sync-member-identities.php --apply --limit=20      seulement 20 membres (essai prudent, puis relancer pour la suite)
 *   php tools/sse-sync-member-identities.php --tenant=7              une seule communauté
 *   php tools/sse-sync-member-identities.php --create-missing        crée aussi l'identité officielle des membres qui n'en ont pas
 *                                                                    (jamais sans cette option)
 *   php tools/sse-sync-member-identities.php --log=FICHIER           journal des alignements (défaut : storage/logs/sse-civil-identities-DATE.jsonl)
 *
 * Ne touche JAMAIS aux identités de couverture, d'emprunt, falsifiées ou générées, ni aux sujets non membres, ni à une identité marquée
 * « nom volontairement différent » ; ne supprime rien (les valeurs remplacées restent dans sse_identity_history, décision « civil »,
 * restaurables depuis la fiche du membre). Idempotent : une seconde passe ne trouve plus rien. Les doublons (plusieurs identités
 * civiles pour un membre) sont signalés, jamais fusionnés. Un compte supprimé n'est jamais nommé.
 *
 * Code de retour : 0 sans erreur, 1 si un membre a échoué (rien n'est alors modifié pour lui), 2 si l'appel est invalide.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

$opts = getopt('', ['apply', 'limit:', 'tenant:', 'create-missing', 'log:', 'help']);
if (isset($opts['help'])) {
    echo "Voir l'en-tête de tools/sse-sync-member-identities.php\n";
    exit(0);
}
foreach (['limit', 'tenant'] as $k) {
    if (isset($opts[$k]) && (!ctype_digit((string) $opts[$k]) || (int) $opts[$k] < 1)) {
        fwrite(STDERR, "--$k attend un entier positif.\n");
        exit(2);
    }
}
$apply = isset($opts['apply']);
$logFile = $apply ? (isset($opts['log']) ? (string) $opts['log'] : $root . '/storage/logs/sse-civil-identities-' . gmdate('Ymd-His') . '.jsonl') : null;

$batch = new App\Services\Sse\SseMemberCivilIdentityBatch(null, null, $logFile);
echo $apply ? "MODE --apply : les identités officielles sont alignées.\n" : "SIMULATION (aucune écriture). Ajoutez --apply pour aligner.\n";
$report = $batch->run(
    $apply,
    isset($opts['tenant']) ? (int) $opts['tenant'] : null,
    isset($opts['limit']) ? (int) $opts['limit'] : null,
    isset($opts['create-missing'])
);
foreach ($report['rows'] as $r) {
    echo sprintf("  [%s] communauté %d, membre %d, identité %d : registre « %s » -> profil « %s » (%s)\n", $r['result'], $r['tenant_id'], $r['user_id'], $r['identity_id'], $r['registry'] !== '' ? $r['registry'] : 'aucune', $r['profile'], $r['state']);
    foreach ($r['changes'] as $c) {
        echo sprintf("      %s : « %s » -> « %s »\n", $c['label'], $c['old'] !== '' ? $c['old'] : '—', $c['new'] !== '' ? $c['new'] : '—');
    }
    if ($r['documents'] !== []) {
        echo '      papiers officiels concernés : ' . implode(' ; ', $r['documents']) . "\n";
    }
    if (isset($r['message']) && $r['result'] === 'error') {
        echo '      ERREUR : ' . $r['message'] . "\n";
    }
}
foreach ($report['notes'] as $n) {
    echo '  (à voir) ' . $n . "\n";
}
$t = $report['totals'];
echo sprintf(
    "\n%d membre(s) examiné(s) : %d aligné(s), %d écart(s), %d marqué(s) « nom volontairement différent », %d sans état civil, %d compte(s) supprimé(s), %d sans identité officielle, %d doublon(s).\n",
    $t['scanned'], $t['aligned'], $t['misaligned'], $t['override'], $t['no_civil_state'], $t['anonymized'], $t['missing'], $t['duplicates']
);
if ($apply) {
    echo sprintf("Alignés : %d, créés : %d, erreurs : %d.\nJournal : %s\n", $t['applied'], $t['created'], $t['errors'], $logFile);
} elseif ($t['misaligned'] + ($t['missing'] > 0 && isset($opts['create-missing']) ? $t['missing'] : 0) > 0) {
    echo "Relancez avec --apply (éventuellement --limit=N d'abord) après relecture de cette liste.\n";
}
exit($t['errors'] > 0 ? 1 : 0);
