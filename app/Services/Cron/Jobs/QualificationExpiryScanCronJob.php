<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Core\Database;
use App\Repositories\QualificationAwardRepository;
use App\Services\Cron\CronJobInterface;
use App\Services\Personnel\QualificationTemporalStatusService;

/**
 * Scan des qualifications proches de l'expiration (alertes — les états restent calculés à la volée).
 */
final class QualificationExpiryScanCronJob implements CronJobInterface
{
    public function __construct(
        private QualificationAwardRepository $awards,
        private QualificationTemporalStatusService $temporal,
    ) {
    }

    public function key(): string
    {
        return 'qualification_expiry_scan';
    }

    public function label(): string
    {
        return 'Alertes qualifications';
    }

    public function description(): string
    {
        return 'Repère les qualifications bientôt expirées ou en période de grâce pour le suivi Effectifs.';
    }

    public function run(): array
    {
        $expiring = 0;
        $grace = 0;
        $expired = 0;
        $tenants = [];
        try {
            $st = Database::getPdo()->query('SELECT id FROM tenants');
            $tenants = $st ? array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Throwable) {
            $tenants = [];
        }

        foreach ($tenants as $tenantId) {
            if ($tenantId <= 0) {
                continue;
            }
            try {
                $rows = $this->awards->listExpiring($tenantId, 60);
            } catch (\Throwable) {
                continue;
            }
            foreach ($rows as $row) {
                $t = $this->temporal->resolve($row);
                match ($t['code']) {
                    QualificationTemporalStatusService::EXPIRING_SOON => $expiring++,
                    QualificationTemporalStatusService::EXPIRED_GRACE => $grace++,
                    QualificationTemporalStatusService::EXPIRED => $expired++,
                    default => null,
                };
                if ($t['code'] === QualificationTemporalStatusService::EXPIRED) {
                    try {
                        $this->awards->addHistory(
                            $tenantId,
                            (int) $row['id'],
                            'expired',
                            null,
                            'Passage calculé en expirée (scan automatique)'
                        );
                    } catch (\Throwable) {
                    }
                }
            }
        }

        return [
            'ok' => true,
            'summary' => sprintf(
                '%d expiration(s) prochaine(s), %d en grâce, %d expirée(s)',
                $expiring,
                $grace,
                $expired
            ),
            'details' => [
                'expiring_soon' => $expiring,
                'expired_grace' => $grace,
                'expired' => $expired,
                'tenants_scanned' => count($tenants),
            ],
        ];
    }
}
