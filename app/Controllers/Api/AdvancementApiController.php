<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AdvancementRepository;
use App\Services\Advancement\AdvancementWorkflowService;
use App\Services\Advancement\GradeScaleTemplateService;
use Throwable;

/**
 * API REST de l'avancement : mêmes règles que les écrans, réponses JSON.
 */
final class AdvancementApiController
{
    private ?string $rawBody = null;

    public function __construct(
        private AdvancementRepository $repository,
        private AdvancementWorkflowService $workflow,
        private GradeScaleTemplateService $templates,
    ) {
    }

    public function grades(Request $request, array $params = []): Response
    {
        $ctx = $this->admin();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return Response::json(['ok' => true, 'grades' => $this->repository->listGrades($ctx[0], true)]);
    }

    public function storeGrade(Request $request, array $params = []): Response
    {
        $ctx = $this->admin(true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        try {
            $data = $this->payload($request);
            if (trim((string) ($data['code'] ?? '')) === '' || trim((string) ($data['label'] ?? '')) === '') {
                return Response::json(['ok' => false, 'error' => 'Code et libellé sont requis.'], 422);
            }
            $id = $this->repository->saveGrade($ctx[0], $data);

            return Response::json(['ok' => true, 'id' => $id], 201);
        } catch (Throwable $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function updateGrade(Request $request, array $params = []): Response
    {
        $ctx = $this->admin(true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->repository->findGrade($id, $ctx[0]);
        if ($existing === null) {
            return Response::json(['ok' => false, 'error' => 'Grade introuvable.'], 404);
        }
        try {
            $this->repository->saveGrade($ctx[0], array_merge($existing, $this->payload($request)), $id);

            return Response::json(['ok' => true, 'id' => $id]);
        } catch (Throwable $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function archiveGrade(Request $request, array $params = []): Response
    {
        $ctx = $this->admin(true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $this->repository->archiveGrade((int) ($params['id'] ?? 0), $ctx[0]);

        return Response::json(['ok' => true]);
    }

    public function filieres(Request $request, array $params = []): Response
    {
        $ctx = $this->admin();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return Response::json(['ok' => true, 'filieres' => $this->repository->listFilieres($ctx[0])]);
    }

    public function storeFiliere(Request $request, array $params = []): Response
    {
        $ctx = $this->admin(true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $data = $this->payload($request);
        try {
            $id = $this->repository->saveFiliere($ctx[0], [
                'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
                'label' => trim((string) ($data['label'] ?? '')),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            return Response::json(['ok' => true, 'id' => $id], 201);
        } catch (Throwable $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function importScale(Request $request, array $params = []): Response
    {
        $ctx = $this->admin(true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $added = $this->templates->completeForTenant($ctx[0]);

        return Response::json(['ok' => true, 'added' => $added]);
    }

    public function campaigns(Request $request, array $params = []): Response
    {
        $ctx = $this->admin();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return Response::json(['ok' => true, 'campaigns' => $this->repository->listCampaigns($ctx[0])]);
    }

    public function storeCampaign(Request $request, array $params = []): Response
    {
        $ctx = $this->admin(true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $data = $this->payload($request);
        try {
            $id = $this->repository->insertCampaign($ctx[0], [
                'grade_id' => (int) ($data['grade_id'] ?? 0),
                'filiere_id' => $data['filiere_id'] ?? null,
                'year' => (int) ($data['year'] ?? date('Y')),
                'opens_at' => (string) ($data['opens_at'] ?? ''),
                'closes_at' => (string) ($data['closes_at'] ?? ''),
                'quota_slots' => $data['quota_slots'] ?? '',
                'created_by' => $ctx[1],
            ]);

            return Response::json(['ok' => true, 'id' => $id], 201);
        } catch (Throwable $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function storeCandidacy(Request $request, array $params = []): Response
    {
        $ctx = $this->admin(true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $data = $this->payload($request);
        try {
            $id = $this->workflow->createCandidacy(
                $ctx[0],
                (int) ($params['id'] ?? 0),
                (int) ($data['personnel_id'] ?? 0),
                $ctx[1],
                $data
            );

            return Response::json(['ok' => true, 'id' => $id], 201);
        } catch (Throwable $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function openCommission(Request $request, array $params = []): Response
    {
        $ctx = $this->admin(true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $data = $this->payload($request);
        try {
            $this->workflow->openCommission(
                $ctx[0],
                (int) ($params['id'] ?? 0),
                isset($data['meeting_date']) ? (string) $data['meeting_date'] : null,
                isset($data['minutes_document_id']) ? (int) $data['minutes_document_id'] : null,
                is_array($data['members'] ?? null) ? $data['members'] : []
            );

            return Response::json(['ok' => true]);
        } catch (Throwable $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function publish(Request $request, array $params = []): Response
    {
        $ctx = $this->admin(true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        try {
            $out = $this->workflow->publish($ctx[0], (int) ($params['id'] ?? 0), $ctx[1]);

            return Response::json(['ok' => true] + $out);
        } catch (Throwable $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function recheck(Request $request, array $params = []): Response
    {
        $ctx = $this->admin(true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        try {
            $n = $this->workflow->recheckCampaign($ctx[0], (int) ($params['id'] ?? 0));

            return Response::json(['ok' => true, 'updated' => $n]);
        } catch (Throwable $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @return array{0:int,1:int}|Response
     */
    private function admin(bool $mutating = false): array|Response
    {
        $userId = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        if ($userId < 1 || $tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Authentification requise.'], 401);
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.access') && !$gate->allows('site.support')) {
            return Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }
        if ($mutating && !$this->csrfOk()) {
            return Response::json(['ok' => false, 'error' => 'Jeton de session invalide.'], 419);
        }

        return [$tenantId, $userId];
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $json = json_decode($this->rawBody(), true);
        if (is_array($json)) {
            return $json;
        }

        return $request->all();
    }

    private function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input') ?: '';
        }

        return $this->rawBody;
    }

    private function csrfOk(): bool
    {
        $header = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $json = json_decode($this->rawBody(), true);
        $body = is_array($json) ? (string) ($json['_csrf_token'] ?? '') : '';
        if ($body === '') {
            $body = (string) ($_POST['_csrf_token'] ?? '');
        }

        return Csrf::validate($header !== '' ? $header : $body);
    }
}
