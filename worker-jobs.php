<?php

declare(strict_types=1);

/**
 * Traite la file async_jobs (e-mails : MAIL_QUEUE et envois reportés par le quota quotidien).
 * La tâche automatique « mail_queue » fait la même chose toutes les 5 minutes ; ce script reste pour un lancement manuel.
 *
 * Usage : php worker-jobs.php [nombre_max]
 */
$root = dirname(__FILE__);
require_once $root . '/bootstrap/autoload.php';
load_env($root);

\App\Core\Database::getPdo();
$processor = new \App\Services\Email\MailQueueProcessor(
    new \App\Repositories\AsyncJobRepository(),
    \App\Core\Container::get(\App\Services\EmailService::class)
);

$max = max(1, min(500, (int) ($argv[1] ?? 25)));
$r = $processor->run($max, 600);

echo sprintf("OK : %d traité(s), %d envoyé(s) ou remis en attente, %d échec(s)\n", $r['processed'], $r['sent'], $r['failed']);
