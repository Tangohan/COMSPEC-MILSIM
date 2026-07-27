<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Repositories\EventRsvpNominativeRepository;
use App\Services\Attendance\EventRsvpNominativeService;
use App\Services\Platform\FeatureGateService;
use App\Support\ModuleFeatureAccess;

final class EventRsvpNominativeAdminController
{
    public function __construct(
        private ?CommunityEventRepository $events = null,
        private ?EventRsvpNominativeRepository $nominative = null,
        private ?EventRsvpNominativeService $service = null,
        private ?FeatureGateService $featureGate = null,
    ) {
        $this->events ??= new CommunityEventRepository();
        $this->nominative ??= new EventRsvpNominativeRepository();
        $this->service ??= new EventRsvpNominativeService($this->nominative);
        $this->featureGate ??= new FeatureGateService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::view('layout.main', [
                'title' => 'Réponses nominatives',
                'content' => 'platform.upgrade',
                'feature' => 'events',
                'planName' => 'pro',
            ]);
        }
        $forbidden = ModuleFeatureAccess::guardOperations('view');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $eventId = (int) ($params['id'] ?? 0);
        $event = $eventId > 0 ? $this->events->findByIdForTenant($eventId, $tenantId) : null;
        if (!$event) {
            Session::flash('error', 'Créneau introuvable.');
            return Response::redirect(url('back-office/events'));
        }

        $filters = $this->filtersFromRequest($request);
        $data = $this->service->listForEvent($tenantId, $eventId, $filters);

        $eventTitle = trim((string) ($event['title'] ?? ''));

        return Response::view('layout.main', [
            'content' => 'admin.organization.event_rsvp_nominative',
            'title' => 'Réponses nominatives',
            'boPageTitle' => 'Réponses nominatives',
            'boPageSubtitle' => ($eventTitle !== '' ? $eventTitle : 'Créneau') . ' · suivi nominatif des réponses, disponibilités et relances.',
            'event' => $event,
            'nominativeRows' => $data['rows'],
            'nominativeStats' => $data['stats'],
            'nominativeSections' => $data['sections'],
            'nominativeFilters' => $filters,
            'responseFilterLabels' => EventRsvpNominativeService::responseFilterLabelsFr(),
            'atakFilterLabels' => EventRsvpNominativeService::atakFilterLabelsFr(),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function export(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $forbidden = ModuleFeatureAccess::guardOperations('export');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $eventId = (int) ($params['id'] ?? 0);
        $event = $eventId > 0 ? $this->events->findByIdForTenant($eventId, $tenantId) : null;
        if (!$event) {
            Session::flash('error', 'Créneau introuvable.');
            return Response::redirect(url('back-office/events'));
        }

        $filters = $this->filtersFromRequest($request);
        $csv = $this->service->exportCsv($tenantId, $eventId, $filters);
        $slug = preg_replace('/[^a-zA-Z0-9_-]+/u', '-', (string) ($event['title'] ?? 'creneau'));
        $slug = trim((string) $slug, '-') ?: 'creneau';

        $response = new Response();
        $response->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="reponses-nominatives-' . $slug . '-' . $eventId . '.csv"')
            ->header('Cache-Control', 'no-store')
            ->setBody("\xEF\xBB\xBF" . $csv);

        return $response;
    }

    public function updateMeta(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $forbidden = ModuleFeatureAccess::guardOperations('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/events/' . (int) ($params['id'] ?? 0) . '/reponses-nominatives'));
        }

        $eventId = (int) ($params['id'] ?? 0);
        $userId = (int) $request->input('user_id', 0);
        $saved = $this->service->updateRowMeta($tenantId, $eventId, $userId, [
            'availability_from' => $request->input('availability_from'),
            'availability_to' => $request->input('availability_to'),
            'admin_comment' => $request->input('admin_comment'),
        ]);
        Session::flash($saved ? 'success' : 'error', $saved
            ? 'Informations mises à jour.'
            : 'Mise à jour impossible pour ce membre.');

        return Response::redirect(url('back-office/events/' . $eventId . '/reponses-nominatives'));
    }

    /**
     * @return array{q: string, response: string, section: string, atak: string}
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'response' => trim((string) $request->query('response', '')),
            'section' => trim((string) $request->query('section', '')),
            'atak' => trim((string) $request->query('atak', '')),
        ];
    }
}
