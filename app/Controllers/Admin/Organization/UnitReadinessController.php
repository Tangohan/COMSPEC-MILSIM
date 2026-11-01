<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UnitRepository;
use App\Services\Personnel\UnitReadinessService;
use App\Support\OrgVisibilityCapabilities;

final class UnitReadinessController
{
    public function __construct(
        private UnitReadinessService $readiness,
        private ?UnitRepository $units = null,
    ) {
        $this->units ??= new UnitRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        $loadError = false;
        // Même confidentialité que l’ORBAT : une unité masquée n’apparaît pas pour un lecteur non habilité.
        // En cas d’erreur on n’affiche rien plutôt qu’une liste non filtrée.
        try {
            $scores = UnitReadinessService::applyVisibility(
                $this->readiness->scoresForTenant($tenantId),
                $this->units->listFlatForStructure($tenantId, true),
                OrgVisibilityCapabilities::fromGate()
            );
        } catch (\Throwable) {
            $scores = [];
            $loadError = true;
        }

        $gate = Gate::getInstance();

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.readiness',
            'title' => 'Disponibilité des unités',
            'isBackOfficeShell' => true,
            'boPageTitle' => 'Disponibilité des unités',
            'boPageKicker' => 'ORGANISATION · DISPONIBILITÉ',
            'boPageSubtitle' => 'Postes pourvus, qualifications à jour et activité récente de chaque unité, calculés à partir des données existantes.',
            'scores' => $scores,
            'readinessLoadError' => $loadError,
            'readinessCanOpenUnit' => $gate->allows('organization.orbat.manage') || $gate->allows('admin.organization'),
        ]);
    }
}
