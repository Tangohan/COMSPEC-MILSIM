<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;
use App\Services\Sse\SseAutomationService;
use App\Services\Sse\SseCrossMatchService;
use App\Services\Sse\SseIntelFoundationService;
use App\Services\Sse\SseTerrainService;
use App\Services\Tactical\AtakActivityLogService;
use App\Support\AtakArmaWriteGuard;
use App\Support\ComspecApiKeyAuth;
use App\Support\SteamId;

/**
 * API SSE — Sensitive Site Exploitation (fiches personnes / photos visage).
 */
final class SseApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** @var array<string, mixed>|null */
    private ?array $jsonBodyCache = null;

    public function __construct(
        private ?SsePersonRepository $persons = null,
        private ?SseCaseRepository $cases = null,
        private ?SseCrossMatchService $cross = null,
        private ?SseAutomationService $automation = null,
        private ?SseSiteRepository $sites = null,
        private ?SseIntelFoundationService $intelFoundation = null,
        private ?SseTerrainService $terrain = null,
        private ?AtakArmaWriteGuard $armaGuard = null,
        private ?AtakActivityLogService $activityLog = null,
        private ?TenantAtakConfigRepository $tenantAtakConfigRepository = null,
        private ?TenantRepository $tenantRepository = null,
    ) {
        $this->persons ??= new SsePersonRepository();
        $this->cases ??= new SseCaseRepository();
        $this->cross ??= new SseCrossMatchService();
        $this->automation ??= new SseAutomationService();
        $this->sites ??= new SseSiteRepository();
        $this->intelFoundation ??= new SseIntelFoundationService();
        $this->terrain ??= new SseTerrainService();
        $this->armaGuard ??= new AtakArmaWriteGuard();
        $this->activityLog ??= new AtakActivityLogService();
        $this->tenantAtakConfigRepository ??= new TenantAtakConfigRepository();
        $this->tenantRepository ??= new TenantRepository();
    }

    public function personsIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);

        $filters = [
            'status' => $request->query('status'),
            'since_id' => $request->query('since_id'),
            'limit' => $request->query('limit') ? (int) $request->query('limit') : 100,
            'offset' => $request->query('offset') ? (int) $request->query('offset') : 0,
        ];

        $persons = $this->persons->listForContext($tenantId, $mapId, array_filter(
            $filters,
            static fn ($v) => $v !== null && $v !== ''
        ));

        return Response::json([
            'persons' => $persons,
            'count' => count($persons),
        ]);
    }

    public function personsStore(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized', 'message' => 'Authentification terrain requise.'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;

        $actor = $this->armaGuard->assertActor($request, $tenantId, $this->jsonBody($request), false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $body = $this->jsonBody($request);
        $body = $this->enrichSeekCaptureLocation($body);
        $mapId = $this->mapId($request, true);

        $last = trim((string) ($body['last_name'] ?? ''));
        $first = trim((string) ($body['first_name'] ?? ''));
        $alias = trim((string) ($body['alias'] ?? ''));
        if ($last === '' && $first === '' && $alias === '') {
            return Response::json([
                'error' => 'identity_required',
                'message' => 'Indiquez au moins un nom, un prénom ou un alias.',
            ], 422);
        }

        $steam = null;
        if (is_array($actor) && !empty($actor['steam_uid'])) {
            $steam = SteamId::normalize((string) $actor['steam_uid']);
        }
        if ($steam === null || $steam === '') {
            $steam = SteamId::normalize((string) ($body['submitter_steam_id'] ?? $body['steam_uid'] ?? ''));
        }

        $data = array_merge($body, [
            'tenant_id' => $tenantId,
            'context_id' => $mapId,
            'submitter_user_id' => $actor['user_id'] ?? null,
            'submitter_callsign' => $body['submitter_callsign'] ?? $actor['callsign'] ?? null,
            'submitter_steam_id' => $steam,
        ]);

        $id = $this->persons->create($data);

        // Échantillons biométriques simulés transmis par le terminal SEEK.
        $samples = $body['biometric_samples'] ?? [];
        if (is_array($samples)) {
            foreach ($samples as $sample) {
                if (!is_array($sample)) {
                    continue;
                }
                $sample['operator_callsign'] = $sample['operator_callsign']
                    ?? ($data['submitter_callsign'] ?? null);
                if (empty($sample['conditions']) && empty($sample['conditions_json'])) {
                    $sample['conditions'] = array_filter([
                        'grid_reference' => $sample['grid_reference'] ?? $body['grid_reference'] ?? null,
                        'pos_x' => $sample['pos_x'] ?? $body['pos_x'] ?? null,
                        'pos_y' => $sample['pos_y'] ?? $body['pos_y'] ?? null,
                        'pos_z' => $sample['pos_z'] ?? $body['pos_z'] ?? null,
                    ], static fn ($v) => $v !== null && $v !== '');
                }
                $this->persons->addBiometricSample($id, $tenantId, $sample);
            }
        }

        // Classement : code dossier saisi sur le terrain.
        $caseCode = strtoupper(trim((string) ($body['case_code'] ?? '')));
        $filing = ['code' => $caseCode, 'linked' => false, 'case' => null];
        $filedCaseId = null;
        if ($caseCode !== '') {
            $case = $this->cases->findByReferenceCode($tenantId, $caseCode);
            if ($case !== null) {
                $this->cases->linkPerson(
                    (int) $case['id'],
                    $id,
                    $tenantId,
                    isset($actor['user_id']) ? (int) $actor['user_id'] : null,
                    'Classement depuis le terminal SEEK'
                );
                $filing['linked'] = true;
                $filedCaseId = (int) $case['id'];
                $filing['case'] = [
                    'id' => (int) $case['id'],
                    'reference_code' => (string) ($case['reference_code'] ?? ''),
                    'title' => (string) ($case['title'] ?? ''),
                ];
            }
        }

        $person = $this->persons->findById($id, $tenantId);
        if (is_array($person)) {
            $person['filing'] = $filing;
            $person['biometric_samples'] = $this->persons->listBiometricSamples($id, $tenantId);

            // Croisement listes de surveillance. Le résultat part vers le journal
            // d'activité (poste de commandement) ; le terrain n'obtient pas de verdict
            // dans la réponse de transmission.
            $hits = $this->cross->matchOne($person, $tenantId);
            $person['watchlist'] = $hits;
            if ($hits !== []) {
                $top = $hits[0];
                $entry = is_array($top['entry'] ?? null) ? $top['entry'] : [];
                $watched = trim(sprintf(
                    '%s %s',
                    (string) ($entry['first_name'] ?? ''),
                    (string) ($entry['last_name'] ?? '')
                ));
                if ($watched === '') {
                    $watched = (string) ($entry['alias'] ?? 'entrée surveillée');
                }
                $this->activityLog->record(
                    $tenantId,
                    $mapId,
                    'SSE_WATCHLIST',
                    sprintf(
                        'Correspondance liste de surveillance : %s ↔ %s (%d%%, %s)',
                        $person['display_name'] ?? 'fiche sans nom',
                        $watched,
                        (int) ($top['score'] ?? 0),
                        (string) ($top['reason'] ?? '')
                    ),
                    (string) ($data['submitter_callsign'] ?? 'Terrain')
                );
            }
        }

        $this->activityLog->record(
            $tenantId,
            $mapId,
            'SSE_PERSON',
            sprintf(
                'Personne enregistrée : %s (%s)',
                $person['display_name'] ?? 'sans nom',
                $person['status_label'] ?? 'Civil'
            ),
            (string) ($data['submitter_callsign'] ?? 'Terrain')
        );

        // Automatismes : classement de secours, doublons, escalade, co-présence.
        // Ils s'exécutent après l'enregistrement — une règle qui échoue ne doit
        // jamais faire perdre la fiche que le terrain vient de transmettre.
        if (is_array($person)) {
            try {
                $applied = $this->automation->onPersonRecorded(
                    $tenantId,
                    $mapId,
                    $person,
                    $person['watchlist'] ?? [],
                    $filedCaseId,
                    (string) ($data['submitter_callsign'] ?? 'Terrain')
                );
                if ($applied !== []) {
                    $person['automation'] = $applied;
                    // Le classement de secours doit se voir sur le terminal : c'est
                    // la seule action automatique dont l'opérateur a besoin sur place.
                    foreach ($applied as $a) {
                        if (($a['rule'] ?? '') === 'A1') {
                            $filing['linked'] = true;
                            $filing['auto'] = true;
                            $filing['message'] = $a['detail'];
                        }
                    }
                    $person['filing'] = $filing;
                }
            } catch (\Throwable) {
                // Silencieux côté terrain : la fiche est enregistrée, c'est l'essentiel.
            }
        }

        if (is_array($person)) {
            try {
                $this->terrain->applyPersonIngest($tenantId, $id, $body);
                $person = $this->persons->findById($id, $tenantId) ?? $person;
                $person['filing'] = $filing;
                $person['biometric_samples'] = $this->persons->listBiometricSamples($id, $tenantId);
                $person['terrain'] = $this->terrain->personTerrainDossier($tenantId, $person);
            } catch (\Throwable) {
                // LOT 3 optionnel si migration absente.
            }

            $this->intelFoundation->onPersonIngested($tenantId, $person, [
                'source_system' => (string) ($body['source_system'] ?? 'ARMA_SSE'),
                'raw_source_id' => $body['raw_source_id'] ?? $body['sse_uid'] ?? null,
                'idempotency_key' => (string) ($body['idempotency_key'] ?? $body['event_uuid'] ?? ''),
                'event_uuid' => $body['event_uuid'] ?? null,
                'case_id' => $filedCaseId,
                'author_label' => (string) ($data['submitter_callsign'] ?? 'Terrain'),
                'unit_label' => $body['unit_label'] ?? $body['unit'] ?? null,
                'lat' => $body['capture_pos_x'] ?? $body['pos_x'] ?? $body['lat'] ?? $person['capture_pos_x'] ?? $person['pos_x'] ?? null,
                'lng' => $body['capture_pos_y'] ?? $body['pos_y'] ?? $body['lng'] ?? $person['capture_pos_y'] ?? $person['pos_y'] ?? null,
                'source_reliability' => $body['source_reliability'] ?? 'C',
                'info_credibility' => $body['info_credibility'] ?? 3,
                'client' => $this->extractSseClientMeta($body),
                'transmission_fields' => $this->extractSsePersonTransmissionFields($body, $person),
            ]);
        }

        return Response::json($person, 201);
    }

    public function personsShow(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $id = (int) ($params['id'] ?? 0);
        $person = $this->persons->findById($id, $r);
        if ($person === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Fiche introuvable.'], 404);
        }
        $person['biometric_samples'] = $this->persons->listBiometricSamples($id, $r);

        return Response::json($person);
    }

    /**
     * Fiche déjà ouverte pour une unité Arma (terminal SEEK : « fiche existante »).
     * L'absence de fiche n'est pas une erreur : 200 avec person = null.
     */
    public function personsByUnit(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $netId = trim((string) ($request->query('netid') ?? $request->query('net_id') ?? ''));
        if ($netId === '') {
            return Response::json(['person' => null, 'message' => 'Unité non précisée.']);
        }

        $person = $this->persons->findByTargetUnit($r, $this->mapId($request), $netId);
        if (is_array($person)) {
            $person['biometric_samples'] = $this->persons->listBiometricSamples((int) $person['id'], $r);
        }

        return Response::json(['person' => $person]);
    }

    public function personsPhotoStore(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized', 'message' => 'Authentification terrain requise.'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $personId = (int) ($params['id'] ?? 0);

        $actor = $this->armaGuard->assertActor($request, $tenantId, [], false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $person = $this->persons->findById($personId, $tenantId);
        if ($person === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Fiche introuvable.'], 404);
        }

        if (empty($_FILES['image']) && empty($_FILES['photo'])) {
            return Response::json([
                'error' => 'missing_image',
                'message' => 'Aucune photo reçue. Reprenez la capture du visage.',
            ], 400);
        }
        $file = $_FILES['image'] ?? $_FILES['photo'];
        $uploadErr = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadErr !== UPLOAD_ERR_OK) {
            return Response::json([
                'error' => 'upload_failed',
                'message' => 'Impossible de recevoir la photo. Vérifiez la liaison puis réessayez.',
            ], 400);
        }
        $maxBytes = 12 * 1024 * 1024;
        if (!empty($file['size']) && (int) $file['size'] > $maxBytes) {
            return Response::json([
                'error' => 'file_too_large',
                'message' => 'La photo est trop lourde. Essayez une capture plus légère.',
            ], 400);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = is_file($tmp) ? (string) $finfo->file($tmp) : '';
        $ext = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($ext === null) {
            return Response::json([
                'error' => 'invalid_image',
                'message' => 'Format de photo non pris en charge. Utilisez une image JPEG ou PNG.',
            ], 400);
        }

        $dir = base_path('public/uploads/sse');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return Response::json(['error' => 'storage', 'message' => 'Impossible d’enregistrer la photo.'], 500);
        }

        $author = trim((string) (
            $_POST['author'] ?? $_POST['author_callsign'] ?? $actor['callsign'] ?? 'Terrain'
        ));
        $safeAuthor = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $author) ?: 'op';
        $filename = sprintf('sse_%d_%s_%s.%s', $personId, time(), $safeAuthor, $ext);
        $dest = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!@move_uploaded_file($tmp, $dest)) {
            return Response::json(['error' => 'storage', 'message' => 'Impossible d’enregistrer la photo.'], 500);
        }

        $relative = 'uploads/sse/' . $filename;
        $gridRef = trim((string) ($_POST['grid_reference'] ?? $_POST['grid_ref'] ?? ''));
        $meta = [];
        if ($gridRef !== '') {
            $meta['grid_reference'] = $gridRef;
        }
        foreach (['pos_x', 'pos_y', 'pos_z'] as $coordKey) {
            if (isset($_POST[$coordKey]) && $_POST[$coordKey] !== '' && is_numeric($_POST[$coordKey])) {
                $meta[$coordKey] = (float) $_POST[$coordKey];
            }
        }
        $photoId = $this->persons->addPhoto($personId, $tenantId, [
            'image_path' => $relative,
            'angle' => $_POST['angle'] ?? 'face',
            'caption' => $_POST['caption'] ?? null,
            'author_callsign' => $author,
            'pos_x' => $_POST['pos_x'] ?? null,
            'pos_y' => $_POST['pos_y'] ?? null,
            'pos_z' => $_POST['pos_z'] ?? null,
            'metadata' => $meta !== [] ? $meta : null,
        ]);

        $photos = $this->persons->listPhotos($personId, $tenantId);
        $photo = null;
        foreach ($photos as $p) {
            if ((int) ($p['id'] ?? 0) === $photoId) {
                $photo = $p;
                break;
            }
        }

        $this->activityLog->record(
            $tenantId,
            (int) ($person['context_id'] ?? 1),
            'SSE_PHOTO',
            sprintf('Photo du visage jointe à %s', $person['display_name'] ?? 'une fiche'),
            $author
        );

        return Response::json($photo ?? ['id' => $photoId, 'image_path' => $relative], 201);
    }

    public function personsBiometricsSim(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized', 'message' => 'Authentification terrain requise.'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $personId = (int) ($params['id'] ?? 0);

        $actor = $this->armaGuard->assertActor($request, $tenantId, $this->jsonBody($request), false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $body = $this->jsonBody($request);
        $kind = strtolower(trim((string) ($body['kind'] ?? 'empreintes')));
        if (!in_array($kind, ['empreintes', 'iris', 'adn'], true)) {
            $kind = 'empreintes';
        }
        $callsign = (string) ($body['submitter_callsign'] ?? $actor['callsign'] ?? 'Terrain');

        if (!$this->persons->markBiometricsSimulated($personId, $tenantId, $kind, $callsign)) {
            return Response::json(['error' => 'not_found', 'message' => 'Fiche introuvable.'], 404);
        }

        $person = $this->persons->findById($personId, $tenantId);

        return Response::json([
            'ok' => true,
            'person' => $person,
            'message' => match ($kind) {
                'iris' => 'Simulation iris enregistrée.',
                'adn' => 'Simulation ADN enregistrée.',
                default => 'Simulation d’empreintes enregistrée.',
            },
        ]);
    }

    // ================= Exploitation de site =================

    public function sitesIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $sites = $this->sites->listForContext($r, $this->mapId($request), [
            'status' => $request->query('status'),
            'site_type' => $request->query('site_type'),
            'limit' => $request->query('limit') ? (int) $request->query('limit') : 100,
        ]);

        return Response::json(['sites' => $sites, 'count' => count($sites)]);
    }

    /**
     * Ouvre un dossier site depuis le terrain. La checklist des pièces est
     * prégarnie selon le type si le terrain n'en fournit pas.
     */
    public function sitesStore(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized', 'message' => 'Authentification terrain requise.'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;

        $actor = $this->armaGuard->assertActor($request, $tenantId, $this->jsonBody($request), false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $body = $this->jsonBody($request);
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            return Response::json([
                'error' => 'name_required',
                'message' => 'Donnez un nom au site exploité.',
            ], 422);
        }

        // Rattachement au dossier : le terrain transmet la référence active, pas un
        // identifiant technique.
        $caseId = null;
        $caseCode = strtoupper(trim((string) ($body['case_code'] ?? '')));
        if ($caseCode !== '') {
            $case = $this->cases->findByReferenceCode($tenantId, $caseCode);
            if ($case !== null) {
                $caseId = (int) $case['id'];
            }
        }

        $data = array_merge($body, [
            'tenant_id' => $tenantId,
            'context_id' => $this->mapId($request, true),
            'case_id' => $caseId,
            'submitter_callsign' => $body['submitter_callsign'] ?? $actor['callsign'] ?? null,
        ]);

        $id = $this->sites->create($data);
        $site = $this->sites->findById($id, $tenantId);

        $this->activityLog->record(
            $tenantId,
            (int) $data['context_id'],
            'SSE_SITE',
            sprintf(
                'Site ouvert : %s — %s (%s)',
                $site['reference_code'] ?? '',
                $site['name'] ?? '',
                $site['site_type_label'] ?? ''
            ),
            (string) ($data['submitter_callsign'] ?? 'Terrain')
        );

        if (is_array($site)) {
            try {
                $pct = $this->terrain->refreshSiteExploitation($tenantId, $id);
                $site['exploitation_pct'] = $pct;
            } catch (\Throwable) {
            }
            $this->intelFoundation->onSiteIngested($tenantId, $site, [
                'source_system' => (string) ($body['source_system'] ?? 'ARMA_SSE'),
                'raw_source_id' => $body['raw_source_id'] ?? $site['reference_code'] ?? null,
                'idempotency_key' => (string) ($body['idempotency_key'] ?? $body['event_uuid'] ?? ''),
                'case_id' => $caseId,
                'author_label' => (string) ($data['submitter_callsign'] ?? 'Terrain'),
                'client' => $this->extractSseClientMeta($body),
                'transmission_fields' => $this->extractSseSiteTransmissionFields($body, $site),
            ]);
        }

        return Response::json($site, 201);
    }

    public function sitesShow(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $site = $this->sites->findById((int) ($params['id'] ?? 0), $r);
        if ($site === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Site introuvable.'], 404);
        }
        $site['five_line_report'] = $this->sites->buildFiveLineReport($site);

        return Response::json($site);
    }

    /** Marque une pièce fouillée / non fouillée. */
    public function siteRoomUpdate(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized', 'message' => 'Authentification terrain requise.'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $body = $this->jsonBody($request);
        $roomId = (int) ($params['roomId'] ?? $body['room_id'] ?? 0);
        if ($roomId < 1) {
            return Response::json(['error' => 'room_required', 'message' => 'Pièce non précisée.'], 422);
        }

        $checked = !empty($body['checked']);
        if (!$this->sites->setRoomChecked($roomId, $r, $checked, $body['notes'] ?? null)) {
            return Response::json(['error' => 'not_found', 'message' => 'Pièce introuvable.'], 404);
        }

        $siteId = (int) ($params['id'] ?? 0);
        $site = $this->sites->findById($siteId, $r);

        $automation = [];
        try {
            $automation = $this->automation->onSiteProgress(
                $r,
                (int) ($site['context_id'] ?? 1),
                $siteId,
                (string) ($body['submitter_callsign'] ?? 'Terrain')
            );
        } catch (\Throwable) {
        }

        return Response::json(['ok' => true, 'site' => $site, 'automation' => $automation]);
    }

    /** Verse une saisie au dossier site. */
    public function siteSeizureStore(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized', 'message' => 'Authentification terrain requise.'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $siteId = (int) ($params['id'] ?? 0);

        $actor = $this->armaGuard->assertActor($request, $tenantId, $this->jsonBody($request), false);
        if ($actor instanceof Response) {
            return $actor;
        }

        if ($this->sites->findById($siteId, $tenantId) === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Site introuvable.'], 404);
        }

        $body = $this->jsonBody($request);
        $items = $body['seizures'] ?? [$body];
        if (!is_array($items)) {
            $items = [$body];
        }

        $created = 0;
        $automation = [];
        $callsign = (string) ($body['submitter_callsign'] ?? $actor['callsign'] ?? 'Terrain');
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $item['site_id'] = $siteId;
            $item['actor_callsign'] = $item['actor_callsign'] ?? $callsign;
            $seizureId = $this->sites->addSeizure($tenantId, $item);
            $created++;

            // Certaines natures de saisie appellent une remontée immédiate, sans
            // attendre la clôture du site.
            try {
                $stored = $this->sites->findSeizure((int) $seizureId, $tenantId) ?? $item;
                $automation = array_merge($automation, $this->automation->onSeizureRecorded(
                    $tenantId,
                    (int) ($this->mapId($request)),
                    $siteId,
                    $stored,
                    $callsign
                ));
            } catch (\Throwable) {
            }
        }

        $site = $this->sites->findById($siteId, $tenantId);
        $this->activityLog->record(
            $tenantId,
            (int) ($site['context_id'] ?? 1),
            'SSE_SEIZURE',
            sprintf('%d saisie(s) versée(s) au site %s', $created, $site['reference_code'] ?? ''),
            $callsign
        );

        return Response::json([
            'ok' => true,
            'created' => $created,
            'site' => $site,
            'automation' => $automation,
        ], 201);
    }

    /** Clôture le site et fige le compte rendu cinq lignes. */
    public function siteClose(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized', 'message' => 'Authentification terrain requise.'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $siteId = (int) ($params['id'] ?? 0);

        $actor = $this->armaGuard->assertActor($request, $tenantId, $this->jsonBody($request), false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $site = $this->sites->findById($siteId, $tenantId);
        if ($site === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Site introuvable.'], 404);
        }

        $body = $this->jsonBody($request);
        $summary = trim((string) ($body['summary'] ?? ''));
        if ($summary === '') {
            $summary = $this->sites->buildFiveLineReport($site);
        }

        $callsign = (string) ($body['submitter_callsign'] ?? $actor['callsign'] ?? 'Terrain');
        $this->sites->close($siteId, $tenantId, $summary, $callsign);
        $site = $this->sites->findById($siteId, $tenantId);

        $this->activityLog->record(
            $tenantId,
            (int) ($site['context_id'] ?? 1),
            'SSE_SITE',
            sprintf('Site clôturé : %s', $site['reference_code'] ?? ''),
            $callsign
        );

        return Response::json($site);
    }

    private function authArma(): bool
    {
        return ComspecApiKeyAuth::armaInlineAuthOk();
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

        if ($this->tenantAtakConfigRepository->isMaintenanceEnabled($id)) {
            $userId = (int) (Session::get('user_id') ?? 0);
            $bypass = $userId > 0 && function_exists('can') && can('admin.access');
            if (!$bypass) {
                $message = $this->tenantAtakConfigRepository->getMaintenanceMessage($id);
                if ($message === '') {
                    $message = 'L’accès à la carte est suspendu pour le moment. Réessayez plus tard.';
                }

                return Response::json(['error' => 'maintenance', 'message' => $message], 503);
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
        $slug = $request->query('tenant_slug');
        if (is_string($slug) && trim($slug) !== '') {
            $t = $this->tenantRepository->findBySlug(trim($slug));

            return $t ? (int) $t['id'] : null;
        }

        return null;
    }

    private function mapId(Request $request, bool $fromBody = false): int
    {
        if ($fromBody) {
            $body = $this->jsonBody($request);
            $map = $body['mapId'] ?? $body['map_id'] ?? $request->query('mapId');
        } else {
            $map = $request->query('mapId');
        }
        $mapId = ($map !== null && $map !== '') ? (int) $map : self::DEFAULT_MAP_ID;

        return $mapId < 1 ? self::DEFAULT_MAP_ID : $mapId;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(Request $request): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            $this->jsonBodyCache = [];

            return $this->jsonBodyCache;
        }
        $decoded = json_decode($raw, true);
        $this->jsonBodyCache = is_array($decoded) ? $decoded : [];

        return $this->jsonBodyCache;
    }

    /**
     * Empreinte logiciel terrain (versions CfgPatches / Workshop).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function extractSseClientMeta(array $body): array
    {
        $modVersion = trim((string) ($body['mod_version'] ?? $body['overwatch_version'] ?? ''));
        $modName = trim((string) ($body['mod_name'] ?? $body['client_name'] ?? ''));
        $modCfg = trim((string) ($body['mod_cfg'] ?? $body['mod_patch'] ?? ''));
        $sseVersion = trim((string) ($body['sse_addon_version'] ?? $body['sse_version'] ?? ''));
        $sseCfg = trim((string) ($body['sse_addon_cfg'] ?? ''));
        $armaVersion = trim((string) ($body['arma_version'] ?? $body['game_version'] ?? ''));

        if ($modName === '' && ($modVersion !== '' || $modCfg !== '')) {
            $modName = 'COMSPEC Overwatch';
        }
        if ($modCfg === '' && $modVersion !== '') {
            $modCfg = 'comspec_overwatch_connect';
        }
        if ($sseCfg === '' && $sseVersion !== '') {
            $sseCfg = 'comspec_sse_main';
        }

        $out = array_filter([
            'mod_name' => $modName !== '' ? $modName : null,
            'mod_version' => $modVersion !== '' ? $modVersion : null,
            'mod_cfg' => $modCfg !== '' ? $modCfg : null,
            'sse_addon_version' => $sseVersion !== '' ? $sseVersion : null,
            'sse_addon_cfg' => $sseCfg !== '' ? $sseCfg : null,
            'arma_version' => $armaVersion !== '' ? $armaVersion : null,
        ], static fn ($v) => $v !== null);

        return $out;
    }

    /**
     * Normalise et propage les coordonnées SEEK sur la fiche et les sous-données.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function enrichSeekCaptureLocation(array $body): array
    {
        $posX = $body['pos_x'] ?? $body['capture_pos_x'] ?? null;
        $posY = $body['pos_y'] ?? $body['capture_pos_y'] ?? null;
        $posZ = $body['pos_z'] ?? $body['capture_pos_z'] ?? null;
        $grid = trim((string) ($body['grid_reference'] ?? $body['grid'] ?? ''));

        if ($posX !== null && $posX !== '' && is_numeric($posX)) {
            $body['pos_x'] = (float) $posX;
            $body['capture_pos_x'] = (float) $posX;
        }
        if ($posY !== null && $posY !== '' && is_numeric($posY)) {
            $body['pos_y'] = (float) $posY;
            $body['capture_pos_y'] = (float) $posY;
        }
        if ($posZ !== null && $posZ !== '' && is_numeric($posZ)) {
            $body['pos_z'] = (float) $posZ;
            $body['capture_pos_z'] = (float) $posZ;
        }
        if ($grid !== '') {
            $body['grid_reference'] = $grid;
        }

        $stamp = static function (mixed $item) use ($body): mixed {
            if (!is_array($item)) {
                return $item;
            }
            if (!isset($item['grid_reference']) || $item['grid_reference'] === '' || $item['grid_reference'] === null) {
                if (!empty($body['grid_reference'])) {
                    $item['grid_reference'] = $body['grid_reference'];
                }
            }
            foreach (['pos_x', 'pos_y', 'pos_z'] as $k) {
                if (!isset($item[$k]) || $item[$k] === '' || $item[$k] === null) {
                    if (isset($body[$k]) && $body[$k] !== null && $body[$k] !== '') {
                        $item[$k] = $body[$k];
                    }
                }
            }

            return $item;
        };

        foreach (['weapons', 'equipment', 'biometric_samples'] as $listKey) {
            if (!isset($body[$listKey]) || !is_array($body[$listKey])) {
                continue;
            }
            $body[$listKey] = array_map($stamp, $body[$listKey]);
        }
        foreach (['medical_context', 'signature', 'identity_query'] as $objKey) {
            if (isset($body[$objKey]) && is_array($body[$objKey])) {
                $body[$objKey] = $stamp($body[$objKey]);
            }
        }

        return $body;
    }

    /**
     * Instantané des champs utiles transmis avec la fiche personne.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $person
     * @return array<string, mixed>
     */
    private function extractSsePersonTransmissionFields(array $body, array $person): array
    {
        $keys = [
            'last_name', 'first_name', 'alias', 'status', 'age_estimated', 'nationality',
            'language_spoken', 'distinguishing_marks', 'affiliation', 'circumstances',
            'statements', 'confidence_level', 'grid_reference', 'submitter_callsign',
            'submitter_steam_id', 'target_unit_netid', 'case_code', 'pos_x', 'pos_y', 'pos_z',
            'capture_pos_x', 'capture_pos_y', 'capture_pos_z', 'biometrics_simulated',
            'consent_recorded', 'weapons', 'equipment', 'medical_context', 'identity_query',
            'signature', 'biometric_samples',
        ];
        $out = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $body)) {
                continue;
            }
            $val = $body[$key];
            if ($val === null || $val === '' || $val === []) {
                continue;
            }
            $out[$key] = $val;
        }
        if (!isset($out['last_name']) && !empty($person['last_name'])) {
            $out['last_name'] = $person['last_name'];
        }
        if (!isset($out['first_name']) && !empty($person['first_name'])) {
            $out['first_name'] = $person['first_name'];
        }
        if (!isset($out['alias']) && !empty($person['alias'])) {
            $out['alias'] = $person['alias'];
        }
        if (!empty($person['display_name'])) {
            $out['display_name'] = $person['display_name'];
        }
        if (!isset($out['grid_reference']) && !empty($person['grid_reference'])) {
            $out['grid_reference'] = $person['grid_reference'];
        }
        foreach (['pos_x' => 'capture_pos_x', 'pos_y' => 'capture_pos_y', 'pos_z' => 'capture_pos_z'] as $posKey => $capKey) {
            if (!isset($out[$posKey]) && isset($person[$posKey]) && $person[$posKey] !== null) {
                $out[$posKey] = $person[$posKey];
            }
            if (!isset($out[$capKey]) && isset($out[$posKey])) {
                $out[$capKey] = $out[$posKey];
            }
        }
        $grid = (string) ($out['grid_reference'] ?? '');
        $px = $out['pos_x'] ?? $out['capture_pos_x'] ?? null;
        $py = $out['pos_y'] ?? $out['capture_pos_y'] ?? null;
        if ($grid !== '' || ($px !== null && $py !== null)) {
            $parts = [];
            if ($grid !== '') {
                $parts[] = 'Grille ' . $grid;
            }
            if ($px !== null && $py !== null) {
                $parts[] = sprintf('Terrain %.1f / %.1f', (float) $px, (float) $py);
            }
            $out['location_summary'] = implode(' · ', $parts);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $site
     * @return array<string, mixed>
     */
    private function extractSseSiteTransmissionFields(array $body, array $site): array
    {
        $keys = [
            'name', 'title', 'site_type', 'grid_reference', 'submitter_callsign',
            'pos_x', 'pos_y', 'pos_z', 'notes', 'description',
        ];
        $out = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $body)) {
                continue;
            }
            $val = $body[$key];
            if ($val === null || $val === '' || $val === []) {
                continue;
            }
            $out[$key] = $val;
        }
        foreach (['name', 'title', 'reference_code', 'site_type'] as $key) {
            if (!isset($out[$key]) && !empty($site[$key])) {
                $out[$key] = $site[$key];
            }
        }

        return $out;
    }
}
