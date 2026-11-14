<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ElevationRequestRepository;
use App\Repositories\PersonnelCorrectionRequestRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Services\Community\TenantTypeConfig;
use App\Services\Effectifs\ElevationApprovalService;
use App\Services\Effectifs\HrRequestsDesk;
use App\Services\Personnel\PersonnelCorrectionRequestService;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Workflow\WorkflowHooks;
use App\Support\BackOfficeTabs;

/**
 * GET /back-office/demandes-rh : file unique des demandes RH en attente de décision (élévation, correction, mobilité),
 * avec l'avis du circuit dans la ligne. Page d'accueil de la famille d'onglets (App\Support\BackOfficeTabs) : chaque
 * onglet est l'ancien écran du type, avec ses droits et ses actions de décision. Cette page ne décide de rien.
 *
 * Elle ne montre que les types que la personne peut lire sur l'écran d'origine (HrRequestsDesk::visibleTypes).
 */
final class HrRequestsController
{
    public const BASE = 'back-office/demandes-rh';

    public function index(Request $request, array $params = []): Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $types = $this->typesForViewer(Gate::getInstance());
        if ($types === []) {
            Session::flash('error', 'Vous n’avez accès à aucune file de demandes RH.');

            return Response::redirect(url('dashboard'));
        }

        $rows = [];
        $counts = [];
        foreach ($types as $type) {
            [$typeRows, $counts[$type]] = match ($type) {
                HrRequestsDesk::TYPE_ELEVATION => $this->elevations($tenantId),
                HrRequestsDesk::TYPE_CORRECTION => $this->corrections($tenantId),
                default => $this->mobility($tenantId),
            };
            array_push($rows, ...$typeRows);
        }
        $rows = HrRequestsDesk::sortOldestFirst($rows);
        foreach ($rows as &$row) {
            $row['href'] = url(BackOfficeTabs::tabPath(self::BASE, (string) $row['tab']));
        }
        unset($row);

        $tabs = [];
        foreach ($types as $type) {
            $meta = HrRequestsDesk::TYPES[$type];
            $tabs[] = [
                'label' => $meta['label'],
                'count' => $counts[$type],
                'href' => url(BackOfficeTabs::tabPath(self::BASE, $meta['tab'])),
            ];
        }

        return Response::view('layout.main', [
            'title' => 'Demandes RH',
            'boPageGroup' => 'Personnel',
            'boPageSubtitle' => 'Ce qui attend une décision, tous types confondus. Chaque type garde son écran de décision.',
            'content' => 'admin.hr_requests.index',
            'hrRequests' => $rows,
            'hrTypeCounts' => $tabs,
            'hrRowsPerType' => HrRequestsDesk::ROWS_PER_TYPE,
        ]);
    }

    /**
     * Types lisibles par la personne (droits de l'écran d'origine) et non retirés du menu par le profil de communauté.
     *
     * @return list<string>
     */
    private function typesForViewer(Gate $gate): array
    {
        $tenantType = function_exists('navigation_current_tenant_type') ? navigation_current_tenant_type() : null;
        $out = [];
        foreach (HrRequestsDesk::visibleTypes($gate) as $type) {
            $legacy = HrRequestsDesk::TYPES[$type]['legacy'];
            if ($tenantType !== null
                && (!TenantTypeConfig::uriAllowed($tenantType, $legacy) || TenantTypeConfig::backOfficePathHidden($tenantType, $legacy))) {
                continue;
            }
            $out[] = $type;
        }

        return $out;
    }

    /** @return array{0: list<array<string, mixed>>, 1: int} */
    private function elevations(int $tenantId): array
    {
        $repo = Container::get(ElevationRequestRepository::class);
        $service = Container::get(ElevationApprovalService::class);
        $requests = $repo->listOpenForTenant($tenantId, HrRequestsDesk::ROWS_PER_TYPE);
        $circuits = WorkflowHooks::summaries($tenantId, WorkflowEngine::SUBJECT_ELEVATION, $requests);
        $rows = [];
        foreach ($requests as $r) {
            $labels = $service->proposalLabels($tenantId, [
                'grade_id' => (int) ($r['proposed_grade_id'] ?? 0) ?: null,
                'role_id' => (int) ($r['proposed_role_id'] ?? 0) ?: null,
                'job_role_id' => (int) ($r['proposed_job_role_id'] ?? 0) ?: null,
                'unit_id' => (int) ($r['proposed_unit_id'] ?? 0) ?: null,
            ]);
            $rows[] = HrRequestsDesk::elevationRow($r, $labels, $circuits[(int) ($r['id'] ?? 0)] ?? null);
        }

        return [$rows, $repo->countOpenForTenant($tenantId)];
    }

    /** @return array{0: list<array<string, mixed>>, 1: int} */
    private function corrections(int $tenantId): array
    {
        $repo = Container::get(PersonnelCorrectionRequestRepository::class);
        $service = Container::get(PersonnelCorrectionRequestService::class);
        $requests = $repo->listOpenForTenant($tenantId, HrRequestsDesk::ROWS_PER_TYPE);
        $circuits = WorkflowHooks::summaries($tenantId, WorkflowEngine::SUBJECT_CORRECTION, $requests);
        $rows = [];
        foreach ($requests as $r) {
            $proposed = is_array($r['proposed'] ?? null) ? $r['proposed'] : [];
            $before = is_array($r['before'] ?? null) ? $r['before'] : [];
            $rows[] = HrRequestsDesk::correctionRow(
                $r,
                $service->diffLinesForDisplay($proposed, $before, $tenantId),
                $circuits[(int) ($r['id'] ?? 0)] ?? null
            );
        }

        return [$rows, $repo->countPendingForTenant($tenantId)];
    }

    /** @return array{0: list<array<string, mixed>>, 1: int} */
    private function mobility(int $tenantId): array
    {
        $repo = Container::get(PersonnelMobilityRequestRepository::class);
        if (!$repo->tableExists()) {
            return [[], 0];
        }
        $requests = $repo->listPendingForTenant($tenantId, HrRequestsDesk::ROWS_PER_TYPE);
        $circuits = WorkflowHooks::summaries($tenantId, WorkflowEngine::SUBJECT_MOBILITY, $requests);
        $rows = [];
        foreach ($requests as $r) {
            $rows[] = HrRequestsDesk::mobilityRow($r, PersonnelMobilityRequestRepository::TYPE_LABELS, $circuits[(int) ($r['id'] ?? 0)] ?? null);
        }

        return [$rows, $repo->countPending($tenantId)];
    }
}
