<?php

declare(strict_types=1);

/**
 * Rappels de participation — point d'entrée historique, conservé pour ne pas casser les
 * crontabs existantes.
 *
 * La logique vit désormais dans AttendanceRemindersCronJob, exécutée par CronRunner : les
 * exécutions sont journalisées (cron_job_runs) et visibles dans l'administration au même
 * titre que les autres tâches, ce qui n'était pas le cas de ce script autonome.
 *
 * Préférer désormais l'entrée HTTP planifiée :
 *   /cron/run?key=<CRON_SECRET>&job=attendance_reminders
 */

$root = dirname(__FILE__);
require $root . '/bootstrap/app.php';

use App\Core\Container;
use App\Services\Cron\CronRunner;

$runner = Container::get(CronRunner::class);
$job = $runner->find('attendance_reminders');

if ($job === null) {
    fwrite(STDERR, date('c') . " — tâche « attendance_reminders » introuvable.\n");
    exit(1);
}

$result = $runner->runOne($job, 'cli');

echo date('c') . ' — ' . (string) ($result['summary'] ?? 'terminé') . "\n";

exit(!empty($result['ok']) ? 0 : 1);
