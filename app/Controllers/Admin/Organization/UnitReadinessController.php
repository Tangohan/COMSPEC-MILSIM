<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Personnel\UnitReadinessService;

final class UnitReadinessController
{
    public function __construct(
        private UnitReadinessService $readiness,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.readiness',
            'title' => 'Disponibilité',
            'isBackOfficeShell' => true,
            'boPageTitle' => 'Score de disponibilité',
            'boPageKicker' => 'ORGANISATION · READINESS',
            'boPageSubtitle' => 'Effectif pourvu, qualifications à jour et dernière activité — agrégation, pas de nouvelle saisie.',
            'scores' => $this->readiness->scoresForTenant($tenantId),
        ]);
    }
}
