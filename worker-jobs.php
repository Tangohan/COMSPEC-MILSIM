<?php

declare(strict_types=1);

/**
 * Traite la file async_jobs (ex. e-mails). Désactive MAIL_QUEUE pendant l’exécution pour éviter la récursion.
 *
 * Usage : php worker-jobs.php [nombre_max]
 */
$root = dirname(__FILE__);
require_once $root . '/bootstrap/autoload.php';
load_env($root);

$_ENV['MAIL_QUEUE'] = '0';
putenv('MAIL_QUEUE=0');

\App\Core\Database::getPdo();
$jobs = new \App\Repositories\AsyncJobRepository();
$email = \App\Core\Container::get(\App\Services\EmailService::class);

$max = max(1, min(500, (int) ($argv[1] ?? 25)));
for ($i = 0; $i < $max; $i++) {
    $row = $jobs->claimNext();
    if ($row === null) {
        break;
    }
    $id = (int) $row['id'];
    $type = (string) ($row['job_type'] ?? '');
    if ($type === 'email_send') {
        $data = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (!is_array($data)) {
            $jobs->delete($id);
            continue;
        }
        $email->send(
            (string) ($data['eventCode'] ?? ''),
            (string) ($data['to'] ?? ''),
            (string) ($data['subject'] ?? ''),
            (string) ($data['htmlBody'] ?? ''),
            (string) ($data['textBody'] ?? ''),
            isset($data['tenantId']) ? (int) $data['tenantId'] : null,
            isset($data['replyTo']) ? (string) $data['replyTo'] : null,
            isset($data['payloadSummary']) && is_array($data['payloadSummary']) ? $data['payloadSummary'] : null
        );
        $jobs->delete($id);
    } else {
        $jobs->release($id, 'Type de tâche non géré : ' . $type, 120);
    }
}

echo "OK\n";
