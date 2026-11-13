<?php

declare(strict_types=1);

/**
 * Étape « Annonce : outils opérateur » (Données).
 *
 * Publie une fois l'annonce « Nouveau : vos outils opérateur » dans les annonces de la plateforme (platform_alerts) :
 * membres connectés seulement, refermable (fermeture mémorisée par membre, user_alert_dismissals), visible deux
 * semaines à partir du passage de cette étape, donc du déploiement de la page. Elle compte dans la cloche du menu.
 * Idempotente : reconnue à son lien (/operateur?annonce=outils-operateur) ; rejouée, elle ne recrée ni ne prolonge rien.
 * Elle se modifie ou se retire ensuite dans Administration › Annonces de la plateforme. Le texte anglais est dans
 * lang/en/site_auto.php (AlertPresentationService le traduit à l'affichage).
 */

try {
    $operatorHubSeed = require $root . '/bootstrap/operator_hub_announcement.php';
    echo $operatorHubSeed($pdo) ? "Annonce « Nouveau : vos outils opérateur » publiée pour 14 jours.\n" : "Annonce des outils opérateur : déjà publiée ou annonces de la plateforme absentes, rien à faire.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] Annonce des outils opérateur : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('operator_hub_announcement', $e);
}
$migrationEnsurePdo();
