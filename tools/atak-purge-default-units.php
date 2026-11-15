<?php

declare(strict_types=1);

/**
 * Rattrapage des pistes périmées de la communauté par défaut (tenants.slug = « default »), celle où écrivent tous les
 * comptes sans communauté : pistes de joueurs, d'IA alliées (ALLY-…) et de balises restées sur la carte ATAK après
 * la fin de leur partie, et que chaque joueur sans communauté recevait.
 *
 *   php tools/atak-purge-default-units.php                SIMULATION (par défaut) : compte les pistes à supprimer ; rien n'est écrit
 *   php tools/atak-purge-default-units.php --apply        supprime les pistes sans signe de vie depuis plus de 24 h
 *   php tools/atak-purge-default-units.php --tenant=1     une seule communauté (refusée si ce n'est pas une communauté par défaut)
 *
 * Bornes : seules les lignes de atak_units d'une communauté par défaut, muettes depuis plus de 24 h
 * (AtakPartyScope::DEFAULT_PURGE_AFTER_SECONDS), sont supprimées, ainsi que les fichiers d'état des cartes réservées
 * (storage/cache/…/t<communauté>_m<carte réservée>…) non modifiés depuis 24 h. Les communautés réelles, leurs notes TOC
 * (toc_radio, toc_vehicle, toc_note) et les cartes choisies à la main ne sont jamais touchées.
 * Idempotent : un second passage ne trouve plus rien. Chaque passage est écrit dans storage/logs/atak-purge-default-units.log.
 * Le moteur est App\Support\AtakPartyScope ; la tâche planifiée « Entretien des données de joueurs » fait ensuite le même
 * travail chaque jour. Compatible sqlite (tests).
 *
 * Code de retour : 0 sans erreur, 1 si une communauté a échoué, 2 si l'appel est invalide.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

$opts = getopt('', ['apply', 'tenant:', 'help']);
if (isset($opts['help'])) {
    echo "Voir l'en-tête de tools/atak-purge-default-units.php\n";
    exit(0);
}
if (isset($opts['tenant']) && (!ctype_digit((string) $opts['tenant']) || (int) $opts['tenant'] < 1)) {
    fwrite(STDERR, "--tenant attend un entier positif.\n");
    exit(2);
}

exit(App\Support\AtakPartyPurgeCli::run(
    App\Core\Database::getPdo(),
    isset($opts['apply']),
    isset($opts['tenant']) ? (int) $opts['tenant'] : null,
    $root . '/storage/cache',
    $root . '/storage/logs/atak-purge-default-units.log',
    static function (string $line): void {
        echo $line . "\n";
    }
));
