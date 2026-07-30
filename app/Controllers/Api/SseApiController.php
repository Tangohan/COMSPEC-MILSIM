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
use App\Services\Sse\SseCrossMatchService;
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
        private ?SseSiteRepository $sites = null,
        private ?AtakArmaWriteGuard $armaGuard = null,
        private ?AtakActivityLogService $activityLog = null,
        private ?TenantAtakConfigRepository $tenantAtakConfigRepository = null,
        private ?TenantRepository $tenantRepository = null,
    ) {
        $this->persons ??= new SsePersonRepository();
        $this->cases ??= new SseCaseRepository();
        $this->cross ??= new SseCrossMatchService();
        $this->sites ??= new SseSiteRepository();
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
                $this->persons->addBiometricSample($id, $tenantId, $sample);
            }
        }

        // Classement : code dossier saisi sur le terrain.
        $caseCode = strtoupper(trim((string) ($body['case_code'] ?? '')));
        $filing = ['code' => $caseCode, 'linked' => false, 'case' => null];
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
        $photoId = $this->persons->addPhoto($personId, $tenantId, [
            'image_path' => $relative,
            'angle' => $_POST['angle'] ?? 'face',
            'caption' => $_POST['caption'] ?? null,
            'author_callsign' => $author,
            'pos_x' => $_POST['pos_x'] ?? null,
            'pos_y' => $_POST['pos_y'] ?? null,
            'pos_z' => $_POST['pos_z'] ?? null,
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

        $site = $this->sites->findById((int) ($params['id'] ?? 0), $r);

        return Response::json(['ok' => true, 'site' => $site]);
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
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $item['site_id'] = $siteId;
            $item['actor_callsign'] = $item['actor_callsign'] ?? $body['submitter_callsign'] ?? $actor['callsign'] ?? null;
            $this->sites->addSeizure($tenantId, $item);
            $created++;
        }

        $site = $this->sites->findById($siteId, $tenantId);
        $this->activityLog->record(
            $tenantId,
            (int) ($site['context_id'] ?? 1),
            'SSE_SEIZURE',
            sprintf('%d saisie(s) versée(s) au site %s', $created, $site['reference_code'] ?? ''),
            (string) ($body['submitter_callsign'] ?? $actor['callsign'] ?? 'Terrain')
        );

        return Response::json(['ok' => true, 'created' => $created, 'site' => $site], 201);
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
}
