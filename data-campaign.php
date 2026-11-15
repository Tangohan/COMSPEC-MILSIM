<?php

declare(strict_types=1);

/**
 * Campagne de fiabilisation des données : ouverture immédiate et simulation, en ligne de commande.
 *
 * La tâche « data_campaign » tourne déjà avec les autres via CronRunner (toutes les heures, voir CronSchedule) : elle ouvre la
 * campagne annuelle (1er au 31 décembre par défaut), envoie les rappels et clôture à la date limite. Ce script lance le MÊME
 * service (DataCampaignService::runScheduled) à la main :
 *
 *   php data-campaign.php --dry-run                    simulation de la tâche planifiée : n'écrit et n'envoie rien
 *   php data-campaign.php --open-now --dry-run         simulation d'une ouverture immédiate : communautés et nombres de membres
 *   php data-campaign.php --open-now                   ouvre tout de suite la campagne de l'année pour toute communauté éligible
 *   php data-campaign.php --open-now --tenant=3        une seule communauté
 *   php data-campaign.php --open-now --deadline=2026-12-31 --blocks=identite,matricules,affectation,qualifications,heures
 *   php data-campaign.php --open-now --no-email        notifications seulement, sans e-mail
 *   php data-campaign.php                              un passage de la tâche planifiée (comme le cron)
 *
 * Garde-fous (identiques à l'ouverture automatique) : une communauté qui a déjà une campagne pour l'année (manuelle ou ouverte
 * par Athena) n'en reçoit pas de seconde ; une communauté sans membre connecté depuis 90 jours est ignorée ; au plus 150 membres
 * sont servis par passage (notification et e-mail par la file existante) ; relancer la commande ne renvoie rien de ce qui est
 * déjà parti. Si plus de la moitié des fiches n'ont ni prénom ni nom, l'avis à la gestion ajoute « fiches incomplètes ».
 * Jamais la moindre fiche n'est modifiée.
 *
 * Procédure avant la première exécution sur un serveur : migration (étape « data_campaigns »), puis --open-now --dry-run,
 * puis --open-now.
 *

 * Les communautés qui ont coupé l'ouverture automatique sont ignorées, sauf avec --tenant (choix explicite).
 *
 * --blocks : identite, matricules, affectation, qualifications, heures, coordonnees (par défaut : tous).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__FILE__);
require $root . '/bootstrap/app.php';

use App\Core\Container;
use App\Repositories\TenantRepository;
use App\Services\DataCampaign\DataCampaignService;

$aliases = [
    'identite' => 'identity', 'identity' => 'identity',
    'matricules' => 'identifiers', 'identifiers' => 'identifiers',
    'affectation' => 'assignment', 'assignment' => 'assignment',
    'qualifications' => 'qualifications',
    'heures' => 'hours', 'hours' => 'hours',
    'coordonnees' => 'contact', 'contact' => 'contact',
];
$options = ['force' => false, 'dry_run' => false, 'email' => true, 'tenant' => null, 'deadline' => null, 'blocks' => null];
foreach (array_slice($argv, 1) as $arg) {
    $arg = (string) $arg;
    if ($arg === '--open-now') {
        $options['force'] = true;
    } elseif ($arg === '--dry-run') {
        $options['dry_run'] = true;
    } elseif ($arg === '--no-email') {
        $options['email'] = false;
    } elseif (preg_match('/^--tenant=(\d+)$/', $arg, $m) === 1) {
        $options['tenant'] = (int) $m[1];
    } elseif (preg_match('/^--deadline=(\d{4}-\d{2}-\d{2})$/', $arg, $m) === 1) {
        $options['deadline'] = $m[1];
    } elseif (preg_match('/^--blocks=([a-z,]+)$/', $arg, $m) === 1) {
        $keys = [];
        foreach (explode(',', $m[1]) as $name) {
            if (!isset($aliases[$name])) {
                fwrite(STDERR, "Partie inconnue : $name\n");
                exit(2);
            }
            $keys[] = $aliases[$name];
        }
        $options['blocks'] = array_values(array_unique($keys));
    } else {
        fwrite(STDERR, "Option inconnue : $arg\n");
        exit(2);
    }
}

$names = [];
foreach ((new TenantRepository())->listBasicAll() as $tenant) {
    $names[(int) $tenant['id']] = (string) ($tenant['name'] ?? '');
}

$report = Container::get(DataCampaignService::class)->runScheduled($options);

echo ($options['dry_run'] ? 'SIMULATION (rien n’est écrit ni envoyé)' : 'EXÉCUTION') . ($options['force'] ? ' — ouverture immédiate' : ' — passage de la tâche planifiée') . "\n";
foreach ($report['plan'] as $plan) {
    $tenantId = (int) $plan['tenant_id'];
    $line = sprintf('#%d %s : ', $tenantId, $names[$tenantId] ?? '?');
    if ($plan['action'] === 'open') {
        $line .= sprintf('ouvrirait une campagne (%d membre(s), date limite %s)%s', $plan['members'], $plan['due_on'], $plan['incomplete'] ? ' — fiches très incomplètes, avis ajouté' : '');
    } else {
        $line .= 'ignorée (' . $plan['reason'] . ')';
    }
    echo $line . "\n";
}
printf(
    "Communautés traitées : %d ; campagnes créées : %d ; clôturées : %d ; membres notifiés : %d ; e-mails mis en file : %d ; rappels : %d ; erreurs : %d\n",
    $report['tenants'],
    $report['opened'],
    $report['closed'],
    $report['notified'],
    $report['mails'],
    $report['reminders'],
    $report['errors']
);
exit($report['errors'] > 0 ? 1 : 0);
