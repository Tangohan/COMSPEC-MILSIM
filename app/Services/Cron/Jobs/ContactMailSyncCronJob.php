<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\ContactMail\ContactMailFactory;
use App\Services\ContactMail\ContactMailSyncService;
use App\Services\Cron\CronJobInterface;

/**
 * Boîte de contact de la plateforme : lit les nouveaux messages de contact@ (IMAP) et les range dans leurs fils.
 * Sans CONTACT_MAIL_PASSWORD dans le .env, ou sans les tables, la tâche ne fait rien et n'ouvre aucune connexion.
 */
final class ContactMailSyncCronJob implements CronJobInterface
{
    public function __construct(private ?ContactMailSyncService $service = null) {}

    public function key(): string
    {
        return 'contact_mail_sync';
    }

    public function label(): string
    {
        return 'Boîte de contact : synchronisation IMAP';
    }

    public function description(): string
    {
        return 'Récupère les nouveaux e-mails de la boîte de contact (IMAP), les range dans leurs fils de discussion et garde la position lue. '
            . 'Inactive tant que CONTACT_MAIL_PASSWORD n’est pas renseigné.';
    }

    public function run(): array
    {
        $service = $this->service ?? ContactMailFactory::sync();
        $r = $service->run(100, 40);
        if (!$r['available']) {
            return ['ok' => true, 'summary' => 'Rien à faire : ' . $r['reason'], 'details' => ['available' => false]];
        }
        if ($r['busy']) {
            return ['ok' => true, 'summary' => 'Synchronisation déjà en cours.', 'details' => ['busy' => true]];
        }
        if (!$r['ok']) {
            return ['ok' => false, 'summary' => 'Échec : ' . $r['reason'], 'details' => ['imported' => $r['imported']]];
        }

        return [
            'ok' => true,
            'summary' => sprintf('%d message(s) importé(s), %d déjà connu(s), %d illisible(s).', $r['imported'], $r['skipped'], $r['errors']),
            'details' => ['imported' => $r['imported'], 'skipped' => $r['skipped'], 'errors' => $r['errors']],
        ];
    }
}
