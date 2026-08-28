<?php

declare(strict_types=1);

/**
 * Exécute les tâches planifiées Athena (formations, modération, bilans recrutement,
 * nettoyage du journal de performance, …).
 *
 * Usage :
 *   php scripts/cron-run.php
 *   php scripts/cron-run.php recruitment_retro_reminders
 *   php scripts/cron-run.php request_telemetry_purge
 *   php scripts/cron-run.php atak_report_routing_escalations
 *
 * Planification recommandée (serveur) : toutes les 5 minutes
 *   bash scripts/install-system-cron.sh
 */

$root = dirname(__DIR__);

require_once $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/app.php';

use App\Core\Container;
use App\Services\Cron\CronRunner;

$only = isset($argv[1]) ? trim((string) $argv[1]) : '';

/** @var CronRunner $runner */
$runner = Container::get(CronRunner::class);
$result = $runner->runAll('cli', $only !== '' ? $only : null);

$ts = date('Y-m-d H:i:s');
foreach ($result['results'] as $r) {
    $status = !empty($r['ok']) ? 'OK' : 'ERREUR';
    $label = (string) ($r['label'] ?? $r['job'] ?? '?');
    $summary = (string) ($r['summary'] ?? '');
    echo "{$ts} — [{$status}] {$label} — {$summary}\n";
}

if ($result['results'] === []) {
    echo "{$ts} — Aucune tâche à exécuter" . ($only !== '' ? " (filtre : {$only})" : '') . ".\n";
}

exit($result['ok'] ? 0 : 1);
