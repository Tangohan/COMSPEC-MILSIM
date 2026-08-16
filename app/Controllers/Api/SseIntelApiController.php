<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseEntityIndexRepository;
use App\Repositories\SseIntelEventRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;
use App\Services\Sse\SseIntelFoundationService;
use App\Services\Sse\SseIntelligenceWorkspaceService;
use App\Services\Sse\SseIntelCycleService;
use App\Services\Sse\SseAnalysisService;
use App\Services\Sse\SseSyncService;
use App\Services\Sse\SseTerrainService;
use App\Support\ComspecApiKeyAuth;

/**
 * API SSE v1 — fondations Intelligence Workspace.
 */
final class SseIntelApiController
{
    public function __construct(
        private ?SseIntelligenceWorkspaceService $workspace = null,
        private ?SseIntelFoundationService $foundation = null,
        private ?SseTerrainService $terrain = null,
        private ?SseIntelCycleService $cycle = null,
        private ?SseAnalysisService $analysis = null,
        private ?SseSyncService $sync = null,
        private ?SseEntityIndexRepository $entities = null,
        private ?SseIntelEventRepository $events = null,
        private ?TenantAtakConfigRepository $tenantAtakConfigRepository = null,
        private ?TenantRepository $tenantRepository = null,
    ) {
        $this->workspace ??= new SseIntelligenceWorkspaceService();
        $this->foundation ??= new SseIntelFoundationService();
        $this->terrain ??= new SseTerrainService();
        $this->cycle ??= new SseIntelCycleService();
        $this->analysis ??= new SseAnalysisService();
        $this->sync ??= new SseSyncService();
        $this->entities ??= new SseEntityIndexRepository();
        $this->events ??= new SseIntelEventRepository();
        $this->tenantAtakConfigRepository ??= new TenantAtakConfigRepository();
        $this->tenantRepository ??= new TenantRepository();
    }

    public function workspaceSummary(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }

        return Response::json($this->workspace->apiSummary($r));
    }

    public function entitiesIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }

        $list = $this->entities->search($r, [
            'q' => $request->query('q'),
            'entity_type' => $request->query('type'),
            'limit' => $request->query('limit') ? (int) $request->query('limit') : 50,
            'offset' => $request->query('offset') ? (int) $request->query('offset') : 0,
        ]);

        return Response::json(['entities' => $list, 'count' => count($list)]);
    }

    public function entitiesShow(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $uuid = trim((string) ($params['uuid'] ?? ''));
        $entity = $this->entities->findByUuid($r, $uuid);
        if ($entity === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Entité introuvable.'], 404);
        }

        return Response::json(['entity' => $entity]);
    }

    public function eventsIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }

        $list = $this->events->listForTenant($r, [
            'case_id' => $request->query('case_id'),
            'entity_uuid' => $request->query('entity_uuid'),
            'event_type' => $request->query('event_type'),
            'source_system' => $request->query('source_system'),
            'since' => $request->query('since'),
            'until' => $request->query('until'),
            'limit' => $request->query('limit') ? (int) $request->query('limit') : 40,
            'offset' => $request->query('offset') ? (int) $request->query('offset') : 0,
        ]);

        return Response::json(['events' => $list, 'count' => count($list)]);
    }

    public function relationsIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }

        $list = $this->foundation->listRelations($r, [
            'case_id' => $request->query('case_id'),
            'status' => $request->query('status'),
            'limit' => $request->query('limit') ? (int) $request->query('limit') : 50,
        ]);

        return Response::json(['relations' => $list, 'count' => count($list)]);
    }

    public function relationsStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants pour créer une relation.'], 403);
        }

        $body = $this->jsonBody($request);
        $result = $this->foundation->createRelation($r, array_merge($body, [
            'author_label' => $body['author_label'] ?? (Session::get('display_name') ?? 'Analyste'),
        ]));

        if (!($result['ok'] ?? false)) {
            return Response::json([
                'error' => 'relation_failed',
                'message' => (string) ($result['message'] ?? 'Échec'),
            ], 422);
        }

        return Response::json(['ok' => true, 'id' => $result['id'] ?? null], 201);
    }

    public function relationsDelete(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $id = (int) ($params['id'] ?? $body['id'] ?? 0);
        $reason = trim((string) ($body['justification'] ?? $body['reason'] ?? 'Suppression justifiée'));
        if ($id < 1 || $reason === '') {
            return Response::json(['error' => 'invalid', 'message' => 'Identifiant et justification requis.'], 422);
        }
        $ok = $this->workspace->softDeleteRelation(
            $r,
            $id,
            (string) ($body['author_label'] ?? Session::get('display_name') ?? 'Analyste'),
            $reason
        );

        return $ok
            ? Response::json(['ok' => true])
            : Response::json(['error' => 'failed', 'message' => 'Suppression impossible.'], 422);
    }

    public function inboxIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $uid = (int) (Session::get('user_id') ?? 0);
        $items = $this->workspace->inbox($r, $uid > 0 ? $uid : null);

        return Response::json(['inbox' => $items, 'count' => count($items)]);
    }

    public function inboxDecide(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $kind = (string) ($body['kind'] ?? '');
        $id = (int) ($body['id'] ?? 0);
        $decision = (string) ($body['decision'] ?? '');
        $result = $this->workspace->decideInboxItem(
            $r,
            $kind,
            $id,
            $decision,
            (string) ($body['author_label'] ?? Session::get('display_name') ?? 'Analyste'),
            (int) (Session::get('user_id') ?? 0) ?: null
        );

        return ($result['ok'] ?? false)
            ? Response::json(['ok' => true, 'message' => $result['message'] ?? ''])
            : Response::json(['error' => 'decide_failed', 'message' => $result['message'] ?? 'Échec'], 422);
    }

    public function graphIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $graph = $this->workspace->buildGraph(
            $r,
            $request->query('root') ? (string) $request->query('root') : null,
            $request->query('depth') ? (int) $request->query('depth') : 2,
            $request->query('case_id') ? (int) $request->query('case_id') : null
        );

        return Response::json($graph);
    }

    public function searchIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $q = trim((string) ($request->query('q') ?? ''));
        $result = $this->workspace->universalSearch($r, $q);

        return Response::json($result);
    }

    public function caseFolderShow(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $caseId = (int) ($params['id'] ?? 0);
        $folder = $this->workspace->caseFolder($r, $caseId);
        if ($folder === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Dossier introuvable.'], 404);
        }

        return Response::json(['folder' => $folder]);
    }

    public function caseFolderUpdate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $caseId = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $result = $this->workspace->updateCaseMeta(
            $r,
            $caseId,
            $body,
            (string) ($body['author_label'] ?? Session::get('display_name') ?? 'Analyste'),
            (int) (Session::get('user_id') ?? 0) ?: null
        );

        return ($result['ok'] ?? false)
            ? Response::json(['ok' => true])
            : Response::json(['error' => 'update_failed', 'message' => $result['message'] ?? 'Échec'], 422);
    }

    public function terrainPhotoStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        try {
            $id = $this->terrain->recordFieldPhoto($r, $body);
        } catch (\Throwable $e) {
            return Response::json([
                'error' => 'photo_failed',
                'message' => 'Enregistrement photo impossible. Vérifiez que la mise à jour terrain est appliquée.',
            ], 422);
        }

        return Response::json(['ok' => true, 'photo_id' => $id], 201);
    }

    public function terrainCustodyUpdate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $seizureId = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $row = $this->terrain->advanceSeizureCustody($r, $seizureId, $body);
        if ($row === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Saisie introuvable.'], 404);
        }

        return Response::json(['ok' => true, 'seizure' => $row]);
    }

    public function terrainSeekAdvance(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $personId = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $ok = $this->terrain->advanceSeekStage(
            $r,
            $personId,
            (string) ($body['seek_stage'] ?? 'capture'),
            (string) ($body['actor_callsign'] ?? Session::get('display_name') ?? '')
        );
        if (!$ok) {
            return Response::json(['error' => 'update_failed', 'message' => 'Étape non mise à jour.'], 422);
        }

        return Response::json(['ok' => true]);
    }

    public function cycleBoard(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $caseId = $request->query('case_id') ? (int) $request->query('case_id') : null;

        return Response::json($this->cycle->cycleBoard($r, $caseId));
    }

    public function cycleRequirementStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $result = $this->cycle->createRequirement(
            $r,
            $body,
            (string) ($body['author_label'] ?? Session::get('display_name') ?? 'Analyste'),
            (int) (Session::get('user_id') ?? 0) ?: null
        );

        return ($result['ok'] ?? false)
            ? Response::json($result, 201)
            : Response::json(['error' => 'create_failed', 'message' => $result['error'] ?? 'Échec'], 422);
    }

    public function cycleRequirementStatus(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $result = $this->cycle->updateRequirementStatus(
            $r,
            (int) ($params['id'] ?? 0),
            (string) ($body['status'] ?? 'ouvert'),
            isset($body['coverage_pct']) ? (int) $body['coverage_pct'] : null,
            (string) ($body['author_label'] ?? Session::get('display_name') ?? 'Analyste')
        );

        return ($result['ok'] ?? false)
            ? Response::json($result)
            : Response::json(['error' => 'update_failed', 'message' => $result['error'] ?? 'Échec'], 422);
    }

    public function cycleTaskingStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $result = $this->cycle->createTasking(
            $r,
            $body,
            (string) ($body['author_label'] ?? Session::get('display_name') ?? 'Analyste'),
            (int) (Session::get('user_id') ?? 0) ?: null
        );

        return ($result['ok'] ?? false)
            ? Response::json($result, 201)
            : Response::json(['error' => 'create_failed', 'message' => $result['error'] ?? 'Échec'], 422);
    }

    public function cycleTaskingUpdate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $result = $this->cycle->advanceTasking(
            $r,
            (int) ($params['id'] ?? 0),
            $body,
            (string) ($body['author_label'] ?? Session::get('display_name') ?? 'Analyste')
        );

        return ($result['ok'] ?? false)
            ? Response::json($result)
            : Response::json(['error' => 'update_failed', 'message' => $result['error'] ?? 'Échec'], 422);
    }

    public function cycleProductGenerate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $result = $this->cycle->generateProduct(
            $r,
            (int) ($body['case_id'] ?? 0),
            (string) ($body['product_type'] ?? 'INITIAL'),
            (string) ($body['release_level'] ?? 'interne'),
            (string) ($body['author_label'] ?? Session::get('display_name') ?? 'Analyste'),
            (int) (Session::get('user_id') ?? 0) ?: null,
            !empty($body['requirement_id']) ? (int) $body['requirement_id'] : null
        );

        return ($result['ok'] ?? false)
            ? Response::json($result, 201)
            : Response::json(['error' => 'create_failed', 'message' => $result['error'] ?? 'Échec'], 422);
    }

    public function cycleProductValidate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $result = $this->cycle->validateProduct(
            $r,
            (int) ($params['id'] ?? 0),
            (string) (Session::get('display_name') ?? 'Analyste')
        );

        return ($result['ok'] ?? false)
            ? Response::json($result)
            : Response::json(['error' => 'validate_failed', 'message' => $result['error'] ?? 'Échec'], 422);
    }

    public function cycleProductSanitise(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $result = $this->cycle->sanitiseProduct(
            $r,
            (int) ($params['id'] ?? 0),
            isset($body['release_level']) ? (string) $body['release_level'] : null,
            (string) (Session::get('display_name') ?? 'Analyste')
        );

        return ($result['ok'] ?? false)
            ? Response::json($result)
            : Response::json(['error' => 'sanitise_failed', 'message' => $result['error'] ?? 'Échec'], 422);
    }

    public function cycleProductDiffuse(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $recipients = $body['recipients'] ?? [];
        if (!is_array($recipients)) {
            $recipients = [];
        }
        // Accepte aussi une liste séparée par virgules côté UI simple.
        if ($recipients === [] && !empty($body['recipients_text'])) {
            foreach (preg_split('/[,;\n]+/', (string) $body['recipients_text']) ?: [] as $chunk) {
                $label = trim($chunk);
                if ($label !== '') {
                    $recipients[] = ['label' => $label];
                }
            }
        }
        $result = $this->cycle->diffuseProduct(
            $r,
            (int) ($params['id'] ?? 0),
            $recipients,
            (string) (Session::get('display_name') ?? 'Analyste')
        );

        return ($result['ok'] ?? false)
            ? Response::json($result)
            : Response::json(['error' => 'diffuse_failed', 'message' => $result['error'] ?? 'Échec'], 422);
    }

    public function analysisBoard(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $caseId = $request->query('case_id') ? (int) $request->query('case_id') : null;
        $entity = trim((string) ($request->query('entity_uuid') ?? ''));
        $days = $request->query('days') ? (int) $request->query('days') : 14;

        return Response::json($this->analysis->analysisBoard(
            $r,
            $caseId,
            $entity !== '' ? $entity : null,
            $days
        ));
    }

    public function analysisFindingDecide(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $result = $this->analysis->decideFinding(
            $r,
            (int) ($params['id'] ?? 0),
            (string) ($body['status'] ?? $body['decision'] ?? 'ecarte'),
            (string) ($body['author_label'] ?? Session::get('display_name') ?? 'Analyste')
        );

        return ($result['ok'] ?? false)
            ? Response::json($result)
            : Response::json(['error' => 'decide_failed', 'message' => $result['error'] ?? 'Échec'], 422);
    }

    public function syncHealth(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }

        return Response::json($this->sync->health($r));
    }

    public function syncMonitor(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }

        return Response::json($this->sync->monitorSnapshot($r));
    }

    public function syncOptimize(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $days = isset($body['retention_days']) ? (int) $body['retention_days'] : 7;

        return Response::json($this->sync->optimize($r, $days));
    }

    public function syncPending(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }

        return Response::json([
            'items' => $this->sync->pending($r, $request->query('limit') ? (int) $request->query('limit') : 40),
        ]);
    }

    public function syncAck(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $result = $this->sync->ack($r, (int) ($body['id'] ?? $params['id'] ?? 0));

        return ($result['ok'] ?? false)
            ? Response::json($result)
            : Response::json(['error' => 'ack_failed', 'message' => $result['error'] ?? 'Échec'], 422);
    }

    public function syncEnqueue(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $payload = is_array($body['payload'] ?? null) ? $body['payload'] : $body;
        $result = $this->sync->enqueue(
            $r,
            (string) ($body['idempotency_key'] ?? ''),
            $payload,
            (string) ($body['channel'] ?? 'arma')
        );

        return ($result['ok'] ?? false)
            ? Response::json($result, !empty($result['created']) ? 201 : 200)
            : Response::json(['error' => 'enqueue_failed', 'message' => $result['error'] ?? 'Échec'], 422);
    }

    public function syncConflicts(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }

        return Response::json(['conflicts' => $this->sync->openConflicts($r)]);
    }

    public function syncConflictResolve(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canWrite()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Droits insuffisants.'], 403);
        }
        $body = $this->jsonBody($request);
        $result = $this->sync->resolveConflict(
            $r,
            (int) ($params['id'] ?? 0),
            (string) ($body['note'] ?? 'Arbitrage analyste'),
            (string) ($body['author_label'] ?? Session::get('display_name') ?? 'Analyste')
        );

        return ($result['ok'] ?? false)
            ? Response::json($result)
            : Response::json(['error' => 'resolve_failed', 'message' => $result['error'] ?? 'Échec'], 422);
    }

    private function canWrite(): bool
    {
        if (ComspecApiKeyAuth::armaInlineAuthOk()) {
            return true;
        }
        $uid = (int) (Session::get('user_id') ?? 0);
        if ($uid < 1) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.case.manage') || can('atak.sse.grant') || can('admin.access') || can('atak.access'));
    }

    private function requireTenant(Request $request): int|Response
    {
        $id = $this->resolveTenantId($request);
        if ($id === null) {
            return Response::json([
                'error' => 'tenant_context_required',
                'message' => 'Communauté non identifiée.',
            ], 403);
        }

        if ($this->tenantAtakConfigRepository->isMaintenanceEnabled($id)) {
            $userId = (int) (Session::get('user_id') ?? 0);
            $bypass = $userId > 0 && function_exists('can') && can('admin.access');
            if (!$bypass) {
                return Response::json(['error' => 'maintenance', 'message' => 'Service temporairement indisponible.'], 503);
            }
        }

        return $id;
    }

    private function resolveTenantId(Request $request): ?int
    {
        $matched = ComspecApiKeyAuth::matchedTenantId();
        if ($matched !== null && $matched > 0) {
            return $matched;
        }
        $sid = Session::get('tenant_id');
        if ($sid !== null && $sid !== '') {
            $n = (int) $sid;

            return $n > 0 ? $n : null;
        }
        $q = $request->query('tenant_id');
        if ($q !== null && $q !== '') {
            $n = (int) $q;

            return $n > 0 ? $n : null;
        }
        $body = $this->jsonBody($request);
        if (!empty($body['tenant_id'])) {
            $n = (int) $body['tenant_id'];

            return $n > 0 ? $n : null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(Request $request): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
