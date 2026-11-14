<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Controllers\Admin\AccessRegisterController;
use App\Repositories\TenantRepository;
use App\Services\Cron\CronJobInterface;
use Throwable;

/**
 * Registre des droits : retire les droits arrivés à échéance et les inscrit à l'historique.
 * Le contrôle d'accès les ignore déjà dès l'échéance (RbacService) ; ce passage remet les listes d'accord.
 */
final class AccessRegisterExpiryCronJob implements CronJobInterface
{
    public function __construct(private TenantRepository $tenants) {}

    public function key(): string
    {
        return 'access_register_expiry';
    }

    public function label(): string
    {
        return 'Échéances du registre des droits';
    }

    public function description(): string
    {
        return 'Retire les droits d’accès arrivés à leur date de fin et les note dans l’historique.';
    }

    public function run(): array
    {
        $service = AccessRegisterController::service();
        $removed = 0;
        $errors = 0;
        foreach ($this->tenants->listBasicAll() as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            try {
                $removed += $service->sweepExpired($tenantId);
            } catch (Throwable $e) {
                $errors++;
                error_log('[cron access_register_expiry] ' . $tenantId . ' : ' . $e->getMessage());
            }
        }

        return [
            'ok' => $errors === 0,
            'summary' => $removed . ' droit(s) échu(s) retiré(s).',
            'details' => ['removed' => $removed, 'errors' => $errors],
        ];
    }
}
