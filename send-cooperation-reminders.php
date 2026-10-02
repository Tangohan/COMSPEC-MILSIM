<?php

declare(strict_types=1);

/**
 * Coopération inter-unités — relances automatiques (J-2 avant la date limite de réponse),
 * signalement des dates limites dépassées et avis d'expiration des autorisations de partage.
 *
 * La tâche « cooperation_reminders » tourne déjà avec les autres via CronRunner (toutes les
 * heures, voir CronSchedule) ; ce script permet de la lancer seule depuis une crontab :
 *   0 * * * * php /chemin/vers/athena/send-cooperation-reminders.php >> /var/log/athena-coop.log 2>&1
 * ou par l'entrée HTTP planifiée :
 *   /cron/run?key=<CRON_SECRET>&job=cooperation_reminders
 */

$root = dirname(__FILE__);
require $root . '/bootstrap/app.php';

use App\Core\Container;
use App\Services\Cron\CronRunner;

$runner = Container::get(CronRunner::class);
$job = $runner->find('cooperation_reminders');

if ($job === null) {
    fwrite(STDERR, date('c') . " — tâche « cooperation_reminders » introuvable.\n");
    exit(1);
}

$result = $runner->runOne($job, 'cli');

echo date('c') . ' — ' . (string) ($result['summary'] ?? 'terminé') . "\n";

exit(!empty($result['ok']) ? 0 : 1);
