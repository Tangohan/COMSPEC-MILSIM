<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseFieldNoteRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Services\Sse\SseFieldNoteService;
use App\Support\AtakArmaWriteGuard;
use App\Support\ComspecApiKeyAuth;
use App\Support\SseFieldNoteCatalog;
use App\Support\SteamId;

/**
 * API des fiches de renseignement simplifiées.
 *
 * C'est le canal du rédacteur plein écran de l'ATAK : il envoie une fiche,
 * puis ses pièces jointes une par une, et relit ses propres fiches.
 */
final class SseFieldNoteApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** @var array<string, mixed>|null */
    private ?array $jsonBodyCache = null;

    public function __construct(
        private ?SseFieldNoteService $noteService = null,
        private ?SseFieldNoteRepository $notes = null,
        private ?AtakArmaWriteGuard $armaGuard = null,
        private ?TenantAtakConfigRepository $tenantAtakConfig = null,
    ) {
        $this->noteService ??= new SseFieldNoteService();
        $this->notes ??= $this->noteService->repository();
        $this->armaGuard ??= new AtakArmaWriteGuard();
        $this->tenantAtakConfig ??= new TenantAtakConfigRepository();
    }

    /** Référentiel affiché par le rédacteur ATAK (mêmes libellés que le portail). */
    public function catalog(Request $request, array $params = []): Response
    {
        return Response::json(SseFieldNoteCatalog::clientCatalog());
    }

    /** Fiches récentes — filtrables sur l'auteur pour « mes fiches ». */
    public function index(Request $request, array $params = []): Response
    {
        $tenant = $this->requireTenant($request);
        if ($tenant instanceof Response) {
            return $tenant;
        }

        $steam = SteamId::normalize((string) ($request->query('steam_uid') ?? $request->query('steam_id') ?? ''));
        $notes = $this->notes->listForTenant($tenant, [
            'limit' => $request->query('limit') ? (int) $request->query('limit') : 25,
            'context_id' => $this->mapId($request),
            'author_steam_id' => $steam !== null ? $steam : '',
            'status' => (string) ($request->query('status') ?? ''),
        ]);

        return Response::json(['notes' => $notes, 'count' => count($notes)]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenant = $this->requireTenant($request);
        if ($tenant instanceof Response) {
            return $tenant;
        }

        $note = $this->noteService->find($tenant, (int) ($params['id'] ?? 0));
        if ($note === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Fiche introuvable.'], 404);
        }

        return Response::json($note);
    }

    /** Réception d'une fiche rédigée sur le terrain. */
    public function store(Request $request, array $params = []): Response
    {
        if (!ComspecApiKeyAuth::armaInlineAuthOk()) {
            return Response::json([
                'error' => 'Unauthorized',
                'message' => 'Authentification terrain requise.',
            ], 401);
        }
        $tenant = $this->requireTenant($request);
        if ($tenant instanceof Response) {
            return $tenant;
        }

        $body = $this->jsonBody($request);
        $actor = $this->armaGuard->assertActor($request, $tenant, $body, false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $steam = null;
        if (is_array($actor) && !empty($actor['steam_uid'])) {
            $steam = SteamId::normalize((string) $actor['steam_uid']);
        }
        if ($steam === null || $steam === '') {
            $steam = SteamId::normalize((string) ($body['author_steam_id'] ?? $body['steam_uid'] ?? ''));
        }

        $input = [
            'context_id' => $this->mapId($request, true),
            'body' => (string) ($body['body'] ?? $body['text'] ?? ''),
            'note_kind' => $body['note_kind'] ?? $body['kind'] ?? SseFieldNoteCatalog::DEFAULT_KIND,
            'themes' => $body['themes'] ?? [],
            'title' => $body['title'] ?? $body['objet'] ?? '',
            'observed_at' => $body['observed_at'] ?? null,
            'place_label' => $body['place_label'] ?? $body['place'] ?? null,
            'grid_reference' => $body['grid_reference'] ?? $body['grid_ref'] ?? null,
            'pos_x' => $body['pos_x'] ?? null,
            'pos_y' => $body['pos_y'] ?? null,
            'pos_z' => $body['pos_z'] ?? null,
            'lat' => $body['lat'] ?? null,
            'lng' => $body['lng'] ?? null,
            'urgency' => $body['urgency'] ?? SseFieldNoteCatalog::DEFAULT_URGENCY,
            'intel_source' => $body['intel_source'] ?? $body['source'] ?? '',
            'classification' => $body['classification'] ?? 'interne',
            'source_reliability' => $body['source_reliability'] ?? 'C',
            'info_credibility' => $body['info_credibility'] ?? 3,
            'origin' => in_array((string) ($body['origin'] ?? ''), ['atak', 'arma'], true)
                ? (string) $body['origin']
                : 'atak',
            'author_label' => $body['author_label'] ?? $body['submitter_callsign'] ?? ($actor['callsign'] ?? null),
            'author_user_id' => $actor['user_id'] ?? null,
            'author_steam_id' => $steam,
            'author_unit' => $body['author_unit'] ?? $body['unit_label'] ?? null,
            'case_code' => $body['case_code'] ?? null,
            'idempotency_key' => $body['idempotency_key'] ?? $body['event_uuid'] ?? null,
            'status' => SseFieldNoteCatalog::DEFAULT_STATUS,
        ];

        $normalizedBody = SseFieldNoteCatalog::normalizeBody($input['body']);
        if ($normalizedBody === '') {
            return Response::json([
                'error' => 'body_required',
                'message' => 'La fiche est vide. Écrivez le renseignement avant de valider.',
            ], 422);
        }
        if (SseFieldNoteCatalog::normalizeThemes($input['themes']) === []) {
            return Response::json([
                'error' => 'theme_required',
                'message' => 'Choisissez au moins un thème pour orienter la fiche.',
            ], 422);
        }

        $result = $this->noteService->create($tenant, $input);
        $note = $result['note'];

        return Response::json([
            'ok' => true,
            'created' => $result['created'],
            'id' => (int) ($note['id'] ?? 0),
            'reference_code' => (string) ($note['reference_code'] ?? ''),
            'note' => $note,
            'message' => $result['created']
                ? 'Fiche transmise au bureau SSE.'
                : 'Fiche déjà transmise — aucun doublon créé.',
        ], $result['created'] ? 201 : 200);
    }

    /**
     * Soumission d'une fiche depuis la vue ATAK web (session navigateur + CSRF).
     *
     * Chemin distinct de store() qui exige une clé API terrain. Ici, l'utilisateur
     * est connecté via son compte Athena : la session suffit à identifier le tenant
     * et l'auteur.
     */
    public function storeWeb(Request $request, array $params = []): Response
    {
        $raw = file_get_contents('php://input');
        $body = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?: []) : [];

        $csrfToken = (string) ($body['_csrf_token'] ?? $request->input('_csrf_token', '') ?? '');
        if (!Csrf::validate($csrfToken)) {
            return Response::json([
                'error' => 'csrf_invalid',
                'message' => 'Session expirée ou requête invalide. Rechargez la page.',
            ], 403);
        }

        $tenant = $this->requireTenant($request);
        if ($tenant instanceof Response) {
            return $tenant;
        }

        $userId = (int) (Session::get('user_id') ?? 0);
        $callsign = trim((string) (Session::get('callsign') ?? Session::get('username') ?? ''));
        if ($callsign === '') {
            $callsign = 'Opérateur ATAK';
        }

        $input = [
            'context_id' => $this->mapId($request, false),
            'body' => (string) ($body['body'] ?? ''),
            'note_kind' => $body['note_kind'] ?? SseFieldNoteCatalog::DEFAULT_KIND,
            'themes' => $body['themes'] ?? [],
            'title' => $body['title'] ?? $body['objet'] ?? '',
            'observed_at' => $body['observed_at'] ?? null,
            'place_label' => $body['place_label'] ?? null,
            'grid_reference' => $body['grid_reference'] ?? null,
            'lat' => $body['lat'] ?? null,
            'lng' => $body['lng'] ?? null,
            'urgency' => $body['urgency'] ?? SseFieldNoteCatalog::DEFAULT_URGENCY,
            'intel_source' => $body['intel_source'] ?? $body['source'] ?? '',
            'classification' => 'interne',
            'source_reliability' => 'C',
            'info_credibility' => 3,
            'origin' => 'atak',
            'author_label' => $body['author_label'] ?? $callsign,
            'author_user_id' => $userId > 0 ? $userId : null,
            'author_steam_id' => null,
            'author_unit' => $body['author_unit'] ?? null,
            'case_code' => $body['case_code'] ?? null,
            'idempotency_key' => $body['idempotency_key'] ?? null,
            'status' => SseFieldNoteCatalog::DEFAULT_STATUS,
        ];

        if (SseFieldNoteCatalog::normalizeBody($input['body']) === '') {
            return Response::json([
                'error' => 'body_required',
                'message' => 'La fiche est vide. Rédigez le renseignement avant de valider.',
            ], 422);
        }
        if (SseFieldNoteCatalog::normalizeThemes($input['themes']) === []) {
            return Response::json([
                'error' => 'theme_required',
                'message' => 'Choisissez au moins un thème pour orienter la fiche.',
            ], 422);
        }

        $result = $this->noteService->create($tenant, $input);
        $note = $result['note'];

        return Response::json([
            'ok' => true,
            'created' => $result['created'],
            'id' => (int) ($note['id'] ?? 0),
            'reference_code' => (string) ($note['reference_code'] ?? ''),
            'note' => $note,
            'message' => $result['created']
                ? 'Fiche transmise au bureau SSE.'
                : 'Fiche déjà transmise — aucun doublon créé.',
        ], $result['created'] ? 201 : 200);
    }

    /** Pièce jointe envoyée après la fiche (photo, capture, document). */
    public function attachmentStore(Request $request, array $params = []): Response
    {
        if (!ComspecApiKeyAuth::armaInlineAuthOk()) {
            return Response::json([
                'error' => 'Unauthorized',
                'message' => 'Authentification terrain requise.',
            ], 401);
        }
        $tenant = $this->requireTenant($request);
        if ($tenant instanceof Response) {
            return $tenant;
        }

        $actor = $this->armaGuard->assertActor($request, $tenant, [], false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $noteId = (int) ($params['id'] ?? 0);
        if ($this->notes->findForTenant($tenant, $noteId) === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Fiche introuvable.'], 404);
        }

        $entry = $_FILES['piece'] ?? $_FILES['image'] ?? $_FILES['photo'] ?? $_FILES['file'] ?? null;
        if (!is_array($entry)) {
            return Response::json([
                'error' => 'missing_file',
                'message' => 'Aucune pièce jointe reçue.',
            ], 400);
        }

        $result = $this->noteService->attachUploadedFile($tenant, $noteId, $entry, [
            'kind' => (string) ($_POST['kind'] ?? ''),
            'caption' => (string) ($_POST['caption'] ?? ''),
            'grid_reference' => (string) ($_POST['grid_reference'] ?? $_POST['grid_ref'] ?? ''),
            'pos_x' => $_POST['pos_x'] ?? null,
            'pos_y' => $_POST['pos_y'] ?? null,
            'pos_z' => $_POST['pos_z'] ?? null,
            'author_label' => (string) ($_POST['author'] ?? $_POST['author_callsign'] ?? $actor['callsign'] ?? 'Terrain'),
        ]);

        if (!$result['ok']) {
            return Response::json([
                'error' => 'attachment_rejected',
                'message' => (string) ($result['error'] ?? 'Pièce jointe refusée.'),
            ], 422);
        }

        return Response::json([
            'ok' => true,
            'attachment' => $result['attachment'],
            'attachment_count' => $this->notes->countAttachments($tenant, $noteId),
        ], 201);
    }

    private function requireTenant(Request $request): int|Response
    {
        $id = $this->resolveTenantId($request);
        if ($id === null) {
            return Response::json([
                'error' => 'tenant_context_required',
                'message' => 'Communauté non identifiée. Reliez le compte Athena en jeu, ou utilisez la clé d’accès fournie par votre administrateur.',
            ], 403);
        }

        if ($this->tenantAtakConfig->isMaintenanceEnabled($id)) {
            $userId = (int) (Session::get('user_id') ?? 0);
            $bypass = $userId > 0 && function_exists('can') && can('admin.access');
            if (!$bypass) {
                $message = $this->tenantAtakConfig->getMaintenanceMessage($id);

                return Response::json([
                    'error' => 'maintenance',
                    'message' => $message !== ''
                        ? $message
                        : 'Le renseignement est suspendu par le commandement. Réessayez plus tard.',
                ], 503);
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
        if ($sid !== null && $sid !== '' && (int) $sid > 0) {
            return (int) $sid;
        }
        $q = $request->query('tenant_id');
        if ($q !== null && $q !== '' && (int) $q > 0) {
            return (int) $q;
        }
        $body = $this->jsonBody($request);
        if (!empty($body['tenant_id']) && (int) $body['tenant_id'] > 0) {
            return (int) $body['tenant_id'];
        }

        return null;
    }

    private function mapId(Request $request, bool $fromBody = false): int
    {
        $map = $fromBody
            ? ($this->jsonBody($request)['mapId'] ?? $this->jsonBody($request)['map_id'] ?? $request->query('mapId'))
            : $request->query('mapId');
        $mapId = ($map !== null && $map !== '') ? (int) $map : self::DEFAULT_MAP_ID;

        return $mapId < 1 ? self::DEFAULT_MAP_ID : $mapId;
    }

    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }
        $raw = file_get_contents('php://input');
        $decoded = ($raw === false || $raw === '') ? null : json_decode($raw, true);
        $this->jsonBodyCache = is_array($decoded) ? $decoded : [];

        return $this->jsonBodyCache;
    }
}
