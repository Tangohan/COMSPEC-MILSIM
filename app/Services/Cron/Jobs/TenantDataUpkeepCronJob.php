<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\AtakRealismRepository;
use App\Repositories\TenantRepository;
use App\Services\Advancement\GradeScaleTemplateService;
use App\Services\Cron\CronJobInterface;
use App\Services\Effectifs\UnitCodeService;
use App\Services\Personnel\NewMemberNumbers;
use Throwable;

/**
 * Remise en ordre des données dérivées de chaque communauté. Ces écritures se faisaient à l'affichage de pages
 * (GET) : matricules manquants, codes d'unité, échelle de grades vide, terminaux ATAK mal classés.
 * Une page ne modifie plus rien ; ce passage s'en charge.
 */
final class TenantDataUpkeepCronJob implements CronJobInterface
{
    public function __construct(
        private TenantRepository $tenants,
        private GradeScaleTemplateService $grades,
        private AtakRealismRepository $realism,
    ) {}

    public function key(): string
    {
        return 'tenant_data_upkeep';
    }

    public function label(): string
    {
        return 'Mise en ordre des données des communautés';
    }

    public function description(): string
    {
        return 'Attribue les matricules manquants, met à jour les codes d’unité, complète une échelle de grades vide et reclasse les terminaux ATAK.';
    }

    public function run(): array
    {
        $numbers = 0;
        $gradesAdded = 0;
        $terminals = 0;
        $errors = 0;
        foreach ($this->tenants->listBasicAll() as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            try {
                $numbers += NewMemberNumbers::assignMissing($tenantId);
                UnitCodeService::forget($tenantId);
                (new UnitCodeService())->sync($tenantId);
                if ($this->grades->ensureForTenant($tenantId)) {
                    $gradesAdded++;
                }
                // Terminaux ATAK : seulement les communautés qui en ont (comme avant, où l'écran du parc les ouvrait).
                if ($this->realism->listTerminals($tenantId) !== []) {
                    $terminals += $this->realism->repairCorruptIdentitiesForTenant($tenantId);
                    $terminals += $this->realism->persistWebSessionClassification($tenantId);
                    $this->realism->ensureDefaultCryptoDomain($tenantId);
                }
            } catch (Throwable $e) {
                $errors++;
                error_log('[cron tenant_data_upkeep] ' . $tenantId . ' : ' . $e->getMessage());
            }
        }

        return [
            'ok' => $errors === 0,
            'summary' => sprintf('%d matricule(s), %d échelle(s) de grades, %d terminal(aux) remis en ordre.', $numbers, $gradesAdded, $terminals),
            'details' => ['member_numbers' => $numbers, 'grade_scales' => $gradesAdded, 'terminals' => $terminals, 'errors' => $errors],
        ];
    }
}
