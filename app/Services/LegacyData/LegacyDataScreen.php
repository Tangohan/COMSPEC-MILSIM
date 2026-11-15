<?php

declare(strict_types=1);

namespace App\Services\LegacyData;

use App\Repositories\TenantRepository;
use App\Support\EnlistmentRetroAutoClose;
use Throwable;

/**
 * Données de la section « Données anciennes à vérifier » de Qualité des données. Lecture seule, jamais bloquant :
 * en cas d'erreur la section s'affiche vide.
 */
final class LegacyDataScreen
{
    /**
     * @return array{
     *   counts: array<string, int>,
     *   lines: list<array{key: string, count: int, text: string}>,
     *   simulation: ?array{available: bool, reason: string, total: int, candidates: list<array{user_id: int, home_tenant_id: int, identity_id: int, character_name: string, first_name: ?string, last_name: string}>},
     *   batches: list<array{batch_id: string, at: string, imported: int, active: int}>,
     *   importAvailable: bool,
     *   importReason: string,
     *   retroEnabled: bool,
     *   retroWindowDays: int,
     *   retroThresholdDays: int
     * }
     */
    public static function build(int $tenantId, bool $simulate): array
    {
        $out = [
            'counts' => array_fill_keys(LegacyDataCounter::KEYS, 0),
            'lines' => [],
            'simulation' => null,
            'batches' => [],
            'importAvailable' => false,
            'importReason' => '',
            'retroEnabled' => true,
            'retroWindowDays' => EnlistmentRetroAutoClose::AUTO_CLOSE_MAX_LATE_DAYS,
            'retroThresholdDays' => EnlistmentRetroAutoClose::AUTO_CLOSE_LATE_DAYS,
        ];
        if ($tenantId < 1) {
            return $out;
        }
        try {
            $out['counts'] = LegacyDataNotice::counts($tenantId);
            $out['lines'] = LegacyDataNotice::lines($out['counts']);
            $import = new LegacyIdentityImport();
            $avail = $import->available();
            $out['importAvailable'] = $avail['ok'];
            $out['importReason'] = $avail['reason'];
            if ($avail['ok']) {
                $out['batches'] = $import->batches($tenantId);
                if ($simulate) {
                    $out['simulation'] = $import->simulate($tenantId);
                }
            }
            $out['retroEnabled'] = EnlistmentRetroAutoClose::enabledFor((new TenantRepository())->getSettings($tenantId));
        } catch (Throwable $e) {
            error_log('[legacy_data] écran de la communauté ' . $tenantId . ' : ' . $e->getMessage());
        }

        return $out;
    }
}
