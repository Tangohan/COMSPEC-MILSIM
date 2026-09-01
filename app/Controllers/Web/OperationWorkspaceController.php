<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\OperationWorkspaceRepository;
use App\Services\Operations\OperationWorkspaceService;
use App\Support\OperationLabels;

final class OperationWorkspaceController
{
    public function __construct(
        private OperationWorkspaceService $workspace,
        private OperationWorkspaceRepository $repo,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $denied = $this->guardView();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $rows = array_map(
            [$this->workspace, 'presentOperation'],
            $this->repo->listForTenant($tenantId)
        );
        if (!$this->workspace->canPlan()) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $op): bool => ($op['status'] ?? '') !== 'draft'
            ));
        }

        return Response::view('layout.main', [
            'title' => 'Opérations',
            'content' => 'operations/workspace/index',
            'opsWorkspacePage' => true,
            'showPortalFooter' => false,
            'operations' => $rows,
            'canPlan' => $this->workspace->canPlan(),
            'statusOptions' => OperationLabels::statusOptions(),
            'classificationOptions' => OperationLabels::classificationOptions(),
            'csrfToken' => Csrf::token(),
            'flash_success' => Session::getFlash('success'),
            'flash_error' => Session::getFlash('error'),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $denied = $this->guardPlan();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('operations'));
        }
        $result = $this->workspace->createOperation(
            (int) Session::get('tenant_id'),
            (int) Session::get('user_id'),
            [
                'name' => (string) $request->input('name'),
                'code' => (string) $request->input('code'),
                'classification' => (string) $request->input('classification'),
                'status' => (string) $request->input('status'),
                'description' => (string) $request->input('description'),
            ]
        );
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Création impossible.'));

            return Response::redirect(url('operations'));
        }
        Session::flash('success', 'L’espace opérationnel a été ouvert.');

        return Response::redirect(url('operations/' . $result['uuid']));
    }

    public function show(Request $request, array $params = []): Response
    {
        $denied = $this->guardView();
        if ($denied !== null) {
            return $denied;
        }
        $uuid = trim((string) ($params['uuid'] ?? ''));
        $canPlan = $this->workspace->canPlan();
        $payload = $this->workspace->workspacePayload((int) Session::get('tenant_id'), $uuid, $canPlan);
        if ($payload === null) {
            Session::flash('error', 'Cette opération n’existe pas dans votre communauté.');

            return Response::redirect(url('operations'));
        }
        if (!$canPlan && ($payload['operation']['status'] ?? '') === 'draft') {
            Session::flash('error', 'Cette opération n’est pas encore visible sur la vue terrain.');

            return Response::redirect(url('operations'));
        }
        $tab = $this->normalizeTab((string) ($request->query('tab') ?? 'overview'), $canPlan);

        return Response::view('layout.main', [
            'title' => (string) $payload['operation']['code'],
            'content' => 'operations/workspace/show',
            'opsWorkspacePage' => true,
            'showPortalFooter' => false,
            'workspace' => $payload,
            'tab' => $tab,
            'canPlan' => $canPlan,
            'canIntel' => $this->workspace->canIntel(),
            'canOrders' => $this->workspace->canOrders(),
            'canPublish' => $this->workspace->canPublish(),
            'canChangePhase' => $this->workspace->canChangePhase(),
            'statusOptions' => OperationLabels::statusOptions(),
            'classificationOptions' => OperationLabels::classificationOptions(),
            'csrfToken' => Csrf::token(),
            'planningJson' => json_encode([
                'objects' => $payload['objects'],
                'overlays' => $payload['overlays'],
                'csrf' => Csrf::token(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'flash_success' => Session::getFlash('success'),
            'flash_error' => Session::getFlash('error'),
        ]);
    }

    public function setStatus(Request $request, array $params = []): Response
    {
        $denied = $this->guardPlan();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->back($params, 'Session expirée. Réessayez.');
        }
        $ok = $this->workspace->setStatus(
            (int) Session::get('tenant_id'),
            (string) ($params['uuid'] ?? ''),
            (string) $request->input('status'),
            (int) Session::get('user_id')
        );

        return $this->back($params, $ok ? 'Le statut de l’opération a été mis à jour.' : 'Mise à jour impossible.', $ok);
    }

    public function setPhase(Request $request, array $params = []): Response
    {
        if (!$this->workspace->canChangePhase()) {
            return $this->forbidden();
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->back($params, 'Session expirée. Réessayez.');
        }
        $ok = $this->workspace->setPhase(
            (int) Session::get('tenant_id'),
            (string) ($params['uuid'] ?? ''),
            (int) $request->input('phase_id'),
            (int) Session::get('user_id')
        );

        return $this->back($params, $ok ? 'La phase en cours a été changée.' : 'Phase introuvable.', $ok, 'overview');
    }

    public function storeObject(Request $request, array $params = []): Response
    {
        $denied = $this->guardPlan();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->jsonOrBack($request, $params, false, 'Session expirée.');
        }
        $result = $this->workspace->addObject(
            (int) Session::get('tenant_id'),
            (string) ($params['uuid'] ?? ''),
            (int) Session::get('user_id'),
            $request->all()
        );

        return $this->jsonOrBack($request, $params, !empty($result['ok']), (string) ($result['error'] ?? 'Objet ajouté.'), 'planning', $result);
    }

    public function updateObject(Request $request, array $params = []): Response
    {
        $denied = $this->guardPlan();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->jsonOrBack($request, $params, false, 'Session expirée.');
        }
        $uuid = (string) ($params['object'] ?? '');
        $geometry = $request->input('geometry');
        if (is_string($geometry) && $geometry !== '') {
            $decoded = json_decode($geometry, true);
            $geometry = is_array($decoded) ? $decoded : null;
        }
        if (is_array($geometry)) {
            $result = $this->workspace->moveObject(
                (int) Session::get('tenant_id'),
                (string) ($params['uuid'] ?? ''),
                $uuid,
                (int) Session::get('user_id'),
                $geometry
            );
        } else {
            $result = $this->workspace->updateObjectMeta(
                (int) Session::get('tenant_id'),
                (string) ($params['uuid'] ?? ''),
                $uuid,
                (int) Session::get('user_id'),
                $request->all()
            );
        }

        return $this->jsonOrBack($request, $params, !empty($result['ok']), (string) ($result['error'] ?? 'Objet mis à jour.'), 'planning', $result);
    }

    public function destroyObject(Request $request, array $params = []): Response
    {
        $denied = $this->guardPlan();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->jsonOrBack($request, $params, false, 'Session expirée.');
        }
        $result = $this->workspace->deleteObject(
            (int) Session::get('tenant_id'),
            (string) ($params['uuid'] ?? ''),
            (string) ($params['object'] ?? ''),
            (int) Session::get('user_id')
        );

        return $this->jsonOrBack($request, $params, !empty($result['ok']), (string) ($result['error'] ?? 'Objet retiré.'), 'planning', $result);
    }

    public function overlayWorkflow(Request $request, array $params = []): Response
    {
        if (!$this->workspace->canPublish() && (string) $request->input('workflow') === 'published') {
            return $this->forbidden();
        }
        $denied = $this->guardPlan();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->back($params, 'Session expirée.', false, 'planning');
        }
        $result = $this->workspace->advanceOverlayWorkflow(
            (int) Session::get('tenant_id'),
            (int) ($params['overlay'] ?? 0),
            (string) $request->input('workflow'),
            (int) Session::get('user_id'),
            trim((string) $request->input('note')) ?: null
        );

        return $this->back(
            $params,
            !empty($result['ok']) ? 'Le calque a été mis à jour.' : (string) ($result['error'] ?? 'Mise à jour impossible.'),
            !empty($result['ok']),
            'planning'
        );
    }

    public function restoreOverlay(Request $request, array $params = []): Response
    {
        $denied = $this->guardPlan();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->back($params, 'Session expirée.', false, 'planning');
        }
        $result = $this->workspace->restoreOverlayVersion(
            (int) Session::get('tenant_id'),
            (int) ($params['overlay'] ?? 0),
            (int) $request->input('version'),
            (int) Session::get('user_id')
        );

        return $this->back(
            $params,
            !empty($result['ok']) ? 'La version a été restaurée.' : (string) ($result['error'] ?? 'Restauration impossible.'),
            !empty($result['ok']),
            'planning'
        );
    }

    public function storeTask(Request $request, array $params = []): Response
    {
        $denied = $this->guardPlan();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->back($params, 'Session expirée.', false, 'tasks');
        }
        $result = $this->workspace->addTask(
            (int) Session::get('tenant_id'),
            (string) ($params['uuid'] ?? ''),
            (int) Session::get('user_id'),
            $request->all()
        );

        return $this->back($params, !empty($result['ok']) ? 'Tâche enregistrée.' : (string) ($result['error'] ?? ''), !empty($result['ok']), 'tasks');
    }

    public function storeTarget(Request $request, array $params = []): Response
    {
        if (!$this->workspace->canIntel()) {
            return $this->forbidden();
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->back($params, 'Session expirée.', false, 'targets');
        }
        $result = $this->workspace->addTarget(
            (int) Session::get('tenant_id'),
            (string) ($params['uuid'] ?? ''),
            (int) Session::get('user_id'),
            $request->all()
        );

        return $this->back($params, !empty($result['ok']) ? 'Objectif enregistré.' : (string) ($result['error'] ?? ''), !empty($result['ok']), 'targets');
    }

    public function storeOrder(Request $request, array $params = []): Response
    {
        if (!$this->workspace->canOrders()) {
            return $this->forbidden();
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->back($params, 'Session expirée.', false, 'orders');
        }
        $result = $this->workspace->saveOrder(
            (int) Session::get('tenant_id'),
            (string) ($params['uuid'] ?? ''),
            (int) Session::get('user_id'),
            $request->all()
        );

        return $this->back($params, !empty($result['ok']) ? 'Ordre enregistré.' : (string) ($result['error'] ?? ''), !empty($result['ok']), 'orders');
    }

    public function snapshot(Request $request, array $params = []): Response
    {
        $denied = $this->guardView();
        if ($denied !== null) {
            return $denied;
        }
        $canPlan = $this->workspace->canPlan();
        $payload = $this->workspace->workspacePayload(
            (int) Session::get('tenant_id'),
            (string) ($params['uuid'] ?? ''),
            $canPlan
        );
        if ($payload === null) {
            return Response::json(['ok' => false], 404);
        }

        return Response::json([
            'ok' => true,
            'objects' => $payload['objects'],
            'overlays' => $payload['overlays'],
            'locks' => $payload['locks'],
            'operation' => $payload['operation'],
        ]);
    }

    public function tactical(Request $request, array $params = []): Response
    {
        $denied = $this->guardView();
        if ($denied !== null) {
            return $denied;
        }
        $uuid = trim((string) ($params['uuid'] ?? ''));
        $payload = $this->workspace->tacticalPayload((int) Session::get('tenant_id'), $uuid);
        if ($payload === null) {
            Session::flash('error', 'Cette opération n’existe pas dans votre communauté.');

            return Response::redirect(url('operations'));
        }

        return Response::view('layout.main', [
            'title' => 'Vue terrain — ' . (string) $payload['operation']['code'],
            'content' => 'operations/workspace/tactical',
            'opsWorkspacePage' => true,
            'tacticalViewPage' => true,
            'showPortalFooter' => false,
            'tactical' => $payload,
            'tacticalJson' => json_encode(['objects' => $payload['objects'] ?? []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'csrfToken' => Csrf::token(),
        ]);
    }

    private function guardView(): ?Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->workspace->canView()) {
            return $this->forbidden();
        }

        return null;
    }

    private function guardPlan(): ?Response
    {
        $view = $this->guardView();
        if ($view !== null) {
            return $view;
        }
        if (!$this->workspace->canPlan()) {
            return $this->forbidden();
        }

        return null;
    }

    private function forbidden(): Response
    {
        Session::flash('error', 'Vous n’avez pas accès à cet espace.');

        return Response::redirect(url('hub'));
    }

    private function back(array $params, string $message, bool $ok = false, string $tab = 'overview'): Response
    {
        Session::flash($ok ? 'success' : 'error', $message);
        $uuid = (string) ($params['uuid'] ?? '');

        return Response::redirect(url('operations/' . $uuid . '?tab=' . rawurlencode($tab)));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function jsonOrBack(Request $request, array $params, bool $ok, string $message, string $tab = 'planning', array $extra = []): Response
    {
        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || $request->input('_json') === '1';
        if ($wantsJson) {
            return Response::json(array_merge(['ok' => $ok, 'message' => $message], $extra), $ok ? 200 : 422);
        }

        return $this->back($params, $message, $ok, $tab);
    }

    private function normalizeTab(string $tab, bool $canPlan): string
    {
        $allowed = ['overview', 'tactical', 'tasks'];
        if ($canPlan) {
            $allowed = array_merge($allowed, ['planning', 'intel', 'targets', 'orders', 'personnel', 'activity']);
        }
        if ($this->workspace->canIntel()) {
            $allowed[] = 'intel';
            $allowed[] = 'targets';
        }
        if ($this->workspace->canOrders()) {
            $allowed[] = 'orders';
        }

        return in_array($tab, array_unique($allowed), true) ? $tab : 'overview';
    }
}
