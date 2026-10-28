<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
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
        private ?UserRepository $users = null,
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
            'title' => 'Qualité des données',
            'summary' => $summary,
            'peopleNames' => $this->peopleNames($tenantId, $summary),
            'occupancyTypes' => BilletOccupancyType::options(),
            'schemaReady' => $this->billetService->schemaReady(),
            'csrfToken' => Csrf::token(),
        ]);
    }

    /**
     * Noms affichés des membres cités par la page (mouvements, auteurs de snapshots, anomalies),
     * pour ne plus montrer de simples numéros « user #12 ». Jamais l’adresse e-mail.
     *
     * @param array<string, mixed> $summary
     * @return array<int, string>
     */
    private function peopleNames(int $tenantId, array $summary): array
    {
        if ($this->users === null) {
            return [];
        }
        $ids = [];
        foreach ((array) ($summary['movements'] ?? []) as $m) {
            $ids[] = (int) ($m['user_id'] ?? 0);
            $ids[] = (int) ($m['actor_user_id'] ?? 0);
        }
        foreach ((array) ($summary['snapshots'] ?? []) as $s) {
            $ids[] = (int) ($s['created_by'] ?? 0);
        }
        foreach ((array) ($summary['anomalies'] ?? []) as $a) {
            $ids[] = (int) ($a['meta']['user_id'] ?? 0);
        }
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        try {
            $rows = $this->users->findByIdsForTenant($tenantId, $ids);
        } catch (\Throwable) {
            return [];
        }
        $names = [];
        foreach ($rows as $uid => $row) {
            foreach (['display_name', 'callsign', 'name', 'username'] as $col) {
                $v = trim((string) ($row[$col] ?? ''));
                if ($v !== '') {
                    $names[(int) $uid] = $v;
                    break;
                }
            }
        }

        return $names;
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
