<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AarReportRepository;
use App\Services\Rbac\RolePermissionMatrixCatalog;
use App\Support\ModuleFeatureAccess;

final class AarReportsApiController
{
    public function __construct(
        private ?AarReportRepository $reports = null,
    ) {
        $this->reports ??= new AarReportRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        if (!ModuleFeatureAccess::allows(RolePermissionMatrixCatalog::MODULE_ATAK, 'view')) {
            return Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }

        return Response::json([
            'ok' => true,
            'reports' => $this->reports->listForTenant($tenantId, ['status' => $request->queryString('status', '')]),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        if (!$this->csrfOk($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée.'], 419);
        }
        // Même droit que le back-office : rédiger ou modifier un compte rendu demande la gestion ATAK.
        if (!ModuleFeatureAccess::allows(RolePermissionMatrixCatalog::MODULE_ATAK, 'manage')) {
            return Response::json(['ok' => false, 'error' => 'Droit insuffisant pour modifier les comptes rendus.'], 403);
        }

        $body = $this->body() + [
            'id' => $request->input('id'),
            'mission_cycle_id' => $request->input('mission_cycle_id'),
            'title' => $request->input('title'),
            'operation_label' => $request->input('operation_label'),
            'status' => $request->input('status'),
            'reported_at' => $request->input('reported_at'),
            'validated_at' => $request->input('validated_at'),
            'summary_text' => $request->input('summary_text'),
            'strengths' => $request->input('strengths'),
            'weaknesses' => $request->input('weaknesses'),
            'open_actions' => $request->input('open_actions'),
            'closed_actions' => $request->input('closed_actions'),
        ];
        $id = isset($params['id']) ? (int) $params['id'] : ((int) ($body['id'] ?? 0));
        try {
            $report = $this->reports->save($tenantId, $id > 0 ? $id : null, $userId, $body);
        } catch (\DomainException $e) {
            // Cycle de mission d'une autre communauté : refusé, rien n'est enregistré (SEC-032).
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return Response::json(['ok' => true, 'report' => $report], $id > 0 ? 200 : 201);
    }

    public function export(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $rows = $this->reports->listForTenant($tenantId, ['status' => $request->queryString('status', '')]);
        $json = json_encode(['generated_at' => gmdate('c'), 'reports' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $response = new Response();
        $response->setStatusCode(200)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="aar-reports-' . gmdate('Ymd-His') . '.json"')
            ->header('Cache-Control', 'no-store')
            ->setBody($json !== false ? $json : '{"reports":[]}');

        return $response;
    }

    private function csrfOk(Request $request): bool
    {
        $body = $this->body();
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf_token') ?? ($body['_csrf_token'] ?? ''));
        return Csrf::validate($token);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
