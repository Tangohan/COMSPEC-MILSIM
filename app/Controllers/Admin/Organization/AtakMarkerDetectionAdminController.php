<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMarkerDetectionRuleRepository;
use App\Support\AtakMarkerDetection;

final class AtakMarkerDetectionAdminController
{
    public function __construct(
        private AtakMarkerDetectionRuleRepository $rules,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $this->markReviewed($tenantId);

        return Response::view('layout.main', [
            'title' => 'Détection des marqueurs',
            'content' => 'admin.organization.atak_marker_detection',
            'detectionRules' => $this->rules->tablesReady() ? $this->rules->listForTenant($tenantId) : [],
            'detectionReady' => $this->rules->tablesReady(),
            'detectionModes' => AtakMarkerDetection::matchModes(),
            'detectionTypes' => AtakMarkerDetection::markerTypes(),
            'detectionRadii' => AtakMarkerDetection::RADII_M,
            'detectionFormAction' => url('back-office/atak/detection-marqueurs'),
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        $redirect = url('back-office/atak/detection-marqueurs');
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        if (!$this->rules->tablesReady()) {
            Session::flash('error', 'La détection des marqueurs n’est pas encore disponible sur ce serveur.');

            return Response::redirect($redirect);
        }
        $action = (string) $request->input('detection_action', 'create');
        if ($action === 'delete') {
            $id = (int) $request->input('rule_id', 0);
            if ($id > 0) {
                $this->rules->delete($tenantId, $id);
            }
            $this->markReviewed($tenantId);
            Session::flash('success', 'Règle retirée.');

            return Response::redirect($redirect);
        }
        $payload = $this->payloadFromRequest($request);
        if ($payload['error'] !== null) {
            Session::flash('error', $payload['error']);

            return Response::redirect($redirect);
        }
        unset($payload['error']);
        if ($action === 'update') {
            $id = (int) $request->input('rule_id', 0);
            if ($id < 1) {
                Session::flash('error', 'Règle introuvable.');

                return Response::redirect($redirect);
            }
            $this->rules->update($tenantId, $id, $payload);
            $this->markReviewed($tenantId);
            Session::flash('success', 'Règle enregistrée. Les marqueurs déjà posés en jeu sont suivis dès le prochain passage.');

            return Response::redirect($redirect);
        }
        $payload['position'] = $this->rules->countForTenant($tenantId) + 1;
        $this->rules->create($tenantId, $payload);
        $this->markReviewed($tenantId);
        Session::flash('success', 'Règle créée. Les marqueurs correspondants posés en jeu apparaissent au poste et, selon votre choix, sur les téléphones.');

        return Response::redirect($redirect);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromRequest(Request $request): array
    {
        $label = mb_substr(trim((string) $request->input('label', '')), 0, 80);
        if ($label === '') {
            return ['error' => 'Indiquez un nom pour cette règle.'];
        }
        $mode = AtakMarkerDetection::normalizeMode((string) $request->input('match_mode', ''));
        $value = trim((string) $request->input('match_value', ''));
        if ($mode === AtakMarkerDetection::MATCH_MARKER_TYPE) {
            $value = strtolower(trim(str_replace([' ', '-'], '_', (string) $request->input('match_type', $value))));
            if (!array_key_exists($value, AtakMarkerDetection::markerTypes())) {
                return ['error' => 'Choisissez un symbole dans la liste.'];
            }
        } else {
            $value = mb_substr($value, 0, 64);
            if ($value === '') {
                return ['error' => 'Indiquez le texte à reconnaître sur le marqueur.'];
            }
        }

        return [
            'error' => null,
            'label' => $label,
            'match_mode' => $mode,
            'match_value' => $value,
            'radius_m' => AtakMarkerDetection::normalizeRadius((int) $request->input('radius_m', 20)),
            'confirm_arrival' => (bool) $request->input('confirm_arrival'),
            'notify_web' => (bool) $request->input('notify_web'),
            'notify_atak' => (bool) $request->input('notify_atak'),
            'is_active' => $request->input('is_active') ? true : $request->input('detection_action') === 'create',
        ];
    }

    private function markReviewed(int $tenantId): void
    {
        try {
            (new \App\Repositories\TenantRepository())->mergeSettings($tenantId, [
                'atak_marker_detection' => ['reviewed' => true],
            ]);
        } catch (\Throwable) {
        }
        try {
            \App\Core\Container::get(\App\Services\ConfigurationUpdate\ConfigurationUpdateService::class)
                ->markCompleted($tenantId, 'ATAK_MARKER_DETECTION_V1', (int) Session::get('user_id') ?: null);
        } catch (\Throwable) {
        }
    }
}
