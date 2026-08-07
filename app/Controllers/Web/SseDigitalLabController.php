<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseDigitalLabRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseDigitalLabService;

/**
 * Portail SSE — Laboratoire numérique (ATH-SSE-LABNUM).
 */
final class SseDigitalLabController
{
    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseCaseRepository $cases = null,
        private ?SseDigitalLabRepository $repo = null,
        private ?SseDigitalLabService $lab = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->cases ??= new SseCaseRepository();
        $this->repo ??= new SseDigitalLabRepository();
        $this->lab ??= new SseDigitalLabService($this->repo);
    }

    public function hub(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $counts = $this->repo->hubCounts($tenantId);

        return $this->portalView('atak.sse.digital.hub', [
            'title' => 'Exploitation numérique',
            'counts' => $counts,
            'recentDevices' => $this->repo->listDevices($tenantId, ['limit' => 8]),
            'pendingFindings' => $this->repo->listFindings($tenantId, ['status' => 'to_review']),
            'activeNav' => 'labnum',
            'labSubnav' => 'hub',
        ]);
    }

    public function devicesIndex(Request $request, array $params = []): Response
    {
        $filters = [
            'status' => (string) $request->query('status', ''),
            'device_type' => (string) $request->query('device_type', ''),
            'q' => (string) $request->query('q', ''),
        ];

        return $this->portalView('atak.sse.digital.devices', [
            'title' => 'Supports numériques',
            'devices' => $this->repo->listDevices($this->tenantId(), $filters),
            'filters' => $filters,
            'statuses' => SseDigitalLabRepository::DEVICE_STATUSES,
            'types' => SseDigitalLabRepository::DEVICE_TYPES,
            'activeNav' => 'labnum',
            'labSubnav' => 'supports',
        ]);
    }

    public function deviceCreateForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/exploitation-numerique/supports'));
        }

        return $this->portalView('atak.sse.digital.device_form', [
            'title' => 'Enregistrer un support',
            'types' => SseDigitalLabRepository::DEVICE_TYPES,
            'statuses' => SseDigitalLabRepository::DEVICE_STATUSES,
            'profiles' => SseDigitalLabRepository::DATA_PROFILES,
            'activeNav' => 'labnum',
            'labSubnav' => 'supports',
        ]);
    }

    public function deviceStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/exploitation-numerique/supports'));
        }

        $type = SseDigitalLabRepository::normalizeDeviceType((string) $request->input('device_type', 'telephone'));
        $result = $this->lab->registerDevice($this->tenantId(), [
            'device_type' => $type,
            'manufacturer' => $request->input('manufacturer'),
            'model' => $request->input('model'),
            'serial_number' => $request->input('serial_number'),
            'color' => $request->input('color'),
            'capacity_label' => $request->input('capacity_label'),
            'apparent_condition' => $request->input('apparent_condition'),
            'lock_state' => $request->input('lock_state'),
            'has_sim' => $request->input('has_sim') === '1',
            'has_memory_card' => $request->input('has_memory_card') === '1',
            'has_battery' => $request->input('has_battery') !== '0',
            'discovery_place' => $request->input('discovery_place'),
            'mission_label' => $request->input('mission_label'),
            'seized_by_label' => $request->input('seized_by_label'),
            'power_state' => $request->input('power_state'),
            'locked' => $request->input('locked') === '1',
            'airplane_mode' => $request->input('airplane_mode') === '1',
            'network_connected' => $request->input('network_connected') === '1',
            'encryption_detected' => $request->input('encryption_detected') === '1',
            'presumed_os' => $request->input('presumed_os'),
            'displayed_time' => $request->input('displayed_time'),
            'language_label' => $request->input('language_label'),
            'damage_notes' => $request->input('damage_notes'),
            'accessories_notes' => $request->input('accessories_notes'),
            'discovered_at' => $this->normalizeDatetime($request->input('discovered_at')),
            'seized_at' => $this->normalizeDatetime($request->input('seized_at')) ?? date('Y-m-d H:i:s'),
            'packaging_notes' => $request->input('packaging_notes'),
            'observations' => $request->input('observations'),
            'seal_label' => $request->input('seal_label'),
            'data_profile' => $request->input('data_profile'),
            'status' => 'seized',
            'classification' => 'confidentiel',
        ], $this->userId());

        Session::flash('success', 'Support enregistré.');

        return Response::redirect(url('atak/sse/exploitation-numerique/supports/' . $result['device_id']));
    }

    public function deviceShow(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $device = $this->repo->findDevice((int) ($params['id'] ?? 0), $tenantId);
        if ($device === null) {
            Session::flash('error', 'Support introuvable.');

            return Response::redirect(url('atak/sse/exploitation-numerique/supports'));
        }

        $deviceId = (int) $device['id'];

        return $this->portalView('atak.sse.digital.device_show', [
            'title' => 'Support — ' . ($device['reference_code'] ?? ''),
            'device' => $device,
            'seizures' => $this->repo->listSeizuresForDevice($deviceId, $tenantId),
            'acquisitions' => $this->repo->listAcquisitions($tenantId, ['device_id' => $deviceId]),
            'artifacts' => $this->repo->listArtifacts($tenantId, ['device_id' => $deviceId, 'limit' => 40]),
            'findings' => $this->repo->listFindings($tenantId, ['device_id' => $deviceId]),
            'methods' => SseDigitalLabRepository::ACQUISITION_METHODS,
            'profiles' => SseDigitalLabRepository::DATA_PROFILES,
            'activeNav' => 'labnum',
            'labSubnav' => 'supports',
        ]);
    }

    public function acquisitionStore(Request $request, array $params = []): Response
    {
        $deviceId = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/exploitation-numerique/supports/' . $deviceId);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        try {
            $acqId = $this->lab->runSimulatedAcquisition($this->tenantId(), $deviceId, [
                'method' => $request->input('method', 'logical'),
                'data_profile' => $request->input('data_profile'),
                'operator_label' => $request->input('operator_label'),
            ], $this->userId());
        } catch (\Throwable $e) {
            Session::flash('error', 'Impossible de lancer l’acquisition.');

            return Response::redirect($back);
        }

        Session::flash('success', 'Acquisition simulée terminée. Les signaux analytiques sont des propositions à examiner.');

        return Response::redirect(url('atak/sse/exploitation-numerique/acquisitions/' . $acqId));
    }

    public function acquisitionsIndex(Request $request, array $params = []): Response
    {
        $filters = ['status' => (string) $request->query('status', '')];

        return $this->portalView('atak.sse.digital.acquisitions', [
            'title' => 'Acquisitions numériques',
            'acquisitions' => $this->repo->listAcquisitions($this->tenantId(), $filters),
            'filters' => $filters,
            'statuses' => SseDigitalLabRepository::ACQUISITION_STATUSES,
            'activeNav' => 'labnum',
            'labSubnav' => 'acquisitions',
        ]);
    }

    public function acquisitionShow(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $acq = $this->repo->findAcquisition((int) ($params['id'] ?? 0), $tenantId);
        if ($acq === null) {
            Session::flash('error', 'Acquisition introuvable.');

            return Response::redirect(url('atak/sse/exploitation-numerique/acquisitions'));
        }

        return $this->portalView('atak.sse.digital.acquisition_show', [
            'title' => 'Acquisition — ' . ($acq['reference_code'] ?? ''),
            'acquisition' => $acq,
            'logs' => $this->repo->listAcquisitionLogs((int) $acq['id'], $tenantId),
            'artifacts' => $this->repo->listArtifacts($tenantId, [
                'acquisition_id' => (int) $acq['id'],
                'limit' => 100,
            ]),
            'activeNav' => 'labnum',
            'labSubnav' => 'acquisitions',
        ]);
    }

    public function artifactsIndex(Request $request, array $params = []): Response
    {
        $filters = [
            'category' => (string) $request->query('category', ''),
            'status' => (string) $request->query('status', ''),
            'q' => (string) $request->query('q', ''),
            'device_id' => (int) $request->query('device_id', 0) ?: null,
        ];

        return $this->portalView('atak.sse.digital.artifacts', [
            'title' => 'Artefacts numériques',
            'artifacts' => $this->repo->listArtifacts($this->tenantId(), $filters),
            'filters' => $filters,
            'categories' => SseDigitalLabRepository::ARTIFACT_CATEGORIES,
            'statuses' => SseDigitalLabRepository::ARTIFACT_STATUSES,
            'activeNav' => 'labnum',
            'labSubnav' => 'artefacts',
        ]);
    }

    public function artifactShow(Request $request, array $params = []): Response
    {
        $artifact = $this->repo->findArtifact((int) ($params['id'] ?? 0), $this->tenantId());
        if ($artifact === null) {
            Session::flash('error', 'Artefact introuvable.');

            return Response::redirect(url('atak/sse/exploitation-numerique/artefacts'));
        }

        return $this->portalView('atak.sse.digital.artifact_show', [
            'title' => 'Artefact — ' . ($artifact['name'] ?? ''),
            'artifact' => $artifact,
            'statuses' => SseDigitalLabRepository::ARTIFACT_STATUSES,
            'activeNav' => 'labnum',
            'labSubnav' => 'artefacts',
        ]);
    }

    public function artifactUpdate(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/exploitation-numerique/artefacts/' . $id);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $this->repo->updateArtifactStatus(
            $id,
            $this->tenantId(),
            (string) $request->input('status', 'unexamined'),
            trim((string) $request->input('analyst_comment', '')) ?: null
        );
        Session::flash('success', 'Fiche artefact mise à jour.');

        return Response::redirect($back);
    }

    public function findingsIndex(Request $request, array $params = []): Response
    {
        $filters = ['status' => (string) $request->query('status', 'to_review')];

        return $this->portalView('atak.sse.digital.findings', [
            'title' => 'Analyses et signaux',
            'findings' => $this->repo->listFindings($this->tenantId(), $filters),
            'filters' => $filters,
            'statuses' => SseDigitalLabRepository::FINDING_STATUSES,
            'activeNav' => 'labnum',
            'labSubnav' => 'analyses',
        ]);
    }

    public function findingReview(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/exploitation-numerique/analyses');
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $status = (string) $request->input('status', '');
        $ok = $this->repo->reviewFinding(
            $id,
            $this->tenantId(),
            $status,
            $this->userId() ?: 0,
            trim((string) $request->input('review_comment', '')) ?: null
        );
        Session::flash($ok ? 'success' : 'error', $ok
            ? 'Décision enregistrée. Aucune consolidation automatique n’a été effectuée.'
            : 'Décision impossible.');

        return Response::redirect($back);
    }

    public function timeline(Request $request, array $params = []): Response
    {
        $filters = [
            'device_id' => (int) $request->query('device_id', 0) ?: null,
            'event_type' => (string) $request->query('event_type', ''),
            'validated' => (string) $request->query('validated', ''),
        ];

        return $this->portalView('atak.sse.digital.timeline', [
            'title' => 'Chronologie numérique',
            'events' => $this->repo->listTimeline($this->tenantId(), $filters),
            'devices' => $this->repo->listDevices($this->tenantId(), ['limit' => 100]),
            'filters' => $filters,
            'activeNav' => 'labnum',
            'labSubnav' => 'chronologies',
        ]);
    }

    public function phoneView(Request $request, array $params = []): Response
    {
        return $this->deviceSpecialView((int) ($params['id'] ?? 0), 'phone', 'atak.sse.digital.phone');
    }

    public function computerView(Request $request, array $params = []): Response
    {
        return $this->deviceSpecialView((int) ($params['id'] ?? 0), 'computer', 'atak.sse.digital.computer');
    }

    public function communications(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $deviceId = (int) $request->query('device_id', 0);
        $devices = $this->repo->listDevices($tenantId, ['limit' => 100]);
        if ($deviceId < 1 && $devices !== []) {
            $deviceId = (int) ($devices[0]['id'] ?? 0);
        }

        return $this->portalView('atak.sse.digital.communications', [
            'title' => 'Communications numériques',
            'devices' => $devices,
            'deviceId' => $deviceId,
            'messages' => $deviceId > 0 ? $this->repo->listMessages($deviceId, $tenantId) : [],
            'calls' => $deviceId > 0 ? $this->repo->listCalls($deviceId, $tenantId) : [],
            'activeNav' => 'labnum',
            'labSubnav' => 'communications',
        ]);
    }

    public function reports(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $deviceId = (int) $request->query('device_id', 0);
        $device = $deviceId > 0 ? $this->repo->findDevice($deviceId, $tenantId) : null;
        $acquisitions = $device
            ? $this->repo->listAcquisitions($tenantId, ['device_id' => $deviceId])
            : [];
        $findings = $device
            ? $this->repo->listFindings($tenantId, ['device_id' => $deviceId])
            : [];
        $timeline = $device
            ? $this->repo->listTimeline($tenantId, ['device_id' => $deviceId, 'limit' => 50])
            : [];

        return $this->portalView('atak.sse.digital.reports', [
            'title' => 'Rapports d’exploitation numérique',
            'devices' => $this->repo->listDevices($tenantId, ['limit' => 100]),
            'device' => $device,
            'acquisitions' => $acquisitions,
            'findings' => $findings,
            'timeline' => $timeline,
            'activeNav' => 'labnum',
            'labSubnav' => 'rapports',
        ]);
    }

    private function deviceSpecialView(int $deviceId, string $kind, string $view): Response
    {
        $tenantId = $this->tenantId();
        $device = $this->repo->findDevice($deviceId, $tenantId);
        if ($device === null) {
            Session::flash('error', 'Support introuvable.');

            return Response::redirect(url('atak/sse/exploitation-numerique/supports'));
        }

        return $this->portalView($view, [
            'title' => ($kind === 'phone' ? 'Extraction mobile' : 'Vue ordinateur') . ' — ' . ($device['reference_code'] ?? ''),
            'device' => $device,
            'contacts' => $this->repo->listContacts($deviceId, $tenantId),
            'messages' => $this->repo->listMessages($deviceId, $tenantId),
            'calls' => $this->repo->listCalls($deviceId, $tenantId),
            'accounts' => $this->repo->listAccounts($deviceId, $tenantId),
            'locations' => $this->repo->listLocations($deviceId, $tenantId),
            'networks' => $this->repo->listNetworks($deviceId, $tenantId),
            'applications' => $this->repo->listApplications($deviceId, $tenantId),
            'media' => $this->repo->listMedia($deviceId, $tenantId),
            'artifacts' => $this->repo->listArtifacts($tenantId, ['device_id' => $deviceId, 'limit' => 100]),
            'timeline' => $this->repo->listTimeline($tenantId, ['device_id' => $deviceId]),
            'activeNav' => 'labnum',
            'labSubnav' => 'supports',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function portalView(string $view, array $data): Response
    {
        $data['isGuest'] = $this->access->isGuest();
        $data['clearanceUntil'] = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);
        $data['guestLabel'] = (string) Session::get('sse_guest_label', '');
        $data['sseTheme'] = sse_ui_theme();
        $data['sseThemeOptions'] = sse_ui_theme_options();
        $data['canGrant'] = $data['canGrant'] ?? $this->canGrant();
        $data['canManage'] = $data['canManage'] ?? $this->canManage();

        $tenantId = $this->tenantId();
        if ($tenantId > 0 && $this->access->hasActiveClearance()) {
            $scope = $this->access->caseScope();
            $allForRail = $this->cases->listForTenant($tenantId, $scope);
            $data['sseFolderTree'] = $this->cases->buildTree($allForRail);
            $data['sseFolderParents'] = array_values(array_filter(
                $allForRail,
                static fn (array $c): bool => !empty($c['is_folder'])
            ));
            if (!isset($data['indexCounts'])) {
                $indexCounts = ['total' => count($allForRail), 'active' => 0, 'archive' => 0];
                foreach ($allForRail as $case) {
                    $status = (string) ($case['status'] ?? '');
                    if (in_array($status, ['ouvert', 'en_cours'], true)) {
                        $indexCounts['active']++;
                    }
                    if ($status === 'archive') {
                        $indexCounts['archive']++;
                    }
                }
                $data['indexCounts'] = $indexCounts;
            }
            $data['sseRecentCases'] = Session::get('sse_recent_cases', []);
            if (!is_array($data['sseRecentCases'])) {
                $data['sseRecentCases'] = [];
            }
        } else {
            $data['sseFolderTree'] = $data['sseFolderTree'] ?? [];
            $data['sseFolderParents'] = $data['sseFolderParents'] ?? [];
            $data['sseRecentCases'] = $data['sseRecentCases'] ?? [];
            $data['indexCounts'] = $data['indexCounts'] ?? ['total' => 0, 'active' => 0, 'archive' => 0];
        }

        return Response::view($view, $data);
    }

    private function tenantId(): int
    {
        $tid = $this->access->tenantId();
        if ($tid > 0) {
            return $tid;
        }

        return (int) Session::get('tenant_id');
    }

    private function userId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    private function canManage(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.case.manage') || can('atak.sse.grant') || can('admin.access'));
    }

    private function canGrant(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.grant') || can('admin.access'));
    }

    private function normalizeDatetime(mixed $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        $s = str_replace('T', ' ', $s);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s)) {
            $s .= ':00';
        }

        return $s;
    }
}
