<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Account\AccountDeletionService;
use App\Services\Cron\CronJobInterface;

/**
 * Anonymise les comptes dont le délai de rétractation (RGPD) est dépassé.
 */
final class AccountDeletionAnonymizeCronJob implements CronJobInterface
{
    public function __construct(private AccountDeletionService $deletionService) {}

    public function key(): string
    {
        return 'account_deletion_anonymize';
    }

    public function label(): string
    {
        return 'Anonymisation des comptes supprimés';
    }

    public function description(): string
    {
        return 'Anonymise les comptes dont le délai de rétractation de 14 jours (suppression RGPD self-service) est dépassé.';
    }

    public function run(): array
    {
        return $this->deletionService->anonymizeDueAccounts();
    }
}
