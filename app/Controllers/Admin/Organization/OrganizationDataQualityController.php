<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Organization\OrbatBilletService;
use App\Support\BilletOccupancyType;
use App\Support\OrgDomainModel;

/**
 * Centre Qualité des données ORBAT — anomalies, mouvements, corbeille, snapshots.
 */
final class OrganizationDataQualityController
{
    public function __construct(
        private OrbatBilletService $billetService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (
            !$gate->allows('admin.organization')
            && !$gate->allows('admin.access')
            && !$gate->allows('organization.orbat.manage')
        ) {
            Session::flash('error', 'Droits insuffisants pour la qualité des données organisationnelles.');

            return Response::redirect(url('dashboard'));
        }

        $summary = $this->billetService->schemaReady()
            ? $this->billetService->dataQualitySummary($tenantId)
            : [
                'totals' => [
                    'anomalies' => 0,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0,
                    'vacant_billet' => 0,
                ],
                'anomalies' => [],
                'movements' => [],
                'trash' => [],
                'snapshots' => [],
                'movement_reasons' => [],
                'domain_model' => OrgDomainModel::catalog(),
            ];

        return Response::view('layout.main', [
            'content' => 'admin.organization.data_quality',
            'title' => 'Qualité des données ORBAT',
            'summary' => $summary,
            'occupancyTypes' => BilletOccupancyType::options(),
            'schemaReady' => $this->billetService->schemaReady(),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function snapshot(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if ($tenantId < 1 || $actorId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/organisation/qualite-donnees'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('organization.orbat.manage')) {
            Session::flash('error', 'Droits insuffisants.');

            return Response::redirect(url('dashboard'));
        }

        $label = trim((string) $request->input('label', 'Snapshot ORBAT'));
        $kind = trim((string) $request->input('kind', 'manual'));
        $effectiveAt = trim((string) $request->input('effective_at', ''));
        $notes = trim((string) $request->input('notes', ''));
        $result = $this->billetService->snapshotOrbat(
            $tenantId,
            $label !== '' ? $label : 'Snapshot ORBAT',
            $kind !== '' ? $kind : 'manual',
            $effectiveAt !== '' ? $effectiveAt : null,
            $actorId,
            $notes !== '' ? $notes : null
        );
        Session::flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok']
                ? 'Snapshot ORBAT enregistré.'
                : ($result['message'] ?? 'Échec du snapshot.')
        );

        return Response::redirect(url('back-office/organisation/qualite-donnees'));
    }

    public function restoreBillet(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/organisation/qualite-donnees'));
        }

        $billetId = (int) ($params['id'] ?? $request->input('billet_id', 0));
        $result = $this->billetService->restoreBillet($tenantId, $billetId, $actorId);
        Session::flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok']
                ? 'Poste restauré.'
                : ($result['message'] ?? 'Restauration impossible.')
        );

        return Response::redirect(url('back-office/organisation/qualite-donnees'));
    }
}
