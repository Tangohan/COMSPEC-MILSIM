<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDataRepository;
use App\Support\ComspecApiKeyAuth;
use App\Repositories\CasNineLineRepository;
use App\Repositories\ReconImageRepository;
use App\Repositories\MapShapeRepository;
use App\Repositories\LaserCodeRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Repositories\ArmaPlaytimeRepository;
use App\Repositories\TacticalBriefingSlideRepository;
use App\Repositories\TacticalPhonePairingRepository;

class AtakApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** @var array<string, mixed>|null Cache php://input (une seule lecture par requête). */
    private ?array $jsonBodyCache = null;

    public function __construct(
        private AtakDataRepository $atak,
        private CasNineLineRepository $casRepo,
        private ReconImageRepository $reconRepo,
        private MapShapeRepository $mapShapeRepo,
        private LaserCodeRepository $laserCodeRepo,
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private ArmaPlaytimeRepository $armaPlaytimeRepository,
        private ?TacticalBriefingSlideRepository $briefingSlideRepository = null,
        private ?TacticalPhonePairingRepository $phonePairingRepository = null,
    ) {
        $this->briefingSlideRepository ??= new TacticalBriefingSlideRepository();
        $this->phonePairingRepository ??= new TacticalPhonePairingRepository();
    }

    /**
     * Diapositives de briefing actives (image + titre + ordre), consommées par l’extension Arma
     * (fonction native GetBriefingSlides) pour affichage in-game (tableau Eden ou dialog de briefing).
     */
    public function briefingSlidesIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $rows = $this->briefingSlideRepository->listActiveForTenant($tenantId);
        $out = [];
        foreach ($rows as $row) {
            $imagePath = trim((string) ($row['image_path'] ?? ''));
            if ($imagePath === '') {
                continue;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => trim((string) ($row['title'] ?? '')),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'image_url' => url($imagePath),
                'updated_at' => (string) ($row['updated_at'] ?? $row['created_at'] ?? ''),
            ];
        }

        return Response::json(['slides' => $out]);
    }

    /**
     * Connexion téléphone (inspiré de cTab) : génère un token (QR) + un code court lisible,
     * consommés par l'extension Arma (fonction native GetPhoneConnectInfo) pour affichage
     * en jeu, puis par un navigateur mobile sans compte sur /atak/connect/{token}.
     */
    public function phonePairingCreate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $pairing = $this->phonePairingRepository->create($tenantId);
        if ($pairing === null) {
            return Response::json(['error' => 'phone_pairing_unavailable'], 503);
        }
        $token = $pairing['token'];

        return Response::json([
            'token' => $token,
            'code' => $pairing['code'],
            'connect_url' => url('atak/connect/' . $token),
            'qr_image_url' => url('api/atak/phone-pairing/' . $token . '/qr.png'),
            'expires_at' => $pairing['expires_at'],
        ]);
    }

    /** PNG du QR code encodant l’URL de connexion — consommé par le même téléchargeur d’image que les diapositives. */
    public function phonePairingQrImage(Request $request, array $params = []): Response
    {
        $token = trim((string) ($params['token'] ?? ''));
        $pairing = $this->phonePairingRepository->findValidByToken($token);
        if ($pairing === null) {
            return (new Response())->setStatusCode(404)->setBody('Not found');
        }
        $connectUrl = url('atak/connect/' . $token);
        if (!class_exists(\Endroid\QrCode\Builder\Builder::class) || !class_exists(\Endroid\QrCode\Writer\PngWriter::class)) {
            return (new Response())->setStatusCode(503)->setBody('QR unavailable');
        }
        try {
            $result = \Endroid\QrCode\Builder\Builder::create()
                ->writer(new \Endroid\QrCode\Writer\PngWriter())
                ->data($connectUrl)
                ->size(400)
                ->margin(12)
                ->build();
        } catch (\Throwable) {
            return (new Response())->setStatusCode(500)->setBody('QR generation failed');
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', $result->getMimeType())
            ->header('Cache-Control', 'no-store')
            ->setBody($result->getString());
    }

    /**
     * Résout le tenant pour l’API ATAK : session, puis query/body (tenant_id), puis tenant_slug, puis env explicite.
     * Ne retombe plus silencieusement sur le tenant 1 — configurez ATAK_DEFAULT_TENANT_ID pour les déploiements mono-tenant.
     */
    private function resolveTenantId(Request $request): ?int
    {
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
        $env = getenv('ATAK_DEFAULT_TENANT_ID') ?: getenv('APP_ATAK_DEFAULT_TENANT_ID');
        if ($env !== false && $env !== null && $env !== '') {
            return (int) $env;
        }

        return null;
    }

    /** @return int|Response */
    private function requireTenant(Request $request): int|Response
    {
        $id = $this->resolveTenantId($request);
        if ($id === null) {
            return Response::json([
                'error' => 'tenant_context_required',
                'message' => 'Indiquez tenant_id ou tenant_slug (query/body), une session avec tenant_id, ou définissez ATAK_DEFAULT_TENANT_ID pour un déploiement mono-tenant.',
            ], 403);
        }

        return $id;
    }

    private function mapId(Request $request, bool $fromBody = false): int
    {
        if ($fromBody) {
            $body = $this->jsonBody($request);
            $map = $body['mapId'] ?? $body['map_id'] ?? $request->query('mapId');
        } else {
            $map = $request->query('mapId');
        }
        return $map !== null && $map !== '' ? (int) $map : self::DEFAULT_MAP_ID;
    }

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

    private function authArma(): bool
    {
        return ComspecApiKeyAuth::armaInlineAuthOk();
    }

    public function ping(Request $request, array $params = []): Response
    {
        return Response::json(['ok' => true, 'service' => 'atak']);
    }

    public function whoami(Request $request, array $params = []): Response
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ip = is_string($forwarded) ? trim(explode(',', $forwarded)[0]) : trim($forwarded[0]);
        }
        return Response::json(['ip' => $ip ?: '—']);
    }

    public function stats(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $last = $this->atak->getLastActivity($tenantId, $mapId);
        $ago = null;
        if ($last !== null) {
            $ago = (int) (time() - strtotime($last));
        }
        $units = $this->atak->getUnits($tenantId, $mapId);
        $unitsCount = count($units);
        $activeCallSigns = $this->atak->getActiveUnitsSummary($tenantId, $mapId, 15);
        return Response::json([
            'sockets' => 0,
            'lastArmaActivity' => $last,
            'lastArmaActivityAgo' => $ago,
            'unitsCount' => $unitsCount,
            'activeCallSigns' => $activeCallSigns,
        ]);
    }

    public function markersIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $since = $request->query('since');
        $rows = $this->atak->getMarkers($tenantId, $mapId, $since);
        $out = array_map(fn ($r) => ['id' => $r['id'], 'layerId' => $r['layerId'], 'markerData' => $r['markerData'], 'updated_at' => $r['updated_at']], $rows);
        return Response::json($out);
    }

    public function markersStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? self::DEFAULT_MAP_ID);
        $layerId = (int) ($body['layerId'] ?? 1);
        $markerData = isset($body['markerData']) ? (is_string($body['markerData']) ? $body['markerData'] : json_encode($body['markerData'])) : '{}';
        $row = $this->atak->addMarker($tenantId, $mapId, $layerId, $markerData);
        return Response::json(['id' => $row['id'], 'layerId' => $row['layerId'], 'markerData' => $row['markerData']], 201);
    }

    public function markersUpdate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::json(['error' => 'Not found'], 404);
        }
        $body = $this->jsonBody($request);
        $markerData = isset($body['markerData'])
            ? (is_string($body['markerData']) ? $body['markerData'] : json_encode($body['markerData']))
            : null;
        if ($markerData === null) {
            return Response::json(['error' => 'markerData required'], 400);
        }
        $layerId = isset($body['layerId']) ? (int) $body['layerId'] : null;
        $row = $this->atak->updateMarker($tenantId, $id, $markerData, $layerId);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json(['id' => $row['id'], 'layerId' => $row['layerId'], 'markerData' => $row['markerData']]);
    }

    public function markersDelete(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::json(['error' => 'Not found'], 404);
        }
        if (!$this->atak->deleteMarker($tenantId, $id)) {
            return Response::json(['error' => 'Not found'], 404);
        }
        $r = new Response();
        $r->setStatusCode(204);
        return $r;
    }

    public function markerUpsert(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? self::DEFAULT_MAP_ID);
        $layerId = (int) ($body['layerId'] ?? 1);
        $armaName = $body['arma_name'] ?? $body['armaName'] ?? null;
        if ($armaName === null || $armaName === '') {
            return Response::json(['error' => 'arma_name required'], 400);
        }
        $markerData = isset($body['markerData']) ? (is_string($body['markerData']) ? $body['markerData'] : json_encode($body['markerData'])) : '{}';
        $row = $this->atak->upsertMarkerByArmaName($tenantId, $mapId, $layerId, $armaName, $markerData);
        return Response::json(['id' => $row['id'], 'layerId' => $row['layerId'], 'markerData' => $row['markerData']], 201);
    }

    public function unitsIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $rows = $this->atak->getUnits($tenantId, $mapId);
        return Response::json($rows);
    }

    public function unitsStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? self::DEFAULT_MAP_ID);
        $row = $this->atak->addUnit($tenantId, $mapId, $body);
        return Response::json($row, 201);
    }

    public function unitsUpdate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $row = $this->atak->updateUnit($tenantId, $id, $body);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json($row);
    }

    public function position(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        $callSign = $body['call_sign'] ?? $body['callsign'] ?? 'Unknown';
        $posX = (float) ($body['pos_x'] ?? $body['pos'][0] ?? 0);
        $posY = (float) ($body['pos_y'] ?? $body['pos'][1] ?? 0);
        $heading = isset($body['heading']) ? (float) $body['heading'] : null;
        $role = $body['role'] ?? '';
        $extra = $body['extra'] ?? [];
        if (!is_array($extra)) {
            $extra = ['role' => $body['role'] ?? '', 'health' => $body['health'] ?? 'ok', 'fuel' => $body['fuel'] ?? '', 'ammo' => $body['ammo'] ?? 'n/a'];
        }
        $this->atak->upsertUnitPosition($tenantId, $mapId, $callSign, $posX, $posY, $heading, $role, json_encode($extra));
        $missionId = 'mission_' . $tenantId . '_map_' . $mapId;
        static $lastReplayLog = [];
        $key = $missionId . ':' . $callSign;
        $now = time();
        if (!isset($lastReplayLog[$key]) || ($now - $lastReplayLog[$key]) >= 2) {
            $lastReplayLog[$key] = $now;
            try {
                $replay = \App\Core\Container::get(\App\Services\Replay\ReplayService::class);
                $replay->logPosition($missionId, $callSign, $callSign, $posX, $posY, null, $heading, null, null, null, $extra);
            } catch (\Throwable) {
            }
        }
        return Response::json(['ok' => true]);
    }

    /**
     * Cumul temps de jeu côté client Arma (mod + extension) — identifiant jeu aligné sur le champ Steam du compte.
     */
    public function playtime(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $uidRaw = trim((string) ($body['player_uid'] ?? $body['playerUid'] ?? $body['steam_id'] ?? ''));
        if ($uidRaw === '') {
            return Response::json(['error' => 'player_uid required'], 400);
        }
        $seconds = (int) ($body['session_seconds'] ?? $body['seconds'] ?? 0);
        if ($seconds < 1) {
            return Response::json(['ok' => true, 'matched' => false, 'recorded' => false]);
        }
        $seconds = min($seconds, 7200);
        if (!$this->armaPlaytimeRepository->schemaReady()) {
            return Response::json(['ok' => false, 'error' => 'schema_not_ready'], 503);
        }
        $user = $this->userRepository->findBySteamIdForTenant($tenantId, $uidRaw);
        if ($user === null) {
            return Response::json(['ok' => true, 'matched' => false, 'recorded' => false]);
        }
        $this->armaPlaytimeRepository->addSeconds($tenantId, (int) $user['id'], $seconds);

        return Response::json(['ok' => true, 'matched' => true, 'recorded' => true, 'recorded_seconds' => $seconds]);
    }

    public function chatIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $limit = (int) ($request->query('limit') ?: 100);
        $rows = $this->atak->getChatMessages($tenantId, $mapId, min($limit, 500));
        return Response::json($rows);
    }

    public function chatStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? self::DEFAULT_MAP_ID);
        $author = $body['author'] ?? 'Anonymous';
        $bodyText = $body['body'] ?? '';
        $row = $this->atak->addChatMessage($tenantId, $mapId, $author, $bodyText);
        return Response::json($row, 201);
    }

    public function pingsIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $limit = (int) ($request->query('limit') ?: 50);
        $rows = $this->atak->getPings($tenantId, $mapId, min($limit, 200));
        return Response::json($rows);
    }

    public function pingsStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? self::DEFAULT_MAP_ID);
        $author = $body['author'] ?? 'Anonymous';
        $posX = (float) ($body['pos_x'] ?? $body['pos'][0] ?? 0);
        $posY = (float) ($body['pos_y'] ?? $body['pos'][1] ?? 0);
        $message = $body['message'] ?? '';
        $row = $this->atak->addPing($tenantId, $mapId, $author, $posX, $posY, $message);
        return Response::json($row, 201);
    }

    public function nineLineIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $rows = $this->atak->getNineLines($tenantId, $mapId);
        return Response::json($rows);
    }

    public function nineLineStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? self::DEFAULT_MAP_ID);
        $author = $body['author'] ?? 'JTAC';
        $row = $this->atak->addNineLine($tenantId, $mapId, $author, $body);
        return Response::json($row, 201);
    }

    public function nineLineUpdate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $status = $body['status'] ?? 'active';
        $row = $this->atak->updateNineLineStatus($tenantId, $id, $status);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json($row);
    }

    public function designatorIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $rows = $this->atak->getDesignators($tenantId, $mapId);
        return Response::json($rows);
    }

    public function designatorStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? self::DEFAULT_MAP_ID);
        $callSign = $body['call_sign'] ?? $body['callsign'] ?? 'Unknown';
        $posX = (float) ($body['pos_x'] ?? $body['pos'][0] ?? 0);
        $posY = (float) ($body['pos_y'] ?? $body['pos'][1] ?? 0);
        $row = $this->atak->upsertDesignator($tenantId, $mapId, $callSign, $posX, $posY);
        return Response::json($row);
    }

    public function sigintStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? self::DEFAULT_MAP_ID);
        $callSign = $body['call_sign'] ?? $body['callsign'] ?? 'Unknown';
        $posX = (float) ($body['pos_x'] ?? $body['pos'][0] ?? 0);
        $posY = (float) ($body['pos_y'] ?? $body['pos'][1] ?? 0);
        $bearing = isset($body['bearing']) ? (float) $body['bearing'] : null;
        $row = $this->atak->addSigint($tenantId, $mapId, $callSign, $posX, $posY, $bearing);
        return Response::json($row, 201);
    }

    public function sigintZones(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $limit = min((int) ($request->query('limit') ?: 50), 200);
        $rows = $this->atak->getSigintZones($tenantId, $mapId, $limit);
        return Response::json($rows);
    }

    public function intelPhotosIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $rows = $this->atak->getIntelPhotos($tenantId, $mapId);
        foreach ($rows as &$r) {
            $r['url'] = '/uploads/intel/' . basename($r['path']);
        }
        return Response::json($rows);
    }

    public function intelPhotosStore(Request $request, array $params = []): Response
    {
        $secret = getenv('ATAK_INTEL_SECRET') ?: getenv('X_COMSPEC_KEY') ?: '';
        if ($secret !== '') {
            $token = $_SERVER['HTTP_X_ATAK_TOKEN'] ?? null;
            if ($token === null && !empty($_SERVER['HTTP_AUTHORIZATION']) && str_starts_with($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ')) {
                $token = trim(substr($_SERVER['HTTP_AUTHORIZATION'], 7));
            }
            if ($token !== $secret) {
                return Response::json(['error' => 'Unauthorized'], 401);
            }
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = (int) ($_POST['mapId'] ?? self::DEFAULT_MAP_ID);
        $author = $_POST['author'] ?? $_POST['callsign'] ?? 'Unknown';
        $posX = isset($_POST['pos_x']) ? (float) $_POST['pos_x'] : null;
        $posY = isset($_POST['pos_y']) ? (float) $_POST['pos_y'] : null;
        if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            return Response::json(['error' => 'Missing or invalid photo'], 400);
        }
        $dir = dirname(__DIR__, 2) . '/../public/uploads/intel';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = date('YmdHis') . '-' . ($_FILES['photo']['name'] ?: 'photo.' . $ext);
        $path = $dir . '/' . $filename;
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $path)) {
            return Response::json(['error' => 'Upload failed'], 500);
        }
        $relativePath = 'intel/' . $filename;
        $row = $this->atak->addIntelPhoto($tenantId, $mapId, $filename, $relativePath, $author, $posX, $posY);
        $row['url'] = '/uploads/intel/' . $filename;
        return Response::json($row, 201);
    }

    public function flightManifestStore(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        $callsign = $body['callsign'] ?? $body['call_sign'] ?? 'Unknown';
        $missionToken = getenv('ATAK_MISSION_AUTH_TOKEN') ?: getenv('COMSPEC_MISSION_AUTH') ?: '';
        $status = $body['status'] ?? 'IN-FLIGHT';
        if ($missionToken !== '' && ($body['auth'] ?? $body['authCode'] ?? '') !== $missionToken) {
            $status = 'SUSPECT';
        }
        $data = array_merge($body, [
            'status' => $status,
            'pos_x' => isset($body['pos']) && is_array($body['pos']) ? ($body['pos'][0] ?? null) : ($body['pos_x'] ?? null),
            'pos_y' => isset($body['pos']) && is_array($body['pos']) ? ($body['pos'][1] ?? null) : ($body['pos_y'] ?? null),
            'pos_z' => isset($body['pos']) && is_array($body['pos']) && isset($body['pos'][2]) ? $body['pos'][2] : ($body['pos_z'] ?? null),
        ]);
        $row = $this->atak->upsertAirAsset($tenantId, $mapId, $callsign, $data);
        $this->atak->setLastActivity($tenantId, $mapId);
        return Response::json($row, 201);
    }

    // --- CAS / 9-Line ---
    public function casIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $assignedTo = $request->query('assignedTo') ?? $request->query('assigned_to');
        $status = $request->query('status');
        $rows = $this->casRepo->listCas($tenantId, $mapId, $assignedTo, $status);
        return Response::json($rows);
    }

    public function casStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        $author = $body['author'] ?? $body['jtac'] ?? 'JTAC';
        $row = $this->casRepo->createCas($tenantId, $mapId, $author, $body);
        return Response::json($row, 201);
    }

    public function casShow(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        $row = $this->casRepo->getCas($tenantId, $id);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json($row);
    }

    public function casUpdate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $row = $this->casRepo->patchCas($tenantId, $id, $body);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json($row);
    }

    public function casAck(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        $row = $this->casRepo->ackCas($tenantId, $id);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json($row);
    }

    public function casCheckLine(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $line = $body['line'] ?? $body['lineKey'] ?? '';
        $checked = (bool) ($body['checked'] ?? true);
        $checkedBy = $body['checkedBy'] ?? $body['checked_by'] ?? 'Pilot';
        if ($line === '') {
            return Response::json(['error' => 'line required'], 400);
        }
        $row = $this->casRepo->updateLineChecked($tenantId, $id, $line, $checked, $checkedBy);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json($row);
    }

    public function casStatus(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $status = $body['status'] ?? '';
        if ($status === '') {
            return Response::json(['error' => 'status required'], 400);
        }
        $row = $this->casRepo->updateCasStatus($tenantId, $id, $status);
        if ($row === null) {
            return Response::json(['error' => 'Not found or invalid status'], 404);
        }
        return Response::json($row);
    }

    // --- Recon images ---
    public function reconImagesIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $missionId = $request->query('mission_id') ?? $request->query('missionId');
        $author = $request->query('author');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $limit = min((int) ($request->query('limit') ?: 100), 200);
        $rows = $this->reconRepo->list($tenantId, $missionId, $author, $dateFrom, $dateTo, $limit);
        foreach ($rows as &$r) {
            $r['url'] = '/uploads/recon/' . basename($r['image_path']);
        }
        return Response::json($rows);
    }

    public function reconImagesStore(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        if (empty($_FILES['image']) && empty($_FILES['photo'])) {
            return Response::json(['error' => 'Missing image file'], 400);
        }
        $file = $_FILES['image'] ?? $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return Response::json(['error' => 'Upload failed'], 400);
        }
        $dir = dirname(__DIR__, 2) . '/../public/uploads/recon';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'recon_' . date('YmdHis') . '_' . ($_POST['author'] ?? 'unknown') . '.' . $ext;
        $path = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return Response::json(['error' => 'Save failed'], 500);
        }
        $data = [
            'image_path' => 'recon/' . $filename,
            'author_callsign' => $_POST['author'] ?? $_POST['author_callsign'] ?? 'Unknown',
            'unit_name' => $_POST['unit_name'] ?? $_POST['unitName'] ?? null,
            'side' => $_POST['side'] ?? 'WEST',
            'mission_id' => $_POST['mission_id'] ?? $_POST['missionId'] ?? null,
            'caption' => $_POST['caption'] ?? null,
            'pos_x' => isset($_POST['pos_x']) ? (float) $_POST['pos_x'] : null,
            'pos_y' => isset($_POST['pos_y']) ? (float) $_POST['pos_y'] : null,
            'pos_z' => isset($_POST['pos_z']) ? (float) $_POST['pos_z'] : null,
            'grid_ref' => $_POST['grid_ref'] ?? $_POST['grid'] ?? null,
            'heading' => isset($_POST['heading']) ? (float) $_POST['heading'] : null,
            'altitude' => isset($_POST['altitude']) ? (float) $_POST['altitude'] : null,
            'device_type' => $_POST['device_type'] ?? $_POST['device'] ?? 'CTAB',
            'captured_at' => isset($_POST['capturedAt']) ? (int) $_POST['capturedAt'] : time(),
        ];
        $row = $this->reconRepo->create($tenantId, $data);
        $row['url'] = '/uploads/recon/' . $filename;
        return Response::json($row, 201);
    }

    public function reconImagesShow(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        $row = $this->reconRepo->get($tenantId, $id);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        $row['url'] = '/uploads/recon/' . basename($row['image_path']);
        return Response::json($row);
    }

    public function reconImagesLinkCas(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $casId = (int) ($body['cas_id'] ?? $body['casId'] ?? 0);
        if ($casId <= 0) {
            return Response::json(['error' => 'cas_id required'], 400);
        }
        $row = $this->reconRepo->linkToCas($tenantId, $id, $casId);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json($row);
    }

    // --- Map shapes ---
    public function mapShapesIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $missionId = $request->query('mission_id') ?? $request->query('missionId');
        $since = $request->query('since');
        $rows = $this->mapShapeRepo->list($tenantId, $mapId, $missionId, $since);
        return Response::json($rows);
    }

    public function mapShapesStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        $row = $this->mapShapeRepo->create($tenantId, $mapId, $body);
        return Response::json($row, 201);
    }

    public function mapShapesUpdate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $row = $this->mapShapeRepo->update($tenantId, $id, $body);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json($row);
    }

    public function mapShapesDelete(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        if (!$this->mapShapeRepo->delete($tenantId, $id)) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json(['ok' => true], 200);
    }

    // --- Laser codes ---
    public function laserCodesIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $rows = $this->laserCodeRepo->list($tenantId, $mapId);
        return Response::json($rows);
    }

    public function laserCodesStore(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        $callSign = $body['call_sign'] ?? $body['callsign'] ?? $body['unit'] ?? 'Unknown';
        $laserCode = $body['laser_code'] ?? $body['laserCode'] ?? '1688';
        $posX = isset($body['pos_x']) ? (float) $body['pos_x'] : (isset($body['pos'][0]) ? (float) $body['pos'][0] : null);
        $posY = isset($body['pos_y']) ? (float) $body['pos_y'] : (isset($body['pos'][1]) ? (float) $body['pos'][1] : null);
        $status = $body['status'] ?? 'ACTIVE';
        $row = $this->laserCodeRepo->upsert($tenantId, $mapId, $callSign, $laserCode, $posX, $posY, $status);
        return Response::json($row);
    }

    public function airAssetsIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $rows = $this->atak->getActiveAirAssets($tenantId, $mapId);
        $cutoff = time() - 30;
        $out = [];
        foreach ($rows as $r) {
            $updated = $r['updated_at'] ? strtotime($r['updated_at']) : 0;
            $status = $updated < $cutoff ? 'OFFLINE' : ($r['status'] ?? 'IN-FLIGHT');
            $out[] = [
                'callsign' => $r['callsign'],
                'model' => $r['model'],
                'aircraft_type' => $r['aircraft_type'],
                'freq' => $r['freq'],
                'laser' => $r['laser'],
                'auth' => $r['auth'],
                'pos_x' => $r['pos_x'] !== null ? (float) $r['pos_x'] : null,
                'pos_y' => $r['pos_y'] !== null ? (float) $r['pos_y'] : null,
                'alt' => $r['alt'] !== null ? (float) $r['alt'] : null,
                'heading' => $r['heading'] !== null ? (float) $r['heading'] : null,
                'side' => $r['side'],
                'status' => $status,
                'pilot_status' => $r['pilot_status'],
                'aircraft_count' => (int) ($r['aircraft_count'] ?? 1),
                'updated_at' => $r['updated_at'],
            ];
        }
        return Response::json($out);
    }

    public function airAssetsPilotStatus(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $callsign = $params['callsign'] ?? '';
        $body = $this->jsonBody($request);
        $pilotStatus = $body['pilot_status'] ?? $body['status'] ?? '';
        if ($callsign === '' || $pilotStatus === '') {
            return Response::json(['error' => 'callsign and pilot_status required'], 400);
        }
        $allowed = ['ROGER', 'INBOUND', 'ENGAGED', 'RTB'];
        if (!in_array(strtoupper($pilotStatus), $allowed, true)) {
            return Response::json(['error' => 'Invalid pilot_status'], 400);
        }
        $row = $this->atak->updateAirAssetPilotStatus($tenantId, $mapId, $callsign, strtoupper($pilotStatus));
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json($row);
    }
}
