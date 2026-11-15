<?php

declare(strict_types=1);

/**
 * Bilans 30 jours : clôture automatique « néant » des dossiers dont le bilan est attendu depuis 45 jours.
 *
 * La tâche « recruitment_retro_auto_close » tourne déjà avec les autres via CronRunner (toutes les heures, voir
 * CronSchedule). Ce script sert à la lancer à la main :
 *
 *   php recruitment-retro-auto-close.php --dry-run             simulation : liste ce qui SERAIT clos, n'écrit rien
 *   php recruitment-retro-auto-close.php --dry-run --tenant=3  simulation pour une seule communauté
 *   php recruitment-retro-auto-close.php                       exécution réelle (comme la tâche planifiée)
 *
 * Avant le premier passage en production : lancer --dry-run ; la tâche planifiée rattrape ensuite les dossiers
 * déjà en retard (au plus 100 par exécution, 25 par communauté). Fenêtre de sécurité : un dossier en retard de plus de
 * 180 jours (ancienne communauté) n'est jamais clos ni notifié ; la simulation les compte séparément.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__FILE__);
require $root . '/bootstrap/app.php';

use App\Core\Container;
use App\Services\Recruitment\EnlistmentRetroAutoCloseService;
use App\Support\EnlistmentRetroAutoClose;

$dryRun = in_array('--dry-run', $argv, true);
$tenant = null;
foreach ($argv as $arg) {
    if (preg_match('/^--tenant=(\d+)$/', (string) $arg, $m) === 1) {
        $tenant = (int) $m[1];
    }
}

$r = Container::get(EnlistmentRetroAutoCloseService::class)->run($dryRun, $tenant);

if (!$r['available']) {
    echo date('c') . ' — ' . $r['reason'] . "\n";
    exit(0);
}
if ($dryRun) {
    echo date('c') . ' — SIMULATION (rien n’est écrit) : ' . count($r['would_close']) . ' dossier(s) seraient clos (seuil : attendu depuis '
        . EnlistmentRetroAutoClose::AUTO_CLOSE_LATE_DAYS . " jours).\n";
    foreach ($r['would_close'] as $c) {
        echo sprintf("  communauté %d · dossier #%d · %s · attendu depuis %d j\n", $c['tenant_id'], $c['enlistment_id'], $c['name'], $c['late_days']);
    }
    foreach ($r['stale'] as $tenantId => $n) {
        echo sprintf("  communauté %d · %d dossier(s) en retard de plus de %d j : laissés intacts (ni clos, ni notifiés)\n", $tenantId, $n, EnlistmentRetroAutoClose::AUTO_CLOSE_MAX_LATE_DAYS);
    }
    foreach ($r['disabled'] as $tenantId) {
        echo sprintf("  communauté %d · clôture automatique désactivée par la communauté\n", $tenantId);
    }
    exit($r['ok'] ? 0 : 1);
}
echo date('c') . ' — ' . $r['closed'] . ' bilan(s) clos automatiquement (néant), ' . $r['errors'] . ' erreur(s), ' . array_sum($r['stale']) . " dossier(s) ancien(s) laissé(s) intact(s).\n";

exit($r['ok'] ? 0 : 1);
