<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Email\MailQueueProcessor;

/**
 * Envoie les e-mails en file : MAIL_QUEUE=true, et ceux reportés quand le quota quotidien est atteint.
 */
final class MailQueueCronJob implements CronJobInterface
{
    public function __construct(private MailQueueProcessor $processor)
    {
    }

    public function key(): string
    {
        return 'mail_queue';
    }

    public function label(): string
    {
        return 'File d’envoi des e-mails';
    }

    public function description(): string
    {
        return 'Envoie les e-mails en attente, dont ceux reportés au lendemain quand le quota quotidien est atteint.';
    }

    public function run(): array
    {
        $r = $this->processor->run(80, 90);

        return [
            'ok' => true,
            'summary' => sprintf('%d e-mail(s) traité(s), %d envoyé(s) ou remis en attente, %d échec(s)', $r['processed'], $r['sent'], $r['failed']),
            'details' => $r,
        ];
    }
}
