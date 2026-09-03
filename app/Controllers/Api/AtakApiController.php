<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDataRepository;
use App\Repositories\AtakExplosiveTimerRepository;
use App\Repositories\AtakMedicalTriageRepository;
use App\Repositories\AtakOperatorIdRepository;
use App\Repositories\AtakOrderRepository;
use App\Repositories\AtakOrderTemplateRepository;
use App\Repositories\AtakOrderTypeRepository;
use App\Repositories\FireTeamRepository;
use App\Support\ComspecApiKeyAuth;
use App\Repositories\CasNineLineRepository;
use App\Repositories\ReconImageRepository;
use App\Repositories\MapShapeRepository;
use App\Repositories\LaserCodeRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Repositories\ArmaPlaytimeRepository;
use App\Repositories\TacticalBriefingSlideRepository;
use App\Repositories\TacticalBriefingSlideCommentRepository;
use App\Repositories\TacticalPhonePairingRepository;
use App\Repositories\TacticalGameLinkRepository;
use App\Repositories\AtakDisconnectRecoveryRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\AtakBetaRegistrationRepository;
use App\Services\Qr\QrPngGenerator;
use App\Services\Tactical\AtakActivityLogService;
use App\Services\Tactical\AtakIntelViewService;
use App\Services\Tactical\AtakTenantDataService;
use App\Services\Tactical\AtakUnitMotionService;
use App\Services\Tactical\RoleplaySimulationService;
use App\Support\ArmaMarkerLabel;
use App\Support\AtakArmaWriteGuard;
use App\Support\AtakOrderWaypoint;
use App\Support\AtakPlayNight;
use App\Support\AtakGameSession;
use App\Support\ChatMentionParser;
use App\Support\GroupMessageParser;
use App\Support\MpMessageParser;
use App\Support\OperatorTacticalIdentity;
use App\Support\MedicalAlertParser;
use App\Support\TacticalAlertParser;
use App\Support\SteamId;
use App\Support\HttpJsonBody;
use App\Support\TerrainUploadedImage;
use App\Support\AtakDeviceLog;
use App\Repositories\AtakDeviceLogRepository;
use App\Repositories\AtakRealismRepository;
use App\Services\Tactical\MissionDisplaySettingsService;
use App\Repositories\OperatorGameProfileRepository;
use App\Services\OperatorGame\OperatorGameObservationNormalizer;
use App\Services\OperatorGame\OperatorGameReconciliationService;

class AtakApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** @var array<string, mixed>|null Cache php://input (une seule lecture par requête). */
    private ?array $jsonBodyCache = null;

    private AtakArmaWriteGuard $armaGuard;
    private RoleplaySimulationService $roleplaySim;
    private AtakIntelViewService $intelView;
    private ?AtakUnitMotionService $unitMotion = null;

    public function __construct(
        private AtakDataRepository $atak,
        private CasNineLineRepository $casRepo,
        private MapShapeRepository $mapShapeRepo,
        private LaserCodeRepository $laserCodeRepo,
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private ArmaPlaytimeRepository $armaPlaytimeRepository,
        private ?ReconImageRepository $reconRepo = null,
        private ?TacticalBriefingSlideRepository $briefingSlideRepository = null,
        private ?TacticalBriefingSlideCommentRepository $briefingSlideCommentRepository = null,
        private ?TacticalPhonePairingRepository $phonePairingRepository = null,
        private ?AtakActivityLogService $activityLog = null,
        private ?TacticalGameLinkRepository $gameLinkRepository = null,
        private ?TenantAtakConfigRepository $tenantAtakConfigRepository = null,
        private ?\App\Repositories\UnitRepository $unitRepository = null,
        private ?AtakOrderRepository $orderRepository = null,
        private ?AtakOrderTemplateRepository $orderTemplateRepository = null,
        private ?AtakOrderTypeRepository $orderTypeRepository = null,
        private ?FireTeamRepository $fireTeamRepository = null,
        private ?AtakOperatorIdRepository $operatorIdRepository = null,
        private ?AtakMedicalTriageRepository $medicalTriageRepository = null,
        private ?AtakBetaRegistrationRepository $betaRegistrationRepository = null,
        private ?\App\Repositories\AtakModReportRepository $modReportRepository = null,
        ?AtakArmaWriteGuard $armaGuard = null,
        ?RoleplaySimulationService $roleplaySim = null,
        ?AtakIntelViewService $intelView = null,
    ) {
        $this->briefingSlideRepository ??= new TacticalBriefingSlideRepository();
        $this->briefingSlideCommentRepository ??= new TacticalBriefingSlideCommentRepository();
        $this->phonePairingRepository ??= new TacticalPhonePairingRepository();
        $this->activityLog ??= new AtakActivityLogService();
        $this->gameLinkRepository ??= new TacticalGameLinkRepository();
        $this->tenantAtakConfigRepository ??= new TenantAtakConfigRepository();
        $this->unitRepository ??= new \App\Repositories\UnitRepository();
        $this->orderRepository ??= new AtakOrderRepository();
        $this->orderTemplateRepository ??= new AtakOrderTemplateRepository();
        $this->orderTypeRepository ??= new AtakOrderTypeRepository();
        $this->fireTeamRepository ??= new FireTeamRepository();
        $this->betaRegistrationRepository ??= new AtakBetaRegistrationRepository();
        // reconRepo / modReportRepository : lazy (évite PDO au boot de toutes les routes ATAK).
        $this->operatorIdRepository ??= new AtakOperatorIdRepository();
        $this->medicalTriageRepository ??= new AtakMedicalTriageRepository();
        $this->armaGuard = $armaGuard ?? new AtakArmaWriteGuard($this->userRepository, $this->activityLog);
        $this->roleplaySim = $roleplaySim ?? new RoleplaySimulationService($this->tenantAtakConfigRepository);
        $this->intelView = $intelView ?? new AtakIntelViewService(null, $this->tenantAtakConfigRepository, $this->atak);
    }

    private function modReports(): \App\Repositories\AtakModReportRepository
    {
        return $this->modReportRepository ??= new \App\Repositories\AtakModReportRepository();
    }

    private function reconImages(): ReconImageRepository
    {
        return $this->reconRepo ??= new ReconImageRepository();
    }

    private ?AtakDeviceLogRepository $deviceLogRepository = null;
    private ?AtakRealismRepository $realismRegistry = null;
    private ?OperatorGameProfileRepository $operatorGameProfiles = null;

    /** Register/sync is deliberately separate from the high-frequency position channel. */
    public function operatorRegister(Request $request, array $params = []): Response
    {
        return $this->syncObservedOperator($request, 'REGISTER');
    }

    public function operatorSync(Request $request, array $params = []): Response
    {
        return $this->syncObservedOperator($request, 'SYNC');
    }

    private function syncObservedOperator(Request $request, string $reason): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $normalizer = new OperatorGameObservationNormalizer();
        $payload = $normalizer->normalize($this->jsonBody($request));
        $steamId = SteamId::normalize((string) ($payload['steam_id'] ?? $payload['steam_uid'] ?? $payload['player_uid'] ?? ''));
        if ($steamId === null) {
            return Response::json(['status' => 'error', 'error' => 'valid_steam_id_required'], 422);
        }
        $repo = $this->operatorGameProfiles ??= new OperatorGameProfileRepository();
        $reference = $repo->referenceForSteam($tenantId, $steamId);
        $linked = $reference !== null;
        if (!$linked) {
            $reference = [];
        }
        $profile = $repo->upsertProfile($tenantId, $reference, $steamId, $payload);
        if (!$linked) {
            if ($profile['first_seen']) {
                $repo->event($tenantId, $profile['id'], $steamId, 'STEAM_ACCOUNT_NOT_FOUND', [
                    'server' => $payload['server_name'] ?? null,
                ]);
            }
            if ($profile['changed'] || $profile['first_seen']) {
                $repo->snapshot($tenantId, $profile['id'], $profile['first_seen'] ? 'FIRST_SEEN' : $reason, $payload);
            }
            $repo->event($tenantId, $profile['id'], $steamId, $profile['first_seen'] ? 'FIRST_SEEN' : 'PROFILE_SYNC', [
                'linked' => false,
            ]);
            return Response::json([
                'status' => 'ok',
                'operator_linked' => false,
                'profile_id' => $profile['id'],
                'discrepancies' => 0,
                'update_required' => false,
                'sync_status' => 'NOT_LINKED',
                'event' => 'UNLINKED_ARMA_OPERATOR',
            ]);
        }
        $observed = $normalizer->observedForReconcile($payload, $steamId);
        $discrepancies = (new OperatorGameReconciliationService())->reconcile($reference, $observed, $repo->versionPolicies($tenantId));
        $snapshotId = null;
        if ($profile['changed'] || $discrepancies !== []) {
            $snapshotId = $repo->snapshot($tenantId, $profile['id'], $profile['first_seen'] ? 'FIRST_SEEN' : $reason, $payload);
        }
        foreach ($discrepancies as $discrepancy) {
            $repo->recordDiscrepancy($tenantId, (int) $reference['user_id'], $profile['id'], $snapshotId, $discrepancy);
        }
        $repo->event($tenantId, $profile['id'], $steamId, $profile['first_seen'] ? 'FIRST_SEEN' : 'PROFILE_SYNC', ['discrepancies' => count($discrepancies)]);
        $updateRequired = false;
        foreach ($discrepancies as $discrepancy) {
            if (($discrepancy['category'] ?? '') === 'SOFTWARE' && in_array($discrepancy['severity'] ?? '', ['ERROR', 'CRITICAL'], true)) {
                $updateRequired = true;
                break;
            }
        }
        return Response::json([
            'status' => 'ok', 'operator_linked' => true, 'profile_id' => $profile['id'],
            'discrepancies' => count($discrepancies), 'update_required' => $updateRequired,
            'sync_status' => $discrepancies === [] ? 'SYNC_OK' : ($updateRequired ? 'CLIENT_OUTDATED' : 'SYNC_WARNING'),
        ]);
    }

    private function deviceLogs(): AtakDeviceLogRepository
    {
        return $this->deviceLogRepository ??= new AtakDeviceLogRepository();
    }

    private function realismRegistry(): AtakRealismRepository
    {
        return $this->realismRegistry ??= new AtakRealismRepository();
    }

    private ?AtakExplosiveTimerRepository $explosiveTimerRepository = null;

    private function explosiveTimers(): AtakExplosiveTimerRepository
    {
        return $this->explosiveTimerRepository ??= new AtakExplosiveTimerRepository();
    }

    /**
     * Diapositives de briefing actives (image + titre + détail + ordre), consommées par l’extension Arma
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
                'detail' => trim((string) ($row['detail_text'] ?? '')),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'image_url' => url($imagePath),
                'updated_at' => (string) ($row['updated_at'] ?? $row['created_at'] ?? ''),
            ];
        }

        return Response::json([
            'slides' => $out,
            'google_slides_url' => $this->resolveTenantGoogleSlidesUrl($tenantId),
        ]);
    }

    /**
     * Lien Google Slides public publié pour la communauté (tenants.settings.briefing.google_slides_url).
     */
    private function resolveTenantGoogleSlidesUrl(int $tenantId): string
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $briefing = $settings['briefing'] ?? null;
        if (!is_array($briefing)) {
            return '';
        }
        $url = trim((string) ($briefing['google_slides_url'] ?? ''));
        if ($url === '' || !self::isValidGoogleSlidesUrl($url)) {
            return '';
        }

        return $url;
    }

    public static function isValidGoogleSlidesUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > 512) {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host !== 'docs.google.com') {
            return false;
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

        return (bool) preg_match('#^/presentation/d/(?:e/)?[a-zA-Z0-9_-]+#', $path);
    }

    /**
     * Présence des clients ATAK / téléphone pendant le briefing.
     * GET : liste. POST : battement de cœur (téléphone via token de liaison, ou clé Arma).
     */
    public function briefingPresence(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveBriefingTenant($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            $body = $this->jsonBody($request);
            $label = trim((string) ($body['label'] ?? $request->input('label', '')));
            $clientKey = trim((string) ($body['client_key'] ?? $request->input('client_key', '')));
            $source = trim((string) ($body['source'] ?? $request->input('source', 'phone')));
            if ($clientKey === '') {
                $clientKey = $this->activityLog->clientKeyFromRequest();
            }
            if ($clientKey === '') {
                $clientKey = 'anon-' . bin2hex(random_bytes(8));
            }
            if ($label === '') {
                $label = $source === 'arma' ? 'Tableau Arma' : 'Téléphone';
            }
            $this->activityLog->heartbeatBriefingPresence($tenantId, $clientKey, $label, $source !== '' ? $source : 'phone');
        }

        $viewers = $this->activityLog->listBriefingPresence($tenantId);
        $out = [];
        $phoneCount = 0;
        $armaCount = 0;
        foreach ($viewers as $v) {
            $src = (string) ($v['source'] ?? 'phone');
            if ($src === 'arma') {
                $armaCount++;
            } elseif ($src === 'phone') {
                $phoneCount++;
            }
            $out[] = [
                'label' => $v['label'],
                'source' => $src,
                'last_seen_at' => $v['last_seen_at'] ?? null,
            ];
        }

        return Response::json([
            'viewers' => $out,
            'count' => count($out),
            'phone_count' => $phoneCount,
            'arma_count' => $armaCount,
        ]);
    }

    /**
     * Liste / ajout de commentaires sur une diapositive (token téléphone ou session admin / clé Arma).
     */
    public function briefingSlideComments(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveBriefingTenant($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $slideId = (int) ($params['id'] ?? 0);
        if ($slideId < 1) {
            return Response::json(['error' => 'not_found', 'message' => 'Diapositive introuvable.'], 404);
        }
        $slide = $this->briefingSlideRepository->findByIdForTenant($slideId, $tenantId);
        if ($slide === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Diapositive introuvable.'], 404);
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            if (!$this->briefingSlideCommentRepository->isReady()) {
                return Response::json([
                    'error' => 'unavailable',
                    'message' => 'Les commentaires ne sont pas encore disponibles. Réessayez après mise à jour de la plateforme.',
                ], 503);
            }
            $body = $this->jsonBody($request);
            $text = trim((string) ($body['body'] ?? $request->input('body', '')));
            $author = trim((string) ($body['author_label'] ?? $request->input('author_label', '')));
            $source = trim((string) ($body['source'] ?? $request->input('source', 'phone')));
            if ($text === '') {
                return Response::json(['error' => 'validation', 'message' => 'Saisissez un commentaire avant d’envoyer.'], 422);
            }
            if ($author === '') {
                $author = 'Opérateur';
            }
            $id = $this->briefingSlideCommentRepository->insert($tenantId, $slideId, $author, $text, $source);
            if ($id === null) {
                return Response::json(['error' => 'unavailable', 'message' => 'Impossible d’enregistrer le commentaire pour le moment.'], 503);
            }
        }

        $comments = $this->briefingSlideCommentRepository->listForSlide($tenantId, $slideId);
        $out = [];
        foreach ($comments as $row) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'author' => trim((string) ($row['author_label'] ?? 'Opérateur')),
                'body' => (string) ($row['body'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return Response::json(['comments' => $out, 'count' => count($out)]);
    }

    /**
     * Tenant pour les endpoints briefing publics (clé Arma, session, ou token de liaison téléphone).
     *
     * @return int|Response
     */
    private function resolveBriefingTenant(Request $request): int|Response
    {
        $token = trim((string) ($request->query('token') ?? ''));
        if ($token === '') {
            $body = $this->jsonBody($request);
            $token = trim((string) ($body['token'] ?? $request->input('token', '')));
        }
        if ($token !== '') {
            $pairing = $this->phonePairingRepository->findBriefingSessionByToken($token);
            if ($pairing === null) {
                return Response::json([
                    'error' => 'expired',
                    'message' => 'Cette liaison téléphone a expiré. Générez un nouveau code depuis la tablette en jeu.',
                ], 401);
            }
            $tenantId = (int) ($pairing['tenant_id'] ?? 0);
            if ($tenantId < 1) {
                return Response::json(['error' => 'tenant_context_required', 'message' => 'Communauté non identifiée.'], 403);
            }

            return $tenantId;
        }

        return $this->requireTenant($request);
    }

    /**
     * Profil public d'un joueur (nom d'affichage, callsign, photo) identifié par son SteamUID,
     * consommé par l'extension Arma (fonction native GetPlayerAvatarInfo) pour affichage en jeu.
     * Ne renvoie jamais l'état civil (prénom/nom légal) — uniquement l'identité de jeu.
     */
    public function playerProfile(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $steamUid = SteamId::normalize((string) ($request->query('steam_uid') ?? ''));
        if ($steamUid === null) {
            return Response::json(['error' => 'invalid_steam_uid'], 400);
        }
        $user = $this->userRepository->findBySteamIdForTenant($tenantId, $steamUid);
        if (!$user) {
            return Response::json(['error' => 'not_found'], 404);
        }
        $userId = (int) ($user['id'] ?? 0);

        $unitName = '';
        $unitIds = $this->unitRepository->unitIdsForUser($tenantId, $userId);
        $primaryUnitId = (int) ($unitIds[0] ?? 0);
        if ($primaryUnitId > 0) {
            $unit = $this->unitRepository->findById($primaryUnitId, $tenantId);
            if ($unit) {
                $unitName = trim((string) ($unit['name'] ?? ''));
            }
        }

        $callsign = trim((string) ($user['callsign'] ?? ''));
        $personnelCs = '';
        try {
            $personnel = (new \App\Repositories\PersonnelProfileRepository())->getByUserId($userId) ?? [];
            $personnelCs = trim((string) ($personnel['callsign'] ?? ''));
            $profileUnitId = (int) ($personnel['primary_unit_id'] ?? 0);
            if ($unitName === '' && $profileUnitId > 0) {
                $fromProfile = $this->unitRepository->findById($profileUnitId, $tenantId);
                if ($fromProfile) {
                    $unitName = trim((string) ($fromProfile['name'] ?? ''));
                }
            }
        } catch (\Throwable) {
        }
        $tenantName = '';
        try {
            $tenantRow = $this->tenantRepository->findById($tenantId);
            if (is_array($tenantRow)) {
                $tenantName = function_exists('community_display_name')
                    ? community_display_name($tenantRow)
                    : trim((string) ($tenantRow['name'] ?? ''));
            }
        } catch (\Throwable) {
        }
        $callsign = OperatorTacticalIdentity::callsign([$personnelCs, $callsign], $tenantName, $tenantName);
        $unitName = OperatorTacticalIdentity::unitAssignment($unitName, $tenantName, $tenantName);
        // Identifiant ATAK stable : le callsign s'il est renseigné, sinon un identifiant technique
        // dérivé du compte — jamais l'état civil, jamais réutilisable pour retrouver un autre joueur.
        $atakId = $callsign !== '' ? $callsign : sprintf('U-%05d', $userId);
        $opIds = $this->operatorIdRepository ?? new AtakOperatorIdRepository();
        $militaryId = $opIds->tablesReady()
            ? $opIds->ensureForUser($tenantId, $userId, $callsign !== '' ? $callsign : null)
            : '';

        // Activité réelle (temps de jeu Arma cumulé + dernier rapport) : absente si la table n'est
        // pas encore migrée ou si le joueur n'a jamais été rapporté — jamais de valeur inventée.
        $playtimeHours = null;
        $lastSeenAt = null;
        $summary = $this->armaPlaytimeRepository->getSummaryForUser($tenantId, $userId);
        if ($summary !== null) {
            $totalSeconds = (int) ($summary['total_seconds'] ?? 0);
            $playtimeHours = round($totalSeconds / 3600, 1);
            // Formaté ici (pas côté SQF, qui n'a pas de parseur de date fiable).
            $rawLastSeen = (string) ($summary['last_report_at'] ?? '');
            if ($rawLastSeen !== '') {
                $ts = strtotime($rawLastSeen);
                $lastSeenAt = $ts !== false ? date('d/m H:i', $ts) : null;
            }
        }

        return Response::json([
            'ok' => true,
            'display_name' => (string) ($user['display_name'] ?? ''),
            'callsign' => $callsign,
            'avatar_url' => (string) ($user['avatar_url'] ?? ''),
            'unit_name' => $unitName,
            'atak_id' => $atakId,
            'military_id' => $militaryId,
            'playtime_hours' => $playtimeHours,
            'last_seen_at' => $lastSeenAt,
        ]);
    }

    /**
     * Statut connexion téléphone pour le dashboard web (et le mod) :
     * indique si un téléphone a déjà scanné / lié, et l’état d’un code en cours.
     * Auth : session membre ou clé ATAK (même règle que phonePairingCreate).
     */
    public function phonePairingStatus(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;

        if (!$this->phonePairingRepository->isReady()) {
            return Response::json([
                'ready' => false,
                'linked' => false,
                'error' => 'phone_pairing_schema_missing',
                'message' => 'La connexion téléphone n’est pas encore activée sur ce serveur. Contactez un administrateur Athena.',
            ], 503);
        }

        $last = $this->phonePairingRepository->latestPairedForTenant($tenantId);
        $linked = $last !== null;
        $payload = [
            'ready' => true,
            'linked' => $linked,
            'last_linked_at' => $linked ? (string) ($last['paired_at'] ?? '') : null,
            'message' => $linked
                ? 'Un téléphone a déjà été lié. Vous pouvez générer un nouveau code pour un autre appareil.'
                : 'Aucun téléphone lié pour le moment. Générez un code pour commencer.',
        ];

        $token = trim((string) ($request->query('token') ?? ''));
        if ($token !== '') {
            $row = $this->phonePairingRepository->findByTokenForTenant($token, $tenantId);
            if ($row === null) {
                $payload['current'] = ['found' => false, 'paired' => false, 'expired' => true];
            } else {
                $expiresAt = (string) ($row['expires_at'] ?? '');
                $expired = true;
                if ($expiresAt !== '') {
                    try {
                        $exp = new \DateTimeImmutable($expiresAt, new \DateTimeZone('UTC'));
                        $expired = $exp < new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                    } catch (\Throwable) {
                        $ts = strtotime($expiresAt);
                        $expired = $ts === false || $ts < time();
                    }
                }
                $payload['current'] = [
                    'found' => true,
                    'paired' => !empty($row['paired_at']),
                    'paired_at' => !empty($row['paired_at']) ? (string) $row['paired_at'] : null,
                    'expires_at' => $expiresAt,
                    'expired' => $expired,
                    'code' => (string) ($row['code'] ?? ''),
                ];
            }
        }

        return Response::json($payload);
    }

    /**
     * Connexion téléphone (inspiré de cTab) : génère un jeton (QR) + un code court lisible,
     * consommés par l'extension Arma (GetPhoneConnectInfo) pour affichage en jeu, puis par
     * un navigateur mobile sans compte sur /connect (saisie) ou /connect/{jeton} (QR).
     * Appelable aussi depuis le dashboard web (session) après une liaison réussie
     * pour re-lier un autre appareil.
     */
    public function phonePairingCreate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            // Journal : clé présentée OK mais communauté manquante ≠ schéma absent.
            $guess = (int) (getenv('ATAK_DEFAULT_TENANT_ID') ?: getenv('APP_ATAK_DEFAULT_TENANT_ID') ?: 0);
            if ($guess > 0) {
                $this->activityLog?->recordAuthAttempt($guess, false, 'Connexion téléphone refusée — communauté non identifiée', [
                    'reason' => 'tenant_context_required',
                    'method' => 'phone',
                ]);
            }

            return Response::json([
                'error' => 'tenant_context_required',
                'message' => 'Communauté non identifiée. Reliez le compte Athena en jeu (code de liaison), ou demandez à un admin de vérifier la clé d’accès.',
            ], 403);
        }
        $tenantId = $r;
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            $actor = $this->guardArmaWrite($request, $tenantId, false);
            if ($actor instanceof Response) {
                return $actor;
            }
        }

        if (!$this->phonePairingRepository->isReady()) {
            $this->activityLog?->recordAuthAttempt($tenantId, false, 'Connexion téléphone indisponible — pas encore activée sur le serveur', [
                'reason' => 'phone_pairing_schema_missing',
                'method' => 'phone',
            ]);

            return Response::json([
                'error' => 'phone_pairing_schema_missing',
                'message' => 'La connexion téléphone n’est pas encore activée sur ce serveur. Contactez un administrateur Athena.',
            ], 503);
        }

        $pairing = $this->phonePairingRepository->create($tenantId);
        if ($pairing === null) {
            $this->activityLog?->recordAuthAttempt($tenantId, false, 'Connexion téléphone indisponible — génération du code impossible', [
                'reason' => 'phone_pairing_create_failed',
                'method' => 'phone',
            ]);

            return Response::json([
                'error' => 'phone_pairing_unavailable',
                'message' => 'Impossible de préparer la connexion téléphone pour le moment. Réessayez dans un instant.',
            ], 503);
        }
        $token = $pairing['token'];
        // Pas de journal Activité ici : préparer un QR n’est pas une connexion réelle
        // (sinon spam « Accès » à chaque régénération / poll tablette).

        // Adresse courte affichée en jeu / sur le TOC ; le QR encode le lien direct (jeton).
        $entryUrl = $this->phoneConnectPublicUrl('connect');
        $destination = strtolower(trim((string) $request->query('destination', '')));
        $destinationPaths = ['chat' => 'tchat', 'orders' => 'ordres', 'explosives' => 'explosifs', 'c2' => 'c2', 'sitac' => 'sitac'];
        $destinationPath = $destinationPaths[$destination] ?? '';
        $pairPath = 'connect/' . $token . ($destinationPath !== '' ? '/' . $destinationPath : '');
        $pairUrl = $this->phoneConnectPublicUrl($pairPath);
        $qrImageUrl = url('api/atak/phone-pairing/' . $token . '/qr.png')
            . ($destinationPath !== '' ? '?destination=' . rawurlencode($destination) : '');

        // Data-URI inline : le dashboard ATAK affiche le QR sans second GET
        // (évite img cassée si l’endpoint PNG est bloqué, en 503, ou mal servi).
        $qrDataUri = $this->phonePairingQrDataUri($pairUrl);

        return Response::json([
            'token' => $token,
            'code' => $pairing['code'],
            // Adresse mobile courte (saisie du code) — affichée sur l’écran téléphone en jeu.
            'connect_url' => $entryUrl,
            // Lien direct (QR / « Ouvrir la page » côté TOC).
            'pair_url' => $pairUrl,
            'qr_image_url' => $qrImageUrl,
            'qr_image_data_uri' => $qrDataUri,
            'expires_at' => $pairing['expires_at'],
        ]);
    }

    /**
     * URL publique téléphone : préfère /connect sans segment /public quand l’hébergeur
     * réécrit la racine (voir .htaccess à la racine du domaine).
     */
    private function phoneConnectPublicUrl(string $path): string
    {
        $full = url($path);
        if (preg_match('#^(https?://[^/]+)/public/(connect(?:/.*)?)$#i', $full, $m)) {
            return $m[1] . '/' . $m[2];
        }

        return $full;
    }

    /**
     * Échange un code de liaison (généré sur le portail) contre les paramètres de connexion du mod.
     * Chemin exempt de clé ATAK : le code à usage unique fait office de secret (TTL court).
     */
    public function gameLinkRedeem(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $code = strtoupper(trim((string) ($body['code'] ?? $request->input('code') ?? '')));
        if ($code === '' || strlen($code) < 4) {
            return Response::json(['error' => 'invalid_code', 'message' => 'Code de liaison manquant ou trop court.'], 400);
        }
        $row = $this->gameLinkRepository->findValidByCode($code);
        if ($row === null) {
            $reason = $this->gameLinkRepository->explainInvalidCode($code);
            $failTenant = $this->resolveTenantId($request) ?? (int) (getenv('ATAK_DEFAULT_TENANT_ID') ?: 0);
            $failLabel = match ($reason) {
                'already_used' => 'Liaison en jeu refusée — code déjà utilisé',
                'expired' => 'Liaison en jeu refusée — code expiré',
                default => 'Liaison en jeu refusée — code inconnu',
            };
            if ($failTenant > 0) {
                $this->activityLog?->recordAuthAttempt($failTenant, false, $failLabel, [
                    'reason' => $reason !== '' ? $reason : 'unknown_code',
                    'method' => 'code',
                ]);
            }
            if ($reason === 'already_used') {
                return Response::json([
                    'error' => 'code_already_used',
                    'message' => 'Ce code a déjà été utilisé. Générez-en un nouveau depuis Athena.',
                ], 404);
            }
            if ($reason === 'expired') {
                return Response::json([
                    'error' => 'code_expired',
                    'message' => 'Ce code a expiré. Générez-en un nouveau depuis Athena (valable 30 minutes).',
                ], 404);
            }

            return Response::json([
                'error' => 'code_invalid_or_expired',
                'message' => 'Ce code est inconnu. Générez-en un nouveau depuis Athena, puis saisissez-le immédiatement en jeu.',
            ], 404);
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $userId = (int) ($row['user_id'] ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['error' => 'code_invalid_or_expired'], 404);
        }

        $steamUid = SteamId::normalize((string) ($body['steam_uid'] ?? $body['player_uid'] ?? $body['steam_id'] ?? ''));
        $modBlock = $this->armaGuard->assertModNotBlocked($tenantId, $steamUid);
        if ($modBlock instanceof Response) {
            return $modBlock;
        }
        if ($steamUid !== null) {
            $user = $this->userRepository->findById($userId, $tenantId);
            if (is_array($user)) {
                $existing = SteamId::normalize((string) ($user['steam_id'] ?? ''));
                // Toujours aligner sur le SteamUID détecté en jeu lors d'une liaison réussie —
                // le code de liaison à usage unique est une preuve plus forte que tout ce qui a
                // pu être saisi manuellement sur /account (source du "aucune remontée compte"
                // quand l'ancienne valeur ne correspondait plus au joueur réel).
                if ($existing !== $steamUid) {
                    $this->userRepository->update($userId, $tenantId, ['steam_id' => $steamUid]);
                }
            }
        }

        $this->gameLinkRepository->markRedeemed((int) $row['id'], $steamUid);

        $config = $this->tenantAtakConfigRepository->getByTenantId($tenantId);
        $apiUrl = atak_client_base_url($config);
        $apiKey = ComspecApiKeyAuth::secretForTenant($tenantId);
        if ($apiKey === '') {
            $this->activityLog?->recordAuthAttempt($tenantId, false, 'Liaison en jeu refusée — clé d’accès non configurée', [
                'reason' => 'api_key_not_configured',
                'method' => 'code',
            ]);

            return Response::json([
                'error' => 'api_key_not_configured',
                'message' => 'La liaison jeu n’est pas encore configurée sur le portail. Un administrateur doit générer une clé d’accès dans la configuration ATAK.',
            ], 503);
        }

        $actor = null;
        $userForActor = $this->userRepository->findById($userId, $tenantId);
        if (is_array($userForActor)) {
            $cs = trim((string) ($userForActor['callsign'] ?? ''));
            $dn = trim((string) ($userForActor['display_name'] ?? ''));
            $actor = $cs !== '' ? $cs : ($dn !== '' ? $dn : null);
        }
        $this->activityLog?->recordAuthAttempt($tenantId, true, 'Liaison en jeu réussie — code accepté', [
            'reason' => 'ok',
            'method' => 'code',
        ], $actor);

        return Response::json([
            'ok' => true,
            'api_url' => $apiUrl,
            'api_key' => $apiKey,
            'tenant_id' => (string) $tenantId,
        ]);
    }

    /**
     * Liaison Arma sans code court : le Steam ID du joueur doit déjà être enregistré sur le compte Athena.
     * POST JSON { steam_uid } — même forme de réponse que gameLinkRedeem.
     */
    public function gameLinkBySteam(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $steamUid = SteamId::normalize((string) ($body['steam_uid'] ?? $body['player_uid'] ?? $body['steam_id'] ?? $request->input('steam_uid') ?? ''));
        if ($steamUid === null) {
            return Response::json([
                'error' => 'invalid_steam_uid',
                'message' => 'Identifiant Steam manquant ou non reconnu. Utilisez le numéro affiché en jeu, ou le format classique Steam.',
            ], 400);
        }

        $user = $this->userRepository->findBySteamId($steamUid);
        if ($user === null) {
            $failTenant = $this->resolveTenantId($request) ?? (int) (getenv('ATAK_DEFAULT_TENANT_ID') ?: 0);
            if ($failTenant > 0) {
                $this->activityLog?->recordAuthAttempt($failTenant, false, 'Liaison en jeu refusée — Steam non reconnu', [
                    'reason' => 'steam_not_linked',
                    'method' => 'steam',
                ]);
            }

            return Response::json([
                'error' => 'steam_not_linked',
                'message' => 'Aucun compte Athena n’est lié à ce Steam. Liez Steam dans votre profil, ou utilisez un code de connexion en jeu.',
            ], 404);
        }

        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            return Response::json(['error' => 'steam_not_linked'], 404);
        }

        $status = strtolower(trim((string) ($user['status'] ?? 'active')));
        if (in_array($status, ['banned', 'disabled', 'suspended', 'deleted'], true)) {
            $this->activityLog?->recordAuthAttempt($tenantId, false, 'Liaison en jeu refusée — compte non autorisé', [
                'reason' => 'account_disabled',
                'method' => 'steam',
            ]);

            return Response::json([
                'error' => 'account_disabled',
                'message' => 'Ce compte Athena n’est pas autorisé à se lier.',
            ], 403);
        }

        $modBlock = $this->armaGuard->assertModNotBlocked($tenantId, $steamUid);
        if ($modBlock instanceof Response) {
            return $modBlock;
        }

        $config = $this->tenantAtakConfigRepository->getByTenantId($tenantId);
        $apiUrl = atak_client_base_url($config);
        $apiKey = ComspecApiKeyAuth::secretForTenant($tenantId);
        if ($apiKey === '') {
            $this->activityLog?->recordAuthAttempt($tenantId, false, 'Liaison en jeu refusée — clé d’accès non configurée', [
                'reason' => 'api_key_not_configured',
                'method' => 'steam',
            ]);

            return Response::json([
                'error' => 'api_key_not_configured',
                'message' => 'La liaison jeu n’est pas encore configurée sur le portail. Un administrateur doit générer une clé d’accès dans la configuration ATAK.',
            ], 503);
        }

        $cs = trim((string) ($user['callsign'] ?? ''));
        $dn = trim((string) ($user['display_name'] ?? ''));
        $actor = $cs !== '' ? $cs : ($dn !== '' ? $dn : null);
        $this->activityLog?->recordAuthAttempt($tenantId, true, 'Liaison en jeu réussie — Steam reconnu', [
            'reason' => 'ok',
            'method' => 'steam',
        ], $actor);

        return Response::json([
            'ok' => true,
            'api_url' => $apiUrl,
            'api_key' => $apiKey,
            'tenant_id' => (string) $tenantId,
            'display_name' => (string) ($user['display_name'] ?? ''),
            'callsign' => (string) ($user['callsign'] ?? ''),
            'member_number' => trim((string) ($user['tenant_member_number'] ?? '')) ?: null,
            'platform_number' => trim((string) ($user['athena_identifier'] ?? '')) ?: null,
        ]);
    }

    /** PNG du QR code encodant l’URL de connexion — consommé par le même téléchargeur d’image que les diapositives. */
    public function phonePairingQrImage(Request $request, array $params = []): Response
    {
        $token = trim((string) ($params['token'] ?? ''));
        $pairing = $this->phonePairingRepository->findValidByToken($token);
        if ($pairing === null) {
            return (new Response())
                ->setStatusCode(404)
                ->header('Content-Type', 'text/plain; charset=UTF-8')
                ->header('Cache-Control', 'no-store')
                ->setBody('Not found');
        }
        $destination = strtolower(trim((string) $request->query('destination', '')));
        $destinationPaths = ['chat' => 'tchat', 'orders' => 'ordres', 'explosives' => 'explosifs', 'c2' => 'c2', 'sitac' => 'sitac'];
        $destinationPath = $destinationPaths[$destination] ?? '';
        $pairUrl = $this->phoneConnectPublicUrl(
            'connect/' . $token . ($destinationPath !== '' ? '/' . $destinationPath : '')
        );
        $generator = new QrPngGenerator();
        // pngOnly : Arma RscPicture n’affiche pas de SVG — forcer un PNG binaire (Endroid, GD ou zlib).
        $png = $generator->png($pairUrl, 512, 16, true);
        if ($png === null || strncmp($png['body'], "\x89PNG", 4) !== 0) {
            // Le code court reste affiché côté Tacmap / jeu ; détail technique en log seulement.
            error_log('[atak_phone_pairing_qr] unavailable token=' . substr($token, 0, 8)
                . ' attempts=' . implode('|', $generator->attempts()));

            return (new Response())
                ->setStatusCode(503)
                ->header('Content-Type', 'text/plain; charset=UTF-8')
                ->header('Cache-Control', 'no-store')
                ->setBody('QR unavailable');
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody($png['body']);
    }

    /** Data-URI PNG pour affichage immédiat dans le navigateur (null si génération impossible). */
    private function phonePairingQrDataUri(string $connectUrl): ?string
    {
        try {
            $generator = new QrPngGenerator();
            $png = $generator->png($connectUrl, 512, 16, true);
            if ($png === null || strncmp($png['body'], "\x89PNG", 4) !== 0) {
                error_log('[atak_phone_pairing_qr] create_inline_unavailable attempts='
                    . implode('|', $generator->attempts()));

                return null;
            }

            return 'data:image/png;base64,' . base64_encode($png['body']);
        } catch (\Throwable $e) {
            error_log('[atak_phone_pairing_qr] create_inline_exception ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Résout le tenant pour l’API ATAK : clé de communauté (access_key), session, query/body,
     * tenant_slug, puis env explicite. Ne retombe plus silencieusement sur le tenant 1.
     */
    private function resolveTenantId(Request $request): ?int
    {
        // Clé Overwatch générée en admin : la communauté est déjà connue via l’auth.
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
                'message' => 'Communauté non identifiée. Reliez le compte Athena en jeu, ou utilisez la clé d’accès fournie par votre administrateur.',
            ], 403);
        }

        try {
            $maintenanceOn = $this->tenantAtakConfigRepository->isMaintenanceEnabled($id);
        } catch (\Throwable) {
            $maintenanceOn = false;
        }
        if ($maintenanceOn && !$this->canBypassAtakMaintenance()) {
            try {
                $message = $this->tenantAtakConfigRepository->getMaintenanceMessage($id);
            } catch (\Throwable) {
                $message = '';
            }
            if ($message === '') {
                $message = 'L’accès à la carte est suspendu pour le moment. Réessayez plus tard.';
            }

            return Response::json([
                'error' => 'maintenance',
                'message' => $message,
            ], 503);
        }

        return $id;
    }

    private function canBypassAtakMaintenance(): bool
    {
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($userId < 1) {
            return false;
        }

        return function_exists('can') && can('admin.access');
    }

    private function motionService(): AtakUnitMotionService
    {
        return $this->unitMotion ??= new AtakUnitMotionService();
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

    private function jsonBody(Request $request): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }
        if (HttpJsonBody::isMultipart()) {
            $this->jsonBodyCache = HttpJsonBody::postFields();

            return $this->jsonBodyCache;
        }
        $raw = HttpJsonBody::rawJson();
        if ($raw === '') {
            $this->jsonBodyCache = [];

            return $this->jsonBodyCache;
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
            if (is_string($converted) && $converted !== '') {
                $raw = $converted;
            }
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            // SQF `str` / locale FR : 19345,12 casse json_decode (objet vide → rapport OTHER).
            $commaFixed = preg_replace('/(?<=[:\[\s])(-?\d+),(\d{1,6})(?=[,}\]\s])/', '$1.$2', $raw);
            if (is_string($commaFixed) && $commaFixed !== $raw) {
                $decoded = json_decode($commaFixed, true);
            }
        }
        unset($raw);
        $this->jsonBodyCache = is_array($decoded) ? $decoded : [];

        return $this->jsonBodyCache;
    }

    /**
     * Métadonnées d’audit pour le journal Activité (compte Athena, Steam, version mod, grille…).
     *
     * @param array<string, mixed> $body
     * @param array{steam_uid?: ?string}|null $actor
     * @return array<string, mixed>
     */
    private function buildActivityMeta(
        int $tenantId,
        int $mapId,
        array $body,
        ?array $actor = null,
        ?string $callSign = null,
        ?array $extra = null
    ): array {
        $meta = [
            'tenant_id' => $tenantId,
            'map_id' => $mapId,
            'source' => 'arma',
        ];
        $cs = trim((string) ($callSign ?? $body['call_sign'] ?? $body['callsign'] ?? ''));
        if ($cs !== '') {
            $meta['call_sign'] = $cs;
        }
        $steam = null;
        if (is_array($actor) && !empty($actor['steam_uid'])) {
            $steam = SteamId::normalize((string) $actor['steam_uid']);
        }
        if ($steam === null || $steam === '') {
            $steamRaw = trim((string) ($body['steam_uid'] ?? $body['steamId'] ?? $body['player_uid'] ?? ''));
            if ($steamRaw !== '') {
                $steam = SteamId::normalize($steamRaw);
            }
        }
        if (is_array($extra) && ($steam === null || $steam === '')) {
            $steam = SteamId::normalize((string) ($extra['steam_uid'] ?? $extra['steamId'] ?? ''));
        }
        if (is_string($steam) && $steam !== '') {
            $meta['steam_uid'] = $steam;
            try {
                $user = $this->userRepository->findBySteamIdForTenant($tenantId, $steam)
                    ?? $this->userRepository->findBySteamId($steam);
                if (is_array($user)) {
                    $uid = (int) ($user['id'] ?? 0);
                    if ($uid > 0) {
                        $meta['user_id'] = $uid;
                    }
                    $dn = trim((string) ($user['display_name'] ?? ''));
                    if ($dn === '') {
                        $dn = trim(trim((string) ($user['first_name'] ?? '')) . ' ' . trim((string) ($user['last_name'] ?? '')));
                    }
                    if ($dn !== '') {
                        $meta['display_name'] = $dn;
                    }
                    $profileCs = trim((string) ($user['callsign'] ?? ''));
                    if ($profileCs !== '') {
                        $meta['profile_callsign'] = $profileCs;
                    }
                }
            } catch (\Throwable) {
            }
        }

        $modVersion = trim((string) ($body['mod_version'] ?? $body['modVersion'] ?? $body['overwatch_version'] ?? ''));
        if ($modVersion === '' && is_array($extra)) {
            $modVersion = trim((string) ($extra['mod_version'] ?? $extra['modVersion'] ?? $extra['overwatch_version'] ?? ''));
        }
        if ($modVersion !== '' && mb_strlen($modVersion) <= 48) {
            $meta['mod_version'] = $modVersion;
        }

        $role = trim((string) ($body['role'] ?? (is_array($extra) ? ($extra['role'] ?? '') : '')));
        if ($role !== '') {
            $meta['role'] = $role;
        }
        $group = trim((string) (
            $body['group_name'] ?? $body['groupName'] ?? $body['group']
            ?? (is_array($extra) ? ($extra['group_name'] ?? $extra['groupName'] ?? $extra['group'] ?? '') : '')
        ));
        if ($group !== '') {
            $meta['group_name'] = $group;
        }

        $posArr = (isset($body['pos']) && is_array($body['pos'])) ? $body['pos'] : null;
        $posX = AtakDataRepository::coerceFloat($body['pos_x'] ?? ($posArr[0] ?? null));
        $posY = AtakDataRepository::coerceFloat($body['pos_y'] ?? ($posArr[1] ?? null));
        if ($posX !== null && $posY !== null && AtakDataRepository::isValidMapPosition($posX, $posY)) {
            $meta['pos_x'] = round($posX, 2);
            $meta['pos_y'] = round($posY, 2);
            $meta['grid'] = (string) round($posX) . ' ' . round($posY);
        }
        $gridBody = trim((string) ($body['grid'] ?? $body['grid_ref'] ?? ''));
        if ($gridBody !== '' && !isset($meta['grid'])) {
            $meta['grid'] = $gridBody;
        }
        $asl = AtakDataRepository::coerceFloat(
            $body['asl_z'] ?? $body['pos_z'] ?? $body['altitude']
            ?? (is_array($extra) ? ($extra['asl_z'] ?? $extra['pos_z'] ?? null) : null)
        );
        if ($asl !== null) {
            $meta['asl_z'] = round($asl, 2);
        }
        $terrainZ = AtakDataRepository::coerceFloat(
            $body['terrain_z'] ?? (is_array($extra) ? ($extra['terrain_z'] ?? null) : null)
        );
        if ($terrainZ !== null) {
            $meta['terrain_z'] = round($terrainZ, 1);
        }
        if (isset($body['heading']) && is_numeric($body['heading'])) {
            $meta['heading'] = round((float) $body['heading'], 1);
        }
        $health = trim((string) ($body['health'] ?? (is_array($extra) ? ($extra['health'] ?? '') : '')));
        if ($health !== '') {
            $meta['health'] = $health;
        }
        $side = trim((string) (is_array($extra) ? ($extra['side'] ?? '') : ''));
        if ($side !== '') {
            $meta['side'] = $side;
        }
        $affiliation = trim((string) (is_array($extra) ? ($extra['affiliation'] ?? '') : ''));
        if ($affiliation !== '') {
            $meta['affiliation'] = $affiliation;
        }

        $flagKeys = ['has_ctab', 'has_atak_enhanced', 'has_athena_ctab', 'mod_athena'];
        foreach ($flagKeys as $fk) {
            $raw = $body[$fk] ?? (is_array($extra) ? ($extra[$fk] ?? null) : null);
            if ($raw === null) {
                continue;
            }
            if ($this->truthyFlag($raw)) {
                $meta[$fk] = true;
            } elseif ($raw === false || $raw === 0 || $raw === '0' || $raw === 'false') {
                $meta[$fk] = false;
            }
        }

        $pickStr = static function (array $sources, array $keys, int $maxLen = 96): string {
            foreach ($sources as $src) {
                if (!is_array($src)) {
                    continue;
                }
                foreach ($keys as $k) {
                    $v = trim((string) ($src[$k] ?? ''));
                    if ($v !== '' && mb_strlen($v) <= $maxLen) {
                        return $v;
                    }
                }
            }

            return '';
        };
        $sources = [$body];
        if (is_array($extra)) {
            $sources[] = $extra;
        }

        $terminalUid = $pickStr($sources, ['terminal_uid', 'terminalUid', 'terminal_id']);
        if ($terminalUid !== '') {
            $meta['terminal_uid'] = $terminalUid;
        }
        $certStatus = strtolower($pickStr($sources, ['cert_status', 'certificate_status', 'certStatus'], 32));
        if ($certStatus !== '') {
            $meta['cert_status'] = $certStatus;
        }
        $certRef = $pickStr($sources, ['certificate_ref', 'cert_ref', 'certificateRef'], 80);
        if ($certRef !== '') {
            $meta['certificate_ref'] = $certRef;
        }
        $radioFreq = $pickStr($sources, ['radio_freq', 'radio_frequency', 'freq', 'wr_freq'], 32);
        if ($radioFreq !== '') {
            $meta['radio_freq'] = $radioFreq;
        }
        $linkState = strtolower($pickStr($sources, ['link_state', 'linkState'], 24));
        if ($linkState !== '') {
            $meta['link_state'] = $linkState;
        }

        foreach (['latency_ms', 'packet_loss', 'packets_sent', 'packets_received'] as $numKey) {
            $rawNum = $body[$numKey] ?? (is_array($extra) ? ($extra[$numKey] ?? null) : null);
            if ($rawNum === null || $rawNum === '') {
                continue;
            }
            if (is_numeric($rawNum)) {
                $meta[$numKey] = str_contains($numKey, 'packet_loss')
                    ? round((float) $rawNum, 1)
                    : (int) round((float) $rawNum);
            }
        }

        // Compléter certificat / latence / fréquence depuis la dernière télémétrie BFT de l’unité.
        $this->mergeUnitTelemetryIntoActivityMeta($tenantId, $mapId, $meta);

        return $meta;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function mergeUnitTelemetryIntoActivityMeta(int $tenantId, int $mapId, array &$meta): void
    {
        $cs = trim((string) ($meta['call_sign'] ?? $meta['callsign'] ?? ''));
        if ($cs === '' || $mapId < 1) {
            return;
        }
        try {
            $unit = $this->atak->getUnitByCallSign($tenantId, $mapId, $cs);
        } catch (\Throwable) {
            return;
        }
        if (!is_array($unit)) {
            return;
        }
        $extraRaw = $unit['extra'] ?? null;
        $extra = is_array($extraRaw) ? $extraRaw : null;
        if ($extra === null && is_string($extraRaw) && $extraRaw !== '') {
            $decoded = json_decode($extraRaw, true);
            $extra = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($extra)) {
            $extra = [];
        }
        $vehicle = (isset($extra['vehicle']) && is_array($extra['vehicle'])) ? $extra['vehicle'] : [];
        $sources = [$extra, $vehicle, $unit];

        $fillStr = static function (array &$meta, string $key, array $sources, array $keys, int $maxLen = 80): void {
            if (isset($meta[$key]) && $meta[$key] !== '') {
                return;
            }
            foreach ($sources as $src) {
                if (!is_array($src)) {
                    continue;
                }
                foreach ($keys as $k) {
                    $v = trim((string) ($src[$k] ?? ''));
                    if ($v !== '' && mb_strlen($v) <= $maxLen) {
                        $meta[$key] = $v;

                        return;
                    }
                }
            }
        };
        $fillStr($meta, 'cert_status', $sources, ['cert_status', 'certificate_status', 'certStatus'], 32);
        $fillStr($meta, 'certificate_ref', $sources, ['certificate_ref', 'cert_ref', 'certificateRef'], 80);
        $fillStr($meta, 'radio_freq', $sources, ['radio_freq', 'radio_frequency', 'freq', 'wr_freq'], 32);
        $fillStr($meta, 'link_state', $sources, ['link_state', 'linkState'], 24);
        $fillStr($meta, 'terminal_uid', $sources, ['terminal_uid', 'terminalUid', 'terminal_id'], 64);
        if (!isset($meta['group_name']) || $meta['group_name'] === '') {
            $fillStr($meta, 'group_name', $sources, ['group_name', 'groupName', 'group'], 96);
        }

        foreach (['latency_ms', 'packet_loss'] as $numKey) {
            if (isset($meta[$numKey]) && $meta[$numKey] !== '') {
                continue;
            }
            foreach ($sources as $src) {
                if (!is_array($src) || !isset($src[$numKey]) || $src[$numKey] === '' || !is_numeric($src[$numKey])) {
                    continue;
                }
                $meta[$numKey] = str_contains($numKey, 'packet_loss')
                    ? round((float) $src[$numKey], 1)
                    : (int) round((float) $src[$numKey]);
                break;
            }
        }
        if (isset($meta['cert_status'])) {
            $meta['cert_status'] = strtolower((string) $meta['cert_status']);
        }
        if (isset($meta['link_state'])) {
            $meta['link_state'] = strtolower((string) $meta['link_state']);
        }
    }

    private function truthyFlag(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }
        if (is_int($raw) || is_float($raw)) {
            return ((int) $raw) === 1;
        }
        if (is_string($raw)) {
            $v = strtolower(trim($raw));

            return in_array($v, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * Le mod Overwatch envoie toujours mapId=1 (Extension.cs) ; fusionner avec le théâtre affiché.
     */
    private function resolveLatestArmaActivity(int $tenantId, int $mapId): ?string
    {
        $modMapId = self::DEFAULT_MAP_ID;
        $last = $this->atak->getLastActivity($tenantId, $mapId);
        if ($mapId === $modMapId) {
            return $last;
        }
        $modLast = $this->atak->getLastActivity($tenantId, $modMapId);
        if ($modLast === null) {
            return $last;
        }
        if ($last === null || strtotime($modLast) > strtotime($last)) {
            return $modLast;
        }

        return $last;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unitsForTransmission(int $tenantId, int $mapId): array
    {
        $modMapId = self::DEFAULT_MAP_ID;
        $units = $this->atak->getUnits($tenantId, $mapId);
        if ($mapId === $modMapId) {
            return $units;
        }
        $modUnits = $this->atak->getUnits($tenantId, $modMapId);
        if ($modUnits === []) {
            return $units;
        }
        if ($units === []) {
            return $modUnits;
        }

        $rank = ['linked' => 3, 'delayed' => 2, 'offline' => 1];
        $byKey = [];
        foreach (array_merge($modUnits, $units) as $u) {
            if (!is_array($u)) {
                continue;
            }
            $callSign = strtolower(trim((string) ($u['call_sign'] ?? '')));
            $key = $callSign !== '' ? $callSign : ('id:' . (string) ($u['id'] ?? count($byKey)));
            if (!isset($byKey[$key])) {
                $byKey[$key] = $u;
                continue;
            }
            $curRank = $rank[(string) ($byKey[$key]['status'] ?? 'offline')] ?? 0;
            $newRank = $rank[(string) ($u['status'] ?? 'offline')] ?? 0;
            if ($newRank > $curRank) {
                $byKey[$key] = $u;
            }
        }

        return array_values($byKey);
    }

    /**
     * @param array{athena_at:int,ctab_at:int,atak_enhanced_at:int,athena_ctab_at:int} $primary
     * @param array{athena_at:int,ctab_at:int,atak_enhanced_at:int,athena_ctab_at:int} $secondary
     * @return array{athena_at:int,ctab_at:int,atak_enhanced_at:int,athena_ctab_at:int}
     */
    private function mergeModDetection(array $primary, array $secondary): array
    {
        $out = $primary;
        foreach ($secondary as $key => $value) {
            $out[$key] = max((int) ($out[$key] ?? 0), (int) $value);
        }

        return $out;
    }

    /**
     * États des canaux de transmission pour le bandeau ATAK.
     * linked = en liaison · present = détecté récemment · absent = non détecté.
     *
     * @param list<array<string, mixed>> $units
     * @return array{
     *   site: array{id:string,label:string,label_en:string,state:string,state_label:string,state_label_en:string,count:int},
     *   athena: array{id:string,label:string,label_en:string,state:string,state_label:string,state_label_en:string},
     *   ctab: array{id:string,label:string,label_en:string,state:string,state_label:string,state_label_en:string},
     *   atak_enhanced: array{id:string,label:string,label_en:string,state:string,state_label:string,state_label_en:string}
     * }
     */
    private function buildTransmissionSources(int $tenantId, int $mapId, ?int $armaAgo, array $units): array
    {
        $modMapId = self::DEFAULT_MAP_ID;
        $webCount = count($this->activityLog->listWebPresence($tenantId, $mapId));
        $detect = $this->activityLog->getModDetection($tenantId, $mapId);
        if ($mapId !== $modMapId) {
            $detect = $this->mergeModDetection(
                $detect,
                $this->activityLog->getModDetection($tenantId, $modMapId)
            );
        }

        $ctabFresh = false;
        $ctabSeen = false;
        $enhancedFresh = false;
        $enhancedSeen = false;
        $athenaFresh = false;
        foreach ($units as $u) {
            if (!is_array($u)) {
                continue;
            }
            $status = (string) ($u['status'] ?? '');
            if ($status !== 'linked' && $status !== 'delayed') {
                continue;
            }
            $fresh = $status === 'linked';
            $extra = [];
            $raw = $u['extra'] ?? null;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $extra = $decoded;
                }
            } elseif (is_array($raw)) {
                $extra = $raw;
            }
            if ($this->truthyFlag($extra['mod_athena'] ?? null) || trim((string) ($extra['mod_version'] ?? '')) !== '') {
                if ($fresh) {
                    $athenaFresh = true;
                }
            }
            if ($this->truthyFlag($extra['has_ctab'] ?? null)) {
                if ($fresh) {
                    $ctabFresh = true;
                } else {
                    $ctabSeen = true;
                }
            }
            if (
                $this->truthyFlag($extra['has_atak_enhanced'] ?? null)
                || $this->truthyFlag($extra['wr_mpu5'] ?? null)
            ) {
                if ($fresh) {
                    $enhancedFresh = true;
                } else {
                    $enhancedSeen = true;
                }
            }
        }

        $siteState = $webCount > 0 ? 'linked' : 'absent';

        $athenaState = 'absent';
        if ($athenaFresh || ($armaAgo !== null && $armaAgo <= 90)) {
            $athenaState = 'linked';
        } elseif (($detect['athena_at'] ?? 0) > 0 || ($armaAgo !== null && $armaAgo <= 600)) {
            $athenaState = 'present';
        }

        $ctabState = 'absent';
        if ($ctabFresh) {
            $ctabState = 'linked';
        } elseif ($ctabSeen || ($detect['ctab_at'] ?? 0) > 0) {
            $ctabState = 'present';
        }

        $enhancedState = 'absent';
        if ($enhancedFresh) {
            $enhancedState = 'linked';
        } elseif ($enhancedSeen || ($detect['atak_enhanced_at'] ?? 0) > 0) {
            $enhancedState = 'present';
        }

        return [
            'site' => $this->transmissionSourceRow('site', 'Sur le site', 'On the site', $siteState, $webCount),
            'athena' => $this->transmissionSourceRow('athena', 'Mod Athena', 'Athena mod', $athenaState),
            'ctab' => $this->transmissionSourceRow('ctab', 'cTab', 'cTab', $ctabState),
            'atak_enhanced' => $this->transmissionSourceRow('atak_enhanced', 'ATAK Enhanced', 'ATAK Enhanced', $enhancedState),
        ];
    }

    /**
     * @return array{id:string,label:string,label_en:string,state:string,state_label:string,state_label_en:string,count?:int}
     */
    private function transmissionSourceRow(string $id, string $labelFr, string $labelEn, string $state, ?int $count = null): array
    {
        [$stateFr, $stateEn] = match ($state) {
            'linked' => ['En liaison', 'Linked'],
            'present' => ['Présent', 'Present'],
            default => ['Absent', 'Absent'],
        };
        $row = [
            'id' => $id,
            'label' => $labelFr,
            'label_en' => $labelEn,
            'state' => $state,
            'state_label' => $stateFr,
            'state_label_en' => $stateEn,
        ];
        if ($count !== null) {
            $row['count'] = $count;
        }

        return $row;
    }

    private function authArma(): bool
    {
        return ComspecApiKeyAuth::armaInlineAuthOk();
    }

    /**
     * Applique les simulations roleplay (latence, déconnexion, packet loss).
     * Retourne une Response d'erreur si la connexion est simulée comme perdue, null sinon.
     */
    private function applyRoleplayEffects(int $tenantId): ?Response
    {
        try {
            // Vérifier déconnexion simulée
            if ($this->roleplaySim->shouldSimulateDisconnection($tenantId)) {
                $message = $this->roleplaySim->getDisconnectionMessage($tenantId);
                return Response::json([
                    'error' => 'connection_lost',
                    'message' => $message,
                ], 503);
            }

            // Perte de paquet : ne bloque plus les lectures (journal radio / unités).
            // Sinon un 503 ponctuel laisse le Journal Radio figé sur « Aucun message »
            // alors que Liaison a bien reçu l’événement.

            // Appliquer latence
            $this->roleplaySim->applyNetworkLatency($tenantId);
        } catch (\Throwable) {
            // Ne jamais faire tomber une route métier pour une panne du simulateur roleplay.
        }

        return null;
    }

    /**
     * @return array{mode:string, reason:string, reason_label:string, intensity:float, scramble_enabled:bool}
     */
    private function resolveIntelView(Request $request, int $tenantId, int $mapId): array
    {
        $terminalUid = trim((string) ($request->query('terminal_uid')
            ?? $request->query('terminalUid')
            ?? ($this->jsonBody($request)['terminal_uid'] ?? '')
            ?? ''));
        $userId = (int) Session::get('user_id');
        $jam = $request->query('jam_intensity');
        $identity = $this->intelView->identityFromRequest(
            $terminalUid !== '' ? $terminalUid : null,
            $userId > 0 ? $userId : null,
            is_numeric($jam) ? (float) $jam : null,
            trim((string) ($request->query('link_state') ?? '')) ?: null,
            trim((string) ($request->query('zone_type') ?? '')) ?: null
        );

        return $this->intelView->resolveViewerMode($tenantId, $mapId, $identity);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function applyIntelScramble(Request $request, int $tenantId, int $mapId, string $type, array $rows): array
    {
        $view = $this->resolveIntelView($request, $tenantId, $mapId);
        if (($view['mode'] ?? '') === AtakIntelViewService::MODE_CLEAR) {
            return $rows;
        }

        return $this->intelView->scrambleList($view, $type, $rows);
    }

    public function intelViewIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $mapId = $this->mapId($request);
        $view = $this->resolveIntelView($request, $r, $mapId);

        return Response::json(['ok' => true, 'intel_view' => $view]);
    }

    public function deviceAlertsIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $mapId = $this->mapId($request);
        try {
            $alerts = $this->intelView->collectDeviceAlerts($r, $mapId);
        } catch (\Throwable) {
            $alerts = [];
        }
        if (!is_array($alerts)) {
            $alerts = [];
        }

        $intelView = [
            'mode' => AtakIntelViewService::MODE_CLEAR,
            'reason' => '',
            'reason_label' => '',
            'intensity' => 0.0,
            'scramble_enabled' => false,
        ];
        try {
            $intelView = $this->resolveIntelView($request, $r, $mapId);
        } catch (\Throwable) {
        }

        return Response::json([
            'ok' => true,
            'mapId' => $mapId,
            'alerts' => $alerts,
            'counts' => [
                'total' => count($alerts),
                'critical' => count(array_filter($alerts, static fn ($a) => ($a['severity'] ?? '') === 'critical')),
                'warn' => count(array_filter($alerts, static fn ($a) => ($a['severity'] ?? '') === 'warn')),
            ],
            'intel_view' => $intelView,
        ]);
    }

    /**
     * Garde écriture Arma : clé déjà vérifiée + Steam lié (si fourni) + session + anti-spoof.
     *
     * @return array{steam_uid: ?string, session_ok: bool}|Response
     */
    private function guardArmaWrite(Request $request, int $tenantId, bool $requireSteam = false, string $channel = 'write'): array|Response
    {
        return $this->armaGuard->assertActor($request, $tenantId, $this->jsonBody($request), $requireSteam, $channel);
    }

    public function ping(Request $request, array $params = []): Response
    {
        return (new AtakPingController())->ping($request, $params);
    }

    /**
     * Statistiques de simulation roleplay pour affichage UI.
     */
    public function roleplayStats(Request $request, array $params = []): Response
    {
        $fallback = [
            'network' => ['enabled' => false],
            'sensor' => ['enabled' => false],
            'measured_packet_loss' => null,
            'zones_enabled' => false,
            'zones_json' => '',
            'intel_scramble_enabled' => false,
            'session_ttl_sec' => 86400,
        ];

        try {
            $tenantId = $this->resolveTenantId($request);
            if ($tenantId === null || $tenantId < 1) {
                return Response::json($fallback);
            }

            $networkStats = $this->roleplaySim->getNetworkStats($tenantId);
            $sensorStats = $this->roleplaySim->getSensorStats($tenantId);
            $mapId = $this->mapId($request);
            $measuredLoss = $this->getLatestLinkTelemetry($tenantId, $mapId);
            $roleplayCfg = ($this->tenantAtakConfigRepository ?? new TenantAtakConfigRepository())->getRoleplayConfig($tenantId);

            $zonesJson = '';
            $zonesArray = null;
            if (!empty($roleplayCfg['zones_config'])) {
                $decoded = is_string($roleplayCfg['zones_config'])
                    ? json_decode($roleplayCfg['zones_config'], true)
                    : $roleplayCfg['zones_config'];
                if (is_array($decoded)) {
                    $zonesArray = $decoded;
                    $zonesJson = json_encode($decoded, JSON_UNESCAPED_UNICODE) ?: '';
                } elseif (is_string($roleplayCfg['zones_config'])) {
                    $zonesJson = $roleplayCfg['zones_config'];
                }
            }

            return Response::json([
                'network' => $networkStats,
                'sensor' => $sensorStats,
                'measured_packet_loss' => $measuredLoss,
                'zones_enabled' => (bool) ($roleplayCfg['zones_enabled'] ?? false),
                'zones_json' => $zonesArray ?? $zonesJson,
                'intel_scramble_enabled' => (bool) ($roleplayCfg['intel_scramble_enabled'] ?? false),
                'session_ttl_sec' => 86400,
            ]);
        } catch (\Throwable) {
            return Response::json($fallback);
        }
    }

    /**
     * Reprise session ATAK post-CTD (TTL court, identité Steam).
     */
    public function sessionRestore(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId === null || $tenantId < 1) {
            return Response::json(['error' => 'Tenant required'], 400);
        }

        $steamUid = trim((string) ($request->query('steam_uid') ?? $request->query('steamUid') ?? ''));
        if ($steamUid === '') {
            return Response::json(['error' => 'steam_uid required'], 400);
        }

        $repo = new AtakDisconnectRecoveryRepository();
        $snapshot = $repo->get($tenantId, $steamUid);
        if ($snapshot === null) {
            return Response::json(['error' => 'not_found'], 404);
        }

        return Response::json([
            'callsign' => (string) ($snapshot['callsign'] ?? ''),
            'link_state' => (string) ($snapshot['link_state'] ?? 'linked'),
            'saved_at' => (int) ($snapshot['saved_at'] ?? 0),
        ]);
    }

    /**
     * Télémétrie liaison remontée par le mod (extra des unités BFT).
     *
     * @return array{
     *   packet_loss_percent: float,
     *   packets_sent: int,
     *   packets_received: int,
     *   latency_ms: ?int,
     *   link_state: string,
     *   unit_callsign: string,
     *   measured_at: ?string
     * }|null
     */
    private function getLatestLinkTelemetry(int $tenantId, int $mapId = self::DEFAULT_MAP_ID): ?array
    {
        $units = $this->unitsForTransmission($tenantId, $mapId);

        $latestMeasurement = null;
        $latestTime = 0;

        foreach ($units as $unit) {
            if (!is_array($unit)) {
                continue;
            }
            $status = (string) ($unit['status'] ?? '');
            if ($status !== 'linked' && $status !== 'delayed') {
                continue;
            }

            $rawExtra = $unit['extra'] ?? null;
            if ($rawExtra === null || $rawExtra === '') {
                continue;
            }

            $extra = is_string($rawExtra) ? json_decode($rawExtra, true) : $rawExtra;
            if (!is_array($extra)) {
                continue;
            }

            $hasTelemetry = array_key_exists('packet_loss', $extra)
                || array_key_exists('link_state', $extra)
                || array_key_exists('latency_ms', $extra);
            if (!$hasTelemetry) {
                continue;
            }

            $updateTime = strtotime((string) ($unit['updated_at'] ?? '')) ?: 0;
            if ($updateTime <= $latestTime) {
                continue;
            }

            $latestTime = $updateTime;
            $latestMeasurement = [
                'packet_loss_percent' => (float) ($extra['packet_loss'] ?? 0),
                'packets_sent' => (int) ($extra['packets_sent'] ?? 0),
                'packets_received' => (int) ($extra['packets_received'] ?? 0),
                'latency_ms' => isset($extra['latency_ms']) ? (int) $extra['latency_ms'] : null,
                'link_state' => strtolower(trim((string) ($extra['link_state'] ?? $status))),
                'unit_callsign' => (string) ($unit['call_sign'] ?? 'Unknown'),
                'measured_at' => $unit['updated_at'] ?? null,
            ];
        }

        return $latestMeasurement;
    }

    /**
     * @deprecated Utiliser getLatestLinkTelemetry — conservé pour compatibilité interne.
     */
    private function getMeasuredPacketLoss(int $tenantId, int $mapId = self::DEFAULT_MAP_ID): ?array
    {
        return $this->getLatestLinkTelemetry($tenantId, $mapId);
    }

    /**
     * Présence des opérateurs connectés à la Tacmap web (portail), distincte des joueurs Arma.
     */
    public function presence(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($userId > 0) {
            $user = $this->userRepository->findById($userId);
            $displayName = '';
            $callsign = '';
            if (is_array($user)) {
                $displayName = trim((string) ($user['display_name'] ?? ''));
                $callsign = trim((string) ($user['callsign'] ?? ''));
            }
            $this->activityLog->heartbeatWebPresence($tenantId, $mapId, $userId, $displayName, $callsign);
        }
        $viewers = $this->activityLog->listWebPresence($tenantId, $mapId);
        $out = [];
        foreach ($viewers as $v) {
            $out[] = [
                'label' => $v['label'],
                'callsign' => $v['callsign'] !== '' ? $v['callsign'] : null,
                'display_name' => $v['display_name'] !== '' ? $v['display_name'] : null,
            ];
        }

        return Response::json([
            'viewers' => $out,
            'count' => count($out),
        ]);
    }

    public function whoami(Request $request, array $params = []): Response
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ip = is_string($forwarded) ? trim(explode(',', $forwarded)[0]) : trim($forwarded[0]);
        }
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId !== null) {
            try {
                $mapId = $this->mapId($request);
                $this->activityLog->recordClientInit(
                    $tenantId,
                    $mapId,
                    $this->activityLog->clientKeyFromRequest()
                );
            } catch (\Throwable) {
            }
        }
        return Response::json(['ip' => $ip ?: '—']);
    }

    /**
     * Inscription accès anticipé (bêta) — premier lancement du mod Overwatch.
     * Route publique rate-limitée ; l’adresse réseau est prise côté serveur.
     */
    public function betaRegister(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $steamRaw = trim((string) ($body['steam_uid'] ?? $body['steam_id'] ?? ''));
        $playerUid = trim((string) ($body['player_uid'] ?? $body['uid'] ?? ''));
        $playerName = trim((string) ($body['player_name'] ?? $body['name'] ?? ''));
        $armaBuild = trim((string) ($body['arma_build'] ?? $body['arma_id'] ?? ''));
        $armaBranch = trim((string) ($body['arma_branch'] ?? ''));
        $modVersion = trim((string) ($body['mod_version'] ?? ''));
        $extVersion = trim((string) ($body['extension_version'] ?? ''));
        $acknowledged = !empty($body['acknowledged']) || !empty($body['ack']);

        $steam = SteamId::normalize($steamRaw !== '' ? $steamRaw : null);
        if ($steam === null && $steamRaw !== '' && SteamId::normalize($playerUid) !== null) {
            $steam = SteamId::normalize($playerUid);
        }
        if ($playerUid === '' && $steam !== null) {
            $playerUid = $steam;
        }

        // Au moins un repère joueur ou un build : évite le spam vide.
        if ($steam === null && $playerUid === '' && $armaBuild === '' && $playerName === '') {
            return Response::json([
                'error' => 'missing_identity',
                'message' => 'Informations insuffisantes pour enregistrer l’accès anticipé.',
            ], 400);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ip = is_string($forwarded) ? trim(explode(',', $forwarded)[0]) : trim((string) $forwarded[0]);
        }
        $ip = trim((string) $ip);
        if (strlen($ip) > 45) {
            $ip = substr($ip, 0, 45);
        }

        try {
            $result = $this->betaRegistrationRepository->upsert([
                'steam_uid' => $steam,
                'player_uid' => $playerUid !== '' ? $playerUid : null,
                'player_name' => $playerName !== '' ? $playerName : null,
                'client_ip' => $ip !== '' ? $ip : null,
                'arma_build' => $armaBuild !== '' ? $armaBuild : null,
                'arma_branch' => $armaBranch !== '' ? $armaBranch : null,
                'mod_version' => $modVersion !== '' ? $modVersion : null,
                'extension_version' => $extVersion !== '' ? $extVersion : null,
                'acknowledged' => $acknowledged,
            ]);
        } catch (\Throwable $e) {
            return Response::json([
                'error' => 'store_failed',
                'message' => 'Impossible d’enregistrer l’accès pour le moment. Réessayez plus tard.',
            ], 503);
        }

        return Response::json([
            'ok' => true,
            'created' => $result['created'],
            'id' => $result['id'],
        ]);
    }

    /**
     * Remontée d’erreurs / bugs du pack Overwatch (Arma → Athena).
     * Public comme beta-register ; rate-limité. Pas de secret requis.
     */
    public function modReport(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $severity = trim((string) ($body['severity'] ?? $body['level'] ?? 'error'));
        $channel = trim((string) ($body['channel'] ?? $body['module'] ?? 'Core'));
        $message = trim((string) ($body['message'] ?? $body['msg'] ?? ''));
        $detail = trim((string) ($body['detail'] ?? $body['detail_text'] ?? ''));
        $fingerprint = trim((string) ($body['fingerprint'] ?? ''));
        $source = trim((string) ($body['source'] ?? 'auto'));
        $steamRaw = trim((string) ($body['steam_uid'] ?? $body['steam_id'] ?? ''));
        $playerUid = trim((string) ($body['player_uid'] ?? $body['uid'] ?? ''));
        $playerName = trim((string) ($body['player_name'] ?? $body['name'] ?? ''));
        $callsign = trim((string) ($body['callsign'] ?? ''));
        $modVersion = trim((string) ($body['mod_version'] ?? ''));
        $extVersion = trim((string) ($body['extension_version'] ?? ''));
        $armaBuild = trim((string) ($body['arma_build'] ?? ''));
        $contextRaw = $body['context'] ?? $body['context_json'] ?? null;

        if ($message === '') {
            return Response::json([
                'error' => 'missing_message',
                'message' => 'Le rapport doit contenir un message.',
            ], 400);
        }

        $steam = SteamId::normalize($steamRaw !== '' ? $steamRaw : null);
        if ($steam === null && $steamRaw !== '' && SteamId::normalize($playerUid) !== null) {
            $steam = SteamId::normalize($playerUid);
        }
        if ($playerUid === '' && $steam !== null) {
            $playerUid = $steam;
        }

        $contextJson = null;
        if (is_array($contextRaw)) {
            $encoded = json_encode($contextRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $contextJson = is_string($encoded) ? $encoded : null;
        } elseif (is_string($contextRaw) && trim($contextRaw) !== '') {
            $contextJson = trim($contextRaw);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ip = is_string($forwarded) ? trim(explode(',', $forwarded)[0]) : trim((string) $forwarded[0]);
        }
        $ip = trim((string) $ip);
        if (strlen($ip) > 45) {
            $ip = substr($ip, 0, 45);
        }

        try {
            $result = $this->modReports()->upsert([
                'severity' => $severity,
                'channel' => $channel,
                'message' => $message,
                'detail_text' => $detail !== '' ? $detail : null,
                'context_json' => $contextJson,
                'fingerprint' => $fingerprint !== '' ? $fingerprint : null,
                'source' => $source !== '' ? $source : 'auto',
                'steam_uid' => $steam,
                'player_uid' => $playerUid !== '' ? $playerUid : null,
                'player_name' => $playerName !== '' ? $playerName : null,
                'callsign' => $callsign !== '' ? $callsign : null,
                'client_ip' => $ip !== '' ? $ip : null,
                'mod_version' => $modVersion !== '' ? $modVersion : null,
                'extension_version' => $extVersion !== '' ? $extVersion : null,
                'arma_build' => $armaBuild !== '' ? $armaBuild : null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return Response::json([
                'error' => 'invalid',
                'message' => 'Rapport incomplet.',
            ], 400);
        } catch (\Throwable $e) {
            return Response::json([
                'ok' => false,
                'error' => 'store_failed',
                'message' => 'Impossible d’enregistrer le rapport pour le moment.',
            ], 503);
        }

        return Response::json([
            'ok' => true,
            'created' => $result['created'],
            'id' => $result['id'],
            'hit_count' => $result['hit_count'],
        ]);
    }

    /**
     * Remontée du journal d’appareil (même lignes que le fichier d’activité Overwatch).
     */
    public function deviceLogsStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $callsign = trim((string) ($body['callsign'] ?? $body['call_sign'] ?? ''));
        $steamUid = trim((string) ($body['steam_uid'] ?? $body['steam_id'] ?? ''));
        $playerName = trim((string) ($body['player_name'] ?? $body['name'] ?? ''));
        $sourceRaw = strtolower(trim((string) ($body['source'] ?? '')));
        $source = match ($sourceRaw) {
            AtakDeviceLog::SOURCE_WEB => AtakDeviceLog::SOURCE_WEB,
            AtakDeviceLog::SOURCE_SYSTEM => AtakDeviceLog::SOURCE_SYSTEM,
            default => AtakDeviceLog::SOURCE_MOD,
        };
        if ($source === AtakDeviceLog::SOURCE_MOD && !$this->authArma()) {
            $brief = $this->sessionUserBrief();
            $source = is_array($brief) ? AtakDeviceLog::SOURCE_WEB : AtakDeviceLog::SOURCE_MOD;
        }

        $uid = trim((string) ($body['terminal_uid'] ?? $body['terminalUid'] ?? ''));
        $uid = $this->resolveDeviceTerminalUid($tenantId, $uid, $callsign, $this->sessionUserBrief());
        if ($uid === '') {
            return Response::json([
                'error' => 'missing_terminal',
                'message' => 'Impossible de rattacher ce journal à un appareil.',
            ], 422);
        }

        $rawLines = $body['lines'] ?? $body['entries'] ?? $body['events'] ?? [];
        if (!is_array($rawLines) || $rawLines === []) {
            $single = trim((string) ($body['message'] ?? $body['line'] ?? ''));
            if ($single === '') {
                return Response::json([
                    'error' => 'missing_lines',
                    'message' => 'Aucune ligne de journal à enregistrer.',
                ], 400);
            }
            $rawLines = [$body];
        }

        $lines = [];
        foreach (array_slice(array_values($rawLines), 0, AtakDeviceLogRepository::MAX_BATCH) as $row) {
            if (is_array($row)) {
                $lines[] = $row;
            } elseif (is_string($row) && trim($row) !== '') {
                $lines[] = ['line' => $row];
            }
        }
        if ($lines === []) {
            return Response::json([
                'error' => 'missing_lines',
                'message' => 'Aucune ligne de journal à enregistrer.',
            ], 400);
        }

        try {
            $result = $this->deviceLogs()->ingest(
                $tenantId,
                $uid,
                $lines,
                $callsign !== '' ? $callsign : null,
                $steamUid !== '' ? $steamUid : null,
                $playerName !== '' ? $playerName : null,
                $source
            );
        } catch (\Throwable) {
            return Response::json([
                'error' => 'store_failed',
                'message' => 'Impossible d’enregistrer le journal pour le moment.',
            ], 503);
        }

        return Response::json([
            'ok' => true,
            'accepted' => $result['accepted'],
            'skipped' => $result['skipped'],
        ]);
    }

    /**
     * Journal d’activité de liaison (init client, indicatifs, messages / positions).
     * Consommé par le panneau « Activité de liaison » et la page dédiée.
     *
     * Query : mapId, limit, after (polling), before (scroll infini),
     * type / q / from / to, archived=1|only, page=1 (mode page dédiée),
     * demo=1 (seed local/debug uniquement).
     */
    public function activityIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $limit = (int) ($request->query('limit') ?: 40);

        $demo = $request->query('demo');
        if (($demo === '1' || $demo === 'true') && $this->activityLog->isDemoSeedAllowed()) {
            $force = $request->query('demo_force') === '1';
            $this->activityLog->seedDemoEvents($tenantId, $mapId, $force);
        }

        $afterRaw = $request->query('after');
        $beforeRaw = $request->query('before');
        $afterId = ($afterRaw !== null && $afterRaw !== '') ? (int) $afterRaw : null;
        $beforeId = ($beforeRaw !== null && $beforeRaw !== '') ? (int) $beforeRaw : null;

        $archivedParam = strtolower(trim((string) ($request->query('archived') ?? '')));
        $includeArchived = in_array($archivedParam, ['1', 'true', 'yes', 'all'], true);
        $archivedOnly = in_array($archivedParam, ['only', 'archived'], true);

        $type = $request->query('type');
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');
        $pageMode = $request->query('page') !== null && $request->query('page') !== ''
            || $beforeId !== null
            || ($type !== null && $type !== '')
            || ($q !== null && $q !== '')
            || ($from !== null && $from !== '')
            || ($to !== null && $to !== '')
            || $includeArchived
            || $archivedOnly;

        if ($pageMode) {
            $opts = [
                'limit' => $limit > 0 ? $limit : 50,
                'before_id' => $beforeId,
                'after_id' => $afterId,
                'type' => $type,
                'q' => is_string($q) ? $q : null,
                'from' => is_string($from) ? $from : null,
                'to' => is_string($to) ? $to : null,
                'include_archived' => $includeArchived || $archivedOnly,
                'archived_only' => $archivedOnly,
            ];
            // Sync BFT et « Position reçue » hors journal par défaut (sauf filtre explicite).
            $typeStr = is_string($type) ? strtolower($type) : '';
            $wantPositionTrail = str_contains($typeStr, 'position')
                || $typeStr === AtakActivityLogService::TYPE_POSITION
                || str_contains($typeStr, 'donnees')
                || str_contains($typeStr, 'ingest');
            if ($typeStr === '' || (!str_contains($typeStr, 'position') && $typeStr !== AtakActivityLogService::TYPE_POSITION)) {
                $opts['exclude_types'] = [AtakActivityLogService::TYPE_POSITION];
            }
            $opts['exclude_position_ingest'] = !$wantPositionTrail;
            $result = $this->activityLog->listFiltered($tenantId, $mapId, $opts);
            $events = $result['events'];

            // Enrichir avec auth/téléphone (carte virtuelle) sauf filtre purement positionnel.
            if ($mapId !== AtakActivityLogService::AUTH_MAP_ID) {
                $authResult = $this->activityLog->listFiltered($tenantId, AtakActivityLogService::AUTH_MAP_ID, array_merge($opts, [
                    'type' => null,
                    'limit' => max(20, (int) ($opts['limit'] ?? 50)),
                    'before_id' => null,
                    'after_id' => null,
                ]));
                $authOnly = array_values(array_filter(
                    $authResult['events'],
                    static fn ($e) => is_array($e) && in_array((string) ($e['type'] ?? ''), ['auth', 'phone'], true)
                ));
                if ($type !== null && $type !== '') {
                    $typeFilter = is_string($type) ? $type : '';
                    $wantAuth = $typeFilter === '' || str_contains($typeFilter, 'connexion')
                        || str_contains($typeFilter, 'auth') || str_contains($typeFilter, 'phone');
                    if (!$wantAuth) {
                        $authOnly = [];
                    }
                }
                if ($authOnly !== []) {
                    $events = $this->mergeActivityEvents($events, $authOnly, (int) ($opts['limit'] ?? 50), $beforeId, $afterId);
                    $result['has_more'] = $result['has_more'] || count($authOnly) > count($events);
                    $result['total'] = max($result['total'], count($events));
                }
            }

            return Response::json([
                'events' => $events,
                'total' => $result['total'],
                'has_more' => $result['has_more'],
            ]);
        }

        $events = $this->activityLog->listRecent($tenantId, $mapId, $limit, $afterId, false);
        $cursor = 0;
        foreach ($events as $e) {
            if (is_array($e)) {
                $cursor = max($cursor, (int) ($e['id'] ?? 0));
            }
        }

        // Inclure les tentatives de liaison / téléphone (journalisées sur la carte « auth »).
        if ($mapId !== AtakActivityLogService::AUTH_MAP_ID) {
            $authEvents = $this->activityLog->listRecent($tenantId, AtakActivityLogService::AUTH_MAP_ID, 20, null, false);
            $authOnly = array_values(array_filter(
                $authEvents,
                static fn ($e) => is_array($e) && in_array((string) ($e['type'] ?? ''), ['auth', 'phone'], true)
            ));
            if ($authOnly !== []) {
                $events = array_merge($events, $authOnly);
                usort($events, static function (array $a, array $b): int {
                    return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0))
                        ?: strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
                });
                $events = array_slice($events, 0, max(1, min(100, $limit)));
            }
        }

        return Response::json([
            'events' => $events,
            'cursor' => $cursor,
        ]);
    }

    /**
     * Archive le journal affiché (ne supprime pas — conserve l’historique).
     */
    public function activityClear(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? $request->query('mapId') ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $archived = $this->activityLog->archiveAll($tenantId, $mapId);
        $archivedAuth = 0;
        if ($mapId !== AtakActivityLogService::AUTH_MAP_ID) {
            $archivedAuth = $this->activityLog->archiveAll($tenantId, AtakActivityLogService::AUTH_MAP_ID);
        }

        return Response::json([
            'ok' => true,
            'archived' => $archived + $archivedAuth,
            'message' => 'Le journal a été mis de côté. Vous pourrez le consulter dans l’historique archivé.',
        ]);
    }

    /**
     * Vide la carte pour une nouvelle soirée : marqueurs, ordres, messages, positions.
     * Les photos restent ; elles se regroupent d’elles-mêmes par soirée (10 h → 10 h).
     */
    public function theatreReset(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            return Response::json([
                'error' => 'web_session_required',
                'message' => 'Cette action se fait depuis le poste de commandement, pas depuis le jeu.',
            ], 403);
        }
        if ($resp = $this->requireReconOperatorSession($tenantId)) {
            return $resp;
        }
        $body = $this->jsonBody($request);
        $confirm = trim((string) ($body['confirm'] ?? $body['confirm_phrase'] ?? ''));
        if (strcasecmp($confirm, AtakPlayNight::CONFIRM_CLEAR_MAP) !== 0) {
            return Response::json([
                'error' => 'confirm_required',
                'message' => 'Pour confirmer, saisissez exactement « ' . AtakPlayNight::CONFIRM_CLEAR_MAP . ' ».',
            ], 422);
        }
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? $request->query('mapId') ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $result = (new AtakTenantDataService(null, $this->activityLog))->resetTheatreKeepPhotos($tenantId, $mapId);
        $removed = array_sum($result['tables']);
        $actor = $this->sessionUserBrief();
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_TOC_NOTE,
            'Carte vidée pour la nouvelle soirée — photos conservées',
            is_array($actor) ? (string) ($actor['callsign'] ?: $actor['displayName'] ?: 'Commandement') : 'Commandement',
            ['source' => 'theatre_reset', 'rows' => $removed]
        );

        return Response::json([
            'ok' => true,
            'cleared' => $removed,
            'archived' => $result['activity_archived'],
            'current_night' => AtakPlayNight::currentKey(),
            'current_night_label' => AtakPlayNight::label(AtakPlayNight::currentKey()),
            'message' => 'Carte vidée. Les photos restent disponibles dans l’onglet Photos, classées par soirée.',
        ]);
    }

    public function photoNightsIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $current = AtakPlayNight::currentKey();
        $nights = (new AtakTenantDataService(null, $this->activityLog))->listPhotoNights($tenantId, $mapId);

        return Response::json([
            'current' => $current,
            'current_label' => AtakPlayNight::label($current),
            'cutoff_hour' => AtakPlayNight::CUTOFF_HOUR,
            'nights' => $nights,
        ]);
    }

    public function photoNightsPurge(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            return Response::json([
                'error' => 'web_session_required',
                'message' => 'Cette action se fait depuis le poste de commandement, pas depuis le jeu.',
            ], 403);
        }
        if ($resp = $this->requireReconOperatorSession($tenantId)) {
            return $resp;
        }
        $body = $this->jsonBody($request);
        $confirm = trim((string) ($body['confirm'] ?? $body['confirm_phrase'] ?? ''));
        if (strcasecmp($confirm, AtakPlayNight::CONFIRM_DELETE_PHOTOS) !== 0) {
            return Response::json([
                'error' => 'confirm_required',
                'message' => 'Pour confirmer, saisissez exactement « ' . AtakPlayNight::CONFIRM_DELETE_PHOTOS . ' ».',
            ], 422);
        }
        $night = AtakPlayNight::normalizeKey((string) ($body['night'] ?? $body['play_night'] ?? ''))
            ?? AtakPlayNight::currentKey();
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? $request->query('mapId') ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $result = (new AtakTenantDataService(null, $this->activityLog))->purgePhotosForNight($tenantId, $mapId, $night);
        $total = $result['recon_hidden'] + $result['intel_removed'];

        return Response::json([
            'ok' => true,
            'night' => $night,
            'night_label' => AtakPlayNight::label($night),
            'removed' => $total,
            'message' => $total > 0
                ? ('Photos de « ' . AtakPlayNight::label($night) . ' » retirées du poste. Les clichés déjà classés en dossier SSE sont conservés.')
                : 'Aucune photo à retirer pour cette soirée.',
        ]);
    }

    /** Entrée manuelle du journal d’opérations (TOC). */
    public function activityStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($userId < 1) {
            return Response::json(['error' => 'Authentification requise'], 401);
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $note = trim((string) ($body['note'] ?? $body['label'] ?? $body['text'] ?? ''));
        if ($note === '') {
            return Response::json(['error' => 'Texte requis', 'message' => 'Saisissez le contenu de l’entrée de journal.'], 422);
        }
        if (mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }
        $actor = trim((string) ($body['author'] ?? ''));
        if ($actor === '') {
            $user = $this->userRepository->findById($userId);
            $actor = is_array($user)
                ? (trim((string) ($user['callsign'] ?? '')) ?: trim((string) ($user['display_name'] ?? 'Commandement')))
                : 'Commandement';
        }
        $label = 'TOC — ' . $note;
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_TOC_NOTE,
            $label,
            $actor,
            ['source' => 'manual']
        );

        return Response::json(['ok' => true, 'message' => 'Entrée ajoutée au journal.'], 201);
    }

    /**
     * Lots d’incidents / remontées depuis la carte web (session opérateur).
     */
    public function webLogStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? $request->query('mapId') ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $rawEvents = $body['events'] ?? [];
        if (!is_array($rawEvents)) {
            return Response::json([
                'error' => 'invalid',
                'message' => 'Aucune entrée à enregistrer.',
            ], 422);
        }
        $brief = $this->sessionUserBrief();
        $actor = 'Carte web';
        if (is_array($brief)) {
            $cs = trim((string) ($brief['callsign'] ?? ''));
            $dn = trim((string) ($brief['displayName'] ?? ''));
            if ($cs !== '') {
                $actor = $cs;
            } elseif ($dn !== '') {
                $actor = $dn;
            }
        }
        $accepted = 0;
        foreach (array_slice($rawEvents, 0, 40) as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $kind = strtolower(trim((string) ($ev['kind'] ?? $ev['type'] ?? '')));
            $label = trim(strip_tags((string) ($ev['label'] ?? $ev['message'] ?? '')));
            if ($label === '') {
                continue;
            }
            $detail = trim(strip_tags((string) ($ev['detail'] ?? '')));
            $meta = ['source' => 'web'];
            if ($detail !== '') {
                $meta['detail'] = mb_strlen($detail) > 240 ? (mb_substr($detail, 0, 240) . '…') : $detail;
            }
            try {
                if ($kind === 'incident' || $kind === 'error') {
                    $this->activityLog->recordError($tenantId, $mapId, $label, $actor, $meta);
                    $accepted++;
                    $this->recordDeviceTrace($tenantId, [
                        'level' => AtakDeviceLog::LEVEL_ERROR,
                        'channel' => 'Carte',
                        'message' => $label,
                        'detail' => $detail,
                        'source' => AtakDeviceLog::SOURCE_WEB,
                    ], is_array($brief) ? $brief['callsign'] : $actor, $brief);
                } elseif ($kind === 'remontee' || $kind === 'ingest' || $kind === 'donnees') {
                    $ingestKind = strtolower(trim((string) ($ev['ingest_kind'] ?? $ev['ingestKind'] ?? 'web')));
                    $this->activityLog->recordIngest($tenantId, $mapId, $ingestKind !== '' ? $ingestKind : 'web', $label, $actor, $meta);
                    $accepted++;
                    $this->recordDeviceTrace($tenantId, [
                        'level' => AtakDeviceLog::LEVEL_INFO,
                        'channel' => 'Carte',
                        'message' => $label,
                        'detail' => $detail,
                        'source' => AtakDeviceLog::SOURCE_WEB,
                    ], is_array($brief) ? $brief['callsign'] : $actor, $brief);
                }
            } catch (\Throwable) {
            }
        }

        return Response::json(['ok' => true, 'accepted' => $accepted]);
    }

    public function soiPaceIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $repo = new \App\Repositories\AtakSoiPaceRepository();

        return Response::json($repo->get($r, $this->mapId($request)));
    }

    public function soiPaceStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($userId < 1) {
            return Response::json(['error' => 'Authentification requise'], 401);
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $user = $this->userRepository->findById($userId);
        $actor = is_array($user)
            ? (trim((string) ($user['callsign'] ?? '')) ?: trim((string) ($user['display_name'] ?? '')))
            : '';
        $repo = new \App\Repositories\AtakSoiPaceRepository();
        $plan = $repo->save($tenantId, $mapId, $body, $actor !== '' ? $actor : null);
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_TOC_NOTE,
            'Plan de fréquences PACE mis à jour',
            $actor !== '' ? $actor : null,
            ['source' => 'soi_pace']
        );

        return Response::json($plan);
    }

    /** Espace de travail temporaire (bloc-notes + tableurs SOI / ETA / ID alliés) — scopé à la carte. */
    public function sessionWorkspaceIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $repo = new \App\Repositories\AtakSessionWorkspaceRepository();

        return Response::json($repo->get($r, $this->mapId($request)));
    }

    public function sessionWorkspaceStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($userId < 1) {
            return Response::json(['error' => 'Authentification requise'], 401);
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $user = $this->userRepository->findById($userId);
        $actor = is_array($user)
            ? (trim((string) ($user['callsign'] ?? '')) ?: trim((string) ($user['display_name'] ?? '')))
            : '';
        $repo = new \App\Repositories\AtakSessionWorkspaceRepository();
        $workspace = $repo->save($tenantId, $mapId, $body, $actor !== '' ? $actor : null);
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_TOC_NOTE,
            'Notes de session mises à jour',
            $actor !== '' ? $actor : null,
            ['source' => 'session_workspace']
        );

        return Response::json($workspace);
    }

    public function medevacIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $status = $request->query('status');

        return Response::json($this->casRepo->listMedevac($r, $this->mapId($request), is_string($status) ? $status : null));
    }

    public function medevacStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        $author = trim((string) ($body['author'] ?? $body['callsign'] ?? ''));
        if ($author === '') {
            $userId = (int) (Session::get('user_id') ?? 0);
            $user = $userId > 0 ? $this->userRepository->findById($userId) : null;
            $author = is_array($user)
                ? (trim((string) ($user['callsign'] ?? '')) ?: trim((string) ($user['display_name'] ?? 'MEDEVAC')))
                : 'MEDEVAC';
        }
        try {
            $row = $this->casRepo->createMedevac($tenantId, $mapId, $author, $body);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'migration_required') {
                return Response::json([
                    'error' => 'migration_required',
                    'message' => 'Mise à jour base de données requise pour les demandes MEDEVAC. Lancez les migrations.',
                ], 503);
            }
            throw $e;
        }

        // fn_requestMEDEVAC.sqf envoie un 9-line structuré (patients par triage, sécurité LZ,
        // fréquence radio, etc.) mais casRepo->createMedevac() ne lit que line1..line9 : ces champs
        // étaient silencieusement perdus. On les persiste aussi dans le système MEDEVAC étendu
        // (triage/golden hour/assignation) qui existe déjà mais n'était alimenté par aucun endpoint.
        if (isset($body['pickup_grid']) || isset($body['patients_t1_urgent'])) {
            try {
                $extendedRepo = new \App\Repositories\AtakMedevacRepository();
                $extendedRepo->create([
                    'tenant_id' => $tenantId,
                    'context_id' => $mapId,
                    'medevac_number' => $extendedRepo->generateMedevacNumber($tenantId, $mapId),
                    'priority' => $body['priority'] ?? 'URGENT',
                    'pickup_grid' => $body['pickup_grid'] ?? null,
                    'pickup_pos_x' => $body['pickup_pos_x'] ?? null,
                    'pickup_pos_y' => $body['pickup_pos_y'] ?? null,
                    'pickup_elevation' => $body['pickup_elevation'] ?? null,
                    'radio_frequency' => $body['radio_frequency'] ?? null,
                    'radio_callsign' => $body['radio_callsign'] ?? null,
                    'patients_t1_urgent' => $body['patients_t1_urgent'] ?? 0,
                    'patients_t2_urgent' => $body['patients_t2_urgent'] ?? 0,
                    'patients_t3_delayed' => $body['patients_t3_delayed'] ?? 0,
                    'patients_t4_expectant' => $body['patients_t4_expectant'] ?? 0,
                    'patients_litter' => $body['patients_litter'] ?? 0,
                    'patients_ambulatory' => $body['patients_ambulatory'] ?? 0,
                    'security_status' => $body['security_status'] ?? 'NO_ENEMY',
                    'lz_marking' => $body['lz_marking'] ?? 'NONE',
                    'lz_marking_color' => $body['lz_marking_color'] ?? null,
                    'patient_nationality' => $body['patient_nationality'] ?? 'FRIENDLY',
                    'patient_status' => $body['patient_status'] ?? 'MILITARY',
                    'nbc_contamination' => $body['nbc_contamination'] ?? 'NONE',
                    'remarks' => $body['remarks'] ?? $body['notes'] ?? null,
                    'requested_by_callsign' => $body['requested_by_callsign'] ?? $author,
                    'requested_by_unit' => $body['requested_by_unit'] ?? null,
                ]);
            } catch (\Throwable $e) {
                // Best-effort : la table étendue peut ne pas être migrée sur tous les déploiements
                // (migrations/2026_07_24_004_atak_medevac_extended.sql) — ne bloque jamais la réponse
                // principale, qui reste celle du système historique (line1..line9) déjà en production.
            }
        }

        $patientBits = [];
        $t1 = (int) ($body['patients_t1_urgent'] ?? 0);
        $t2 = (int) ($body['patients_t2_urgent'] ?? 0);
        $t3 = (int) ($body['patients_t3_delayed'] ?? 0);
        $totalPatients = $t1 + $t2 + $t3;
        if ($totalPatients > 0) {
            $patientBits[] = $totalPatients . ' blessé' . ($totalPatients > 1 ? 's' : '');
        }
        $gridHint = trim((string) ($body['pickup_grid'] ?? $body['line1'] ?? ''));
        $activityLabel = 'Demande d’évacuation médicale — ' . $author;
        if ($patientBits !== []) {
            $activityLabel .= ' · ' . $patientBits[0];
        }
        if ($gridHint !== '') {
            $activityLabel .= ' · ' . $gridHint;
        }

        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_MEDEVAC,
            $activityLabel,
            $author
        );

        return Response::json($row, 201);
    }

    public function medevacStatus(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $status = strtoupper(trim((string) ($body['status'] ?? '')));
        $row = $this->casRepo->updateCasStatus($r, $id, $status);
        if ($row === null) {
            return Response::json(['error' => 'Introuvable', 'message' => 'Cette demande MEDEVAC est introuvable.'], 404);
        }

        return Response::json($row);
    }

    /** Suppression définitive d’une demande MEDEVAC (9-line). */
    public function medevacDelete(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::json(['error' => 'Introuvable', 'message' => 'Cette demande MEDEVAC est introuvable.'], 404);
        }
        $existing = $this->casRepo->getCas($r, $id);
        if ($existing === null) {
            return Response::json(['error' => 'Introuvable', 'message' => 'Cette demande MEDEVAC est introuvable.'], 404);
        }
        $kind = strtolower(trim((string) ($existing['mission_kind'] ?? $existing['missionKind'] ?? '')));
        // Anciennes lignes sans mission_kind : accepter la suppression via l’endpoint MEDEVAC.
        if ($kind !== '' && $kind !== CasNineLineRepository::KIND_MEDEVAC) {
            return Response::json(['error' => 'Introuvable', 'message' => 'Cette demande MEDEVAC est introuvable.'], 404);
        }
        if (!$this->casRepo->deleteCas($r, $id)) {
            return Response::json(['error' => 'Introuvable', 'message' => 'Cette demande MEDEVAC est introuvable.'], 404);
        }

        return Response::json(['ok' => true, 'deleted' => true, 'id' => $id]);
    }

    /** Compte rendu SALUTE structuré → canal messagerie + journal. */
    public function saluteStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        $callSign = trim((string) ($body['call_sign'] ?? $body['callsign'] ?? $body['author'] ?? ''));
        if ($callSign === '') {
            $userId = (int) (Session::get('user_id') ?? 0);
            $user = $userId > 0 ? $this->userRepository->findById($userId) : null;
            $callSign = is_array($user)
                ? (trim((string) ($user['callsign'] ?? '')) ?: trim((string) ($user['display_name'] ?? 'Observateur')))
                : 'Observateur';
        }
        $fields = [
            'size' => (string) ($body['size'] ?? $body['S'] ?? ''),
            'activity' => (string) ($body['activity'] ?? $body['A'] ?? ''),
            'location' => (string) ($body['location'] ?? $body['L'] ?? ''),
            'unit' => (string) ($body['unit'] ?? $body['U'] ?? ''),
            'time' => (string) ($body['time'] ?? $body['T'] ?? ''),
            'equipment' => (string) ($body['equipment'] ?? $body['E'] ?? ''),
        ];
        $filled = array_filter($fields, static fn ($v) => trim($v) !== '');
        if ($filled === []) {
            return Response::json(['error' => 'Champs requis', 'message' => 'Renseignez au moins un champ du compte rendu SALUTE.'], 422);
        }
        $grid = trim((string) ($body['grid'] ?? ''));
        $posX = $body['pos_x'] ?? $body['x'] ?? null;
        $posY = $body['pos_y'] ?? $body['y'] ?? null;
        $saluteBody = TacticalAlertParser::buildSaluteBody($fields);
        $msg = 'ALERTE TACTIQUE|SALUTE|' . $callSign . '|' . $grid . '|'
            . ($posX !== null && $posX !== '' ? (string) $posX : '') . '|'
            . ($posY !== null && $posY !== '' ? (string) $posY : '') . '|'
            . $saluteBody;
        $source = ComspecApiKeyAuth::extractPresentedKey() !== '' ? 'game' : 'web';
        $row = $this->atak->addChatMessage($tenantId, $mapId, $callSign, $msg, $source);
        $parsed = TacticalAlertParser::enrichChatRow(is_array($row) ? $row : []);
        $summary = is_array($parsed) ? (string) ($parsed['summary'] ?? 'SALUTE') : 'SALUTE';
        $activityMeta = [
            'kind' => 'salute',
            'kind_label' => 'Compte rendu SALUTE',
            'grid' => $grid,
            'summary' => $summary,
        ];
        if (is_array($parsed)) {
            if (!empty($parsed['salute']) && is_array($parsed['salute'])) {
                $activityMeta['salute'] = $parsed['salute'];
            }
            if (isset($parsed['pos_x'])) {
                $activityMeta['pos_x'] = $parsed['pos_x'];
            }
            if (isset($parsed['pos_y'])) {
                $activityMeta['pos_y'] = $parsed['pos_y'];
            }
        }
        if (is_array($row) && !empty($row['id'])) {
            $activityMeta['chat_id'] = (int) $row['id'];
        }
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_TACTICAL_ALERT,
            'SALUTE — ' . $summary,
            $callSign,
            $activityMeta
        );
        if (is_array($row) && is_array($parsed)) {
            $row['tactical'] = $parsed;
        }

        return Response::json($row ?: ['ok' => true], 201);
    }

    /** Synthèse PERSTAT (KIA / WIA / RAS) à partir des unités et alertes médicales. */
    public function perstatIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $units = $this->atak->getUnits($tenantId, $mapId);
        $teams = [];
        $totals = ['ras' => 0, 'wia' => 0, 'kia' => 0, 'offline' => 0, 'total' => 0];

        foreach ($units as $u) {
            $cs = trim((string) ($u['call_sign'] ?? ''));
            if ($cs === '') {
                continue;
            }
            $team = $this->perstatTeamKey($cs);
            if (!isset($teams[$team])) {
                $teams[$team] = ['team' => $team, 'ras' => 0, 'wia' => 0, 'kia' => 0, 'offline' => 0, 'total' => 0, 'members' => []];
            }
            $extra = [];
            $raw = $u['extra'] ?? null;
            if (is_string($raw) && $raw !== '') {
                $d = json_decode($raw, true);
                if (is_array($d)) {
                    $extra = $d;
                }
            } elseif (is_array($raw)) {
                $extra = $raw;
            }
            $health = strtolower(trim((string) ($extra['health'] ?? $u['health'] ?? 'ok')));
            $status = strtolower(trim((string) ($u['status'] ?? '')));
            $bucket = 'ras';
            if (in_array($health, ['dead', 'kia'], true)) {
                $bucket = 'kia';
            } elseif (in_array($health, ['wounded', 'injured', 'unconscious', 'cardiac_arrest', 'cardiac-arrest'], true)) {
                $bucket = 'wia';
            } elseif ($status === 'offline') {
                $bucket = 'offline';
            }
            $teams[$team][$bucket]++;
            $teams[$team]['total']++;
            $teams[$team]['members'][] = [
                'call_sign' => $cs,
                'role' => (string) ($u['role'] ?? ''),
                'status' => $status,
                'health' => $health,
                'bucket' => $bucket,
            ];
            $totals[$bucket]++;
            $totals['total']++;
        }
        ksort($teams);

        return Response::json([
            'totals' => $totals,
            'teams' => array_values($teams),
            'generated_at' => gmdate('c'),
        ]);
    }

    /** Agrégat logistique fuel / munitions depuis les positions BFT. */
    public function logisticsSnapshot(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $units = $this->atak->getUnits($tenantId, $mapId);
        $rows = [];
        $alerts = ['critical' => 0, 'low' => 0];
        foreach ($units as $u) {
            $status = strtolower(trim((string) ($u['status'] ?? '')));
            if ($status === 'offline') {
                continue;
            }
            $extra = [];
            $raw = $u['extra'] ?? null;
            if (is_string($raw) && $raw !== '') {
                $d = json_decode($raw, true);
                if (is_array($d)) {
                    $extra = $d;
                }
            } elseif (is_array($raw)) {
                $extra = $raw;
            }
            $fuelRaw = $extra['fuel'] ?? null;
            $ammoRaw = $extra['ammo'] ?? null;
            $fuel = null;
            if ($fuelRaw !== null && $fuelRaw !== '' && is_numeric($fuelRaw)) {
                $fuel = (float) $fuelRaw;
                if ($fuel <= 1.0) {
                    $fuel *= 100.0;
                }
            }
            $ammo = ($ammoRaw !== null && $ammoRaw !== '' && strtolower((string) $ammoRaw) !== 'n/a')
                ? (string) $ammoRaw
                : null;
            $ammoLevel = $this->ammoStockLevel($ammo);
            if ($fuel === null && $ammo === null) {
                continue;
            }
            $fuelLevel = $fuel === null ? 'unknown' : ($fuel <= 15 ? 'critical' : ($fuel <= 35 ? 'low' : 'ok'));
            $needsResupply = ($fuelLevel === 'critical' || $fuelLevel === 'low' || $ammoLevel === 'critical' || $ammoLevel === 'low');
            if ($fuelLevel === 'critical' || $ammoLevel === 'critical') {
                $alerts['critical']++;
            } elseif ($fuelLevel === 'low' || $ammoLevel === 'low') {
                $alerts['low']++;
            }
            $cs = trim((string) ($u['call_sign'] ?? ''));
            $signal = null;
            if ($fuelLevel === 'critical') {
                $signal = 'Carburant critique';
            } elseif ($ammoLevel === 'critical') {
                $signal = 'Munitions critiques';
            } elseif ($fuelLevel === 'low') {
                $signal = 'Carburant bas';
            } elseif ($ammoLevel === 'low') {
                $signal = 'Munitions basses';
            }
            $rows[] = [
                'call_sign' => $cs,
                'team' => $this->perstatTeamKey($cs),
                'role' => (string) ($u['role'] ?? ''),
                'fuel' => $fuel,
                'ammo' => $ammo,
                'status' => $status,
                'grid_ref' => (string) ($u['grid_ref'] ?? ''),
                'updated_at' => (string) ($u['updated_at'] ?? ''),
                'fuel_level' => $fuelLevel,
                'ammo_level' => $ammoLevel,
                'needs_resupply' => $needsResupply,
                'signal' => $signal,
                'thresholds' => [
                    'fuel_critical' => 15,
                    'fuel_low' => 35,
                ],
            ];
        }
        usort($rows, static function (array $a, array $b): int {
            $rank = static function (array $r): int {
                $f = (string) ($r['fuel_level'] ?? '');
                $aLvl = (string) ($r['ammo_level'] ?? '');
                if ($f === 'critical' || $aLvl === 'critical') {
                    return 0;
                }
                if ($f === 'low' || $aLvl === 'low') {
                    return 1;
                }

                return 2;
            };
            $cmp = $rank($a) <=> $rank($b);
            if ($cmp !== 0) {
                return $cmp;
            }
            $fa = $a['fuel'] ?? 999;
            $fb = $b['fuel'] ?? 999;

            return $fa <=> $fb;
        });

        $medevacOpen = 0;
        $transportHint = null;
        try {
            $medevacs = $this->casRepo->listMedevac($tenantId, $mapId, null);
            if (is_array($medevacs)) {
                foreach ($medevacs as $m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    $st = strtolower(trim((string) ($m['status'] ?? '')));
                    if (in_array($st, ['open', 'pending', 'requested', 'en_route', 'assigned', 'active'], true) || $st === '') {
                        $medevacOpen++;
                    }
                }
            }
        } catch (\Throwable) {
            $medevacOpen = 0;
        }

        $resupply = [];
        try {
            $result = $this->activityLog->listFiltered($tenantId, $mapId, [
                'limit' => 40,
                'type' => AtakActivityLogService::TYPE_TOC_NOTE,
            ]);
            $events = is_array($result['events'] ?? null) ? $result['events'] : [];
            foreach ($events as $ev) {
                if (!is_array($ev)) {
                    continue;
                }
                $meta = is_array($ev['meta'] ?? null) ? $ev['meta'] : [];
                if (($meta['kind'] ?? '') !== 'resupply_request') {
                    continue;
                }
                $resupply[] = [
                    'id' => (int) ($ev['id'] ?? 0),
                    'at' => (string) ($ev['at'] ?? ''),
                    'call_sign' => (string) ($meta['call_sign'] ?? $ev['actor'] ?? ''),
                    'need' => (string) ($meta['need'] ?? 'ravitaillement'),
                    'note' => (string) ($meta['note'] ?? ''),
                    'grid_ref' => (string) ($meta['grid_ref'] ?? ''),
                ];
                if (count($resupply) >= 12) {
                    break;
                }
            }
        } catch (\Throwable) {
            $resupply = [];
        }

        if ($medevacOpen > 0) {
            $transportHint = $medevacOpen === 1
                ? '1 évacuation sanitaire en cours — vérifier le volet médical.'
                : $medevacOpen . ' évacuations sanitaires en cours — vérifier le volet médical.';
        }

        return Response::json([
            'rows' => $rows,
            'count' => count($rows),
            'alerts' => $alerts,
            'low_stock_count' => $alerts['critical'] + $alerts['low'],
            'resupply_requests' => $resupply,
            'medevac_open' => $medevacOpen,
            'transport_hint' => $transportHint,
            'generated_at' => gmdate('c'),
        ]);
    }

    /**
     * Demande de ravitaillement depuis le TOC (liée au suivi logistique).
     * POST /api/atak/logistics/resupply
     */
    public function logisticsResupplyRequest(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? $this->mapId($request));
        $callSign = trim((string) ($body['call_sign'] ?? $body['callSign'] ?? ''));
        $need = trim((string) ($body['need'] ?? 'ravitaillement'));
        $note = trim((string) ($body['note'] ?? ''));
        $grid = trim((string) ($body['grid_ref'] ?? $body['grid'] ?? ''));
        if ($callSign === '') {
            return Response::json([
                'error' => 'call_sign_required',
                'message' => 'Indiquez l’indicatif concerné par le ravitaillement.',
            ], 400);
        }
        $needKey = strtolower($need);
        $needLabel = match (true) {
            str_contains($needKey, 'fuel') || str_contains($needKey, 'carbur') => 'Carburant',
            str_contains($needKey, 'ammo') || str_contains($needKey, 'munition') => 'Munitions',
            str_contains($needKey, 'both') || str_contains($needKey, 'complet') => 'Carburant et munitions',
            default => 'Ravitaillement',
        };
        $label = 'Demande de ravitaillement — ' . $callSign . ' · ' . $needLabel;
        if ($grid !== '') {
            $label .= ' · ' . $grid;
        }
        $this->activityLog->record(
            $tenantId,
            $mapId > 0 ? $mapId : self::DEFAULT_MAP_ID,
            AtakActivityLogService::TYPE_TOC_NOTE,
            $label,
            $callSign,
            [
                'kind' => 'resupply_request',
                'call_sign' => $callSign,
                'need' => $needLabel,
                'note' => $note !== '' ? mb_substr($note, 0, 500) : '',
                'grid_ref' => $grid,
            ]
        );

        return Response::json([
            'ok' => true,
            'message' => 'Demande de ravitaillement enregistrée pour ' . $callSign . '.',
        ], 201);
    }

    private function ammoStockLevel(?string $ammo): string
    {
        if ($ammo === null || $ammo === '') {
            return 'unknown';
        }
        $raw = strtolower(trim($ammo));
        if (in_array($raw, ['empty', '0', 'winchester', 'out', 'vide', 'épuisé', 'epuise'], true)) {
            return 'critical';
        }
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*%?$/', $raw, $m)) {
            $n = (float) str_replace(',', '.', $m[1]);
            if ($n <= 1.0) {
                $n *= 100.0;
            }
            if ($n <= 15) {
                return 'critical';
            }
            if ($n <= 35) {
                return 'low';
            }

            return 'ok';
        }
        if (preg_match('/(low|bas|faible|limited|critique|critical)/', $raw)) {
            return str_contains($raw, 'crit') ? 'critical' : 'low';
        }
        if (preg_match('/(full|plein|ok|green)/', $raw)) {
            return 'ok';
        }

        return 'unknown';
    }

    private function perstatTeamKey(string $callSign): string
    {
        $cs = trim($callSign);
        if ($cs === '') {
            return 'Autres';
        }
        if (preg_match('/^([A-Za-z]+)/', $cs, $m)) {
            return strtoupper($m[1]);
        }
        if (str_contains($cs, '-')) {
            return strtoupper(explode('-', $cs, 2)[0]);
        }

        return 'Autres';
    }

    /**
     * @param list<array<string, mixed>> $primary
     * @param list<array<string, mixed>> $extra
     * @return list<array<string, mixed>>
     */
    private function mergeActivityEvents(array $primary, array $extra, int $limit, ?int $beforeId, ?int $afterId): array
    {
        $merged = array_merge($primary, $extra);
        usort($merged, static function (array $a, array $b): int {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''))
                ?: (((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0)));
        });
        if ($beforeId !== null && $beforeId > 0) {
            $merged = array_values(array_filter(
                $merged,
                static fn ($e) => is_array($e) && (int) ($e['id'] ?? 0) < $beforeId
            ));
        }
        if ($afterId !== null && $afterId > 0) {
            $merged = array_values(array_filter(
                $merged,
                static fn ($e) => is_array($e) && (int) ($e['id'] ?? 0) > $afterId
            ));
        }

        return array_slice($merged, 0, max(1, min(200, $limit)));
    }

    /**
     * Signal d’initialisation explicite depuis le mod.
     * Émet un jeton de session court si Steam fourni et lié à la communauté.
     */
    public function clientInit(Request $request, array $params = []): Response
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
        $callSign = trim((string) ($body['call_sign'] ?? $body['callsign'] ?? ''));

        $steamRaw = $this->armaGuard->extractSteamRaw($request, $body);
        $steam = $steamRaw !== '' ? SteamId::normalize($steamRaw) : null;
        if ($steamRaw !== '' && $steam === null) {
            $this->activityLog?->recordAuthAttempt($tenantId, false, 'Initialisation jeu refusée — Steam invalide', [
                'reason' => 'invalid_steam_uid',
            ]);

            return Response::json([
                'error' => 'invalid_steam_uid',
                'message' => 'Identifiant Steam non reconnu. Relancez la liaison depuis Athena.',
            ], 400);
        }

        $sessionToken = '';
        $expiresIn = 0;
        $requireSteam = ComspecApiKeyAuth::matchedTenantId() !== null
            || filter_var((string) (($_ENV['ATAK_ARMA_REQUIRE_STEAM'] ?? null) ?: (getenv('ATAK_ARMA_REQUIRE_STEAM') ?: '')), FILTER_VALIDATE_BOOLEAN);
        if ($steam === null && $requireSteam) {
            $this->activityLog?->recordAuthAttempt($tenantId, false, 'Initialisation jeu refusée — Steam manquant', [
                'reason' => 'steam_required',
            ]);

            return Response::json([
                'error' => 'steam_required',
                'message' => 'Identifiant Steam requis. Mettez à jour le mod Overwatch, puis reconnectez-vous.',
            ], 403);
        }
        if ($steam !== null) {
            $user = $this->userRepository->findBySteamIdForTenant($tenantId, $steam);
            if ($user === null) {
                $this->activityLog?->recordAuthAttempt($tenantId, false, 'Initialisation jeu refusée — Steam non lié', [
                    'reason' => 'steam_not_linked',
                ]);

                return Response::json([
                    'error' => 'steam_not_linked',
                    'message' => 'Aucun compte Athena n’est lié à ce Steam pour cette communauté. Utilisez un code de liaison ou liez Steam dans votre profil.',
                ], 403);
            }
            $status = strtolower(trim((string) ($user['status'] ?? 'active')));
            if (in_array($status, ['banned', 'disabled', 'suspended', 'deleted'], true)) {
                $this->activityLog?->recordAuthAttempt($tenantId, false, 'Initialisation jeu refusée — compte non autorisé', [
                    'reason' => 'account_disabled',
                ]);

                return Response::json([
                    'error' => 'account_disabled',
                    'message' => 'Ce compte Athena n’est pas autorisé.',
                ], 403);
            }
            $modBlock = $this->armaGuard->assertModNotBlocked($tenantId, $steam);
            if ($modBlock instanceof Response) {
                return $modBlock;
            }
            if ($callSign === '') {
                $fromProfile = trim((string) ($user['callsign'] ?? ''));
                // Ne jamais utiliser display_name (ex. Newp1) : ça crée un 2ᵉ contact fantôme
                // à côté de l’indicatif tactique (N-10). Fallback stable par compte.
                if ($fromProfile === '') {
                    $uid = (int) ($user['id'] ?? 0);
                    $fromProfile = $uid > 0 ? sprintf('U-%05d', $uid) : '';
                }
                if ($fromProfile !== '') {
                    $callSign = $fromProfile;
                }
            }
            $issued = AtakGameSession::issue($tenantId, $steam, ComspecApiKeyAuth::extractPresentedKey());
            $sessionToken = $issued['token'];
            $expiresIn = $issued['expires_in'];
        }

        $this->activityLog->recordClientInit(
            $tenantId,
            $mapId,
            $this->activityLog->clientKeyFromRequest(),
            $callSign !== '' ? $callSign : null,
            $this->buildActivityMeta($tenantId, $mapId, $body, [
                'steam_uid' => $steam,
            ], $callSign !== '' ? $callSign : null)
        );
        try {
            // client-init = toujours le mod Athena ; cTab / Enhanced si le handshake les annonce.
            $this->activityLog->touchModDetection($tenantId, $mapId, [
                'mod_athena' => true,
                'has_ctab' => $this->truthyFlag($body['has_ctab'] ?? false),
                'has_atak_enhanced' => $this->truthyFlag($body['has_atak_enhanced'] ?? false),
                'has_athena_ctab' => $this->truthyFlag($body['has_athena_ctab'] ?? false),
            ]);
        } catch (\Throwable) {
        }

        $payload = ['ok' => true];
        if ($sessionToken !== '') {
            $payload['session_token'] = $sessionToken;
            $payload['expires_in'] = $expiresIn;
            $payload['steam_uid'] = $steam;
        }
        if ($callSign !== '') {
            $payload['call_sign'] = $callSign;
            $payload['callsign'] = $callSign;
        }
        if ($steam !== null && isset($user) && is_array($user)) {
            try {
                $live = \App\Core\Container::get(\App\Services\MissionPlanning\MissionPlanningLiveService::class);
                $sync = $live->onPlayerConnected($tenantId, $user, $steam, $callSign);
                if (!empty($sync['callsign'])) {
                    $slotCs = (string) $sync['callsign'];
                    $payload['call_sign'] = $slotCs;
                    $payload['callsign'] = $slotCs;
                    $callSign = $slotCs;
                }
                $payload['mission_slot'] = [
                    'status' => (string) ($sync['status'] ?? 'none'),
                    'callsign' => $sync['callsign'] ?? null,
                ];
            } catch (\Throwable) {
            }
        }
        // ID BFT lié à l’indicatif (même identité TOC / carte / terminal).
        if ($steam !== null && isset($user) && is_array($user)) {
            try {
                $opIds = $this->operatorIdRepository ?? new AtakOperatorIdRepository();
                if ($opIds->tablesReady()) {
                    $uid = (int) ($user['id'] ?? 0);
                    $mid = $uid > 0
                        ? $opIds->ensureForUser($tenantId, $uid, $callSign !== '' ? $callSign : null)
                        : ($callSign !== '' ? $opIds->ensureForCallSign($tenantId, $callSign) : '');
                    if ($mid !== '') {
                        $payload['military_id'] = $mid;
                        $payload['bft_id'] = $mid;
                        $payload['atak_id'] = $callSign !== '' ? $callSign : $mid;
                    }
                }
            } catch (\Throwable) {
            }
            $this->persistArmaBloodType((int) ($user['id'] ?? 0), $body);
        } elseif ($callSign !== '') {
            try {
                $opIds = $this->operatorIdRepository ?? new AtakOperatorIdRepository();
                if ($opIds->tablesReady()) {
                    $mid = $opIds->ensureForCallSign($tenantId, $callSign);
                    $payload['military_id'] = $mid;
                    $payload['bft_id'] = $mid;
                    $payload['atak_id'] = $callSign;
                }
            } catch (\Throwable) {
            }
        }

        try {
            $expSvc = new \App\Services\Tactical\AtakExperienceService();
            $payload['experience'] = $expSvc->payloadForGame($tenantId);
        } catch (\Throwable) {
        }

        $termUid = trim((string) ($body['terminal_uid'] ?? $body['terminalUid'] ?? ''));
        try {
            $uid = $termUid !== '' ? $termUid : $this->resolveDeviceTerminalUid($tenantId, '', $callSign);
            if ($uid !== '') {
                $this->deviceLogs()->recordEvent($tenantId, $uid, [
                    'level' => AtakDeviceLog::LEVEL_INFO,
                    'channel' => 'Etat',
                    'message' => ($callSign !== '' ? $callSign . ' — ' : '') . 'Liaison établie avec Athena',
                    'source' => AtakDeviceLog::SOURCE_SYSTEM,
                ], $callSign !== '' ? $callSign : null);
            }
            $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
            $host = preg_replace('/:\d+$/', '', $host) ?? $host;
            $this->realismRegistry()->recordTerminalHeartbeat($tenantId, [
                'terminal_uid' => $uid,
                'operator_callsign' => $callSign,
                'mod_version' => trim((string) ($body['mod_version'] ?? $body['modVersion'] ?? '')),
                'extension_version' => trim((string) ($body['extension_version'] ?? $body['dll_version'] ?? '')),
                'last_client_ip' => $request->ip(),
                'server_host' => $host,
            ]);
        } catch (\Throwable) {
        }

        return Response::json($payload);
    }

    /**
     * Déconnexion explicite depuis le mod (sortie mission / quit Arma).
     * Marque l’unité hors ligne et retire la session d’activité.
     */
    public function disconnect(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $actor = $this->guardArmaWrite($request, $tenantId, false);
        if ($actor instanceof Response) {
            return $actor;
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $callSign = trim((string) ($body['call_sign'] ?? $body['callsign'] ?? ''));
        $activityMeta = $this->buildActivityMeta($tenantId, $mapId, $body, is_array($actor) ? $actor : null, $callSign !== '' ? $callSign : null);
        $resolved = '';
        try {
            $resolved = $this->activityLog->recordDisconnect(
                $tenantId,
                $mapId,
                $this->activityLog->clientKeyFromRequest(),
                $callSign !== '' ? $callSign : null,
                $activityMeta
            );
        } catch (\Throwable) {
            $resolved = $callSign;
        }
        if ($resolved !== '') {
            try {
                $this->atak->markUnitOfflineByCallSign($tenantId, $mapId, $resolved);
            } catch (\Throwable) {
            }
            try {
                $live = \App\Core\Container::get(\App\Services\MissionPlanning\MissionPlanningLiveService::class);
                $live->onPlayerDisconnected($tenantId, $resolved);
            } catch (\Throwable) {
            }
            // Hors liaison : masquer les alertes actives (bannière / À secourir), archive intacte.
            try {
                $this->autoResolveOpenMedicalAlertsForCallSign(
                    $tenantId,
                    $mapId,
                    $resolved,
                    'Opérateur hors liaison — alertes actives clôturées'
                );
            } catch (\Throwable) {
            }
        }

        $this->recordDeviceTrace($tenantId, [
            'level' => AtakDeviceLog::LEVEL_WARN,
            'channel' => 'Etat',
            'message' => ($resolved !== '' ? $resolved . ' — ' : '') . 'Fin de liaison (déconnexion du jeu)',
            'source' => AtakDeviceLog::SOURCE_SYSTEM,
        ], $resolved !== '' ? $resolved : $callSign);

        return Response::json(['ok' => true]);
    }

    public function stats(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $empty = [
            'sockets' => 0,
            'lastArmaActivity' => null,
            'lastArmaActivityAgo' => null,
            'unitsCount' => 0,
            'activeCallSigns' => [],
            'transmissions' => [],
            'link_telemetry' => null,
            'measured_packet_loss' => null,
        ];
        try {
            $mapId = $this->mapId($request);
            $last = $this->resolveLatestArmaActivity($tenantId, $mapId);
            $ago = null;
            if ($last !== null) {
                $ago = (int) (time() - strtotime($last));
            }
            $units = $this->unitsForTransmission($tenantId, $mapId);
            $unitsCount = count(array_filter(
                $units,
                static fn ($u) => is_array($u) && (string) ($u['status'] ?? '') === 'linked'
            ));
            $activeCallSigns = $this->atak->getActiveUnitsSummary($tenantId, $mapId, 15);
            if ($mapId !== self::DEFAULT_MAP_ID) {
                $modSummary = $this->atak->getActiveUnitsSummary($tenantId, self::DEFAULT_MAP_ID, 15);
                if ($modSummary !== []) {
                    $seen = [];
                    foreach ($activeCallSigns as $row) {
                        $cs = strtolower(trim((string) ($row['call_sign'] ?? '')));
                        if ($cs !== '') {
                            $seen[$cs] = true;
                        }
                    }
                    foreach ($modSummary as $row) {
                        $cs = strtolower(trim((string) ($row['call_sign'] ?? '')));
                        if ($cs === '' || isset($seen[$cs])) {
                            continue;
                        }
                        $activeCallSigns[] = $row;
                        $seen[$cs] = true;
                    }
                    $activeCallSigns = array_slice($activeCallSigns, 0, 15);
                }
            }
            $transmissions = $this->buildTransmissionSources($tenantId, $mapId, $ago, $units);
            $linkTelemetry = $this->getLatestLinkTelemetry($tenantId, $mapId);

            return Response::json([
                'sockets' => 0,
                'lastArmaActivity' => $last,
                'lastArmaActivityAgo' => $ago,
                'unitsCount' => $unitsCount,
                'activeCallSigns' => $activeCallSigns,
                'transmissions' => $transmissions,
                'link_telemetry' => $linkTelemetry,
                'measured_packet_loss' => $linkTelemetry,
            ]);
        } catch (\Throwable) {
            return Response::json($empty);
        }
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
        try {
            $rows = $this->atak->getMarkers($tenantId, $mapId, $since);
        } catch (\Throwable) {
            return Response::json([
                'ok' => false,
                'error' => 'database_unavailable',
                'message' => 'Impossible de charger les marqueurs pour le moment.',
            ], 503);
        }
        $out = array_map(fn ($r) => ['id' => $r['id'], 'layerId' => $r['layerId'], 'markerData' => $r['markerData'], 'updated_at' => $r['updated_at']], $rows);
        $out = $this->applyIntelScramble($request, $tenantId, $mapId, 'marker', $out);

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
        $rawMarker = $body['markerData'] ?? '{}';
        $decoded = [];
        if (is_array($rawMarker)) {
            $decoded = $rawMarker;
        } elseif (is_string($rawMarker) && trim($rawMarker) !== '') {
            $parsed = json_decode($rawMarker, true);
            $decoded = is_array($parsed) ? $parsed : [];
        }
        if (empty($decoded['source'])) {
            $decoded['source'] = 'web';
        }
        if (empty($decoded['type'])) {
            $decoded['type'] = 'manual';
        }
        $markerData = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $row = $this->atak->addMarker($tenantId, $mapId, $layerId, $markerData);
        $author = trim((string) ($decoded['author'] ?? $decoded['callsign'] ?? ''));
        $label = trim((string) ($decoded['label'] ?? $decoded['text'] ?? 'Marqueur'));
        if ($label === '') {
            $label = 'Marqueur';
        }
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_MARKER,
            'Marqueur placé — ' . $label,
            $author !== '' ? $author : null,
            [
                'source' => 'web',
                'grid' => $decoded['grid'] ?? null,
                'label' => $label,
            ]
        );
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

        return Response::json(['ok' => true]);
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
        $actor = $this->guardArmaWrite($request, $tenantId, false);
        if ($actor instanceof Response) {
            return $actor;
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? self::DEFAULT_MAP_ID);
        $layerId = (int) ($body['layerId'] ?? 1);
        $armaName = $body['arma_name'] ?? $body['armaName'] ?? null;
        if ($armaName === null || $armaName === '') {
            return Response::json(['error' => 'arma_name required'], 400);
        }
        $deleted = !empty($body['deleted']) || (($body['action'] ?? '') === 'delete');
        if ($deleted) {
            $ok = $this->atak->deleteMarkerByArmaName($tenantId, $mapId, (string) $armaName);
            if ($ok) {
                $removedLabel = ArmaMarkerLabel::displayLabel((string) $armaName, []);
                $this->activityLog->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_MARKER,
                    'Marqueur retiré — ' . $removedLabel,
                    is_string($actor) ? $actor : null
                );
            }

            return Response::json(['ok' => true, 'deleted' => $ok]);
        }
        $existingRow = $this->atak->findMarkerByArmaName($tenantId, $mapId, (string) $armaName);
        $markerData = $this->normalizeArmaMarkerData($body['markerData'] ?? '{}', (string) $armaName);
        $row = $this->atak->upsertMarkerByArmaName($tenantId, $mapId, $layerId, (string) $armaName, $markerData);
        $decoded = json_decode($markerData, true);
        $decoded = is_array($decoded) ? $decoded : [];
        $label = ArmaMarkerLabel::displayLabel((string) $armaName, $decoded);
        $logActor = ArmaMarkerLabel::actorFromMarker($decoded, is_string($actor) ? $actor : '');

        $prevLabel = '';
        if ($existingRow !== null) {
            $prevDecoded = json_decode((string) ($existingRow['marker_data'] ?? ''), true);
            if (is_array($prevDecoded)) {
                $prevLabel = ArmaMarkerLabel::displayLabel((string) $armaName, $prevDecoded);
            }
        }
        if ($existingRow === null || $label !== $prevLabel) {
            $this->activityLog->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_MARKER,
                'Marqueur placé — ' . $label,
                $logActor
            );
        }
        return Response::json(['id' => $row['id'], 'layerId' => $row['layerId'], 'markerData' => $row['markerData']], 201);
    }

    /**
     * Normalise le blob marqueur Arma (pos/type/color/text) pour le rendu web.
     *
     * @param mixed $raw
     */
    private function normalizeArmaMarkerData(mixed $raw, string $armaName): string
    {
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                $decoded = [];
            } else {
                $decoded = json_decode($trimmed, true);
                if (!is_array($decoded)) {
                    // Locale FR : virgules décimales hors chaînes
                    $fixed = preg_replace('/(?<=\d),(?=\d)/', '.', $trimmed) ?? $trimmed;
                    $decoded = json_decode($fixed, true);
                }
                if (!is_array($decoded)) {
                    $decoded = [];
                }
            }
        } elseif (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = [];
        }

        if (!isset($decoded['source']) || trim((string) $decoded['source']) === '') {
            $decoded['source'] = 'arma';
        }
        if (empty($decoded['text']) && empty($decoded['label']) && $armaName !== '') {
            if (!str_starts_with($armaName, 'comspec_') && !ArmaMarkerLabel::isTechnicalName($armaName)) {
                $decoded['text'] = $armaName;
            }
        }
        $resolvedText = ArmaMarkerLabel::displayLabel($armaName, $decoded);
        if ($resolvedText !== '' && (empty($decoded['text']) || ArmaMarkerLabel::isTechnicalName((string) ($decoded['text'] ?? '')))) {
            $decoded['text'] = $resolvedText;
        }
        // Alias label → text pour le rendu web unifié.
        if (empty($decoded['text']) && !empty($decoded['label'])) {
            $decoded['text'] = (string) $decoded['label'];
        }
        if (isset($decoded['pos']) && is_array($decoded['pos'])) {
            $decoded['pos'] = array_map(static function ($v) {
                if (is_string($v)) {
                    $v = str_replace(',', '.', $v);
                }

                return is_numeric($v) ? (float) $v : $v;
            }, $decoded['pos']);
        }

        // Type CfgMarkers : clé normalisée (mil_warning, hd_dot, b_inf…).
        if (!empty($decoded['type']) && is_string($decoded['type'])) {
            $decoded['type'] = strtolower(trim(str_replace([' ', '-'], '_', $decoded['type'])));
        } elseif (!empty($decoded['icon']) && is_string($decoded['icon']) && empty($decoded['type'])) {
            $decoded['type'] = strtolower(trim(str_replace([' ', '-'], '_', $decoded['icon'])));
        }

        // Texture PAA Arma → pngUrl CDN (Phase 0 miroir icônes).
        $textureRaw = '';
        if (!empty($decoded['texture']) && is_string($decoded['texture'])) {
            $textureRaw = trim(str_replace('\\', '/', (string) $decoded['texture']));
        } elseif (!empty($decoded['iconPath']) && is_string($decoded['iconPath'])) {
            $textureRaw = trim(str_replace('\\', '/', (string) $decoded['iconPath']));
        }
        if ($textureRaw !== '') {
            $decoded['texture'] = $textureRaw;
            if (empty($decoded['pngUrl']) || !is_string($decoded['pngUrl'])) {
                $png = function_exists('atak_marker_icon_url') ? atak_marker_icon_url($textureRaw) : null;
                if (is_string($png) && $png !== '') {
                    $decoded['pngUrl'] = $png;
                }
            }
        } elseif (!empty($decoded['pngUrl']) && is_string($decoded['pngUrl'])) {
            $decoded['pngUrl'] = trim((string) $decoded['pngUrl']);
        } elseif (!empty($decoded['type']) && is_string($decoded['type']) && function_exists('atak_marker_icon_relpath_from_type')) {
            $fromType = atak_marker_icon_relpath_from_type((string) $decoded['type']);
            if (is_string($fromType) && $fromType !== '' && function_exists('atak_marker_icon_url')) {
                $png = atak_marker_icon_url($fromType);
                if (is_string($png) && $png !== '') {
                    $decoded['pngUrl'] = $png;
                }
            }
        }

        // Couleur Arma (ColorRed / ColorWEST…) → hex stable pour le miroir web.
        if (!empty($decoded['color']) && is_string($decoded['color'])) {
            $decoded['color'] = $this->normalizeArmaMarkerColor((string) $decoded['color']);
        }

        if (!empty($decoded['shape']) && is_string($decoded['shape'])) {
            $decoded['shape'] = strtoupper(trim((string) $decoded['shape']));
        }
        if (isset($decoded['dir']) || isset($decoded['heading'])) {
            $dir = $decoded['dir'] ?? $decoded['heading'];
            if (is_string($dir)) {
                $dir = str_replace(',', '.', $dir);
            }
            if (is_numeric($dir)) {
                $n = fmod((float) $dir, 360.0);
                if ($n < 0) {
                    $n += 360.0;
                }
                $decoded['dir'] = $n;
            }
        }
        if (isset($decoded['alpha'])) {
            $a = $decoded['alpha'];
            if (is_string($a)) {
                $a = str_replace(',', '.', $a);
            }
            if (is_numeric($a)) {
                $decoded['alpha'] = max(0.0, min(1.0, (float) $a));
            }
        }

        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '{}';
    }

    /** Couleurs CfgMarkerColors / noms courants → hex. */
    private function normalizeArmaMarkerColor(string $colorName): string
    {
        $key = strtolower(trim($colorName));
        if ($key !== '' && $key[0] === '#') {
            return $key;
        }
        // Déjà un hex sans # (rare)
        if (preg_match('/^[0-9a-f]{6}$/i', $key)) {
            return '#' . $key;
        }
        static $map = [
            'colorred' => '#d9534f',
            'colorblue' => '#4e9de0',
            'colorgreen' => '#4ec94e',
            'coloryellow' => '#e7cc5b',
            'colororange' => '#e9974a',
            'colorpink' => '#a78bfa',
            'colorpurple' => '#a78bfa',
            'colorbrown' => '#a16207',
            'colorkhaki' => '#a16207',
            'colorwhite' => '#f2f2f2',
            'colorblack' => '#222222',
            'colorgrey' => '#b9b9b9',
            'colorgray' => '#b9b9b9',
            'colorwest' => '#4e9de0',
            'colorblufor' => '#4e9de0',
            'coloreast' => '#d9534f',
            'coloropfor' => '#d9534f',
            'colorguer' => '#4ec94e',
            'colorindependent' => '#4ec94e',
            'colorresistance' => '#4ec94e',
            'colorciv' => '#cfcfcf',
            'colorcivilian' => '#cfcfcf',
            'colorunknown' => '#b9b9b9',
            'default' => '#ef4444',
        ];
        $compact = preg_replace('/[^a-z0-9]/', '', $key) ?? $key;
        if (isset($map[$compact])) {
            return $map[$compact];
        }
        foreach (['blufor', 'west', 'opfor', 'east', 'independent', 'guer', 'civilian', 'civ', 'unknown', 'red', 'blue', 'green', 'yellow', 'orange', 'white', 'black'] as $needle) {
            if (str_contains($compact, $needle)) {
                return $map['color' . $needle] ?? $map[$needle] ?? '#ef4444';
            }
        }

        return $colorName !== '' ? $colorName : '#ef4444';
    }

    public function unitsIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        
        // Simulation roleplay
        $roleplayResponse = $this->applyRoleplayEffects($tenantId);
        if ($roleplayResponse !== null) {
            return $roleplayResponse;
        }
        
        $mapId = $this->mapId($request);
        try {
            $rows = $this->unitsForTransmission($tenantId, $mapId);
            $this->logStaleUnitDisconnects($tenantId, $mapId);
        } catch (\Throwable) {
            // Ne pas renvoyer une liste vide : le poste prendrait ça pour « plus personne ».
            $includeGateway = $request->query('include_gateway') === '1'
                || $request->query('includeGateway') === '1'
                || $request->query('gateway') === '1';
            if ($includeGateway) {
                return Response::json([
                    'ok' => false,
                    'unavailable' => true,
                ]);
            }

            return Response::json([]);
        }
        try {
            $opIds = $this->operatorIdRepository ?? new AtakOperatorIdRepository();
            if ($opIds->tablesReady() && $opIds->unitsMilitaryIdColumnReady()) {
            foreach ($rows as &$row) {
                $mid = trim((string) ($row['military_id'] ?? ''));
                $cs = trim((string) ($row['call_sign'] ?? ''));
                $extra = AtakDataRepository::decodeExtra($row['extra'] ?? null);
                $extraMid = trim((string) ($extra['bft_id'] ?? $extra['military_id'] ?? ''));
                if ($mid === '' && $extraMid !== '') {
                    $mid = $extraMid;
                    $row['military_id'] = $mid;
                }
                if ($mid === '' && $cs !== '') {
                    $steam = trim((string) ($extra['steam_uid'] ?? $extra['steamId'] ?? $extra['player_uid'] ?? ''));
                    $userId = null;
                    if ($steam !== '') {
                        try {
                            $user = $this->userRepository->findBySteamIdForTenant($tenantId, $steam)
                                ?? $this->userRepository->findBySteamId($steam);
                            if (is_array($user)) {
                                $uid = (int) ($user['id'] ?? 0);
                                if ($uid > 0) {
                                    $userId = $uid;
                                }
                            }
                        } catch (\Throwable) {
                            $userId = null;
                        }
                    }
                    $mid = $opIds->syncUnitMilitaryId($tenantId, (int) ($row['id'] ?? 0), $cs, $userId);
                    $row['military_id'] = $mid;
                }
                // Miroir lecture seule pour l’UI (pas d’écriture DB à chaque poll).
                if ($mid !== '') {
                    $row['bft_id'] = $mid;
                    if ($extraMid === '') {
                        $extra['bft_id'] = $mid;
                        $extra['military_id'] = $mid;
                        $row['extra'] = $extra;
                    }
                }
            }
            unset($row);
            }
        } catch (\Throwable) {
        }

        // Filtre opt-in : ne pas casser Tacmap (qui n’envoie pas ce paramètre).
        $onlineOnly = $request->query('online_only') === '1'
            || $request->query('onlineOnly') === '1'
            || $request->query('live_only') === '1';
        if ($onlineOnly) {
            $rows = array_values(array_filter(
                $rows,
                static fn ($u): bool => is_array($u) && in_array((string) ($u['status'] ?? ''), ['linked', 'delayed'], true)
            ));
        }

        try {
            $rows = $this->enrichUnitsWithFireTeams($tenantId, $rows);
        } catch (\Throwable) {
        }
        try {
            $rows = $this->motionService()->attachToUnits($tenantId, $mapId, $rows);
        } catch (\Throwable) {
        }

        $includeGateway = $request->query('include_gateway') === '1'
            || $request->query('includeGateway') === '1'
            || $request->query('gateway') === '1';
        if ($includeGateway) {
            try {
                $rows = array_merge($rows, $this->collectGatewayMirrorUnits($tenantId));
            } catch (\Throwable) {
            }
        }

        try {
            $rows = $this->applyIntelScramble($request, $tenantId, $mapId, 'unit', $rows);
        } catch (\Throwable) {
        }

        return Response::json($rows);
    }

    /**
     * Passerelles actives pour le tenant courant (lecture).
     */
    public function gatewaysIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        try {
            $svc = \App\Core\Container::get(\App\Services\Tactical\AtakMapGatewayService::class);
            if (!$svc->schemaReady()) {
                return Response::json(['items' => [], 'ready' => false]);
            }
            $items = $svc->listDecoratedForTenant($r);
            $active = array_values(array_filter(
                $items,
                static fn (array $g): bool => ($g['status'] ?? '') === 'active'
            ));

            return Response::json(['ready' => true, 'items' => $items, 'active' => $active]);
        } catch (\Throwable) {
            return Response::json(['items' => [], 'ready' => false]);
        }
    }

    /**
     * Unités miroir (lecture seule) des communautés liées par passerelle active.
     */
    public function gatewaysMirrorUnits(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }

        return Response::json($this->collectGatewayMirrorUnits($r));
    }

    /**
     * Marqueurs miroir (lecture seule) si le périmètre le permet.
     */
    public function gatewaysMirrorMarkers(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }

        return Response::json($this->collectGatewayMirrorMarkers($r));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectGatewayMirrorUnits(int $tenantId): array
    {
        try {
            $repo = \App\Core\Container::get(\App\Repositories\AtakMapGatewayRepository::class);
            if (!$repo->schemaReady()) {
                return [];
            }
            $out = [];
            foreach ($repo->listActiveForTenant($tenantId) as $gw) {
                if (empty($gw['share_units'])) {
                    continue;
                }
                $peerId = $repo->peerTenantId($gw, $tenantId);
                if ($peerId < 1) {
                    continue;
                }
                $peerMap = $repo->peerMapId($gw, $tenantId);
                $peerUnits = $this->atak->getUnits($peerId, $peerMap);
                $peerLabel = 'Allié';
                try {
                    $peerTenant = $this->tenantRepository->findById($peerId);
                    if (is_array($peerTenant)) {
                        $peerLabel = community_display_name($peerTenant);
                    }
                } catch (\Throwable) {
                }
                foreach ($peerUnits as $u) {
                    if (!is_array($u)) {
                        continue;
                    }
                    $u['gateway_id'] = (int) ($gw['id'] ?? 0);
                    $u['gateway_partner'] = true;
                    $u['gateway_peer_tenant_id'] = $peerId;
                    $u['gateway_peer_label'] = $peerLabel;
                    $u['id'] = 'gw-' . (int) ($gw['id'] ?? 0) . '-' . (string) ($u['id'] ?? '0');
                    $cs = trim((string) ($u['call_sign'] ?? ''));
                    if ($cs !== '') {
                        $u['call_sign'] = $cs . ' · ' . $peerLabel;
                    }
                    $out[] = $u;
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectGatewayMirrorMarkers(int $tenantId): array
    {
        try {
            $repo = \App\Core\Container::get(\App\Repositories\AtakMapGatewayRepository::class);
            if (!$repo->schemaReady()) {
                return [];
            }
            $out = [];
            foreach ($repo->listActiveForTenant($tenantId) as $gw) {
                if (empty($gw['share_markers'])) {
                    continue;
                }
                $peerId = $repo->peerTenantId($gw, $tenantId);
                if ($peerId < 1) {
                    continue;
                }
                $peerMap = $repo->peerMapId($gw, $tenantId);
                $markers = $this->atak->getMarkers($peerId, $peerMap);
                foreach ($markers as $m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    $m['gateway_id'] = (int) ($gw['id'] ?? 0);
                    $m['gateway_partner'] = true;
                    $m['id'] = 'gw-' . (int) ($gw['id'] ?? 0) . '-m-' . (string) ($m['id'] ?? '0');
                    $out[] = $m;
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function enrichUnitsWithFireTeams(int $tenantId, array $rows): array
    {
        $ftRepo = $this->fireTeamRepository ?? new FireTeamRepository();
        if (!$ftRepo->tablesReady() || $rows === []) {
            return $rows;
        }
        $byCallsign = [];
        $byUser = [];
        try {
            $teams = $ftRepo->listForTenant($tenantId, []);
        } catch (\Throwable) {
            return $rows;
        }
        foreach ($teams as $team) {
            if (empty($team['is_active'])) {
                continue;
            }
            $info = [
                'id' => (int) ($team['id'] ?? 0),
                'label' => trim((string) ($team['label'] ?? '')),
                'color' => strtoupper(trim((string) ($team['color'] ?? '#2563EB'))) ?: '#2563EB',
            ];
            foreach ($team['members'] ?? [] as $member) {
                if (!is_array($member)) {
                    continue;
                }
                $cs = strtoupper(trim((string) ($member['effective_callsign'] ?? $member['callsign'] ?? '')));
                if ($cs !== '' && !isset($byCallsign[$cs])) {
                    $byCallsign[$cs] = $info;
                }
                $uid = (int) ($member['user_id'] ?? 0);
                if ($uid > 0 && !isset($byUser[$uid])) {
                    $byUser[$uid] = $info;
                }
            }
        }
        if ($byCallsign === [] && $byUser === []) {
            return $rows;
        }

        // Index callsign Athena → user_id pour rattacher via compte lié.
        $callsignToUser = [];
        try {
            foreach ($this->userRepository->allForTenant($tenantId) as $u) {
                $cs = strtoupper(trim((string) ($u['callsign'] ?? '')));
                $uid = (int) ($u['id'] ?? 0);
                if ($cs !== '' && $uid > 0) {
                    $callsignToUser[$cs] = $uid;
                }
            }
        } catch (\Throwable) {
        }

        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $cs = strtoupper(trim((string) ($row['call_sign'] ?? '')));
            $ft = ($cs !== '' && isset($byCallsign[$cs])) ? $byCallsign[$cs] : null;
            if ($ft === null && $cs !== '' && isset($callsignToUser[$cs])) {
                $uid = $callsignToUser[$cs];
                if (isset($byUser[$uid])) {
                    $ft = $byUser[$uid];
                }
            }
            if ($ft === null) {
                continue;
            }
            $row['fire_team_id'] = $ft['id'];
            $row['fire_team_label'] = $ft['label'];
            $row['fire_team_color'] = $ft['color'];
        }
        unset($row);

        return $rows;
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
        $sessionUser = $this->sessionUserBrief();
        if ($sessionUser === null && ComspecApiKeyAuth::extractPresentedKey() === '') {
            return Response::json([
                'error' => 'unauthorized',
                'message' => 'Connectez-vous pour modifier un contact.',
            ], 401);
        }
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $before = $this->atak->getUnitById($tenantId, $id);
        if ($before === null) {
            return Response::json(['error' => 'Not found', 'message' => 'Contact introuvable.'], 404);
        }

        $linkUserId = (int) ($body['link_user_id'] ?? $body['linkUserId'] ?? 0);
        $linked = null;
        if ($linkUserId > 0) {
            if (!$this->canLinkPersonnelOnTacmap()) {
                return Response::json([
                    'error' => 'forbidden',
                    'message' => 'Vous n’avez pas l’autorisation de lier un contact à une fiche personnel.',
                ], 403);
            }
            $targetUser = $this->userRepository->findById($linkUserId, $tenantId)
                ?? $this->userRepository->findById($linkUserId);
            if (!is_array($targetUser) || (int) ($targetUser['tenant_id'] ?? 0) !== $tenantId) {
                return Response::json([
                    'error' => 'not_found',
                    'message' => 'Fiche personnel introuvable dans votre communauté.',
                ], 404);
            }
            $profileCall = trim((string) ($targetUser['callsign'] ?? ''));
            $unitCall = trim((string) ($body['call_sign'] ?? $body['callsign'] ?? ($before['call_sign'] ?? '')));
            $beforeExtra = AtakDataRepository::decodeExtra($before['extra'] ?? null);
            $beforeIsAlly = AtakDataRepository::isProxyContactExtra($beforeExtra)
                || AtakDataRepository::callSignLooksLikeProxy($unitCall);
            $applyProfileCallsign = !empty($body['apply_profile_callsign']) || !empty($body['applyProfileCallsign']);
            // Jamais coller l’indicatif opérateur sur une IA alliée (sinon pastilles fusionnées).
            if ($applyProfileCallsign && $profileCall !== '' && !$beforeIsAlly) {
                $body['call_sign'] = $profileCall;
                $unitCall = $profileCall;
            } elseif ($applyProfileCallsign && $profileCall !== '' && $beforeIsAlly) {
                $body['display_name'] = $profileCall;
                unset($body['call_sign'], $body['callsign']);
            }
            $opIds = $this->operatorIdRepository ?? new AtakOperatorIdRepository();
            if ($opIds->tablesReady()) {
                $mid = $opIds->syncUnitMilitaryId($tenantId, $id, $unitCall !== '' ? $unitCall : ($profileCall ?: 'Operateur'), $linkUserId);
                $body['military_id'] = $mid;
            }
            $linked = [
                'userId' => $linkUserId,
                'url' => url('personnel/' . $linkUserId),
                'callsign' => $profileCall,
                'member_number' => trim((string) ($targetUser['tenant_member_number'] ?? '')) ?: null,
                'label' => $this->personnelLabelFromUser($targetUser),
            ];
        }

        $patch = $body;
        unset(
            $patch['link_user_id'],
            $patch['linkUserId'],
            $patch['apply_profile_callsign'],
            $patch['applyProfileCallsign'],
            $patch['mapId'],
            $patch['map_id'],
            $patch['military_id']
        );
        if (isset($patch['callsign']) && !isset($patch['call_sign'])) {
            $patch['call_sign'] = $patch['callsign'];
        }
        unset($patch['callsign']);

        // Alias explicites → clés TOC stockées dans extra (sans toucher radio_freq jeu).
        $tocAliases = [
            'toc_radio' => ['toc_radio', 'tocRadio', 'frequence_radio', 'fréquence radio'],
            'toc_vehicle' => ['toc_vehicle', 'tocVehicle', 'vehicule', 'véhicule'],
            'toc_note' => ['toc_note', 'tocNote', 'note_libre'],
        ];
        $hasTocPatch = false;
        foreach ($tocAliases as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $patch)) {
                    $patch[$canonical] = $patch[$alias];
                    $hasTocPatch = true;
                    break;
                }
            }
        }
        foreach (['tocRadio', 'frequence_radio', 'fréquence radio', 'tocVehicle', 'vehicule', 'véhicule', 'tocNote', 'note_libre'] as $alias) {
            unset($patch[$alias]);
        }
        if ($hasTocPatch && $sessionUser === null) {
            return Response::json([
                'error' => 'unauthorized',
                'message' => 'Connectez-vous pour modifier les notes du contact.',
            ], 401);
        }

        $prevCall = trim((string) ($before['call_sign'] ?? ''));
        $prevExtra = AtakDataRepository::decodeExtra($before['extra'] ?? null);
        $prevLabel = AtakDataRepository::displayCallSign($prevCall, $prevExtra);
        if (isset($patch['display_name']) && is_string($patch['display_name'])) {
            $extraPatch = is_array($patch['extra'] ?? null) ? $patch['extra'] : AtakDataRepository::decodeExtra($patch['extra'] ?? null);
            $extraPatch['display_name'] = trim($patch['display_name']);
            $patch['extra'] = array_merge($prevExtra, $extraPatch);
            unset($patch['display_name']);
        }
        $row = $this->atak->updateUnit($tenantId, $id, $patch);
        if ($row === null) {
            return Response::json(['error' => 'Not found', 'message' => 'Contact introuvable.'], 404);
        }

        $newCall = trim((string) ($row['call_sign'] ?? ''));
        $newExtra = AtakDataRepository::decodeExtra($row['extra'] ?? null);
        $newLabel = AtakDataRepository::displayCallSign($newCall, $newExtra);
        $labelChanged = $prevLabel !== '' && $newLabel !== '' && strcasecmp($prevLabel, $newLabel) !== 0;
        $keyChanged = $prevCall !== '' && $newCall !== '' && strcasecmp($prevCall, $newCall) !== 0;
        if ($labelChanged || $keyChanged) {
            $actor = $sessionUser
                ? ($sessionUser['callsign'] !== '' ? $sessionUser['callsign'] : $sessionUser['displayName'])
                : 'Opérateur';
            $mapId = (int) ($row['map_id'] ?? $this->mapId($request, true));
            $this->activityLog?->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_CALLSIGN_CHANGE,
                'Indicatif mis à jour — ' . $prevLabel . ' → ' . $newLabel,
                $actor !== '' ? $actor : null,
                ['from' => $prevLabel, 'to' => $newLabel, 'unit_id' => $id]
            );
            if ($keyChanged) {
                $opIds = $this->operatorIdRepository ?? new AtakOperatorIdRepository();
                if ($opIds->tablesReady()) {
                    $linkUid = $linkUserId > 0 ? $linkUserId : null;
                    if ($linkUid === null) {
                        $byCs = $opIds->findByCallSign($tenantId, $prevCall);
                        if ($byCs && !empty($byCs['user_id'])) {
                            $linkUid = (int) $byCs['user_id'];
                        }
                    }
                    $opIds->syncUnitMilitaryId($tenantId, $id, $newCall, $linkUid);
                    if ($linkUid) {
                        $opIds->ensureForUser($tenantId, $linkUid, $newCall);
                    }
                }
            }
        }

        if ($linked !== null) {
            $row['linked_personnel'] = $linked;
        }

        return Response::json($row);
    }

    public function unitsDelete(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $sessionUser = $this->sessionUserBrief();
        if ($sessionUser === null) {
            return Response::json([
                'error' => 'unauthorized',
                'message' => 'Connectez-vous pour retirer un opérateur de la carte.',
            ], 401);
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['error' => 'not_found', 'message' => 'Contact introuvable.'], 404);
        }
        $before = $this->atak->getUnitById($tenantId, $id);
        if ($before === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Contact introuvable.'], 404);
        }
        if (!$this->canDeleteUnitOnTacmap($before, $sessionUser)) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Vous n’avez pas l’autorisation de retirer ce contact. Réservé à l’état-major, à l’administration, ou à l’opérateur concerné.',
            ], 403);
        }
        if (!$this->atak->deleteUnit($tenantId, $id)) {
            return Response::json(['error' => 'not_found', 'message' => 'Contact introuvable.'], 404);
        }

        $callSign = trim((string) ($before['call_sign'] ?? ''));
        $actor = $sessionUser['callsign'] !== '' ? $sessionUser['callsign'] : $sessionUser['displayName'];
        $mapId = (int) ($before['map_id'] ?? $this->mapId($request, true));
        $this->activityLog?->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_DISCONNECT,
            'Opérateur retiré de la carte' . ($callSign !== '' ? ' — ' . $callSign : ''),
            $actor !== '' ? $actor : null,
            ['unit_id' => $id, 'call_sign' => $callSign]
        );

        $r = new Response();
        $r->setStatusCode(204);

        return $r;
    }

    /**
     * Couper la liaison ATAK depuis la carte (opérateur concerné ou état-major).
     * Ne supprime pas le contact : statut hors liaison + journal Déconnexion.
     */
    public function unitsDisconnect(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $sessionUser = $this->sessionUserBrief();
        if ($sessionUser === null) {
            return Response::json([
                'error' => 'unauthorized',
                'message' => 'Connectez-vous pour couper la liaison d’un contact.',
            ], 401);
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['error' => 'not_found', 'message' => 'Contact introuvable.'], 404);
        }
        $before = $this->atak->getUnitById($tenantId, $id);
        if ($before === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Contact introuvable.'], 404);
        }
        if (!$this->canDeleteUnitOnTacmap($before, $sessionUser)) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Vous n’avez pas l’autorisation de couper cette liaison. Réservé à l’état-major, à l’administration, ou à l’opérateur concerné.',
            ], 403);
        }

        $callSign = trim((string) ($before['call_sign'] ?? ''));
        $mapId = (int) ($before['map_id'] ?? $this->mapId($request, true));
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }

        $this->atak->markUnitOfflineById($tenantId, $id);
        if ($callSign !== '') {
            try {
                $this->atak->markUnitOfflineByCallSign($tenantId, $mapId, $callSign);
            } catch (\Throwable) {
            }
            try {
                $live = \App\Core\Container::get(\App\Services\MissionPlanning\MissionPlanningLiveService::class);
                $live->onPlayerDisconnected($tenantId, $callSign);
            } catch (\Throwable) {
            }
            try {
                $this->autoResolveOpenMedicalAlertsForCallSign(
                    $tenantId,
                    $mapId,
                    $callSign,
                    'Liaison coupée — alertes actives clôturées'
                );
            } catch (\Throwable) {
            }
        }

        $actor = $sessionUser['callsign'] !== '' ? $sessionUser['callsign'] : $sessionUser['displayName'];
        $selfCut = $callSign !== '' && $sessionUser['callsign'] !== ''
            && strcasecmp($callSign, $sessionUser['callsign']) === 0;
        $label = $selfCut
            ? ('Liaison coupée' . ($callSign !== '' ? ' — ' . $callSign : ''))
            : ('Liaison coupée par ' . ($actor !== '' ? $actor : 'état-major')
                . ($callSign !== '' ? ' — ' . $callSign : ''));
        $this->activityLog?->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_DISCONNECT,
            $label,
            $actor !== '' ? $actor : null,
            [
                'unit_id' => $id,
                'call_sign' => $callSign,
                'source' => 'web',
                'reason' => 'manual',
            ]
        );

        $row = $this->atak->getUnitById($tenantId, $id);
        if (is_array($row)) {
            $row['status'] = 'offline';
            $row['db_status'] = 'offline';
        }

        return Response::json([
            'ok' => true,
            'message' => 'Liaison coupée' . ($callSign !== '' ? ' — ' . $callSign : '') . '.',
            'unit' => $row,
        ]);
    }

    /**
     * Demande au terminal du contact de vibrer (signal haptique — pas un ordre C2 à acquitter).
     */
    public function unitsVibrate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->orderRepository || !$this->orderRepository->tablesReady()) {
            return Response::json([
                'error' => 'not_migrated',
                'message' => 'La vibration du terminal n’est pas encore disponible sur ce serveur.',
            ], 503);
        }
        if (!$this->canIssueOrdersFromWeb()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour faire vibrer un terminal.',
            ], 403);
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['error' => 'not_found', 'message' => 'Contact introuvable.'], 404);
        }
        $unit = $this->atak->getUnitById($r, $id);
        if ($unit === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Contact introuvable.'], 404);
        }

        $callSign = trim((string) ($unit['call_sign'] ?? ''));
        if ($callSign === '') {
            return Response::json([
                'error' => 'no_callsign',
                'message' => 'Ce contact n’a pas d’indicatif — impossible de cibler le terminal.',
            ], 400);
        }

        $mapId = (int) ($unit['map_id'] ?? $this->mapId($request, true));
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }

        $user = $this->sessionUserBrief();
        $issuer = '';
        if ($user) {
            $issuer = (string) ($user['callsign'] ?: $user['displayName'] ?: '');
        }
        if ($issuer === '') {
            $issuer = 'État-major';
        }

        $externalId = 'VIB-W-' . bin2hex(random_bytes(5));
        $row = $this->orderRepository->upsertByExternalId($r, $mapId, [
            'external_id' => $externalId,
            'parent_external_id' => '',
            'order_type' => 'VIBRATE',
            'type_label' => 'Faire vibrer le terminal',
            'target' => $callSign,
            'target_type' => 'solo',
            'target_ref' => $callSign,
            'target_label' => $callSign,
            'payload' => 'Vibration demandée depuis Athena',
            'priority' => 'URGENT',
            'issuer' => $issuer,
            'issuer_user_id' => $user['userId'] ?? null,
            'status' => 'PENDING',
            'source' => 'web',
            'radio_sim' => false,
            'note' => 'Signal haptique (pas un ordre)',
        ]);
        if (!$row) {
            return Response::json([
                'error' => 'store_failed',
                'message' => 'Impossible d’envoyer la vibration.',
            ], 500);
        }

        $this->activityLog?->record(
            $r,
            $mapId,
            AtakActivityLogService::TYPE_ORDER,
            'Terminal — vibration — ' . $callSign,
            $issuer,
            [
                'unit_id' => $id,
                'call_sign' => $callSign,
                'order_type' => 'VIBRATE',
                'signal' => 'vibrate',
                'source' => 'web',
            ]
        );

        return Response::json([
            'ok' => true,
            'message' => 'Vibration envoyée vers ' . $callSign . ' — le terminal vibre en jeu.',
            'signal' => 'vibrate',
            'order' => $this->serializeOrder($row),
        ], 201);
    }

    /**
     * Envoie une notification lisible sur le terminal ATAK du contact (cliquable dans Athena).
     */
    public function unitsNotify(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->orderRepository || !$this->orderRepository->tablesReady()) {
            return Response::json([
                'error' => 'not_migrated',
                'message' => 'Les notifications terminal ne sont pas encore disponibles sur ce serveur.',
            ], 503);
        }
        if (!$this->canIssueOrdersFromWeb()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour notifier un terminal.',
            ], 403);
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['error' => 'not_found', 'message' => 'Contact introuvable.'], 404);
        }
        $unit = $this->atak->getUnitById($r, $id);
        if ($unit === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Contact introuvable.'], 404);
        }

        $callSign = trim((string) ($unit['call_sign'] ?? ''));
        if ($callSign === '') {
            return Response::json([
                'error' => 'no_callsign',
                'message' => 'Ce contact n’a pas d’indicatif — impossible de cibler le terminal.',
            ], 400);
        }

        $body = $this->jsonBody($request);
        $message = trim((string) ($body['message'] ?? $body['payload'] ?? $body['text'] ?? ''));
        if ($message === '') {
            return Response::json([
                'error' => 'message_required',
                'message' => 'Saisissez le texte de la notification.',
            ], 400);
        }
        if (mb_strlen($message) > 500) {
            $message = mb_substr($message, 0, 500);
        }

        $mapId = (int) ($unit['map_id'] ?? $this->mapId($request, true));
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }

        $user = $this->sessionUserBrief();
        $issuer = '';
        if ($user) {
            $issuer = (string) ($user['callsign'] ?: $user['displayName'] ?: '');
        }
        if ($issuer === '') {
            $issuer = 'État-major';
        }

        $externalId = 'NTF-W-' . bin2hex(random_bytes(5));
        $row = $this->orderRepository->upsertByExternalId($r, $mapId, [
            'external_id' => $externalId,
            'parent_external_id' => '',
            'order_type' => 'NOTIFY',
            'type_label' => 'Notification terminal',
            'target' => $callSign,
            'target_type' => 'solo',
            'target_ref' => $callSign,
            'target_label' => $callSign,
            'payload' => $message,
            'priority' => 'IMPORTANT',
            'issuer' => $issuer,
            'issuer_user_id' => $user['userId'] ?? null,
            'status' => 'PENDING',
            'source' => 'web',
            'radio_sim' => false,
            'note' => 'Notification terminal (pas un ordre)',
        ]);
        if (!$row) {
            return Response::json([
                'error' => 'store_failed',
                'message' => 'Impossible d’envoyer la notification.',
            ], 500);
        }

        $this->activityLog?->record(
            $r,
            $mapId,
            AtakActivityLogService::TYPE_ORDER,
            'Terminal — notification — ' . $callSign,
            $issuer,
            [
                'unit_id' => $id,
                'call_sign' => $callSign,
                'order_type' => 'NOTIFY',
                'signal' => 'notify',
                'source' => 'web',
            ]
        );

        return Response::json([
            'ok' => true,
            'message' => 'Notification envoyée vers ' . $callSign . ' — visible sur le terminal en jeu.',
            'signal' => 'notify',
            'order' => $this->serializeOrder($row),
        ], 201);
    }

    /**
     * Demande ciblée : photo casque, photo HD ou flux d’aperçus rapides (pas de RTMP).
     * Body : { "mode": "snap" | "snap_hd" | "stream" }
     */
    public function unitsRequestHelmetMedia(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->orderRepository || !$this->orderRepository->tablesReady()) {
            return Response::json([
                'error' => 'not_migrated',
                'message' => 'Les demandes de vue casque ne sont pas encore disponibles sur ce serveur.',
            ], 503);
        }
        if (!$this->canIssueOrdersFromWeb()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour demander une vue casque.',
            ], 403);
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['error' => 'not_found', 'message' => 'Contact introuvable.'], 404);
        }
        $unit = $this->atak->getUnitById($r, $id);
        if ($unit === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Contact introuvable.'], 404);
        }

        $callSign = trim((string) ($unit['call_sign'] ?? ''));
        if ($callSign === '') {
            return Response::json([
                'error' => 'no_callsign',
                'message' => 'Ce contact n’a pas d’indicatif — impossible de cibler le terminal.',
            ], 400);
        }

        $body = $this->jsonBody($request);
        $mode = strtolower(trim((string) ($body['mode'] ?? $body['quality'] ?? 'snap')));
        if (in_array($mode, ['stream', 'video', 'flux'], true)) {
            return Response::json([
                'error' => 'not_ready',
                'message' => 'La vue casque en temps réel n’est pas encore au point. Demandez une photo, ou consultez l’onglet Photos pour les clichés du terminal ATAK.',
            ], 422);
        }
        [$orderType, $typeLabel, $payload, $activityLabel, $userMessage] = match ($mode) {
            'snap_hd', 'hd' => [
                'HELMET_SNAP_HD',
                'Photo casque HD',
                'Demande de photo casque haute définition depuis Athena',
                'Caméra — photo HD — ',
                'Demande de photo casque HD envoyée vers ',
            ],
            'stream', 'video', 'flux' => [
                'HELMET_STREAM',
                'Flux casque (aperçus rapides)',
                'Demande de flux casque (~3 min, aperçus toutes les ~5 s)',
                'Caméra — flux casque — ',
                'Demande de flux casque envoyée vers ',
            ],
            default => [
                'HELMET_SNAP',
                'Photo casque',
                'Demande de photo casque depuis Athena',
                'Caméra — photo — ',
                'Demande de photo casque envoyée vers ',
            ],
        };

        $mapId = (int) ($unit['map_id'] ?? $this->mapId($request, true));
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }

        $user = $this->sessionUserBrief();
        $issuer = '';
        if ($user) {
            $issuer = (string) ($user['callsign'] ?: $user['displayName'] ?: '');
        }
        if ($issuer === '') {
            $issuer = 'État-major';
        }

        $externalId = 'CAM-W-' . bin2hex(random_bytes(5));
        $row = $this->orderRepository->upsertByExternalId($r, $mapId, [
            'external_id' => $externalId,
            'parent_external_id' => '',
            'order_type' => $orderType,
            'type_label' => $typeLabel,
            'target' => $callSign,
            'target_type' => 'solo',
            'target_ref' => $callSign,
            'target_label' => $callSign,
            'payload' => $payload,
            'priority' => 'IMPORTANT',
            'issuer' => $issuer,
            'issuer_user_id' => $user['userId'] ?? null,
            'status' => 'PENDING',
            'source' => 'web',
            'radio_sim' => false,
            'note' => 'Demande caméra casque (pas un ordre C2)',
        ]);
        if (!$row) {
            return Response::json([
                'error' => 'store_failed',
                'message' => 'Impossible d’envoyer la demande.',
            ], 500);
        }

        $this->activityLog?->record(
            $r,
            $mapId,
            AtakActivityLogService::TYPE_ORDER,
            $activityLabel . $callSign,
            $issuer,
            [
                'unit_id' => $id,
                'call_sign' => $callSign,
                'order_type' => $orderType,
                'signal' => 'helmet_media',
                'mode' => $mode,
                'source' => 'web',
            ]
        );

        return Response::json([
            'ok' => true,
            'message' => $userMessage . $callSign . ' — le terminal capture et transmet.',
            'signal' => 'helmet_media',
            'mode' => $mode,
            'order' => $this->serializeOrder($row),
        ], 201);
    }

    /**
     * Journalise les déconnexions détectées par expiration du heartbeat (TTL).
     */
    private function logStaleUnitDisconnects(int $tenantId, int $mapId): void
    {
        $expired = $this->atak->drainStaleDisconnects($tenantId, $mapId);
        if ($expired === []) {
            return;
        }
        foreach ($expired as $u) {
            $callSign = trim((string) ($u['call_sign'] ?? ''));
            $unitId = (int) ($u['id'] ?? 0);
            $label = $callSign !== ''
                ? 'Déconnexion — ' . $callSign . ' (plus de signal)'
                : 'Déconnexion (plus de signal)';
            try {
                $this->activityLog?->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_DISCONNECT,
                    $label,
                    $callSign !== '' ? $callSign : null,
                    [
                        'unit_id' => $unitId,
                        'call_sign' => $callSign,
                        'source' => 'ttl',
                        'reason' => 'stale',
                        'ttl_seconds' => AtakDataRepository::UNIT_LIVE_TTL_SECONDS,
                    ]
                );
            } catch (\Throwable) {
            }
            if ($callSign !== '') {
                try {
                    $this->autoResolveOpenMedicalAlertsForCallSign(
                        $tenantId,
                        $mapId,
                        $callSign,
                        'Opérateur hors liaison — alertes actives clôturées'
                    );
                } catch (\Throwable) {
                }
            }
        }
    }

    /**
     * Annuaire léger des fiches personnel (pour lier un contact BFT).
     */
    public function personnelDirectory(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $canView = $this->canViewPersonnelOnTacmap();
        $canLink = $this->canLinkPersonnelOnTacmap();
        if (!$canView) {
            return Response::json([
                'ok' => true,
                'items' => [],
                'canView' => false,
                'canLink' => false,
                'message' => 'Vous n’avez pas l’autorisation de consulter les fiches personnel.',
            ]);
        }

        $q = trim((string) ($request->query('q') ?? ''));
        $items = [];
        $personnelProfiles = new \App\Repositories\PersonnelProfileRepository();
        foreach ($this->userRepository->allForTenant($tenantId) as $u) {
            $uid = (int) ($u['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $status = (string) ($u['status'] ?? '');
            if ($status !== '' && $status !== 'active') {
                continue;
            }
            $label = $this->personnelLabelFromUser($u);
            $callsign = trim((string) ($u['callsign'] ?? ''));
            $characterName = '';
            try {
                $pp = $personnelProfiles->getByUserId($uid);
                if (is_array($pp)) {
                    $characterName = trim((string) ($pp['character_name'] ?? ''));
                }
            } catch (\Throwable) {
            }
            if ($q !== '') {
                $hay = mb_strtolower($label . ' ' . $callsign . ' ' . $characterName);
                if (!str_contains($hay, mb_strtolower($q))) {
                    continue;
                }
            }
            $items[] = [
                'id' => $uid,
                'label' => $label,
                'callsign' => $callsign,
                'characterName' => $characterName,
                'url' => url('personnel/' . $uid),
            ];
        }
        usort($items, static fn ($a, $b) => strcasecmp((string) $a['label'], (string) $b['label']));

        return Response::json([
            'ok' => true,
            'items' => $items,
            'canView' => true,
            'canLink' => $canLink,
        ]);
    }

    private function canViewPersonnelOnTacmap(): bool
    {
        if (!function_exists('can')) {
            return $this->sessionUserBrief() !== null;
        }

        return can('personnel.profile.view') || can('admin.access') || can('admin.organization');
    }

    private function canLinkPersonnelOnTacmap(): bool
    {
        if (!function_exists('can')) {
            return $this->sessionUserBrief() !== null;
        }

        return can('personnel.profile.update') || can('admin.access') || can('admin.organization');
    }

    /**
     * Staff / état-major : tout contact. Sinon : uniquement son propre indicatif.
     *
     * @param array<string, mixed> $unit
     * @param array{displayName: string, callsign: string, userId: int} $sessionUser
     */
    private function canDeleteUnitOnTacmap(array $unit, array $sessionUser): bool
    {
        if (function_exists('can') && (can('admin.access') || can('admin.organization'))) {
            return true;
        }
        $unitCall = trim((string) ($unit['call_sign'] ?? ''));
        if ($unitCall === '') {
            return false;
        }
        $aliases = [];
        $userCall = trim((string) ($sessionUser['callsign'] ?? ''));
        if ($userCall !== '') {
            $aliases[] = $userCall;
        }
        // Repli : indicatif plateforme / profil si la session est incomplète.
        $uid = (int) ($sessionUser['userId'] ?? 0);
        if ($uid > 0) {
            try {
                $fresh = $this->userRepository->findById($uid, (int) (Session::get('tenant_id') ?? 0))
                    ?? $this->userRepository->findById($uid);
                if (is_array($fresh)) {
                    $cs = trim((string) ($fresh['callsign'] ?? ''));
                    if ($cs !== '') {
                        $aliases[] = $cs;
                    }
                }
            } catch (\Throwable) {
            }
        }
        foreach ($aliases as $alias) {
            if (strcasecmp($unitCall, $alias) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $user */
    private function personnelLabelFromUser(array $user): string
    {
        $callsign = trim((string) ($user['callsign'] ?? ''));
        $display = trim((string) ($user['display_name'] ?? ''));
        if ($display === '') {
            $fn = trim((string) ($user['first_name'] ?? ''));
            $ln = trim((string) ($user['last_name'] ?? ''));
            $display = trim($fn . ' ' . $ln);
        }
        if ($display === '') {
            $display = $callsign !== '' ? $callsign : ('Membre #' . (int) ($user['id'] ?? 0));
        }
        if ($callsign !== '' && strcasecmp($callsign, $display) !== 0) {
            return $display . ' (' . $callsign . ')';
        }

        return $display;
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
        $actor = $this->guardArmaWrite($request, $tenantId, false, 'position');
        if ($actor instanceof Response) {
            return $actor;
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        $callSign = trim((string) ($body['call_sign'] ?? $body['callsign'] ?? ''));
        $steamNorm = $actor['steam_uid'] ?? null;
        $rawExtra = $body['extra'] ?? null;
        $extraEarly = AtakDataRepository::decodeExtra($rawExtra);
        $isProxyTerrain = AtakDataRepository::isProxyContactExtra($extraEarly)
            || AtakDataRepository::extraLooksLikeProxy($rawExtra, $extraEarly)
            || AtakDataRepository::callSignLooksLikeProxy($callSign);
        if ($isProxyTerrain) {
            $steamNorm = null;
        }
        if ($callSign === '' || strcasecmp($callSign, 'Unknown') === 0 || strcasecmp($callSign, 'Inconnu') === 0) {
            if ($steamNorm !== null && $steamNorm !== '') {
                // Réutiliser l’indicatif BFT déjà connu pour ce Steam (évite Newp1 + N-10).
                try {
                    $bySteam = $this->atak->findUnitBySteamUidRaw($tenantId, $mapId, $steamNorm);
                } catch (\Throwable) {
                    $bySteam = null;
                }
                if (is_array($bySteam)) {
                    $steamCs = trim((string) ($bySteam['call_sign'] ?? ''));
                    if ($steamCs !== '' && strcasecmp($steamCs, 'Unknown') !== 0 && strcasecmp($steamCs, 'Inconnu') !== 0) {
                        $callSign = $steamCs;
                    }
                }
            }
            if ($callSign === '' || strcasecmp($callSign, 'Unknown') === 0 || strcasecmp($callSign, 'Inconnu') === 0) {
                if ($steamNorm !== null && $steamNorm !== '') {
                    $user = $this->userRepository->findBySteamIdForTenant($tenantId, $steamNorm)
                        ?? $this->userRepository->findBySteamId($steamNorm);
                    if (is_array($user)) {
                        $fromProfile = trim((string) ($user['callsign'] ?? ''));
                        // Jamais display_name : source classique des fantômes « Newp1 ».
                        if ($fromProfile === '') {
                            $uid = (int) ($user['id'] ?? 0);
                            $fromProfile = $uid > 0 ? sprintf('U-%05d', $uid) : '';
                        }
                        if ($fromProfile !== '') {
                            $callSign = $fromProfile;
                        }
                    }
                }
            }
        }
        if ($callSign === '' || strcasecmp($callSign, 'Unknown') === 0 || strcasecmp($callSign, 'Inconnu') === 0) {
            $callSign = 'Operateur';
        }
        // coerceFloat : accepte « 1850,12 » (virgule locale) — (float)"1850,12" → 1850 tronqué / faux.
        $posX = AtakDataRepository::coerceFloat($body['pos_x'] ?? $body['pos'][0] ?? null) ?? 0.0;
        $posY = AtakDataRepository::coerceFloat($body['pos_y'] ?? $body['pos'][1] ?? null) ?? 0.0;
        $coordsOk = $this->armaGuard->assertPositionCoords($posX, $posY, $tenantId);
        if ($coordsOk instanceof Response) {
            return $coordsOk;
        }
        // Altitude ASL (getPosASL Z) — top-level ou extra ; stockée en JSON extra (pas de migration)
        $posZRaw = $body['asl_z'] ?? $body['pos_z'] ?? $body['altitude']
            ?? (isset($body['pos']) && is_array($body['pos']) ? ($body['pos'][2] ?? null) : null);
        $posZ = null;
        if ($posZRaw !== null && $posZRaw !== '' && is_numeric($posZRaw)) {
            $posZ = (float) $posZRaw;
            if (!is_finite($posZ)) {
                $posZ = null;
            }
        }
        $heading = isset($body['heading']) ? (float) $body['heading'] : null;
        if ($heading !== null && !is_finite($heading)) {
            $heading = null;
        }
        $role = $body['role'] ?? '';
        $extra = AtakDataRepository::decodeExtra($body['extra'] ?? null);
        if ($extra === [] && !is_array($body['extra'] ?? null) && !is_string($body['extra'] ?? null)) {
            $extra = ['role' => $body['role'] ?? '', 'health' => $body['health'] ?? 'ok', 'fuel' => $body['fuel'] ?? '', 'ammo' => $body['ammo'] ?? 'n/a'];
        }
        if ($posZ === null) {
            $extraZ = $extra['asl_z'] ?? $extra['pos_z'] ?? $extra['altitude'] ?? null;
            if ($extraZ !== null && $extraZ !== '' && is_numeric($extraZ)) {
                $posZ = (float) $extraZ;
                if (!is_finite($posZ)) {
                    $posZ = null;
                }
            }
        }
        if ($posZ !== null) {
            $extra['asl_z'] = $posZ;
            $extra['pos_z'] = $posZ;
        }
        $terrainZ = AtakDataRepository::coerceFloat($body['terrain_z'] ?? $extra['terrain_z'] ?? null);
        if ($terrainZ === null && is_array($extra)) {
            $terrainZ = AtakDataRepository::coerceFloat($extra['terrain_z'] ?? null);
        }
        if ($terrainZ !== null) {
            $extra['terrain_z'] = round($terrainZ, 1);
        }
        // Groupe : indicatif + affectation — jamais le titre de communauté (groupId Arma)
        $groupName = trim((string) ($body['group_name'] ?? $body['groupName'] ?? $body['group'] ?? $extra['group_name'] ?? $extra['groupName'] ?? $extra['group'] ?? ''));
        $groupId = trim((string) ($body['group_id'] ?? $body['groupId'] ?? $extra['group_id'] ?? $extra['groupId'] ?? ''));
        $tenantName = '';
        try {
            $tenantRow = $this->tenantRepository->findById($tenantId);
            if (is_array($tenantRow)) {
                $tenantName = function_exists('community_display_name')
                    ? community_display_name($tenantRow)
                    : trim((string) ($tenantRow['name'] ?? ''));
            }
        } catch (\Throwable) {
        }
        $groupName = OperatorTacticalIdentity::groupLabel(
            $callSign,
            (string) ($extra['unit'] ?? $extra['unit_name'] ?? $extra['assignment'] ?? ''),
            $tenantName,
            $tenantName,
            $groupName !== '' ? $groupName : $groupId
        );
        if ($groupName !== '') {
            $extra['group_name'] = $groupName;
            $extra['group'] = $groupName;
        }
        if ($groupId !== '') {
            $extra['group_id'] = $groupId;
            if (($extra['group_name'] ?? '') === '') {
                $extra['group_name'] = $groupName;
                $extra['group'] = $groupName;
            }
        }
        if ($steamNorm !== null && $steamNorm !== '') {
            $extra['steam_uid'] = $steamNorm;
        }
        $isProxyTerrain = AtakDataRepository::isProxyContactExtra($extra)
            || AtakDataRepository::extraLooksLikeProxy($body['extra'] ?? null, $extra)
            || AtakDataRepository::callSignLooksLikeProxy($callSign);
        if ($isProxyTerrain) {
            $steamNorm = null;
            unset($extra['steam_uid'], $extra['steamId'], $extra['player_uid'], $extra['bft_id'], $extra['military_id'], $extra['atak_id']);
        }
        if (AtakDataRepository::shouldHideEnemyAiContact($extra, (string) $callSign)) {
            return Response::json(['ok' => true]);
        }
        try {
            $this->activityLog->touchModDetection($tenantId, $mapId, [
                'mod_athena' => $this->truthyFlag($extra['mod_athena'] ?? true),
                'has_ctab' => $this->truthyFlag($extra['has_ctab'] ?? false),
                'has_atak_enhanced' => $this->truthyFlag($extra['has_atak_enhanced'] ?? false)
                    || $this->truthyFlag($extra['wr_mpu5'] ?? false),
                'has_athena_ctab' => $this->truthyFlag($extra['has_athena_ctab'] ?? false),
            ]);
        } catch (\Throwable) {
        }
        $upsert = $this->atak->upsertUnitPosition($tenantId, $mapId, $callSign, $posX, $posY, $heading, $role, json_encode($extra));
        try {
            $this->motionService()->ingestGround(
                $tenantId,
                $mapId,
                (int) ($upsert['unit_id'] ?? 0),
                $callSign,
                $posX,
                $posY,
                $heading,
                is_array($extra) ? $extra : []
            );
        } catch (\Throwable) {
        }
        $terminalUidPos = trim((string) ($extra['terminal_uid'] ?? ''));
        $compromisePos = strtolower(trim((string) ($extra['compromise_state'] ?? '')));
        if ($terminalUidPos !== '' && in_array($compromisePos, ['none', 'captured', 'compromised'], true)) {
            try {
                $realism = new \App\Repositories\AtakRealismRepository();
                $existing = $realism->findTerminalByUid($tenantId, $terminalUidPos);
                $prev = strtolower((string) ($existing['compromise_state'] ?? 'none'));
                if ($existing !== null && $prev !== $compromisePos) {
                    $realism->setTerminalCompromise(
                        $tenantId,
                        $terminalUidPos,
                        $compromisePos,
                        $compromisePos === 'none' ? null : 'Remonté depuis le terrain'
                    );
                    if ($compromisePos !== 'none') {
                        $this->activityLog->record(
                            $tenantId,
                            $mapId,
                            AtakActivityLogService::TYPE_INTEL,
                            $compromisePos === 'captured'
                                ? 'Appareil capturé — données illisibles'
                                : 'Appareil compromis — données illisibles',
                            (string) $callSign,
                            ['terminal_uid' => $terminalUidPos, 'compromise_state' => $compromisePos, 'source' => 'position']
                        );
                    }
                }
            } catch (\Throwable) {
            }
        }
        if ($steamNorm !== null && $steamNorm !== '') {
            try {
                (new AtakDisconnectRecoveryRepository())->save($tenantId, $steamNorm, [
                    'callsign' => $callSign,
                    'link_state' => (string) ($extra['link_state'] ?? 'linked'),
                    'pos_x' => $posX,
                    'pos_y' => $posY,
                    'heading' => $heading,
                ]);
            } catch (\Throwable) {
            }
        }
        $healthNow = strtolower(trim((string) ($extra['health'] ?? $body['health'] ?? '')));
        if (MedicalAlertParser::isRecoveredHealth($healthNow)) {
            try {
                $this->autoResolveOpenMedicalAlertsForCallSign($tenantId, $mapId, (string) $callSign);
            } catch (\Throwable) {
            }
        }
        try {
            if (!$isProxyTerrain) {
                $hideEnemyAi = AtakDataRepository::shouldHideEnemyAiContact(
                    is_array($extra) ? $extra : [],
                    (string) $callSign
                );
                if (!$hideEnemyAi) {
                    $this->activityLog->recordFromPosition(
                        $tenantId,
                        $mapId,
                        $this->activityLog->clientKeyFromRequest(),
                        (string) $callSign,
                        !empty($upsert['created']),
                        $this->buildActivityMeta(
                            $tenantId,
                            $mapId,
                            $body,
                            is_array($actor) ? $actor : null,
                            (string) $callSign,
                            $extra
                        )
                    );
                }
            }
        } catch (\Throwable) {
        }
        $missionId = 'mission_' . $tenantId . '_map_' . $mapId;
        static $lastReplayLog = [];
        $key = $missionId . ':' . $callSign;
        $now = time();
        if (!isset($lastReplayLog[$key]) || ($now - $lastReplayLog[$key]) >= 2) {
            $lastReplayLog[$key] = $now;
            try {
                $replay = \App\Core\Container::get(\App\Services\Replay\ReplayService::class);
                $speed = isset($extra['speed']) && is_numeric($extra['speed']) ? (float) $extra['speed'] : null;
                $replay->logPosition($missionId, $callSign, $callSign, $posX, $posY, $posZ, $heading, null, null, $speed, $extra);
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
        $actor = $this->guardArmaWrite($request, $tenantId, true);
        if ($actor instanceof Response) {
            return $actor;
        }
        $body = $this->jsonBody($request);
        $uidRaw = $actor['steam_uid'] ?? SteamId::normalize((string) ($body['player_uid'] ?? $body['playerUid'] ?? $body['steam_id'] ?? ''));
        if ($uidRaw === null) {
            return Response::json([
                'error' => 'player_uid required',
                'message' => 'Identifiant Steam requis pour enregistrer le temps de jeu.',
            ], 400);
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
        
        // Simulation roleplay
        $roleplayResponse = $this->applyRoleplayEffects($tenantId);
        if ($roleplayResponse !== null) {
            return $roleplayResponse;
        }
        
        $mapId = $this->mapId($request);
        $limit = (int) ($request->query('limit') ?: 100);
        $afterRaw = $request->query('after');
        $afterId = ($afterRaw !== null && $afterRaw !== '') ? (int) $afterRaw : 0;
        // Sur-échantillonner puis filtrer le bruit technique (réglages camps) déjà en base.
        $fetchLimit = min(max((int) ($limit * 2), $limit), 500);
        try {
            if ($afterId > 0) {
                $rows = $this->atak->getChatMessagesAfter($tenantId, $mapId, $afterId, $fetchLimit);
            } else {
                $rows = $this->atak->getChatMessages($tenantId, $mapId, $fetchLimit);
            }
        } catch (\Throwable) {
            return Response::json([]);
        }
        $rows = array_values(array_filter(
            is_array($rows) ? $rows : [],
            static function ($row): bool {
                if (!is_array($row)) {
                    return false;
                }
                $body = (string) ($row['body'] ?? '');
                $author = (string) ($row['author'] ?? '');
                // Anciens messages parfois découpés (auteur REGLAGES / corps AFFICHAGE|…)
                if (TacticalAlertParser::isHiddenSystemChatBody($body)) {
                    return false;
                }
                if (
                    mb_strtoupper(trim($author)) === 'REGLAGES'
                    && str_starts_with(mb_strtoupper(trim($body)), 'AFFICHAGE|')
                ) {
                    return false;
                }

                return true;
            }
        ));

        // Filtrage MP P2P : TOC voit tout ; ?callsign= ne remonte que les fils où l’indicatif est partie.
        $mpCallsign = trim((string) ($request->query('callsign') ?? $request->query('for_callsign') ?? ''));
        if ($mpCallsign !== '') {
            $rows = array_values(array_filter(
                $rows,
                static function ($row) use ($mpCallsign): bool {
                    if (!is_array($row)) {
                        return false;
                    }
                    $mp = MpMessageParser::parse(isset($row['body']) ? (string) $row['body'] : null);
                    if ($mp === null) {
                        return true;
                    }

                    return MpMessageParser::concernsCallSign($mp, $mpCallsign);
                }
            ));
        }

        // Enrichissement léger pour l’UI (groupe / MP / tactique déjà parsé côté JS aussi).
        foreach ($rows as &$chatRow) {
            if (!is_array($chatRow)) {
                continue;
            }
            $chatRow['source'] = AtakDataRepository::normalizeChatSource(
                isset($chatRow['source']) ? (string) $chatRow['source'] : null
            );
            $group = GroupMessageParser::enrichChatRow($chatRow);
            if ($group !== null) {
                $chatRow['group'] = $group;
            }
            $mp = MpMessageParser::enrichChatRow($chatRow);
            if ($mp !== null) {
                $chatRow['mp'] = $mp;
            }
        }
        unset($chatRow);

        if (count($rows) > $limit) {
            $rows = array_slice($rows, -$limit);
        }

        try {
            $rows = $this->applyIntelScramble($request, $tenantId, $mapId, 'chat', $rows);
        } catch (\Throwable) {
            // Un brouillage intel défaillant ne doit pas couper le journal radio.
        }

        return Response::json($rows);
    }

    /**
     * Assistances médicales en cours : alertes tchat (ALERTE MÉDICALE / WIA) + unités à l’état critique.
     * Les alertes de plus de 30 minutes sont exclues de la liste active.
     */
    public function medicalAlertsIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        
        // Simulation roleplay
        $roleplayResponse = $this->applyRoleplayEffects($tenantId);
        if ($roleplayResponse !== null) {
            return $roleplayResponse;
        }
        $mapId = $this->mapId($request);
        $limit = (int) ($request->query('limit') ?: 40);
        $this->logStaleUnitDisconnects($tenantId, $mapId);
        $alerts = $this->atak->getMedicalAlertsFromChat($tenantId, $mapId, min($limit, 100));
        $alerts = $this->enrichMedicalAlertsWithTriage($tenantId, $alerts);
        $alerts = $this->reconcileMedicalAlertsWithUnitHealth($tenantId, $mapId, $alerts);
        $alerts = MedicalAlertParser::collapseToHighestSeverityPerCallSign($alerts);
        $criticalUnits = $this->atak->getUnitsWithCriticalHealth($tenantId, $mapId);
        $emergencyCount = 0;
        foreach ($alerts as $a) {
            if (($a['severity'] ?? '') === 'critical' && empty($a['triage']['is_resolved'])) {
                $emergencyCount++;
            }
        }
        foreach ($criticalUnits as $u) {
            if (($u['severity'] ?? '') === 'critical') {
                $emergencyCount++;
            }
        }

        $alerts = $this->applyIntelScramble($request, $tenantId, $mapId, 'alert', $alerts);
        $criticalUnits = $this->applyIntelScramble($request, $tenantId, $mapId, 'unit', $criticalUnits);

        return Response::json([
            'mapId' => $mapId,
            'alerts' => $alerts,
            'criticalUnits' => $criticalUnits,
            'counts' => [
                'alerts' => count($alerts),
                'criticalUnits' => count($criticalUnits),
                'emergency' => $emergencyCount,
            ],
            'active_window_seconds' => MedicalAlertParser::ACTIVE_WINDOW_SECONDS,
            'can_triage' => $this->canTriageMedicalAlerts(),
            'triage_statuses' => array_map(
                static fn (string $s): array => [
                    'value' => $s,
                    'label' => MedicalAlertParser::triageLabelFr($s),
                ],
                MedicalAlertParser::TRIAGE_STATUSES
            ),
            'intel_view' => $this->resolveIntelView($request, $tenantId, $mapId),
        ]);
    }

    /**
     * Alertes tactiques (Contact, fin de contact, FRAGO, SALUTE, opérateur à terre).
     */
    public function tacticalAlertsIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        $limit = (int) ($request->query('limit') ?: 40);
        $alerts = $this->atak->getTacticalAlertsFromChat($tenantId, $mapId, min($limit, 100));
        $alerts = $this->applyIntelScramble($request, $tenantId, $mapId, 'alert', $alerts);

        return Response::json([
            'mapId' => $mapId,
            'alerts' => $alerts,
            'active_window_seconds' => TacticalAlertParser::ACTIVE_WINDOW_SECONDS,
            'kinds' => array_map(
                static fn (string $k): array => [
                    'value' => $k,
                    'label' => TacticalAlertParser::kindLabelFr($k),
                ],
                TacticalAlertParser::KINDS
            ),
            'intel_view' => $this->resolveIntelView($request, $tenantId, $mapId),
        ]);
    }

    /**
     * Réglages d’affichage camps pour Tacmap (GET / POST).
     */
    public function missionSettings(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $svc = new MissionDisplaySettingsService();
        $method = strtoupper((string) ($request->method() ?? 'GET'));

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $body = $this->jsonBody($request);
            $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? $this->mapId($request, true));
            if ($mapId < 1) {
                $mapId = self::DEFAULT_MAP_ID;
            }
            $saved = $svc->put($tenantId, $mapId, [
                'show_east' => (bool) ($body['show_east'] ?? $body['showEast'] ?? true),
                'show_guer' => (bool) ($body['show_guer'] ?? $body['showGuer'] ?? true),
                'show_civ' => (bool) ($body['show_civ'] ?? $body['showCiv'] ?? true),
            ]);

            return Response::json(['ok' => true, 'mapId' => $mapId, 'settings' => $saved]);
        }

        $mapId = $this->mapId($request);

        return Response::json([
            'mapId' => $mapId,
            'settings' => $svc->get($tenantId, $mapId),
        ]);
    }

    /**
     * Météo mission (snapshot ATAK Enhanced Weather → bandeau Tacmap / ATAK).
     */
    public function weather(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $svc = new \App\Services\Tactical\MissionWeatherService();
        $method = strtoupper((string) ($request->method() ?? 'GET'));

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            if (!$this->authArma()) {
                return Response::json(['error' => 'Unauthorized'], 401);
            }
            $actor = $this->guardArmaWrite($request, $tenantId, false);
            if ($actor instanceof Response) {
                return $actor;
            }
            $body = $this->jsonBody($request);
            $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? $this->mapId($request, true));
            if ($mapId < 1) {
                $mapId = self::DEFAULT_MAP_ID;
            }
            $saved = $svc->put($tenantId, $mapId, $body);

            return Response::json(['ok' => true, 'mapId' => $mapId, 'weather' => $saved]);
        }

        $mapId = $this->mapId($request);

        try {
            return Response::json([
                'mapId' => $mapId,
                'weather' => $svc->get($tenantId, $mapId),
            ]);
        } catch (\Throwable) {
            return Response::json([
                'mapId' => $mapId,
                'weather' => [
                    'condition' => '',
                    'temperature_c' => null,
                    'wind_kph' => null,
                    'wind_dir' => null,
                    'cloud_pct' => null,
                    'fog_pct' => null,
                    'rain_pct' => null,
                    'humidity_pct' => null,
                    'call_sign' => '',
                    'updated_at' => '',
                ],
            ]);
        }
    }

    /**
     * Roster caméras casque / drone (aperçus photo, pas de flux vidéo temps réel).
     */
    public function videoFeeds(Request $request, array $params = []): Response
    {
        $svc = new \App\Services\Tactical\AtakVideoFeedsService();
        $method = strtoupper((string) ($request->method() ?? 'GET'));

        try {
            $r = $this->requireTenant($request);
            if ($r instanceof Response) {
                return $r;
            }
            $tenantId = $r;

            if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
                if (!$this->authArma()) {
                    return Response::json(['error' => 'Unauthorized'], 401);
                }
                $actor = $this->guardArmaWrite($request, $tenantId, false);
                if ($actor instanceof Response) {
                    return $actor;
                }
                $body = $this->jsonBody($request);
                $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? $this->mapId($request, true));
                if ($mapId < 1) {
                    $mapId = self::DEFAULT_MAP_ID;
                }
                $feeds = $body['feeds'] ?? [];
                if (!is_array($feeds)) {
                    $feeds = [];
                }
                $reporter = trim((string) ($body['callsign'] ?? $body['call_sign'] ?? $body['reporter'] ?? ''));
                if ($reporter === '' && is_array($actor)) {
                    $reporter = trim((string) ($actor['callsign'] ?? $actor['call_sign'] ?? ''));
                }
                $saved = $svc->put($tenantId, $mapId, $feeds, $reporter);
                $saved = $this->attachFeedSnapshots($tenantId, $saved);

                return Response::json(['ok' => true] + $saved);
            }

            $mapId = $this->mapId($request);
            $payload = $svc->get($tenantId, $mapId);
            $payload = $this->attachFeedSnapshots($tenantId, $payload);

            return Response::json($payload);
        } catch (\Throwable $e) {
            error_log('[atak/video-feeds] ' . $e->getMessage());

            return Response::json([
                'error' => 'video_feeds_failed',
                'message' => 'Impossible de mettre à jour les caméras pour le moment.',
            ], 503);
        }
    }

    /**
     * Attache la dernière capture recon à chaque flux (par id de feed ou auteur + type).
     *
     * @param array{mapId?: int, feeds: list<array<string, mixed>>, updated_at?: string} $payload
     * @return array{mapId?: int, feeds: list<array<string, mixed>>, updated_at?: string}
     */
    private function attachFeedSnapshots(int $tenantId, array $payload): array
    {
        $feeds = $payload['feeds'] ?? [];
        if (!is_array($feeds) || $feeds === []) {
            return $payload;
        }
        $ids = [];
        foreach ($feeds as $f) {
            if (is_array($f) && trim((string) ($f['id'] ?? '')) !== '') {
                $ids[] = (string) $f['id'];
            }
        }
        try {
            $snap = $this->reconImages()->latestSnapshots($tenantId, $ids, 120);
        } catch (\Throwable $e) {
            error_log('[atak/video-feeds] snapshots: ' . $e->getMessage());
            $snap = ['by_feed' => [], 'by_author_device' => [], 'by_author' => []];
        }
        $byFeed = $snap['by_feed'] ?? [];
        $byAuthor = $snap['by_author_device'] ?? [];
        $byAuthorAny = $snap['by_author'] ?? [];
        foreach ($feeds as &$feed) {
            if (!is_array($feed)) {
                continue;
            }
            $id = trim((string) ($feed['id'] ?? ''));
            $row = ($id !== '' && isset($byFeed[$id])) ? $byFeed[$id] : null;
            $cs = strtoupper(trim((string) ($feed['callsign'] ?? '')));
            if ($row === null) {
                $kind = strtolower(trim((string) ($feed['kind'] ?? 'helmet')));
                $device = match ($kind) {
                    'drone', 'uav' => 'DRONE',
                    'vehicle' => 'UAV',
                    default => 'HELMET',
                };
                $key = $cs . ':' . $device;
                $row = ($cs !== '' && isset($byAuthor[$key])) ? $byAuthor[$key] : null;
            }
            if ($row === null && $cs !== '' && isset($byAuthorAny[$cs])) {
                $row = $byAuthorAny[$cs];
            }
            if (is_array($row)) {
                $feed['snapshot_url'] = user_media_public_url('uploads/recon/' . basename((string) ($row['image_path'] ?? '')));
                $feed['snapshot_id'] = (int) ($row['id'] ?? 0);
                $feed['snapshot_at'] = $row['created_at'] ?? $row['captured_at'] ?? null;
                $feed['snapshot_caption'] = $row['caption'] ?? null;
            } else {
                $feed['snapshot_url'] = null;
                $feed['snapshot_id'] = null;
                $feed['snapshot_at'] = null;
                $feed['snapshot_caption'] = null;
            }
            $feed['kind_label'] = match (strtolower((string) ($feed['kind'] ?? ''))) {
                'drone', 'uav' => 'Caméra drone',
                'vehicle' => 'Caméra véhicule',
                default => 'Caméra casque',
            };
            // Évite INF/NAN qui font échouer json_encode → réponse 500 opaque.
            foreach (['pos_x', 'pos_y', 'pos_z'] as $coord) {
                if (!array_key_exists($coord, $feed) || $feed[$coord] === null) {
                    continue;
                }
                $n = (float) $feed[$coord];
                $feed[$coord] = is_finite($n) ? $n : null;
            }
        }
        unset($feed);
        $payload['feeds'] = $feeds;

        return $payload;
    }

    /**
     * Modules pont ATAK Enhanced / cTab (activables par communauté).
     */
    public function modModules(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $svc = new \App\Services\Tactical\AtakBridgeModulesService();
        $method = strtoupper((string) ($request->method() ?? 'GET'));

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $isGame = ComspecApiKeyAuth::extractPresentedKey() !== '';
            if ($isGame) {
                return Response::json([
                    'error' => 'forbidden',
                    'message' => 'Les modules se règlent depuis l’administration web.',
                ], 403);
            }
            if (!function_exists('can') || !can('admin.access')) {
                return Response::json(['error' => 'Forbidden'], 403);
            }
            $body = $this->jsonBody($request);
            $incoming = is_array($body['modules'] ?? null) ? $body['modules'] : $body;
            $boolMap = [];
            foreach ($svc->catalog() as $row) {
                $id = $row['id'];
                if (array_key_exists($id, $incoming)) {
                    $boolMap[$id] = (bool) $incoming[$id];
                }
            }
            $saved = $svc->put($tenantId, $boolMap);

            return Response::json([
                'ok' => true,
                'modules' => $saved['modules'],
                'catalog' => $svc->catalogWithState($tenantId),
                'updated_at' => $saved['updated_at'],
            ]);
        }

        $state = $svc->get($tenantId);

        return Response::json([
            'modules' => $state['modules'],
            'catalog' => $svc->catalogWithState($tenantId),
            'updated_at' => $state['updated_at'],
        ]);
    }

    /**
     * Expérience Overwatch par communauté (réalisme, troll, guide).
     */
    public function experience(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $svc = new \App\Services\Tactical\AtakExperienceService();
        $method = strtoupper((string) ($request->method() ?? 'GET'));

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $isGame = ComspecApiKeyAuth::extractPresentedKey() !== '';
            if ($isGame) {
                return Response::json([
                    'error' => 'forbidden',
                    'message' => 'L’expérience se règle depuis l’administration web.',
                ], 403);
            }
            if (!function_exists('can') || !can('admin.access')) {
                return Response::json(['error' => 'Forbidden'], 403);
            }
            $body = $this->jsonBody($request);
            $incoming = is_array($body['settings'] ?? null) ? $body['settings'] : $body;
            $saved = $svc->put($tenantId, $incoming);

            return Response::json([
                'ok' => true,
                'settings' => $saved['settings'],
                'catalog' => $svc->catalogWithState($tenantId),
                'guide' => $saved['guide'],
                'updated_at' => $saved['updated_at'],
            ]);
        }

        $payload = $svc->payloadForGame($tenantId);

        return Response::json($payload);
    }

    /**
     * Met à jour le triage d’une alerte médicale (message tchat).
     * Réservé aux profils médecin / RH (web) ou au flux jeu (clé API).
     */
    public function medicalAlertTriage(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;

        if (!$this->medicalTriageRepository || !$this->medicalTriageRepository->tablesReady()) {
            return Response::json([
                'error' => 'not_migrated',
                'message' => 'Le triage médical n’est pas encore disponible sur ce serveur.',
            ], 503);
        }

        $isGame = ComspecApiKeyAuth::extractPresentedKey() !== '';
        if ($isGame) {
            $actor = $this->guardArmaWrite($request, $tenantId, false);
            if ($actor instanceof Response) {
                return $actor;
            }
        } elseif (!$this->canTriageMedicalAlerts()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous pour indiquer le statut de secours d’une alerte.',
            ], 403);
        }

        $chatId = (int) ($params['id'] ?? 0);
        if ($chatId < 1) {
            return Response::json(['error' => 'not_found', 'message' => 'Alerte introuvable.'], 404);
        }

        $chatRow = $this->atak->getChatMessageById($tenantId, $chatId);
        if (!$chatRow) {
            return Response::json(['error' => 'not_found', 'message' => 'Alerte introuvable.'], 404);
        }
        $medical = MedicalAlertParser::enrichChatRow($chatRow);
        if ($medical === null) {
            return Response::json([
                'error' => 'not_medical',
                'message' => 'Ce message n’est pas une alerte médicale.',
            ], 400);
        }

        $body = $this->jsonBody($request);
        $status = (string) ($body['status'] ?? $body['triage'] ?? '');
        if (!MedicalAlertParser::isValidTriageStatus($status)) {
            return Response::json([
                'error' => 'invalid_status',
                'message' => 'Statut de triage non reconnu. Choisissez : À secourir, En cours, Traité, KIA ou Annulé.',
            ], 400);
        }

        $user = $this->sessionUserBrief();
        $by = trim((string) ($body['by'] ?? $body['status_by'] ?? ''));
        if ($by === '' && $user) {
            $by = (string) ($user['callsign'] ?: $user['displayName'] ?: '');
        }
        if ($by === '' && $isGame) {
            $by = trim((string) ($body['author'] ?? $chatRow['author'] ?? 'Théâtre'));
        }
        $note = (string) ($body['note'] ?? $body['status_note'] ?? '');
        $mapId = (int) ($chatRow['map_id'] ?? $body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }

        $triage = $this->medicalTriageRepository->upsert($tenantId, $mapId, $chatId, $status, $by, $note);
        if ($triage === null) {
            return Response::json([
                'error' => 'save_failed',
                'message' => 'Impossible d’enregistrer le triage pour le moment.',
            ], 500);
        }

        $medical['triage'] = $triage;
        $this->activityLog?->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_CHAT,
            'Triage médical — ' . MedicalAlertParser::triageLabelFr((string) ($triage['status'] ?? ''))
                . ' — ' . ($medical['summary'] ?? ''),
            $by !== '' ? $by : (string) ($medical['call_sign'] ?? '')
        );

        return Response::json([
            'ok' => true,
            'alert' => $medical,
            'triage' => $triage,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $alerts
     * @return list<array<string, mixed>>
     */
    private function enrichMedicalAlertsWithTriage(int $tenantId, array $alerts): array
    {
        if ($alerts === [] || !$this->medicalTriageRepository || !$this->medicalTriageRepository->tablesReady()) {
            foreach ($alerts as &$a) {
                $a['triage'] = [
                    'status' => 'a_secourir',
                    'status_label' => MedicalAlertParser::triageLabelFr('a_secourir'),
                    'status_by' => '',
                    'status_note' => '',
                    'updated_at' => '',
                    'is_resolved' => false,
                ];
            }
            unset($a);

            return $alerts;
        }
        $ids = [];
        foreach ($alerts as $a) {
            $id = (int) ($a['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $byId = $this->medicalTriageRepository->getByChatIds($tenantId, $ids);
        foreach ($alerts as &$a) {
            $id = (int) ($a['id'] ?? 0);
            $a['triage'] = $byId[$id] ?? [
                'status' => 'a_secourir',
                'status_label' => MedicalAlertParser::triageLabelFr('a_secourir'),
                'status_by' => '',
                'status_note' => '',
                'updated_at' => '',
                'is_resolved' => false,
            ];
        }
        unset($a);

        return $alerts;
    }

    /**
     * Si l’effectif correspondant est rétabli et en liaison, ou hors ligne, clôturer les alertes KO non triagées.
     * Évite la bannière CRITIQUE alors que l’onglet Effectifs affiche déjà « État stable »,
     * et retire les alertes actives après déconnexion / absence de liaison.
     *
     * @param list<array<string, mixed>> $alerts
     * @return list<array<string, mixed>>
     */
    private function reconcileMedicalAlertsWithUnitHealth(int $tenantId, int $mapId, array $alerts): array
    {
        if ($alerts === []) {
            return $alerts;
        }

        $unitsByCallSign = $this->buildUnitHealthByCallSign($tenantId, $mapId);
        if ($unitsByCallSign === []) {
            return $alerts;
        }

        $canPersist = $this->medicalTriageRepository && $this->medicalTriageRepository->tablesReady();
        $out = [];
        foreach ($alerts as $a) {
            if (!is_array($a)) {
                continue;
            }
            if (!empty($a['triage']['is_resolved'])) {
                // Conservées pour historique court côté API ; le JS les masque déjà.
                $out[] = $a;
                continue;
            }
            $cs = mb_strtoupper(trim((string) ($a['call_sign'] ?? '')));
            if ($cs === '') {
                $cs = mb_strtoupper(trim((string) ($a['author'] ?? '')));
            }
            $shouldResolve = false;
            $note = 'Rétablissement constaté — opérateur à nouveau opérationnel';
            if ($cs !== '' && isset($unitsByCallSign[$cs])) {
                $unit = $unitsByCallSign[$cs];
                $status = strtolower(trim((string) ($unit['status'] ?? '')));
                if ($status === 'offline') {
                    $shouldResolve = true;
                    $note = 'Opérateur hors liaison — alertes actives clôturées';
                } elseif (in_array($status, ['linked', 'delayed'], true)
                    && MedicalAlertParser::isRecoveredHealth($unit['health'] ?? null)) {
                    $shouldResolve = true;
                }
            }
            if (!$shouldResolve) {
                $out[] = $a;
                continue;
            }
            $chatId = (int) ($a['id'] ?? 0);
            if ($canPersist && $chatId > 0) {
                $triage = $this->medicalTriageRepository->upsert(
                    $tenantId,
                    $mapId,
                    $chatId,
                    'annule',
                    (string) ($a['call_sign'] ?? $cs),
                    $note
                );
                if (is_array($triage)) {
                    $a['triage'] = $triage;
                }
            } else {
                $a['triage'] = [
                    'status' => 'annule',
                    'status_label' => MedicalAlertParser::triageLabelFr('annule'),
                    'status_by' => (string) ($a['call_sign'] ?? $cs),
                    'status_note' => $note,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'is_resolved' => true,
                ];
            }
            $out[] = $a;
        }

        return $out;
    }

    /**
     * Clôture les alertes médicales ouvertes d’un indicatif (ex. après position « stable » ou déconnexion).
     */
    private function autoResolveOpenMedicalAlertsForCallSign(
        int $tenantId,
        int $mapId,
        string $callSign,
        string $note = 'Rétablissement constaté — opérateur à nouveau opérationnel'
    ): void {
        $callSign = trim($callSign);
        if ($callSign === '' || !$this->medicalTriageRepository || !$this->medicalTriageRepository->tablesReady()) {
            return;
        }
        static $lastAttempt = [];
        $debounceKey = $tenantId . ':' . $mapId . ':' . mb_strtoupper($callSign) . ':' . md5($note);
        $now = time();
        if (isset($lastAttempt[$debounceKey]) && ($now - $lastAttempt[$debounceKey]) < 12) {
            return;
        }
        $lastAttempt[$debounceKey] = $now;

        $alerts = $this->atak->getMedicalAlertsFromChat($tenantId, $mapId, 40);
        $alerts = $this->enrichMedicalAlertsWithTriage($tenantId, $alerts);
        $csUp = mb_strtoupper($callSign);
        foreach ($alerts as $a) {
            if (!empty($a['triage']['is_resolved'])) {
                continue;
            }
            $ecs = mb_strtoupper(trim((string) ($a['call_sign'] ?? '')));
            if ($ecs === '') {
                $ecs = mb_strtoupper(trim((string) ($a['author'] ?? '')));
            }
            if ($ecs !== $csUp) {
                continue;
            }
            $chatId = (int) ($a['id'] ?? 0);
            if ($chatId < 1) {
                continue;
            }
            $this->medicalTriageRepository->upsert(
                $tenantId,
                $mapId,
                $chatId,
                'annule',
                $callSign,
                $note !== '' ? $note : 'Rétablissement constaté — opérateur à nouveau opérationnel'
            );
        }
    }

    /**
     * Escalade médicale : clôture les alertes ouvertes moins graves du même indicatif
     * (ex. inconscient quand un arrêt cardiaque vient d’être journalisé).
     */
    private function resolveLowerSeverityMedicalAlertsForCallSign(
        int $tenantId,
        int $mapId,
        string $callSign,
        string $newKind,
        string $by = ''
    ): void {
        $callSign = trim($callSign);
        $newKind = strtolower(trim($newKind));
        if ($callSign === '' || $newKind === ''
            || !$this->medicalTriageRepository || !$this->medicalTriageRepository->tablesReady()) {
            return;
        }
        $newRank = MedicalAlertParser::kindSeverityRank($newKind);
        if ($newRank < 1) {
            return;
        }
        $alerts = $this->atak->getMedicalAlertsFromChat($tenantId, $mapId, 40);
        $alerts = $this->enrichMedicalAlertsWithTriage($tenantId, $alerts);
        $csUp = mb_strtoupper($callSign);
        $by = trim($by) !== '' ? trim($by) : $callSign;
        foreach ($alerts as $a) {
            if (!empty($a['triage']['is_resolved'])) {
                continue;
            }
            $ecs = mb_strtoupper(trim((string) ($a['call_sign'] ?? '')));
            if ($ecs === '') {
                $ecs = mb_strtoupper(trim((string) ($a['author'] ?? '')));
            }
            if ($ecs !== $csUp) {
                continue;
            }
            $oldKind = strtolower(trim((string) ($a['kind'] ?? '')));
            if ($oldKind === $newKind) {
                continue;
            }
            if (MedicalAlertParser::kindSeverityRank($oldKind) >= $newRank) {
                continue;
            }
            $chatId = (int) ($a['id'] ?? 0);
            if ($chatId < 1) {
                continue;
            }
            $this->medicalTriageRepository->upsert(
                $tenantId,
                $mapId,
                $chatId,
                'annule',
                $by,
                'Remplacée par une alerte plus grave — ' . MedicalAlertParser::healthLabelFr($newKind)
            );
        }
    }

    /**
     * @return array<string, array{health: string, status: string}>
     */
    private function buildUnitHealthByCallSign(int $tenantId, int $mapId): array
    {
        $out = [];
        foreach ($this->atak->getUnits($tenantId, $mapId) as $unit) {
            if (!is_array($unit)) {
                continue;
            }
            $cs = mb_strtoupper(trim((string) ($unit['call_sign'] ?? '')));
            if ($cs === '') {
                continue;
            }
            $extra = [];
            $raw = $unit['extra'] ?? null;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $extra = $decoded;
                }
            } elseif (is_array($raw)) {
                $extra = $raw;
            }
            $out[$cs] = [
                'health' => (string) ($extra['health'] ?? $unit['health'] ?? ''),
                'status' => (string) ($unit['status'] ?? ''),
            ];
        }

        return $out;
    }

    private function canTriageMedicalAlerts(): bool
    {
        // Compte connecté : le profil de session (spécialité Médecin) gouverne l’UI.
        // Le flux jeu (clé API) passe par un autre chemin dans medicalAlertTriage.
        return $this->sessionUserBrief() !== null;
    }

    /**
     * Alimente atak_units depuis une alerte chat (tactique / médicale).
     * Accepte x/y monde ou grille carte (ex. 180097) — sinon Contact OK mais effectifs vides.
     *
     * @param array<string, mixed> $extraPatch
     */
    private function upsertUnitFromAlertMessage(
        int $tenantId,
        int $mapId,
        string $callSign,
        mixed $posX,
        mixed $posY,
        ?string $grid,
        array $extraPatch,
        ?string $steamUid = null
    ): void {
        $callSign = trim($callSign);
        if ($callSign === '') {
            return;
        }
        [$px, $py] = AtakDataRepository::resolveAlertPosition($posX, $posY, $grid);
        if (!AtakDataRepository::isValidMapPosition($px, $py)) {
            return;
        }
        $coordsOk = $this->armaGuard->assertPositionCoords($px, $py, $tenantId);
        if ($coordsOk instanceof Response) {
            return;
        }

        $gridTrim = trim((string) $grid);
        if ($gridTrim !== '') {
            $extraPatch['grid'] = $gridTrim;
        }
        $steamNorm = $steamUid !== null && $steamUid !== '' ? SteamId::normalize($steamUid) : '';
        if ($steamNorm !== '') {
            $extraPatch['steam_uid'] = $steamNorm;
        }

        // Rattacher au BFT réel (Steam) plutôt que créer un fantôme sous le nom profil.
        $resolvedCallSign = $callSign;
        $bySteam = null;
        if ($steamNorm !== '') {
            try {
                $bySteam = $this->atak->findUnitBySteamUid($tenantId, $mapId, $steamNorm);
            } catch (\Throwable) {
                $bySteam = null;
            }
        }
        if (is_array($bySteam)) {
            $steamCs = trim((string) ($bySteam['call_sign'] ?? ''));
            if ($steamCs !== '' && strcasecmp($steamCs, $callSign) !== 0) {
                $resolvedCallSign = $steamCs;
                // Fantôme déjà créé sous le mauvais indicatif → hors ligne.
                $ghost = null;
                try {
                    $ghost = $this->atak->getUnitByCallSign($tenantId, $mapId, $callSign);
                } catch (\Throwable) {
                    $ghost = null;
                }
                if (is_array($ghost)) {
                    $ghostId = (int) ($ghost['id'] ?? 0);
                    $ghostExtra = [];
                    $rawG = $ghost['extra'] ?? null;
                    if (is_string($rawG) && $rawG !== '') {
                        $decodedG = json_decode($rawG, true);
                        if (is_array($decodedG)) {
                            $ghostExtra = $decodedG;
                        }
                    } elseif (is_array($rawG)) {
                        $ghostExtra = $rawG;
                    }
                    $ghostSrc = (string) ($ghostExtra['source'] ?? '');
                    if ($ghostId > 0 && ($ghostSrc === 'medical_chat' || $ghostSrc === 'tactical_alert')) {
                        $this->atak->markUnitOfflineById($tenantId, $ghostId);
                    }
                }
            }
        }

        $role = 'operator';
        $mergedExtra = $extraPatch;
        try {
            $existing = $this->atak->getUnitByCallSign($tenantId, $mapId, $resolvedCallSign);
            if (is_array($existing)) {
                $prevRole = trim((string) ($existing['role'] ?? ''));
                if ($prevRole !== '') {
                    $role = $prevRole;
                }
                $prevExtra = [];
                $rawExtra = $existing['extra'] ?? null;
                if (is_string($rawExtra) && $rawExtra !== '') {
                    $decoded = json_decode($rawExtra, true);
                    if (is_array($decoded)) {
                        $prevExtra = $decoded;
                    }
                } elseif (is_array($rawExtra)) {
                    $prevExtra = $rawExtra;
                }
                $mergedExtra = array_merge($prevExtra, $extraPatch);
            }
        } catch (\Throwable) {
        }

        $extraJson = json_encode($mergedExtra, JSON_UNESCAPED_UNICODE);
        try {
            $upPhone = $this->atak->upsertUnitPosition(
                $tenantId,
                $mapId,
                $resolvedCallSign,
                $px,
                $py,
                null,
                $role,
                is_string($extraJson) ? $extraJson : '{}'
            );
            $this->motionService()->ingestGround(
                $tenantId,
                $mapId,
                (int) ($upPhone['unit_id'] ?? 0),
                $resolvedCallSign,
                $px,
                $py,
                null,
                is_array($mergedExtra) ? $mergedExtra : []
            );
        } catch (\Throwable) {
        }
    }

    public function chatStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        // Flux jeu (clé API) : lier Steam si fourni ; flux web (cookie) : inchangé.
        $gameActor = null;
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            $actor = $this->guardArmaWrite($request, $tenantId, false);
            if ($actor instanceof Response) {
                return $actor;
            }
            $gameActor = is_array($actor) ? $actor : null;
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? self::DEFAULT_MAP_ID);
        $author = $body['author'] ?? 'Anonymous';
        $bodyText = $body['body'] ?? '';
        $chatActivityMeta = $this->buildActivityMeta(
            $tenantId,
            $mapId,
            $body,
            $gameActor,
            is_string($author) ? $author : null
        );
        $source = $gameActor !== null ? 'game' : 'web';
        $chatActivityMeta['source'] = $source;

        // Réglages d’affichage camps (CBA / params mission → Tacmap) :
        // appliqués silencieusement — jamais stockés ni affichés dans le journal radio.
        $factionSettings = TacticalAlertParser::parseFactionSettings(is_string($bodyText) ? $bodyText : null);
        if (is_array($factionSettings)) {
            $settingsSvc = new MissionDisplaySettingsService();
            $saved = $settingsSvc->put($tenantId, $mapId, $factionSettings);

            return Response::json([
                'ok' => true,
                'hidden_from_chat' => true,
                'mission_settings' => $saved,
            ], 201);
        }

        // Dédup alertes auto (KO / arrêt cardiaque) : ne pas réinsérer la même alerte en boucle.
        $medicalDup = false;
        $row = null;
        $parsedMedical = MedicalAlertParser::parse(is_string($bodyText) ? $bodyText : null);
        if (is_array($parsedMedical) && in_array((string) ($parsedMedical['kind'] ?? ''), ['unconscious', 'cardiac_arrest'], true)) {
            $cs = trim((string) ($parsedMedical['call_sign'] ?? ''));
            if ($cs === '') {
                $cs = trim((string) $author);
            }
            $existing = $this->atak->findRecentDuplicateMedicalAlert(
                $tenantId,
                $mapId,
                $cs,
                (string) $parsedMedical['kind'],
                300
            );
            if (is_array($existing)) {
                $row = $existing;
                $medicalDup = true;
            }
        }
        if (!$medicalDup) {
            $row = $this->atak->addChatMessage($tenantId, $mapId, $author, $bodyText, $source);
        }
        if (is_array($row)) {
            $row['source'] = AtakDataRepository::normalizeChatSource(
                isset($row['source']) ? (string) $row['source'] : $source
            );
        }

        // Messages de groupe ATAK Enhanced (GROUPE|…)
        $groupMsg = GroupMessageParser::enrichChatRow(is_array($row) ? $row : []);
        if ($groupMsg !== null && is_array($row)) {
            $row['group'] = $groupMsg;
            $this->activityLog?->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_CHAT,
                'Message de groupe — ' . (string) (($groupMsg['call_sign'] ?? '') !== ''
                    ? $groupMsg['call_sign']
                    : $author),
                (string) (($groupMsg['call_sign'] ?? '') !== '' ? $groupMsg['call_sign'] : $author),
                array_merge($chatActivityMeta, [
                    'channel' => 'GROUPE',
                    'group_id' => (string) ($groupMsg['group_id'] ?? ''),
                    'grid' => (string) ($groupMsg['grid'] ?? ''),
                    'summary' => (string) ($groupMsg['text'] ?? ''),
                    'chat_id' => (int) ($row['id'] ?? 0),
                ])
            );
        }

        // Messages privés cTab (MP|from|to|…) — archive TOC, hors messagerie sociale
        $mpMsg = MpMessageParser::enrichChatRow(is_array($row) ? $row : []);
        if ($mpMsg !== null && is_array($row)) {
            $row['mp'] = $mpMsg;
            $mpFrom = trim((string) ($mpMsg['from'] ?? ''));
            $mpTo = trim((string) ($mpMsg['to'] ?? ''));
            $this->activityLog?->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_CHAT,
                'Message privé — ' . ($mpFrom !== '' ? $mpFrom : $author)
                    . ($mpTo !== '' ? ' → ' . $mpTo : ''),
                $mpFrom !== '' ? $mpFrom : (string) $author,
                array_merge($chatActivityMeta, [
                    'channel' => 'MP',
                    'from' => $mpFrom,
                    'to' => $mpTo,
                    'summary' => (string) ($mpMsg['text'] ?? ''),
                    'chat_id' => (int) ($row['id'] ?? 0),
                ])
            );
        }

        // Alertes tactiques TIC / CLEAR / FRAGO / SALUTE / Eagle Down
        $tactical = TacticalAlertParser::enrichChatRow(is_array($row) ? $row : []);
        if ($tactical !== null) {
            if (is_array($row)) {
                $row['tactical'] = $tactical;
            }
            $tacSummary = TacticalAlertParser::activityLabel($tactical);
            $tacMeta = array_merge($chatActivityMeta, [
                'kind' => (string) ($tactical['kind'] ?? ''),
                'kind_label' => (string) ($tactical['kind_label'] ?? ''),
                'grid' => (string) ($tactical['grid'] ?? ''),
                'summary' => (string) ($tactical['summary'] ?? ''),
                'chat_id' => (int) ($row['id'] ?? 0),
            ]);
            if (!empty($tactical['order_id'])) {
                $tacMeta['order_id'] = (string) $tactical['order_id'];
            }
            if (isset($tactical['pos_x'])) {
                $tacMeta['pos_x'] = $tactical['pos_x'];
            }
            if (isset($tactical['pos_y'])) {
                $tacMeta['pos_y'] = $tactical['pos_y'];
            }
            if (!empty($tactical['frago']) && is_array($tactical['frago'])) {
                $tacMeta['frago'] = $tactical['frago'];
            }
            if (!empty($tactical['salute']) && is_array($tactical['salute'])) {
                $tacMeta['salute'] = $tactical['salute'];
            }
            if (!empty($tactical['bda']) && is_array($tactical['bda'])) {
                $tacMeta['bda'] = $tactical['bda'];
            }
            if (!empty($tactical['eagle_down']) && is_array($tactical['eagle_down'])) {
                $tacMeta['eagle_down'] = $tactical['eagle_down'];
            }
            if (!empty($tactical['tic']) && is_array($tactical['tic'])) {
                $tacMeta['tic'] = $tactical['tic'];
            }
            $this->activityLog?->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_TACTICAL_ALERT,
                $tacSummary,
                (string) (($tactical['call_sign'] ?? '') !== '' ? $tactical['call_sign'] : $author),
                $tacMeta
            );
            // Coords (x/y ou grille) → atak_units pour effectifs / carte web.
            $tcs = trim((string) ($tactical['call_sign'] ?? ''));
            if ($tcs === '') {
                $tcs = trim((string) $author);
            }
            $this->upsertUnitFromAlertMessage(
                $tenantId,
                $mapId,
                $tcs,
                $tactical['pos_x'] ?? null,
                $tactical['pos_y'] ?? null,
                isset($tactical['grid']) ? (string) $tactical['grid'] : null,
                [
                    'source' => 'tactical_alert',
                    'kind' => (string) ($tactical['kind'] ?? ''),
                    'affiliation' => 'friend',
                ]
            );
            $this->persistIcemanReportFromAlert(
                $tenantId,
                $mapId,
                $tactical,
                $gameActor,
                (int) ($row['id'] ?? 0)
            );
        }

        // Pont jeu → web : messages ORDER|… émis par le mod (SendChat)
        $parsedOrder = $this->orderRepository?->parseOrderChatBody((string) $bodyText);
        if (is_array($parsedOrder) && ($parsedOrder['external_id'] ?? '') !== '') {
            if (($parsedOrder['issuer'] ?? '') === '') {
                $parsedOrder['issuer'] = (string) $author;
            }
            $orderRow = $this->orderRepository->upsertByExternalId($tenantId, $mapId, $parsedOrder);
            if (is_array($orderRow)) {
                $row['order'] = $this->serializeOrder($orderRow);
                $this->activityLog?->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_ORDER,
                    'Ordre reçu du théâtre — ' . $this->orderTypeLabelFr(
                        (string) ($orderRow['order_type'] ?? ''),
                        (string) ($orderRow['type_label'] ?? '')
                    ),
                    (string) ($orderRow['issuer'] ?? $author)
                );
            }
        }

        // Lot 1A : FRAGO IceMan / alerte tactique → canon atak_orders (onglet Ordres)
        if (
            is_array($tactical)
            && ($tactical['kind'] ?? '') === 'frago'
            && !isset($row['order'])
            && $this->orderRepository
            && $this->orderRepository->tablesReady()
        ) {
            $linkedOid = trim((string) ($tactical['order_id'] ?? ''));
            $alreadyLinked = $linkedOid !== ''
                && $this->orderRepository->findByExternalId($tenantId, $mapId, $linkedOid) !== null;
            $fragoOrder = $this->upsertFragoOrderFromTacticalAlert(
                $tenantId,
                $mapId,
                $tactical,
                (string) $author,
                (int) ($row['id'] ?? 0)
            );
            if (is_array($fragoOrder)) {
                $row['order'] = $this->serializeOrder($fragoOrder);
                if (!$alreadyLinked) {
                    $this->activityLog?->record(
                        $tenantId,
                        $mapId,
                        AtakActivityLogService::TYPE_ORDER,
                        'Ordre fragmentaire reçu du théâtre — ' . $this->orderTypeLabelFr(
                            (string) ($fragoOrder['order_type'] ?? 'FRAGO'),
                            (string) ($fragoOrder['type_label'] ?? '')
                        ),
                        (string) ($fragoOrder['issuer'] ?? $author)
                    );
                }
            }
        }

        $medical = MedicalAlertParser::enrichChatRow(is_array($row) ? $row : []);
        if ($medical !== null) {
            $row['medical'] = $medical;
            $mcs = trim((string) ($medical['call_sign'] ?? ''));
            if ($mcs === '') {
                $mcs = trim((string) $author);
            }
            // Si l’alerte porte encore le nom profil alors qu’un indicatif tactique BFT existe, rattacher.
            $preferredCs = trim((string) ($chatActivityMeta['call_sign'] ?? $chatActivityMeta['profile_callsign'] ?? ''));
            $steamForMed = is_array($gameActor) ? ($gameActor['steam_uid'] ?? null) : ($chatActivityMeta['steam_uid'] ?? null);
            if ($preferredCs !== '' && strcasecmp($mcs, $preferredCs) !== 0) {
                $hasPreferred = $this->atak->getUnitByCallSign($tenantId, $mapId, $preferredCs) !== null;
                $hasMcs = $mcs !== '' && $this->atak->getUnitByCallSign($tenantId, $mapId, $mcs) !== null;
                if ($hasPreferred && (!$hasMcs || strcasecmp($mcs, trim((string) $author)) === 0)) {
                    $mcs = $preferredCs;
                }
            }
            if (!$medicalDup) {
                $medMeta = $chatActivityMeta;
                $medKind = trim((string) ($medical['kind'] ?? ''));
                if ($medKind !== '') {
                    $medMeta['kind'] = $medKind;
                }
                if (isset($medical['grid']) && trim((string) $medical['grid']) !== '') {
                    $medMeta['grid'] = trim((string) $medical['grid']);
                }
                $medMeta['chat_id'] = (int) ($row['id'] ?? 0);
                if (isset($medical['summary']) && trim((string) $medical['summary']) !== '') {
                    $medMeta['summary'] = trim((string) $medical['summary']);
                }
                $this->activityLog?->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_CHAT,
                    'Assistance médicale — ' . ($medical['summary'] ?? $author),
                    (string) (($medical['call_sign'] ?? '') !== '' ? $medical['call_sign'] : $author),
                    $medMeta
                );
                // Coords (POS… ou grille) → atak_units pour effectifs / carte web.
                $this->upsertUnitFromAlertMessage(
                    $tenantId,
                    $mapId,
                    $mcs,
                    $medical['pos_x'] ?? null,
                    $medical['pos_y'] ?? null,
                    isset($medical['grid']) ? (string) $medical['grid'] : null,
                    [
                        'source' => 'medical_chat',
                        'health' => (string) ($medical['kind'] ?? 'unconscious'),
                        'affiliation' => 'friend',
                    ],
                    is_string($steamForMed) ? $steamForMed : null
                );
            }
            // Escalade (ex. inconscient → arrêt cardiaque) : clôturer les alertes moins graves.
            try {
                $this->resolveLowerSeverityMedicalAlertsForCallSign(
                    $tenantId,
                    $mapId,
                    $mcs,
                    (string) ($medical['kind'] ?? ''),
                    $mcs
                );
            } catch (\Throwable) {
            }
        } elseif (
            // Déjà journalisé métier (ordre / alerte tactique / groupe / MP / réglages camps) : pas de doublon « Message envoyé ».
            !isset($row['order'])
            && !isset($row['group'])
            && !isset($row['mp'])
            && $tactical === null
        ) {
            $mentionSummary = $this->applyChatMentions(
                $tenantId,
                $mapId,
                (string) $author,
                is_string($bodyText) ? $bodyText : ''
            );
            if (is_array($row) && is_array($mentionSummary)) {
                $row['mentions'] = $mentionSummary['mentions'];
                $row['mention_pings'] = $mentionSummary['pings'];
            }
            $activityLabel = "Message envoy\u{00e9} \u{2014} " . $author;
            $activityMeta = $chatActivityMeta;
            $bodyStr = is_string($bodyText) ? $bodyText : '';
            // Message ATAK destinataire HQ → libellé TOC clair (pas un vague « Message envoyé »)
            if (str_contains($bodyStr, '[HQ]') || str_starts_with($bodyStr, 'HQ|')) {
                $hqExcerpt = $bodyStr;
                if (preg_match('/\[HQ\]\s*(.+)$/u', $bodyStr, $hm)) {
                    $hqExcerpt = trim((string) $hm[1]);
                } elseif (str_starts_with($bodyStr, 'HQ|')) {
                    $parts = explode('|', $bodyStr, 2);
                    $hqExcerpt = trim((string) ($parts[1] ?? $bodyStr));
                }
                if (function_exists('mb_strlen') && mb_strlen($hqExcerpt) > 120) {
                    $hqExcerpt = mb_substr($hqExcerpt, 0, 117) . '…';
                } elseif (strlen($hqExcerpt) > 120) {
                    $hqExcerpt = substr($hqExcerpt, 0, 117) . '...';
                }
                $activityLabel = 'Message HQ — ' . $author . ($hqExcerpt !== '' ? ' : ' . $hqExcerpt : '');
                $activityMeta['channel'] = 'HQ';
            }
            if (is_array($mentionSummary) && $mentionSummary['mentions'] !== []) {
                $labels = [];
                foreach ($mentionSummary['mentions'] as $m) {
                    $cs = trim((string) ($m['call_sign'] ?? $m['token'] ?? ''));
                    if ($cs !== '') {
                        $labels[] = '@' . $cs;
                    }
                }
                if ($labels !== []) {
                    $activityLabel = "Mention \u{2014} " . $author . ' → ' . implode(', ', $labels);
                }
                $activityMeta['mentions'] = array_values(array_map(
                    static fn (array $m): string => (string) ($m['call_sign'] ?? $m['token'] ?? ''),
                    $mentionSummary['mentions']
                ));
                $activityMeta['mentions'] = array_values(array_filter(
                    $activityMeta['mentions'],
                    static fn (string $s): bool => $s !== ''
                ));
            }
            $this->activityLog?->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_CHAT,
                $activityLabel,
                (string) $author,
                $activityMeta
            );
        }
        return Response::json($row, 201);
    }

    /**
     * Mentions @indicatif : résolution unités + ping carte « Attention » si position connue.
     *
     * @return array{mentions: list<array<string, mixed>>, pings: list<array<string, mixed>>}|null
     */
    private function applyChatMentions(
        int $tenantId,
        int $mapId,
        string $author,
        string $bodyText
    ): ?array {
        $tokens = ChatMentionParser::extractTokens($bodyText);
        if ($tokens === []) {
            return null;
        }
        $units = $this->atak->getUnits($tenantId, $mapId);
        $resolved = ChatMentionParser::resolve($bodyText, $units);
        if ($resolved === []) {
            return null;
        }

        $authorKey = mb_strtoupper(trim($author));
        $pings = [];
        $mentionsOut = [];
        foreach ($resolved as $m) {
            $cs = trim((string) ($m['call_sign'] ?? $m['token'] ?? ''));
            $pinged = false;
            $posX = isset($m['pos_x']) && is_numeric($m['pos_x']) ? (float) $m['pos_x'] : null;
            $posY = isset($m['pos_y']) && is_numeric($m['pos_y']) ? (float) $m['pos_y'] : null;
            $isSelf = $cs !== '' && mb_strtoupper($cs) === $authorKey;
            if (
                !$isSelf
                && !empty($m['matched'])
                && $posX !== null
                && $posY !== null
            ) {
                $pingMsg = '[Attention] Mention de ' . $cs . ' — par ' . $author;
                $pingRow = $this->atak->addPing($tenantId, $mapId, $author, $posX, $posY, $pingMsg);
                $pings[] = $pingRow;
                $pinged = true;
                $this->activityLog?->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_PING,
                    'Ping mention — ' . $cs,
                    $author,
                    ['mentions' => [$cs]]
                );
            }
            $mentionsOut[] = [
                'token' => (string) ($m['token'] ?? $cs),
                'call_sign' => $cs,
                'matched' => !empty($m['matched']),
                'pinged' => $pinged,
                'status' => (string) ($m['status'] ?? ''),
            ];
        }

        return [
            'mentions' => $mentionsOut,
            'pings' => $pings,
        ];
    }

    /**
     * Liste des ordres C2 pour la carte courante.
     * L’émetteur / opérateur connecté voit l’état réel (y compris transit radio).
     * Les destinataires ne voient l’ordre qu’après deliver_at.
     */
    public function ordersIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->orderRepository || !$this->orderRepository->tablesReady()) {
            return Response::json([
                'ok' => false,
                'error' => 'not_migrated',
                'message' => 'Les ordres ne sont pas encore disponibles sur ce serveur.',
                'orders' => [],
            ], 503);
        }
        $mapId = $this->mapId($request);
        $limit = (int) ($request->query('limit') ?: 80);
        $sinceRaw = $request->query('since');
        $since = is_string($sinceRaw) ? trim($sinceRaw) : '';
        $isDelta = $since !== '';
        $user = $this->sessionUserBrief();
        $forAi = (int) ($request->query('for_ai') ?? 0) === 1;
        $forGame = (int) ($request->query('for_game') ?? 0) === 1
            || $forAi
            || ($user === null && ComspecApiKeyAuth::matchedTenantId() !== null);
        // Vue émetteur (web connecté) : voit aussi le transit radio. Jeu / clé API : destinataire.
        // Ordres IA : le poll jeu doit les voir tout de suite (pas d’attente radio).
        $issuerView = ($user !== null && !$forGame) || $forAi;
        $rows = $this->orderRepository->listForMap(
            $r,
            $mapId,
            min($limit, $isDelta ? 500 : 200),
            $issuerView,
            $isDelta ? $since : null
        );

        $steamRaw = (string) ($request->query('steam_uid') ?? $request->query('steam') ?? '');
        $steam = SteamId::normalize($steamRaw);
        $callsignQ = trim((string) ($request->query('callsign') ?? $request->query('call_sign') ?? ''));
        $recipientAliases = ($forGame && !$forAi)
            ? $this->resolveGameRecipientAliases($r, $mapId, $steam, $callsignQ)
            : [];

        $orders = [];
        $maxUpdated = '';
        foreach ($rows as $row) {
            $orderType = strtoupper((string) ($row['order_type'] ?? ''));
            $targetTypeRow = strtolower((string) ($row['target_type'] ?? 'all'));
            $isTerminalSignal = in_array($orderType, AtakOrderRepository::TERMINAL_SIGNAL_TYPES, true);
            if ($forAi) {
                if ($targetTypeRow !== 'ally' || $orderType !== 'MOVE') {
                    continue;
                }
                $aiStatus = strtoupper((string) ($row['status'] ?? 'PENDING'));
                if (!in_array($aiStatus, ['PENDING', 'DELIVERED'], true)) {
                    continue;
                }
            }
            if ($forGame && !$forAi && $targetTypeRow === 'ally') {
                continue;
            }
            // Signaux terminal : visibles côté jeu uniquement, jamais dans le panneau ordres web.
            if (!$forGame && $isTerminalSignal) {
                continue;
            }
            // Vibration / notif : one-shot. Une fois ACK (ou annulé), ne plus les renvoyer
            // au poll jeu — sinon chaque reconnexion Arma rejoue le buzz.
            if ($forGame && $isTerminalSignal) {
                $signalStatus = strtoupper((string) ($row['status'] ?? 'PENDING'));
                if (!in_array($signalStatus, ['PENDING', 'DELIVERED'], true)) {
                    continue;
                }
            }

            $serialized = $this->serializeOrder($row);
            if ($forAi && empty($serialized['waypoint'])) {
                continue;
            }
            $aliases = $this->orderMatchAliases($r, $mapId, $row);
            $serialized['match_aliases'] = $aliases;

            if ($forGame && !$forAi && $recipientAliases !== [] && !$this->orderMatchesRecipientAliases($row, $aliases, $recipientAliases)) {
                continue;
            }

            $updatedAt = (string) ($serialized['updated_at'] ?? '');
            if ($updatedAt !== '' && ($maxUpdated === '' || strcmp($updatedAt, $maxUpdated) > 0)) {
                $maxUpdated = $updatedAt;
            }
            $orders[] = $serialized;
        }

        // Compteurs : snapshot complet SQL (delta) ou dérivés du payload (premier poll).
        $counts = $isDelta
            ? $this->orderRepository->countStatsForMap($r, $mapId)
            : [
                'total' => 0,
                'pending' => 0,
                'overdue' => 0,
            ];
        if (!$isDelta) {
            foreach ($orders as $serialized) {
                $status = strtoupper((string) ($serialized['status'] ?? ''));
                $counts['total']++;
                if (in_array($status, ['PENDING', 'DELIVERED'], true)) {
                    $counts['pending']++;
                }
                if (!empty($serialized['is_overdue'])) {
                    $counts['overdue']++;
                }
            }
        }

        $serverTime = date('Y-m-d H:i:s');
        $cursor = $maxUpdated !== '' ? $maxUpdated : ($isDelta ? $since : $serverTime);
        $orders = $this->applyIntelScramble($request, $r, $mapId, 'order', $orders);

        return Response::json([
            'ok' => true,
            'mapId' => $mapId,
            'orders' => $orders,
            'delta' => $isDelta,
            'since' => $isDelta ? $since : null,
            'server_time' => $serverTime,
            'cursor' => $cursor,
            'counts' => $counts,
            'canIssue' => $this->canIssueOrdersFromWeb(),
            'features' => [
                'structured_targets' => $this->orderRepository->v2ColumnsReady(),
                'radio_sim' => $this->orderRepository->v2ColumnsReady(),
                'since' => true,
                'custom_templates' => $this->orderTemplateRepository?->tablesReady() ?? false,
                'custom_types' => $this->orderTypeRepository?->tablesReady() ?? false,
            ],
            'intel_view' => $this->resolveIntelView($request, $r, $mapId),
        ]);
    }

    /**
     * Modèles d’ordres personnalisés du tenant (libellé + consignes par défaut).
     */
    public function ordersTemplatesIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canIssueOrdersFromWeb()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour gérer vos modèles d’ordres.',
            ], 403);
        }
        try {
            $templates = $this->orderTemplateRepository && $this->orderTemplateRepository->tablesReady()
                ? $this->orderTemplateRepository->listForTenant($r)
                : [];
            $persisted = $this->orderTemplateRepository?->tablesReady() ?? false;
        } catch (\Throwable) {
            $templates = [];
            $persisted = false;
        }

        return Response::json([
            'ok' => true,
            'templates' => $templates,
            'persisted' => $persisted,
        ]);
    }

    /**
     * Créer un modèle d’ordre personnalisé.
     */
    public function ordersTemplatesStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canIssueOrdersFromWeb()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour enregistrer un modèle d’ordre.',
            ], 403);
        }
        if (!$this->orderTemplateRepository || !$this->orderTemplateRepository->tablesReady()) {
            return Response::json([
                'error' => 'not_migrated',
                'message' => 'Les modèles d’ordres ne sont pas encore disponibles sur ce serveur.',
            ], 503);
        }

        $body = $this->jsonBody($request);
        $label = trim((string) ($body['label'] ?? $body['name'] ?? ''));
        if ($label === '') {
            return Response::json([
                'error' => 'label_required',
                'message' => 'Indiquez un nom pour ce modèle d’ordre.',
            ], 400);
        }
        $defaultPayload = trim((string) ($body['default_payload'] ?? $body['payload'] ?? $body['defaultPayload'] ?? ''));
        $user = $this->sessionUserBrief();
        $createdBy = isset($user['userId']) ? (int) $user['userId'] : null;

        $row = $this->orderTemplateRepository->create($r, $label, $defaultPayload, $createdBy);
        if (!$row) {
            return Response::json([
                'error' => 'store_failed',
                'message' => 'Impossible d’enregistrer ce modèle d’ordre.',
            ], 500);
        }

        return Response::json(['ok' => true, 'template' => $row], 201);
    }

    /**
     * Supprimer un modèle d’ordre personnalisé.
     */
    public function ordersTemplatesDelete(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canIssueOrdersFromWeb()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour supprimer un modèle d’ordre.',
            ], 403);
        }
        if (!$this->orderTemplateRepository || !$this->orderTemplateRepository->tablesReady()) {
            return Response::json([
                'error' => 'not_migrated',
                'message' => 'Les modèles d’ordres ne sont pas encore disponibles sur ce serveur.',
            ], 503);
        }

        $id = (int) ($params['id'] ?? $request->query('id') ?? 0);
        if ($id < 1) {
            $body = $this->jsonBody($request);
            $id = (int) ($body['id'] ?? 0);
        }
        if ($id < 1) {
            return Response::json([
                'error' => 'invalid_id',
                'message' => 'Modèle introuvable.',
            ], 400);
        }

        $ok = $this->orderTemplateRepository->deleteForTenant($r, $id);
        if (!$ok) {
            return Response::json([
                'error' => 'not_found',
                'message' => 'Ce modèle d’ordre n’existe pas ou a déjà été retiré.',
            ], 404);
        }

        return Response::json(['ok' => true]);
    }

    /**
     * Types d’ordres personnalisés du tenant (libellés du sélecteur).
     */
    public function ordersTypesIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canIssueOrdersFromWeb()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour gérer les types d’ordre.',
            ], 403);
        }
        try {
            $types = $this->orderTypeRepository && $this->orderTypeRepository->tablesReady()
                ? $this->orderTypeRepository->listForTenant($r)
                : [];
            $persisted = $this->orderTypeRepository?->tablesReady() ?? false;
        } catch (\Throwable) {
            $types = [];
            $persisted = false;
        }

        return Response::json([
            'ok' => true,
            'types' => $types,
            'persisted' => $persisted,
        ]);
    }

    /**
     * Créer un type d’ordre personnalisé.
     */
    public function ordersTypesStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canIssueOrdersFromWeb()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour créer un type d’ordre.',
            ], 403);
        }
        if (!$this->orderTypeRepository || !$this->orderTypeRepository->tablesReady()) {
            return Response::json([
                'error' => 'not_migrated',
                'message' => 'Les types d’ordre personnalisés ne sont pas encore disponibles sur ce serveur.',
            ], 503);
        }

        $body = $this->jsonBody($request);
        $label = trim((string) ($body['label'] ?? $body['name'] ?? ''));
        if ($label === '') {
            return Response::json([
                'error' => 'label_required',
                'message' => 'Indiquez un intitulé pour ce type d’ordre.',
            ], 400);
        }
        $description = trim((string) ($body['description'] ?? ''));
        $user = $this->sessionUserBrief();
        $createdBy = isset($user['userId']) ? (int) $user['userId'] : null;

        $row = $this->orderTypeRepository->create($r, $label, $description, $createdBy);
        if (!$row) {
            return Response::json([
                'error' => 'store_failed',
                'message' => 'Impossible de créer ce type d’ordre.',
            ], 500);
        }

        return Response::json(['ok' => true, 'type' => $row], 201);
    }

    /**
     * Supprimer un type d’ordre personnalisé.
     */
    public function ordersTypesDelete(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->canIssueOrdersFromWeb()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour supprimer un type d’ordre.',
            ], 403);
        }
        if (!$this->orderTypeRepository || !$this->orderTypeRepository->tablesReady()) {
            return Response::json([
                'error' => 'not_migrated',
                'message' => 'Les types d’ordre personnalisés ne sont pas encore disponibles sur ce serveur.',
            ], 503);
        }

        $id = (int) ($params['id'] ?? $request->query('id') ?? 0);
        if ($id < 1) {
            $body = $this->jsonBody($request);
            $id = (int) ($body['id'] ?? 0);
        }
        if ($id < 1) {
            return Response::json([
                'error' => 'invalid_id',
                'message' => 'Type d’ordre introuvable.',
            ], 400);
        }

        $ok = $this->orderTypeRepository->deleteForTenant($r, $id);
        if (!$ok) {
            return Response::json([
                'error' => 'not_found',
                'message' => 'Ce type d’ordre n’existe pas ou a déjà été retiré.',
            ], 404);
        }

        return Response::json(['ok' => true]);
    }

    /**
     * Listes fermées pour le sélecteur de destinataire (utilisateurs, groupes en jeu, fire teams, canaux, ATAK solo).
     */
    public function ordersRecipients(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $mapId = $this->mapId($request);
        $opIds = $this->operatorIdRepository ?? new AtakOperatorIdRepository();
        $liveUnits = $this->atak->getUnits($r, $mapId);

        $liveByCallsign = [];
        $liveBySteam = [];
        foreach ($liveUnits as $unit) {
            $cs = trim((string) ($unit['call_sign'] ?? ''));
            if ($cs !== '') {
                $liveByCallsign[mb_strtolower($cs)] = $unit;
            }
            $extra = $this->parseUnitExtra($unit);
            $steam = SteamId::normalize((string) ($extra['steam_uid'] ?? $extra['steamId'] ?? ''));
            if ($steam !== null && $steam !== '') {
                $liveBySteam[$steam] = $unit;
            }
        }

        $users = [];
        $seenUserIds = [];
        foreach ($this->userRepository->allForTenant($r) as $u) {
            $uid = (int) ($u['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $status = (string) ($u['status'] ?? '');
            if ($status !== '' && $status !== 'active') {
                continue;
            }
            $callsign = trim((string) ($u['callsign'] ?? ''));
            $display = trim((string) ($u['display_name'] ?? ''));
            if ($display === '') {
                $fn = trim((string) ($u['first_name'] ?? ''));
                $ln = trim((string) ($u['last_name'] ?? ''));
                $display = trim($fn . ' ' . $ln);
            }
            if ($display === '') {
                $display = $callsign !== '' ? $callsign : ('Utilisateur #' . $uid);
            }
            $mid = $opIds->tablesReady()
                ? $opIds->ensureForUser($r, $uid, $callsign !== '' ? $callsign : null)
                : '';
            $steam = SteamId::normalize((string) ($u['steam_id'] ?? ''));
            $onMap = false;
            if ($steam !== null && $steam !== '' && isset($liveBySteam[$steam])) {
                $onMap = true;
            } elseif ($callsign !== '' && isset($liveByCallsign[mb_strtolower($callsign)])) {
                $onMap = true;
            }
            $label = $display;
            if ($callsign !== '') {
                $label .= ' (' . $callsign . ')';
            }
            if ($mid !== '') {
                $label .= ' — ' . $mid;
            }
            if ($onMap) {
                $label .= ' · en liaison';
            }
            $users[] = [
                'id' => (string) $uid,
                'label' => $label,
                'callsign' => $callsign,
                'military_id' => $mid,
                'on_map' => $onMap,
            ];
            $seenUserIds[$uid] = true;
        }

        // Opérateurs en liaison sans compte tenant déjà listé : rattacher via Steam si possible
        foreach ($liveUnits as $unit) {
            $extra = $this->parseUnitExtra($unit);
            $steam = SteamId::normalize((string) ($extra['steam_uid'] ?? ''));
            if ($steam === null || $steam === '') {
                continue;
            }
            $linked = $this->userRepository->findBySteamIdForTenant($r, $steam)
                ?? $this->userRepository->findBySteamId($steam);
            if (!is_array($linked)) {
                continue;
            }
            $uid = (int) ($linked['id'] ?? 0);
            if ($uid < 1 || isset($seenUserIds[$uid])) {
                continue;
            }
            if ((int) ($linked['tenant_id'] ?? 0) !== $r) {
                continue;
            }
            $status = (string) ($linked['status'] ?? '');
            if ($status !== '' && $status !== 'active') {
                continue;
            }
            $callsign = trim((string) ($linked['callsign'] ?? $unit['call_sign'] ?? ''));
            $display = trim((string) ($linked['display_name'] ?? ''));
            if ($display === '') {
                $display = $callsign !== '' ? $callsign : ('Utilisateur #' . $uid);
            }
            $mid = $opIds->tablesReady()
                ? $opIds->ensureForUser($r, $uid, $callsign !== '' ? $callsign : null)
                : trim((string) ($unit['military_id'] ?? ''));
            $label = $display;
            if ($callsign !== '') {
                $label .= ' (' . $callsign . ')';
            }
            if ($mid !== '') {
                $label .= ' — ' . $mid;
            }
            $label .= ' · en liaison';
            $users[] = [
                'id' => (string) $uid,
                'label' => $label,
                'callsign' => $callsign,
                'military_id' => $mid,
                'on_map' => true,
            ];
            $seenUserIds[$uid] = true;
        }
        usort($users, static function ($a, $b) {
            $ao = !empty($a['on_map']) ? 0 : 1;
            $bo = !empty($b['on_map']) ? 0 : 1;
            if ($ao !== $bo) {
                return $ao <=> $bo;
            }

            return strcasecmp((string) $a['label'], (string) $b['label']);
        });

        // Groupes Arma (groupId) distincts parmi les unités en liaison — pas les groupes RH du site
        $groupsByKey = [];
        foreach ($liveUnits as $unit) {
            $gName = $this->unitArmaGroupName($unit);
            if ($gName === '') {
                continue;
            }
            $key = mb_strtolower($gName);
            if (!isset($groupsByKey[$key])) {
                $groupsByKey[$key] = [
                    'id' => $gName,
                    'label' => $gName,
                    'members' => [],
                    'member_count' => 0,
                ];
            }
            $cs = trim((string) ($unit['call_sign'] ?? ''));
            $unitId = (int) ($unit['id'] ?? 0);
            $mid = trim((string) ($unit['military_id'] ?? ''));
            $extra = $this->parseUnitExtra($unit);
            $steam = SteamId::normalize((string) ($extra['steam_uid'] ?? ''));
            if ($cs !== '') {
                $groupsByKey[$key]['members'][] = [
                    'call_sign' => $cs,
                    'unit_id' => $unitId > 0 ? $unitId : null,
                    'military_id' => $mid,
                    'steam_uid' => $steam,
                ];
            }
        }
        $groups = [];
        foreach ($groupsByKey as $g) {
            $n = count($g['members']);
            $g['member_count'] = $n;
            $g['label'] = $g['id'] . ' (' . $n . ' opérateur' . ($n > 1 ? 's' : '') . ')';
            $groups[] = $g;
        }
        usort($groups, static fn ($a, $b) => strcasecmp((string) $a['label'], (string) $b['label']));

        $fireTeams = [];
        $ftRepo = $this->fireTeamRepository ?? new FireTeamRepository();
        if ($ftRepo->tablesReady()) {
            foreach ($ftRepo->listForTenant($r, []) as $team) {
                $id = (int) ($team['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $label = trim((string) ($team['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $kind = (string) ($team['kind'] ?? '');
                $suffix = $kind === FireTeamRepository::KIND_PERMANENT ? 'Organigramme' : 'Mission';
                $teamMapId = isset($team['map_id']) && $team['map_id'] !== null ? (int) $team['map_id'] : null;
                // Fire teams mission : préférer celles de la carte courante, garder les autres
                if ($kind === FireTeamRepository::KIND_EPHEMERAL && $teamMapId !== null && $teamMapId !== $mapId) {
                    continue;
                }
                $fireTeams[] = [
                    'id' => (string) $id,
                    'label' => $label . ' (' . $suffix . ')',
                    'kind' => $kind,
                    'color' => strtoupper(trim((string) ($team['color'] ?? '#2563EB'))) ?: '#2563EB',
                ];
            }
        }

        $channels = [];
        foreach (AtakOrderRepository::CHANNELS as $ch) {
            $channels[] = [
                'id' => $ch,
                'label' => $this->orderRepository->channelLabelFr($ch),
            ];
        }

        $solos = [];
        foreach ($liveUnits as $unit) {
            $callSign = trim((string) ($unit['call_sign'] ?? ''));
            if ($callSign === '') {
                continue;
            }
            $unitId = (int) ($unit['id'] ?? 0);
            $mid = trim((string) ($unit['military_id'] ?? ''));
            if ($mid === '' && $opIds->tablesReady()) {
                $mid = $opIds->syncUnitMilitaryId($r, $unitId, $callSign, null);
            }
            $status = (string) ($unit['status'] ?? '');
            $label = $callSign;
            if ($mid !== '') {
                $label .= ' — ' . $mid;
            }
            if ($status === 'offline') {
                $label .= ' (hors ligne)';
            }
            $solos[] = [
                'id' => $callSign,
                'label' => $label,
                'military_id' => $mid,
                'status' => $status,
            ];
        }
        usort($solos, static fn ($a, $b) => strcasecmp((string) $a['label'], (string) $b['label']));

        $allies = [];
        foreach ($liveUnits as $unit) {
            if (!$this->unitIsAllyAi($unit)) {
                continue;
            }
            $allyId = $this->unitAllyId($unit);
            $callSign = trim((string) ($unit['call_sign'] ?? ''));
            if ($allyId === '' && $callSign === '') {
                continue;
            }
            $label = $callSign !== '' ? $callSign : $allyId;
            $status = (string) ($unit['status'] ?? '');
            if ($status === 'offline') {
                $label .= ' (hors ligne)';
            }
            $allies[] = [
                'id' => $allyId !== '' ? $allyId : $callSign,
                'label' => $label,
                'callsign' => $callSign,
                'status' => $status,
            ];
        }
        usort($allies, static fn ($a, $b) => strcasecmp((string) $a['label'], (string) $b['label']));

        return Response::json([
            'ok' => true,
            'mapId' => $mapId,
            'recipient_types' => [
                ['id' => 'all', 'label' => 'Toute l’équipe'],
                ['id' => 'user', 'label' => 'Utilisateur'],
                ['id' => 'group', 'label' => 'Groupe en jeu'],
                ['id' => 'fire_team', 'label' => 'Fire team'],
                ['id' => 'channel', 'label' => 'Canal'],
                ['id' => 'solo', 'label' => 'ATAK Solo'],
                ['id' => 'ally', 'label' => 'Unité alliée'],
            ],
            'users' => $users,
            'groups' => $groups,
            'fire_teams' => $fireTeams,
            'channels' => $channels,
            'solos' => $solos,
            'allies' => $allies,
        ]);
    }

    /**
     * Émettre un ordre depuis la Tacmap web.
     */
    public function ordersStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->orderRepository || !$this->orderRepository->tablesReady()) {
            return Response::json([
                'error' => 'not_migrated',
                'message' => 'Les ordres ne sont pas encore disponibles sur ce serveur.',
            ], 503);
        }
        if (!$this->canIssueOrdersFromWeb()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour émettre un ordre depuis la carte.',
            ], 403);
        }

        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }

        $user = $this->sessionUserBrief();
        $issuer = trim((string) ($body['issuer'] ?? ''));
        if ($issuer === '' && $user) {
            $issuer = (string) ($user['callsign'] ?: $user['displayName'] ?: 'Opérateur');
        }
        if ($issuer === '') {
            $issuer = 'Opérateur';
        }
        $issuerUserId = $user['userId'] ?? null;

        $externalId = trim((string) ($body['external_id'] ?? $body['externalId'] ?? $body['id'] ?? ''));
        if ($externalId === '') {
            $externalId = 'ORD-W-' . bin2hex(random_bytes(6));
        }

        $targetType = $this->orderRepository->normalizeTargetType(
            (string) ($body['target_type'] ?? $body['targetType'] ?? 'all')
        );
        $targetRef = trim((string) ($body['target_ref'] ?? $body['targetRef'] ?? $body['target_id'] ?? ''));
        $targetLabel = trim((string) ($body['target_label'] ?? $body['targetLabel'] ?? ''));
        $legacyTarget = trim((string) ($body['target'] ?? ''));

        if ($targetType !== 'all' && $targetRef === '' && $legacyTarget !== '') {
            $targetRef = $legacyTarget;
        }

        $resolved = $this->resolveOrderTargetLabel($r, $mapId, $targetType, $targetRef, $targetLabel, $legacyTarget);
        if ($resolved['error'] !== null) {
            return Response::json(['error' => 'target_invalid', 'message' => $resolved['error']], 400);
        }

        $radioSim = true;
        if ($targetType === 'ally') {
            $radioSim = false;
        } elseif (array_key_exists('radio_sim', $body)) {
            $radioSim = (bool) $body['radio_sim'];
        } elseif (array_key_exists('radioSim', $body)) {
            $radioSim = (bool) $body['radioSim'];
        }

        $row = $this->orderRepository->upsertByExternalId($r, $mapId, [
            'external_id' => $externalId,
            'parent_external_id' => (string) ($body['parent_external_id'] ?? $body['parentId'] ?? ''),
            'order_type' => (string) ($body['order_type'] ?? $body['type'] ?? 'MOVE'),
            'type_label' => (string) ($body['type_label'] ?? $body['typeLabel'] ?? ''),
            'target' => $resolved['target'],
            'target_type' => $targetType,
            'target_ref' => $resolved['target_ref'],
            'target_label' => $resolved['target_label'],
            'payload' => (string) ($body['payload'] ?? $body['body'] ?? ''),
            'priority' => (string) ($body['priority'] ?? 'IMPORTANT'),
            'issuer' => $issuer,
            'issuer_user_id' => $issuerUserId,
            'status' => 'PENDING',
            'source' => 'web',
            'radio_sim' => $radioSim,
        ]);
        if (!$row) {
            return Response::json(['error' => 'store_failed', 'message' => 'Impossible d’enregistrer l’ordre.'], 500);
        }

        $this->activityLog?->record(
            $r,
            $mapId,
            AtakActivityLogService::TYPE_ORDER,
            'Ordre émis — ' . $this->orderTypeLabelFr(
                (string) ($row['order_type'] ?? ''),
                (string) ($row['type_label'] ?? '')
            ),
            $issuer
        );

        return Response::json(['ok' => true, 'order' => $this->serializeOrder($row)], 201);
    }

    /**
     * Accuser réception / annuler / mettre à jour le statut d’un ordre.
     */
    public function ordersUpdateStatus(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->orderRepository || !$this->orderRepository->tablesReady()) {
            return Response::json([
                'error' => 'not_migrated',
                'message' => 'Les ordres ne sont pas encore disponibles sur ce serveur.',
            ], 503);
        }

        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? $request->query('mapId') ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $status = (string) ($body['status'] ?? '');
        if ($status === '') {
            return Response::json(['error' => 'status_required', 'message' => 'Indiquez le nouveau statut de l’ordre.'], 400);
        }

        $user = $this->sessionUserBrief();
        $by = trim((string) ($body['by'] ?? $body['status_by'] ?? ''));
        if ($by === '' && $user) {
            $by = (string) ($user['callsign'] ?: $user['displayName'] ?: '');
        }
        $note = (string) ($body['note'] ?? '');

        // Identifiant : segment d’URL (ORD-…), corps (external_id / id), ou clé primaire (db_id).
        $candidates = [];
        $pathId = rawurldecode(trim((string) ($params['id'] ?? '')));
        if ($pathId !== '') {
            $candidates[] = $pathId;
        }
        foreach (['external_id', 'externalId', 'id'] as $bodyKey) {
            $cand = trim((string) ($body[$bodyKey] ?? ''));
            if ($cand !== '' && !in_array($cand, $candidates, true)) {
                $candidates[] = $cand;
            }
        }

        $existing = null;
        foreach ($candidates as $cand) {
            $existing = $this->orderRepository->resolveOrder($r, $mapId, $cand);
            if ($existing) {
                break;
            }
        }
        if (!$existing) {
            $dbId = (int) ($body['db_id'] ?? $body['dbId'] ?? 0);
            if ($dbId > 0) {
                $existing = $this->orderRepository->findByPrimaryKey($r, $dbId);
            }
        }
        if (!$existing) {
            return Response::json(['error' => 'not_found', 'message' => 'Ordre introuvable.'], 404);
        }

        $externalId = trim((string) ($existing['external_id'] ?? ''));
        $orderMapId = (int) ($existing['map_id'] ?? $mapId);
        if ($orderMapId < 1) {
            $orderMapId = $mapId;
        }
        if ($externalId === '') {
            return Response::json(['error' => 'not_found', 'message' => 'Ordre introuvable.'], 404);
        }

        $normalized = $this->orderRepository->normalizeStatus($status);
        if ($normalized === 'CANCELLED') {
            // Annulation : émetteur ou utilisateur connecté
            if (!$this->canIssueOrdersFromWeb()) {
                return Response::json([
                    'error' => 'forbidden',
                    'message' => 'Connectez-vous pour annuler un ordre.',
                ], 403);
            }
        }

        $row = null;
        try {
            $row = $this->orderRepository->updateStatus($r, $orderMapId, $externalId, $normalized, $by, $note);
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'invalid_transition') {
                return Response::json([
                    'error' => 'invalid_transition',
                    'message' => 'Confirmez d’abord la réception de l’ordre avant de le passer en cours.',
                ], 409);
            }
            throw $e;
        }
        if (!$row) {
            return Response::json([
                'error' => 'update_failed',
                'message' => 'Impossible de mettre à jour le statut de cet ordre.',
            ], 500);
        }

        $orderTitle = $this->orderTypeLabelFr(
            (string) ($row['order_type'] ?? ''),
            (string) ($row['type_label'] ?? '')
        );
        $orderRef = trim((string) ($row['external_id'] ?? $externalId));
        $statusFr = $this->orderStatusLabelFr((string) ($row['status'] ?? ''));
        $activityLabel = $orderTitle !== ''
            ? ($orderRef !== ''
                ? $orderTitle . ' (' . $orderRef . ') — statut : ' . $statusFr
                : $orderTitle . ' — statut : ' . $statusFr)
            : ($orderRef !== ''
                ? 'Ordre ' . $orderRef . ' — statut : ' . $statusFr
                : 'Statut d’ordre mis à jour — ' . $statusFr);

        $this->activityLog?->record(
            $r,
            $orderMapId,
            AtakActivityLogService::TYPE_ORDER,
            $activityLabel,
            $by !== '' ? $by : (string) ($row['issuer'] ?? ''),
            [
                'order_id' => $orderRef,
                'order_type' => (string) ($row['order_type'] ?? ''),
                'order_title' => $orderTitle,
                'status' => (string) ($row['status'] ?? ''),
                'status_label' => $statusFr,
            ]
        );

        return Response::json(['ok' => true, 'order' => $this->serializeOrder($row)]);
    }

    /**
     * @return array{target: string, target_ref: string, target_label: string, error: string|null}
     */
    private function resolveOrderTargetLabel(
        int $tenantId,
        int $mapId,
        string $targetType,
        string $targetRef,
        string $targetLabel,
        string $legacyTarget
    ): array {
        if ($targetType === 'all') {
            return [
                'target' => '',
                'target_ref' => '',
                'target_label' => 'Toute l’équipe',
                'error' => null,
            ];
        }

        if ($targetRef === '') {
            return [
                'target' => '',
                'target_ref' => '',
                'target_label' => '',
                'error' => 'Choisissez un destinataire dans la liste.',
            ];
        }

        $label = $targetLabel !== '' ? $targetLabel : $legacyTarget;
        // `target` doit rester matchable in-game (callsign / groupId), pas le libellé UI.
        $matchTarget = '';

        if ($targetType === 'user') {
            $uid = (int) $targetRef;
            $found = null;
            foreach ($this->userRepository->allForTenant($tenantId) as $u) {
                if ((int) ($u['id'] ?? 0) === $uid) {
                    $found = $u;
                    break;
                }
            }
            if (!$found) {
                // Repli : utilisateur lié Steam via une unit en liaison
                foreach ($this->atak->getUnits($tenantId, $mapId) as $unit) {
                    $extra = $this->parseUnitExtra($unit);
                    $steam = SteamId::normalize((string) ($extra['steam_uid'] ?? ''));
                    if ($steam === null || $steam === '') {
                        continue;
                    }
                    $linked = $this->userRepository->findBySteamIdForTenant($tenantId, $steam);
                    if (is_array($linked) && (int) ($linked['id'] ?? 0) === $uid) {
                        $found = $linked;
                        break;
                    }
                }
                if (!$found) {
                    return ['target' => '', 'target_ref' => '', 'target_label' => '', 'error' => 'Utilisateur introuvable.'];
                }
            }
            $callsign = trim((string) ($found['callsign'] ?? ''));
            $display = trim((string) ($found['display_name'] ?? ''));
            $mid = ($this->operatorIdRepository ?? new AtakOperatorIdRepository())->tablesReady()
                ? ($this->operatorIdRepository ?? new AtakOperatorIdRepository())->ensureForUser($tenantId, $uid, $callsign !== '' ? $callsign : null)
                : '';
            if ($label === '') {
                $label = $display !== '' ? $display : ($callsign !== '' ? $callsign : ('Utilisateur #' . $uid));
                if ($callsign !== '' && $display !== '' && strcasecmp($display, $callsign) !== 0) {
                    $label = $display . ' (' . $callsign . ')';
                }
                if ($mid !== '') {
                    $label .= ' — ' . $mid;
                }
            }
            // Priorité callsign (filtre jeu), sinon MID, sinon display / user:id
            if ($callsign !== '') {
                $matchTarget = $callsign;
            } elseif ($mid !== '') {
                $matchTarget = $mid;
            } else {
                $matchTarget = $display !== '' ? $display : ('user:' . $uid);
            }
        } elseif ($targetType === 'group') {
            $foundName = '';
            $members = [];
            foreach ($this->atak->getUnits($tenantId, $mapId) as $unit) {
                $gName = $this->unitArmaGroupName($unit);
                if ($gName === '' || strcasecmp($gName, $targetRef) !== 0) {
                    continue;
                }
                $foundName = $gName;
                $cs = trim((string) ($unit['call_sign'] ?? ''));
                if ($cs !== '') {
                    $members[] = $cs;
                }
            }
            if ($foundName === '') {
                return ['target' => '', 'target_ref' => '', 'target_label' => '', 'error' => 'Groupe en jeu introuvable sur la carte.'];
            }
            $targetRef = $foundName;
            $matchTarget = $foundName; // groupId Arma — filtré côté SQF receiveOrder
            $n = count($members);
            $computed = $foundName . ' (' . $n . ' opérateur' . ($n > 1 ? 's' : '') . ')';
            if ($label === '' || str_contains(mb_strtolower($label), 'opérateur')) {
                $label = $computed;
            }
        } elseif ($targetType === 'fire_team') {
            $ftRepo = $this->fireTeamRepository ?? new FireTeamRepository();
            $team = $ftRepo->tablesReady() ? $ftRepo->findByIdForTenant((int) $targetRef, $tenantId) : null;
            if (!$team) {
                return ['target' => '', 'target_ref' => '', 'target_label' => '', 'error' => 'Fire team introuvable.'];
            }
            if ($label === '') {
                $label = trim((string) ($team['label'] ?? 'Fire team'));
            }
            $matchTarget = $label;
        } elseif ($targetType === 'channel') {
            $ch = strtoupper($targetRef);
            if (!in_array($ch, AtakOrderRepository::CHANNELS, true)) {
                return ['target' => '', 'target_ref' => '', 'target_label' => '', 'error' => 'Canal inconnu.'];
            }
            $label = $this->orderRepository->channelLabelFr($ch);
            $targetRef = $ch;
            $matchTarget = $ch;
        } elseif ($targetType === 'solo') {
            $units = $this->atak->getUnits($tenantId, $mapId);
            $found = null;
            foreach ($units as $unit) {
                if (strcasecmp((string) ($unit['call_sign'] ?? ''), $targetRef) === 0) {
                    $found = $unit;
                    break;
                }
            }
            if (!$found) {
                return ['target' => '', 'target_ref' => '', 'target_label' => '', 'error' => 'Terminal ATAK introuvable sur cette carte.'];
            }
            $mid = trim((string) ($found['military_id'] ?? ''));
            if ($mid === '') {
                $opIds = $this->operatorIdRepository ?? new AtakOperatorIdRepository();
                if ($opIds->tablesReady()) {
                    $mid = $opIds->syncUnitMilitaryId($tenantId, (int) ($found['id'] ?? 0), $targetRef, null);
                }
            }
            $matchTarget = $targetRef;
            if ($label === '') {
                $label = $targetRef . ($mid !== '' ? ' — ' . $mid : '');
            }
        } elseif ($targetType === 'ally') {
            $found = null;
            foreach ($this->atak->getUnits($tenantId, $mapId) as $unit) {
                if (!$this->unitIsAllyAi($unit)) {
                    continue;
                }
                $allyId = $this->unitAllyId($unit);
                $cs = trim((string) ($unit['call_sign'] ?? ''));
                if (strcasecmp($allyId, $targetRef) === 0 || strcasecmp($cs, $targetRef) === 0) {
                    $found = $unit;
                    $targetRef = $allyId !== '' ? $allyId : $cs;
                    break;
                }
            }
            if (!$found) {
                return ['target' => '', 'target_ref' => '', 'target_label' => '', 'error' => 'Unité alliée introuvable sur cette carte. Pensez à l’afficher depuis Zeus (IA alliée sur l’ATAK).'];
            }
            $matchTarget = $targetRef;
            if ($label === '') {
                $cs = trim((string) ($found['call_sign'] ?? ''));
                $label = $cs !== '' ? $cs : $targetRef;
            }
        } else {
            $matchTarget = $legacyTarget !== '' ? $legacyTarget : $targetRef;
            if ($label === '') {
                $label = $matchTarget;
            }
        }

        if ($matchTarget === '') {
            $matchTarget = $targetRef;
        }
        if ($label === '') {
            $label = $matchTarget;
        }

        return [
            'target' => mb_substr($matchTarget, 0, 128),
            'target_ref' => $targetRef,
            'target_label' => $label,
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $unit
     * @return array<string, mixed>
     */
    private function parseUnitExtra(array $unit): array
    {
        $raw = $unit['extra'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : AtakDataRepository::decodeExtra($raw);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $unit
     */
    private function unitIsAllyAi(array $unit): bool
    {
        $extra = $this->parseUnitExtra($unit);
        foreach (['ally_ai', 'is_ai'] as $flag) {
            $val = $extra[$flag] ?? false;
            if ($val === true || $val === 1 || $val === '1' || $val === 'true') {
                return true;
            }
        }
        if (strtolower(trim((string) ($extra['source'] ?? ''))) === 'ally') {
            return true;
        }
        $cs = trim((string) ($unit['call_sign'] ?? ''));

        return $cs !== '' && str_starts_with(function_exists('mb_strtoupper') ? mb_strtoupper($cs, 'UTF-8') : strtoupper($cs), 'ALLY-');
    }

    /**
     * Identifiant stable ALLY-… (netId), pas le libellé d’affichage.
     *
     * @param array<string, mixed> $unit
     */
    private function unitAllyId(array $unit): string
    {
        $extra = $this->parseUnitExtra($unit);
        $id = trim((string) ($extra['ally_id'] ?? ''));
        if ($id !== '') {
            return $id;
        }
        $cs = trim((string) ($unit['call_sign'] ?? ''));
        if (preg_match('/^(ALLY-[^\s·]+)/iu', $cs, $m) === 1) {
            return (string) $m[1];
        }

        return $cs;
    }

    /**
     * Nom de groupe Arma (groupId) stocké dans extra unit, sinon vide.
     *
     * @param array<string, mixed> $unit
     */
    private function unitArmaGroupName(array $unit): string
    {
        $extra = $this->parseUnitExtra($unit);
        foreach (['group_name', 'group', 'groupName', 'group_id', 'groupId'] as $key) {
            $v = trim((string) ($extra[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }
        foreach (['group_name', 'group', 'groupName'] as $key) {
            $v = trim((string) ($unit[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function serializeOrder(array $row): array
    {
        $type = (string) ($row['order_type'] ?? 'MOVE');
        $priority = (string) ($row['priority'] ?? 'IMPORTANT');
        $status = (string) ($row['status'] ?? 'PENDING');
        $isOverdue = !empty($row['is_overdue']);
        $statusLabel = $this->orderStatusLabelFr($status);
        if ($isOverdue && in_array(strtoupper($status), ['PENDING', 'DELIVERED'], true)) {
            $statusLabel = 'En retard';
        }

        $targetType = (string) ($row['target_type'] ?? 'all');
        $targetLabel = (string) ($row['target_label'] ?? '');
        if ($targetLabel === '') {
            $targetLabel = (string) ($row['target'] ?? '');
        }
        if ($targetLabel === '' && $targetType === 'all') {
            $targetLabel = 'Toute l’équipe';
        }

        $isCancelled = strtoupper($status) === 'CANCELLED';

        $rawPayload = (string) ($row['payload'] ?? '');
        $waypointMeta = AtakOrderWaypoint::parse($rawPayload);

        $result = [
            'id' => (string) ($row['external_id'] ?? ''),
            'db_id' => (int) ($row['id'] ?? 0),
            'parent_id' => (string) ($row['parent_external_id'] ?? ''),
            'type' => $type,
            'type_label' => $this->orderTypeLabelFr($type, (string) ($row['type_label'] ?? '')),
            'target' => (string) ($row['target'] ?? ''),
            'target_type' => $targetType,
            'target_type_label' => $this->orderTargetTypeLabelFr($targetType),
            'target_ref' => (string) ($row['target_ref'] ?? ''),
            'target_label' => $targetLabel,
            'payload' => $rawPayload,
            'payload_display' => $waypointMeta !== null
                ? AtakOrderWaypoint::displayPayload($rawPayload)
                : $rawPayload,
            'priority' => $priority,
            'priority_label' => $this->orderPriorityLabelFr($priority),
            'issuer' => (string) ($row['issuer'] ?? ''),
            'issuer_user_id' => isset($row['issuer_user_id']) ? (int) $row['issuer_user_id'] : null,
            'status' => $status,
            'status_label' => $statusLabel,
            // Tombstone soft-delete (pas de DELETE physique) — pour merge delta côté clients.
            'deleted' => $isCancelled,
            'is_overdue' => $isOverdue,
            'note' => (string) ($row['note'] ?? ''),
            'status_by' => (string) ($row['status_by'] ?? ''),
            'ack_at' => (string) ($row['ack_at'] ?? ''),
            'ack_by' => (string) ($row['ack_by'] ?? ''),
            'cancelled_at' => (string) ($row['cancelled_at'] ?? ''),
            'cancelled_by' => (string) ($row['cancelled_by'] ?? ''),
            'deliver_at' => (string) ($row['deliver_at'] ?? ''),
            'ack_deadline_at' => (string) ($row['ack_deadline_at'] ?? ''),
            'radio_sim' => (int) ($row['radio_sim'] ?? 0) === 1,
            'sim_state' => (string) ($row['sim_state'] ?? ''),
            'sim_state_label' => $this->orderSimStateLabelFr((string) ($row['sim_state'] ?? '')),
            'sim_latency_sec' => (int) ($row['sim_latency_sec'] ?? 0),
            'sim_event' => (string) ($row['sim_event'] ?? ''),
            'sim_event_label' => $this->orderSimEventLabelFr((string) ($row['sim_event'] ?? '')),
            'visible_to_recipient' => array_key_exists('visible_to_recipient', $row)
                ? (bool) $row['visible_to_recipient']
                : true,
            'source' => (string) ($row['source'] ?? 'web'),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];

        if ($waypointMeta !== null) {
            $result['waypoint'] = [
                'pos_x' => $waypointMeta['pos_x'],
                'pos_y' => $waypointMeta['pos_y'],
                'grid_reference' => $waypointMeta['grid_reference'],
                'eta_min' => $waypointMeta['eta_min'],
                'distance_m' => $waypointMeta['distance_m'],
                'speed_kph' => $waypointMeta['speed_kph'],
                'label' => $waypointMeta['label'],
            ];
        }

        return $result;
    }

    /**
     * Canonise un FRAGO (alerte IceMan / Overwatch) dans atak_orders pour l’onglet Ordres.
     *
     * @param array<string, mixed> $tactical
     * @return array<string, mixed>|null
     */
    private function upsertFragoOrderFromTacticalAlert(
        int $tenantId,
        int $mapId,
        array $tactical,
        string $author,
        int $chatId
    ): ?array {
        if (!$this->orderRepository || !$this->orderRepository->tablesReady()) {
            return null;
        }
        $externalId = trim((string) ($tactical['order_id'] ?? ''));
        if ($externalId === '' || preg_match('/^[A-Za-z0-9_.:\-]+$/', $externalId) !== 1) {
            $externalId = $chatId > 0
                ? ('FRAGO-CHAT-' . $chatId)
                : ('FRAGO-TAC-' . bin2hex(random_bytes(4)));
        }

        $issuer = trim((string) ($tactical['call_sign'] ?? ''));
        if ($issuer === '') {
            $issuer = trim($author) !== '' ? trim($author) : 'Terrain';
        }
        $payload = TacticalAlertParser::formatFragoOrderPayload($tactical);
        if ($payload === '') {
            $payload = trim((string) ($tactical['summary'] ?? 'Ordre fragmentaire'));
        }
        $grid = trim((string) ($tactical['grid'] ?? ''));
        if ($grid !== '' && mb_stripos($payload, $grid) === false) {
            $payload .= ' — Grille ' . $grid;
        }

        return $this->orderRepository->upsertByExternalId($tenantId, $mapId, [
            'external_id' => $externalId,
            'order_type' => 'FRAGO',
            'type_label' => 'Ordre fragmentaire',
            'target' => '',
            'target_type' => 'all',
            'payload' => $payload,
            'priority' => 'IMPORTANT',
            'issuer' => $issuer,
            'status' => 'PENDING',
            'source' => 'game',
            'radio_sim' => false,
        ]);
    }

    private function reportTypeLabelFr(string $type): string
    {
        return \App\Support\AtakIcemanReportCatalog::labelFr($type);
    }

    /**
     * Enregistre un rapport structuré (table rapports) à partir d’une alerte Iceman.
     *
     * @param array<string, mixed> $tactical
     * @param array<string, mixed>|null $actor
     */
    private function persistIcemanReportFromAlert(
        int $tenantId,
        int $mapId,
        array $tactical,
        ?array $actor,
        int $chatId
    ): void {
        $kind = strtolower(trim((string) ($tactical['kind'] ?? '')));
        $reportType = \App\Support\AtakIcemanReportCatalog::reportTypeForAlertKind($kind);
        if ($reportType === null || !\App\Support\AtakIcemanReportCatalog::shouldPersist($reportType)) {
            return;
        }
        $fields = [];
        foreach (['eagle_down', 'bda', 'salute', 'frago', 'tic'] as $bag) {
            if (!empty($tactical[$bag]) && is_array($tactical[$bag])) {
                $fields = $tactical[$bag];
                break;
            }
        }
        try {
            $repo = new \App\Repositories\AtakTacticalReportRepository();
            if ($chatId > 0 && $repo->findBySourceChatId($tenantId, $chatId) !== null) {
                return;
            }
            $summary = trim((string) ($tactical['summary'] ?? ''));
            if ($summary === '' && $fields !== []) {
                $summary = \App\Support\AtakIcemanReportCatalog::summaryFromFields($reportType, $fields);
            }
            if ($summary === '') {
                $summary = \App\Support\AtakIcemanReportCatalog::labelFr($reportType);
            }
            $callsign = trim((string) ($tactical['call_sign'] ?? ''));
            if ($callsign === '' && is_array($actor)) {
                $callsign = trim((string) ($actor['callsign'] ?? ''));
            }
            $repo->create([
                'tenant_id' => $tenantId,
                'context_id' => $mapId,
                'report_type' => $reportType,
                'report_number' => $repo->generateReportNumber($tenantId, $mapId, $reportType),
                'priority' => \App\Support\AtakIcemanReportCatalog::priorityFor($reportType, $fields),
                'classification' => 'UNCLASSIFIED',
                'submitter_user_id' => is_array($actor) ? ($actor['user_id'] ?? null) : null,
                'submitter_callsign' => $callsign !== '' ? $callsign : null,
                'submitter_steam_id' => is_array($actor) ? ($actor['steam_uid'] ?? null) : null,
                'source_chat_id' => $chatId > 0 ? $chatId : null,
                'pos_x' => $tactical['pos_x'] ?? null,
                'pos_y' => $tactical['pos_y'] ?? null,
                'grid_reference' => $tactical['grid'] ?? ($fields['grid'] ?? null),
                'dtg' => $fields['dtg'] ?? null,
                'structured_data' => $fields,
                'summary' => $summary,
                'details' => $summary,
                'status' => 'SUBMITTED',
                'visibility' => 'ALL',
            ]);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[atak_iceman_report] persist kind=%s chat=%d tenant=%d error=%s',
                $kind,
                $chatId,
                $tenantId,
                $e->getMessage()
            ));
        }
    }

    private function orderTypeLabelFr(string $type, string $customLabel = ''): string
    {
        $customLabel = trim($customLabel);
        $upper = strtoupper($type);
        $builtin = match ($upper) {
            'HOLD' => 'Tenir la position',
            'RECON' => 'Reconnaissance',
            'CAS' => 'Appui aérien',
            'QRF' => 'Force de réaction',
            'MOVE' => 'Se déplacer',
            'FRAGO' => 'Ordre fragmentaire',
            'VIBRATE' => 'Faire vibrer le terminal',
            'NOTIFY' => 'Notification terminal',
            'HELMET_SNAP' => 'Photo casque',
            'HELMET_SNAP_HD' => 'Photo casque HD',
            'HELMET_STREAM' => 'Flux casque',
            'PHONE_GEOLOC' => 'Géolocalisation téléphone',
            'PHONE_GEOLOC_OFF' => 'Arrêt géolocalisation téléphone',
            default => null,
        };
        if ($builtin !== null) {
            return $builtin;
        }
        if ($customLabel !== '') {
            return $customLabel;
        }
        if (AtakOrderTypeRepository::idFromCode($upper) !== null) {
            return 'Ordre personnalisé';
        }

        return match ($upper) {
            'CUSTOM' => 'Ordre personnalisé',
            default => (str_starts_with($upper, 'CUSTOM') || str_starts_with($upper, 'TPL_'))
                ? 'Ordre personnalisé'
                : 'Se déplacer',
        };
    }

    private function orderPriorityLabelFr(string $priority): string
    {
        return match (strtoupper($priority)) {
            'URGENT' => 'Urgent',
            'CONTACT' => 'Contact',
            'ROUTINE' => 'Routine',
            default => 'Important',
        };
    }

    private function orderStatusLabelFr(string $status): string
    {
        return match (strtoupper($status)) {
            'DELIVERED' => 'Reçu',
            'ACK' => 'Confirmé',
            'EXEC' => 'En cours',
            'FAILED' => 'Échec',
            'CANCELLED' => 'Annulé',
            default => 'Émis',
        };
    }

    private function orderTargetTypeLabelFr(string $type): string
    {
        return match (strtolower($type)) {
            'user' => 'Utilisateur',
            'group' => 'Groupe en jeu',
            'fire_team' => 'Fire team',
            'channel' => 'Canal',
            'solo' => 'ATAK Solo',
            'ally' => 'Unité alliée',
            default => 'Toute l’équipe',
        };
    }

    private function orderSimStateLabelFr(string $state): string
    {
        return match (strtolower($state)) {
            'queued' => 'En file d’attente radio',
            'transmitting' => 'Transmission en cours',
            'jammed' => 'Brouillage détecté',
            'retransmit' => 'Retransmission',
            'lost' => 'Non reçu — nouvel essai',
            'delivered' => 'Livré',
            default => '',
        };
    }

    private function orderSimEventLabelFr(string $event): string
    {
        return match (strtolower($event)) {
            'jamming' => 'Brouillage',
            'retransmit' => 'Retransmission',
            'lost_retry' => 'Perte puis nouvel essai',
            default => '',
        };
    }

    private function canIssueOrdersFromWeb(): bool
    {
        return $this->sessionUserBrief() !== null;
    }

    /**
     * Alias matchables pour un ordre (callsign, MID, steam, user:id, fire team…).
     *
     * @param array<string, mixed> $row
     * @return list<string>
     */
    private function orderMatchAliases(int $tenantId, int $mapId, array $row): array
    {
        $out = [];
        $add = static function (string $value) use (&$out): void {
            $value = trim($value);
            if ($value === '') {
                return;
            }
            foreach ($out as $existing) {
                if (strcasecmp($existing, $value) === 0) {
                    return;
                }
            }
            $out[] = $value;
        };

        $add((string) ($row['target'] ?? ''));
        $add((string) ($row['target_ref'] ?? ''));
        $targetType = strtolower((string) ($row['target_type'] ?? 'all'));
        $targetRef = trim((string) ($row['target_ref'] ?? ''));
        $opIds = $this->operatorIdRepository ?? new AtakOperatorIdRepository();

        if ($targetType === 'user') {
            $uid = (int) $targetRef;
            if ($uid > 0) {
                $add('user:' . $uid);
                $user = $this->userRepository->findById($uid, $tenantId);
                if (is_array($user)) {
                    $add((string) ($user['callsign'] ?? ''));
                    $add((string) ($user['display_name'] ?? ''));
                    $steam = SteamId::normalize((string) ($user['steam_id'] ?? ''));
                    if ($steam !== null && $steam !== '') {
                        $add($steam);
                        $add('steam:' . $steam);
                    }
                    if ($opIds->tablesReady()) {
                        $add($opIds->ensureForUser($tenantId, $uid, trim((string) ($user['callsign'] ?? '')) ?: null));
                    }
                }
            }
        } elseif ($targetType === 'solo') {
            $cs = $targetRef !== '' ? $targetRef : trim((string) ($row['target'] ?? ''));
            $add($cs);
            foreach ($this->atak->getUnits($tenantId, $mapId) as $unit) {
                if (strcasecmp((string) ($unit['call_sign'] ?? ''), $cs) !== 0) {
                    continue;
                }
                $add((string) ($unit['military_id'] ?? ''));
                $extra = $this->parseUnitExtra($unit);
                $steam = SteamId::normalize((string) ($extra['steam_uid'] ?? $extra['steamId'] ?? ''));
                if ($steam !== null && $steam !== '') {
                    $add($steam);
                    $add('steam:' . $steam);
                }
                break;
            }
            if ($cs !== '' && $opIds->tablesReady()) {
                $byCs = $opIds->findByCallSign($tenantId, $cs);
                if ($byCs) {
                    $add((string) ($byCs['military_id'] ?? ''));
                    $uid = (int) ($byCs['user_id'] ?? 0);
                    if ($uid > 0) {
                        $add('user:' . $uid);
                    }
                }
            }
        } elseif ($targetType === 'ally') {
            $add($targetRef);
            $add((string) ($row['target'] ?? ''));
            foreach ($this->atak->getUnits($tenantId, $mapId) as $unit) {
                if (!$this->unitIsAllyAi($unit)) {
                    continue;
                }
                $allyId = $this->unitAllyId($unit);
                $cs = trim((string) ($unit['call_sign'] ?? ''));
                if (strcasecmp($allyId, $targetRef) === 0 || strcasecmp($cs, $targetRef) === 0) {
                    $add($allyId);
                    $add($cs);
                    break;
                }
            }
        } elseif ($targetType === 'group') {
            $add($targetRef);
            $add((string) ($row['target'] ?? ''));
        } elseif ($targetType === 'channel') {
            $add(strtoupper($targetRef));
            $add((string) ($row['target'] ?? ''));
        } elseif ($targetType === 'fire_team') {
            $ftId = (int) $targetRef;
            if ($ftId > 0) {
                $add('ft:' . $ftId);
                $ftRepo = $this->fireTeamRepository ?? new FireTeamRepository();
                $team = $ftRepo->tablesReady() ? $ftRepo->findByIdForTenant($ftId, $tenantId) : null;
                if (is_array($team)) {
                    $add((string) ($team['label'] ?? ''));
                    foreach (($team['members'] ?? []) as $mem) {
                        if (!is_array($mem)) {
                            continue;
                        }
                        $add((string) ($mem['effective_callsign'] ?? $mem['callsign'] ?? ''));
                        $uid = (int) ($mem['user_id'] ?? 0);
                        if ($uid > 0) {
                            $add('user:' . $uid);
                            if ($opIds->tablesReady()) {
                                $add($opIds->ensureForUser($tenantId, $uid, null));
                            }
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Identité locale du joueur jeu (steam / callsign / MID / fire teams).
     *
     * @return list<string>
     */
    private function resolveGameRecipientAliases(int $tenantId, int $mapId, ?string $steam, string $callsign): array
    {
        $out = [];
        $add = static function (string $value) use (&$out): void {
            $value = trim($value);
            if ($value === '') {
                return;
            }
            foreach ($out as $existing) {
                if (strcasecmp($existing, $value) === 0) {
                    return;
                }
            }
            $out[] = $value;
        };

        $add($callsign);
        $opIds = $this->operatorIdRepository ?? new AtakOperatorIdRepository();
        $user = null;

        if ($steam !== null && $steam !== '') {
            $add($steam);
            $add('steam:' . $steam);
            $user = $this->userRepository->findBySteamIdForTenant($tenantId, $steam)
                ?? $this->userRepository->findBySteamId($steam);
            foreach ($this->atak->getUnits($tenantId, $mapId) as $unit) {
                $extra = $this->parseUnitExtra($unit);
                $unitSteam = SteamId::normalize((string) ($extra['steam_uid'] ?? $extra['steamId'] ?? ''));
                if ($unitSteam === null || $unitSteam !== $steam) {
                    continue;
                }
                $add((string) ($unit['call_sign'] ?? ''));
                $add((string) ($unit['military_id'] ?? ''));
                $add($this->unitArmaGroupName($unit));
                break;
            }
        }

        if (is_array($user) && (int) ($user['tenant_id'] ?? 0) === $tenantId) {
            $uid = (int) ($user['id'] ?? 0);
            if ($uid > 0) {
                $add('user:' . $uid);
                $add((string) ($user['callsign'] ?? ''));
                $add((string) ($user['display_name'] ?? ''));
                if ($opIds->tablesReady()) {
                    $add($opIds->ensureForUser($tenantId, $uid, trim((string) ($user['callsign'] ?? '')) ?: null));
                }
                $ftRepo = $this->fireTeamRepository ?? new FireTeamRepository();
                if ($ftRepo->tablesReady()) {
                    foreach ($ftRepo->listForTenant($tenantId) as $team) {
                        $tid = (int) ($team['id'] ?? 0);
                        if ($tid < 1) {
                            continue;
                        }
                        foreach (($team['members'] ?? []) as $mem) {
                            if (!is_array($mem)) {
                                continue;
                            }
                            if ((int) ($mem['user_id'] ?? 0) === $uid) {
                                $add('ft:' . $tid);
                                $add((string) ($team['label'] ?? ''));
                                break;
                            }
                        }
                    }
                }
            }
        } elseif ($callsign !== '' && $opIds->tablesReady()) {
            $byCs = $opIds->findByCallSign($tenantId, $callsign);
            if ($byCs) {
                $add((string) ($byCs['military_id'] ?? ''));
                $uid = (int) ($byCs['user_id'] ?? 0);
                if ($uid > 0) {
                    $add('user:' . $uid);
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $orderAliases
     * @param list<string> $recipientAliases
     */
    private function orderMatchesRecipientAliases(array $row, array $orderAliases, array $recipientAliases): bool
    {
        $targetType = strtolower((string) ($row['target_type'] ?? 'all'));
        $target = trim((string) ($row['target'] ?? ''));
        if ($targetType === 'all' || $target === '') {
            return true;
        }
        // Canaux : laisser passer (filtre fin côté jeu / radio).
        if ($targetType === 'channel') {
            return true;
        }

        foreach ($orderAliases as $oa) {
            foreach ($recipientAliases as $ra) {
                if (strcasecmp($oa, $ra) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array{displayName: string, callsign: string, userId: int}|null */
    private function sessionUserBrief(): ?array
    {
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($userId < 1) {
            return null;
        }
        $display = trim((string) (Session::get('display_name') ?? ''));
        $callsign = trim((string) (Session::get('callsign') ?? ''));

        return [
            'displayName' => $display,
            'callsign' => $callsign,
            'userId' => $userId,
        ];
    }

    /**
     * @param array{level?:string,channel?:string,message:string,detail?:string,source?:string} $event
     * @param array{displayName?:string,callsign?:string,userId?:int}|null $brief
     */
    private function recordDeviceTrace(int $tenantId, array $event, string $callsign = '', ?array $brief = null): void
    {
        try {
            $uid = $this->resolveDeviceTerminalUid($tenantId, '', $callsign, $brief);
            if ($uid === '') {
                return;
            }
            $name = is_array($brief) ? trim((string) ($brief['displayName'] ?? '')) : '';
            $this->deviceLogs()->recordEvent(
                $tenantId,
                $uid,
                $event,
                $callsign !== '' ? $callsign : null,
                null,
                $name !== '' ? $name : null
            );
        } catch (\Throwable) {
        }
    }

    /**
     * Mémorise le groupe sanguin lu en jeu (ACE / plaque) pour le prochain bilan médical.
     *
     * @param array<string, mixed> $body
     */
    private function persistArmaBloodType(int $userId, array $body): void
    {
        if ($userId < 1) {
            return;
        }
        $raw = trim((string) ($body['blood_type'] ?? $body['bloodType'] ?? ''));
        $normalized = \App\Support\RoleplayDeadlinePolicy::normalizeBloodType($raw);
        if ($normalized === '') {
            return;
        }
        try {
            $profiles = new \App\Repositories\PersonnelProfileRepository();
            $existing = $profiles->getByUserId($userId) ?? [];
            $previous = \App\Support\RoleplayDeadlinePolicy::normalizeBloodType((string) ($existing['rp_arma_blood_type'] ?? ''));
            if ($previous === $normalized) {
                return;
            }
            $profiles->update($userId, [
                'rp_arma_blood_type' => $normalized,
                'rp_arma_blood_type_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * @param array{displayName?:string,callsign?:string,userId?:int}|null $brief
     */
    private function resolveDeviceTerminalUid(int $tenantId, string $terminalUid, string $callsign, ?array $brief = null): string
    {
        $uid = trim($terminalUid);
        if ($uid !== '') {
            return $uid;
        }
        try {
            $registry = $this->realismRegistry();
            if ($callsign !== '') {
                $row = $registry->findLatestTerminalByCallsign($tenantId, $callsign);
                $found = trim((string) ($row['terminal_uid'] ?? ''));
                if ($found !== '') {
                    return $found;
                }
            }
            $userId = is_array($brief) ? (int) ($brief['userId'] ?? 0) : 0;
            if ($userId > 0) {
                $web = $registry->findWebSessionForUser($tenantId, $userId);
                $found = trim((string) ($web['terminal_uid'] ?? ''));
                if ($found !== '') {
                    return $found;
                }
            }
        } catch (\Throwable) {
        }

        return '';
    }

    /**
     * Calque « Dossiers SSE » + LOT 5 (PIR, taskings, photos, tracks, historique).
     */
    public function sseCaseOverlay(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);

        try {
            $service = new \App\Services\Sse\SseAtakLayersService();
            $payload = $service->buildOverlay($tenantId, $mapId);

            return Response::json($payload);
        } catch (\Throwable) {
            return Response::json([
                'mapId' => $mapId,
                'count' => 0,
                'points' => [],
                'layers' => [],
                'counts' => [],
            ]);
        }
    }

    /**
     * Enregistre un tracé / tracé fantôme SSE sur la carte ATAK.
     */
    public function sseTrackStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $userId = (int) (Session::get('user_id') ?? 0) ?: null;
        $author = trim((string) (Session::get('display_name') ?? Session::get('arma_callsign') ?? ''));
        $body = $this->jsonBody($request);
        if (!is_array($body)) {
            $body = [];
        }
        $body['map_id'] = (int) ($body['map_id'] ?? $this->mapId($request));

        $service = new \App\Services\Sse\SseAtakLayersService();
        $result = $service->saveTrack($tenantId, $body, $author, $userId);
        if (!($result['ok'] ?? false)) {
            return Response::json(['error' => $result['error'] ?? 'Enregistrement impossible.'], 422);
        }

        return Response::json(['ok' => true, 'id' => $result['id'], 'message' => $result['message'] ?? 'OK']);
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
        try {
            $rows = $this->atak->getPings($tenantId, $mapId, min($limit, 200));
        } catch (\Throwable) {
            return Response::json([]);
        }
        return Response::json($rows);
    }

    public function pingsStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            $actor = $this->guardArmaWrite($request, $tenantId, false);
            if ($actor instanceof Response) {
                return $actor;
            }
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? self::DEFAULT_MAP_ID);
        $author = $body['author'] ?? 'Anonymous';
        $posX = (float) ($body['pos_x'] ?? $body['pos'][0] ?? 0);
        $posY = (float) ($body['pos_y'] ?? $body['pos'][1] ?? 0);
        $coordsOk = $this->armaGuard->assertPositionCoords($posX, $posY, $tenantId);
        if ($coordsOk instanceof Response) {
            return $coordsOk;
        }
        $message = $body['message'] ?? '';
        $row = $this->atak->addPing($tenantId, $mapId, $author, $posX, $posY, $message);
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_PING,
            'Repère envoyé — ' . $author,
            (string) $author
        );
        return Response::json($row, 201);
    }

    public function pingsDelete(Request $request, array $params = []): Response
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
        if (!$this->atak->deletePing($tenantId, $id)) {
            return Response::json(['error' => 'Not found'], 404);
        }

        return Response::json(['ok' => true]);
    }

    public function explosiveTimersIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        try {
            $rows = $this->explosiveTimers()->listForMap($tenantId, $mapId);
        } catch (\Throwable) {
            return Response::json([
                'items' => [],
                'armed_count' => 0,
                'can_command_detonate' => false,
            ]);
        }

        return Response::json([
            'items' => $rows,
            'armed_count' => count(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? '') === 'armed')),
            'can_command_detonate' => $this->canIssueOrdersFromWeb()
                && ComspecApiKeyAuth::extractPresentedKey() === ''
                && $this->explosiveTimers()->commandColumnsReady(),
        ]);
    }

    public function explosiveTimersStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            $actor = $this->guardArmaWrite($request, $tenantId, false);
            if ($actor instanceof Response) {
                return $actor;
            }
        }
        if (!$this->explosiveTimers()->tablesReady()) {
            return Response::json([
                'error' => 'migration_required',
                'message' => 'Le suivi des charges à retardement n’est pas encore disponible sur cette communauté.',
            ], 503);
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $status = strtolower(trim((string) ($body['status'] ?? 'armed')));
        if ($status === 'armed' || $status === '') {
            $posX = (float) ($body['pos_x'] ?? $body['pos'][0] ?? 0);
            $posY = (float) ($body['pos_y'] ?? $body['pos'][1] ?? 0);
            $coordsOk = $this->armaGuard->assertPositionCoords($posX, $posY, $tenantId);
            if ($coordsOk instanceof Response) {
                return $coordsOk;
            }
            $body['pos_x'] = $posX;
            $body['pos_y'] = $posY;
            $body['status'] = 'armed';
        }
        $row = $this->explosiveTimers()->upsert($tenantId, $mapId, $body);
        if ($row === null) {
            return Response::json(['error' => 'Enregistrement impossible.'], 422);
        }
        if (($row['status'] ?? '') === 'armed' && (int) ($row['id'] ?? 0) > 0) {
            $startedTs = strtotime((string) ($row['started_at'] ?? ''));
            if ($startedTs !== false && abs(time() - $startedTs) <= 8) {
                $author = (string) ($row['author'] ?? '');
                $kind = (string) ($row['trigger_kind'] ?? 'timer');
                $prefix = $kind === 'timer' ? 'Charge à retardement — ' : 'Charge posée — ';
                $this->activityLog->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_EXPLOSIVE_TIMER,
                    $prefix . ($author !== '' ? $author : 'terrain'),
                    $author !== '' ? $author : null
                );
            }
        }

        return Response::json($row, 201);
    }

    public function explosiveTimersCommands(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);
        if (!$this->explosiveTimers()->tablesReady()) {
            return Response::json(['commands' => []]);
        }

        return Response::json([
            'commands' => $this->explosiveTimers()->listPendingDetonations($tenantId, $mapId),
        ]);
    }

    public function explosiveTimersDetonate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Le déclenchement d’une charge se fait depuis le poste de commandement, pas depuis le terrain.',
            ], 403);
        }
        if (!$this->canIssueOrdersFromWeb()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour déclencher une charge depuis la carte.',
            ], 403);
        }
        if (!$this->explosiveTimers()->commandColumnsReady()) {
            return Response::json([
                'error' => 'migration_required',
                'message' => 'Le déclenchement des charges n’est pas encore disponible sur cette communauté.',
            ], 503);
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['error' => 'Not found'], 404);
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? $request->query('mapId') ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $user = $this->sessionUserBrief();
        $by = '';
        if ($user) {
            $by = (string) ($user['callsign'] ?: $user['displayName'] ?: 'Poste de commandement');
        }
        if ($by === '') {
            $by = 'Poste de commandement';
        }
        $row = $this->explosiveTimers()->requestDetonate($tenantId, $mapId, $id, $by);
        if ($row === null) {
            return Response::json([
                'error' => 'unavailable',
                'message' => 'Cette charge ne se déclenche pas depuis le poste, ou elle n’est plus armée.',
            ], 404);
        }
        $label = (string) ($row['magazine_label'] ?? 'Charge');
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_EXPLOSIVE_TIMER,
            'Déclenchement demandé — ' . $label,
            $by
        );

        return Response::json($row);
    }

    public function explosiveTimersDetonateAll(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Le déclenchement groupé se fait depuis le poste de commandement, pas depuis le terrain.',
            ], 403);
        }
        if (!$this->canIssueOrdersFromWeb()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour déclencher les charges depuis la carte.',
            ], 403);
        }
        if (!$this->explosiveTimers()->commandColumnsReady()) {
            return Response::json([
                'error' => 'migration_required',
                'message' => 'Le déclenchement des charges n’est pas encore disponible sur cette communauté.',
            ], 503);
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? $request->query('mapId') ?? self::DEFAULT_MAP_ID);
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $user = $this->sessionUserBrief();
        $by = '';
        if ($user) {
            $by = (string) ($user['callsign'] ?: $user['displayName'] ?: 'Poste de commandement');
        }
        if ($by === '') {
            $by = 'Poste de commandement';
        }
        $items = $this->explosiveTimers()->requestDetonateAll($tenantId, $mapId, $by, ['atak']);
        $n = count($items);
        if ($n > 0) {
            $this->activityLog->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_EXPLOSIVE_TIMER,
                'Déclenchement groupé demandé — ' . $n . ' charge(s) ATAK',
                $by
            );
        }

        return Response::json([
            'items' => $items,
            'count' => $n,
            'message' => $n > 0
                ? 'Déclenchement demandé pour ' . $n . ' charge(s). En attente du terrain.'
                : 'Aucune charge ATAK armée à déclencher pour le moment.',
        ]);
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
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_NINE_LINE,
            'Demande d’appui aérien envoyée — ' . $author,
            (string) $author
        );
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
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_DESIGNATOR,
            'Désignateur signalé — ' . $callSign,
            (string) $callSign
        );
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
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_SIGINT,
            'Signalement radio — ' . $callSign,
            (string) $callSign
        );
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
        $night = trim((string) ($request->query('night') ?? $request->query('play_night') ?? 'current'));
        $rows = $this->atak->getIntelPhotos($tenantId, $mapId);
        $rows = $this->applyPlayNightFilter($rows, $night, ['created_at']);
        foreach ($rows as &$r) {
            $r['url'] = '/uploads/intel/' . basename($r['path']);
        }
        unset($r);
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
        if (empty($_FILES['photo'])) {
            return Response::json([
                'error' => 'missing_photo',
                'message' => 'Aucune photo reçue. Reprenez la capture depuis le terrain.',
            ], 400);
        }
        $uploadErr = (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $msg = match ($uploadErr) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La photo est trop lourde pour être transmise. Essayez une capture plus légère.',
                UPLOAD_ERR_PARTIAL => 'Envoi interrompu — la liaison semble dégradée. Réessayez.',
                UPLOAD_ERR_NO_FILE => 'Fichier photo introuvable. Reprenez la capture.',
                default => 'Impossible de recevoir la photo. Vérifiez la liaison puis réessayez.',
            };

            return Response::json(['error' => 'upload_failed', 'message' => $msg], 400);
        }
        $dir = dirname(__DIR__, 2) . '/../public/uploads/intel';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = date('YmdHis') . '-' . ($_FILES['photo']['name'] ?: 'photo.' . $ext);
        $path = $dir . '/' . $filename;
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $path)) {
            return Response::json([
                'error' => 'save_failed',
                'message' => 'La photo n’a pas pu être enregistrée côté poste de commandement. Réessayez.',
            ], 500);
        }
        $relativePath = 'intel/' . $filename;
        $row = $this->atak->addIntelPhoto($tenantId, $mapId, $filename, $relativePath, $author, $posX, $posY);
        $row['url'] = '/uploads/intel/' . $filename;
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_INTEL,
            'Photo de reconnaissance reçue — ' . $author,
            (string) $author
        );
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
        $actor = $this->guardArmaWrite($request, $tenantId, false);
        if ($actor instanceof Response) {
            return $actor;
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        $occupancy = \App\Services\Tactical\AtakAirAssetMergeService::isOccupancyPayload($body);
        $callsign = $this->resolveFlightManifestCallsign($tenantId, $mapId, $body, $actor);
        $missionToken = getenv('ATAK_MISSION_AUTH_TOKEN') ?: getenv('COMSPEC_MISSION_AUTH') ?: '';
        $status = $body['status'] ?? 'IN-FLIGHT';
        if (
            !$occupancy
            && $missionToken !== ''
            && ($body['auth'] ?? $body['authCode'] ?? '') !== $missionToken
        ) {
            $status = 'SUSPECT';
        }
        $data = array_merge($body, [
            'status' => $status,
            'callsign' => $callsign,
            'call_sign' => $callsign,
            'pos_x' => isset($body['pos']) && is_array($body['pos']) ? ($body['pos'][0] ?? null) : ($body['pos_x'] ?? null),
            'pos_y' => isset($body['pos']) && is_array($body['pos']) ? ($body['pos'][1] ?? null) : ($body['pos_y'] ?? null),
            'pos_z' => isset($body['pos']) && is_array($body['pos']) && isset($body['pos'][2]) ? $body['pos'][2] : ($body['pos_z'] ?? null),
        ]);
        $row = $this->atak->upsertAirAsset($tenantId, $mapId, $callsign, $data);
        try {
            $this->motionService()->ingestAir($tenantId, $mapId, $callsign, is_array($row) ? array_merge($data, $row) : $data);
        } catch (\Throwable) {
        }
        $this->atak->setLastActivity($tenantId, $mapId);
        if (!$occupancy) {
            $this->activityLog->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_FLIGHT,
                'Manifeste de vol déclaré — ' . $callsign,
                $callsign,
                $this->buildActivityMeta($tenantId, $mapId, $body, is_array($actor) ? $actor : null, $callsign, [
                    'model' => (string) ($data['model'] ?? ''),
                    'aircraft_type' => (string) ($data['aircraft_type'] ?? $data['aircraftType'] ?? ''),
                    'laser' => (string) ($data['laser'] ?? ''),
                ])
            );
        }
        return Response::json($row, 201);
    }

    /**
     * Indicatif manifeste : jamais le libellé technique anglais « Unknown ».
     *
     * @param array<string, mixed> $body
     * @param array{steam_uid?: ?string}|null $actor
     */
    private function resolveFlightManifestCallsign(int $tenantId, int $mapId, array $body, ?array $actor): string
    {
        $candidates = [
            $body['callsign'] ?? null,
            $body['call_sign'] ?? null,
            $body['pilot'] ?? null,
            $body['unit'] ?? null,
        ];
        foreach ($candidates as $raw) {
            $cs = trim((string) ($raw ?? ''));
            if ($cs === '') {
                continue;
            }
            if (strcasecmp($cs, 'Unknown') === 0 || strcasecmp($cs, 'Inconnu') === 0) {
                continue;
            }
            return $cs;
        }

        $steam = is_array($actor) ? trim((string) ($actor['steam_uid'] ?? '')) : '';
        if ($steam !== '' && $tenantId > 0 && $mapId > 0) {
            try {
                $unit = $this->atak->findUnitBySteamUid($tenantId, $mapId, $steam);
                $fromUnit = trim((string) ($unit['call_sign'] ?? $unit['callsign'] ?? ''));
                if ($fromUnit !== '' && strcasecmp($fromUnit, 'Unknown') !== 0 && strcasecmp($fromUnit, 'Inconnu') !== 0) {
                    return $fromUnit;
                }
            } catch (\Throwable) {
                // ignore — repli ci-dessous
            }
        }

        return 'Sans indicatif';
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
        try {
            $rows = $this->casRepo->listCas($tenantId, $mapId, $assignedTo, $status, CasNineLineRepository::KIND_CAS);

            return Response::json($rows);
        } catch (\Throwable) {
            return Response::json([]);
        }
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
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_NINE_LINE,
            'Demande d’appui aérien envoyée — ' . $author,
            (string) $author
        );
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
        try {
            $missionId = $request->query('mission_id') ?? $request->query('missionId');
            $author = $request->query('author');
            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');
            $deviceType = $request->query('device_type') ?? $request->query('device');
            $limit = min((int) ($request->query('limit') ?: 100), 200);
            $night = trim((string) ($request->query('night') ?? $request->query('play_night') ?? ''));
            $rows = $this->reconImages()->list($tenantId, $missionId, $author, $dateFrom, $dateTo, $limit);
            if (is_string($deviceType) && $deviceType !== '') {
                $want = strtoupper($deviceType);
                $rows = array_values(array_filter($rows, static function (array $row) use ($want): bool {
                    return strtoupper((string) ($row['device_type'] ?? '')) === $want;
                }));
            }
            if ($night !== '') {
                $rows = $this->applyPlayNightFilter($rows, $night, ['captured_at', 'created_at']);
            } else {
                $rows = $this->applyPlayNightFilter($rows, 'all', ['captured_at', 'created_at']);
            }
            foreach ($rows as &$row) {
                $row['url'] = user_media_public_url('uploads/recon/' . basename((string) ($row['image_path'] ?? '')));
                $row['device_label'] = $this->reconDeviceLabel((string) ($row['device_type'] ?? 'CTAB'));
            }
            unset($row);

            return Response::json($rows);
        } catch (\Throwable $e) {
            error_log('[atak/recon-images] index ' . $e->getMessage());

            return Response::json([]);
        }
    }

    private function reconDeviceLabel(string $deviceType): string
    {
        return match (strtoupper(trim($deviceType))) {
            'HELMET', 'HCAM' => 'Caméra casque',
            'DRONE' => 'Caméra drone',
            'UAV', 'VEHICLE' => 'Caméra aérienne',
            'CTAB', 'TABLET' => 'Photo tablette',
            default => 'Photo terrain',
        };
    }

    public function reconImagesStore(Request $request, array $params = []): Response
    {
        try {
            if (!$this->authArma()) {
                return Response::json(['error' => 'Unauthorized'], 401);
            }
            $r = $this->requireTenant($request);
            if ($r instanceof Response) {
                return $r;
            }
            $tenantId = $r;
            $actor = $this->guardArmaWrite($request, $tenantId, false);
            if ($actor instanceof Response) {
                return $actor;
            }
            $file = TerrainUploadedImage::fromGlobals();
            if ($file === null) {
                error_log(sprintf(
                    '[atak/recon-images] missing_image files=%s ct=%s cl=%s',
                    implode(',', array_keys($_FILES)),
                    (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
                    (string) ($_SERVER['CONTENT_LENGTH'] ?? '')
                ));

                return Response::json([
                    'error' => 'missing_image',
                    'message' => 'Aucune image reçue. Reprenez la capture depuis le terrain.',
                ], 400);
            }
            if (TerrainUploadedImage::isSseFaceFileName((string) ($file['name'] ?? ''))) {
                return Response::json([
                    'ok' => true,
                    'ignored' => true,
                    'message' => 'Photo de visage transmise avec la fiche, pas comme cliché de reconnaissance.',
                ], 200);
            }
            $uploadErr = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadErr !== UPLOAD_ERR_OK) {
                $msg = match ($uploadErr) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La photo est trop lourde pour être transmise. Essayez une capture plus légère.',
                    UPLOAD_ERR_PARTIAL => 'Envoi interrompu — la liaison semble dégradée. Réessayez.',
                    UPLOAD_ERR_NO_FILE => 'Fichier photo introuvable. Reprenez la capture.',
                    default => 'Impossible de recevoir la photo. Vérifiez la liaison puis réessayez.',
                };

                return Response::json(['error' => 'upload_failed', 'message' => $msg], 400);
            }

            $dir = function_exists('base_path')
                ? base_path('public/uploads/recon')
                : (dirname(__DIR__, 2) . '/../public/uploads/recon');
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                error_log('[atak/recon-images] mkdir failed: ' . $dir);

                return Response::json([
                    'error' => 'save_failed',
                    'message' => 'La photo n’a pas pu être enregistrée côté poste de commandement. Réessayez.',
                ], 503);
            }
            $ext = pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION) ?: 'jpg';
            $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?: 'jpg';
            $filename = 'recon_' . date('YmdHis') . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_POST['author'] ?? 'unknown')) . '.' . $ext;
            $path = $dir . DIRECTORY_SEPARATOR . $filename;
            if (!TerrainUploadedImage::move((string) $file['tmp_name'], $path)) {
                return Response::json([
                    'error' => 'save_failed',
                    'message' => 'La photo n’a pas pu être enregistrée côté poste de commandement. Réessayez.',
                ], 503);
            }
            $rawDevice = (string) ($_POST['device_type'] ?? $_POST['device'] ?? 'CTAB');
            $deviceNorm = strtoupper(trim($rawDevice));
            if (in_array($deviceNorm, ['HCAM', 'HELMET_CAM'], true)) {
                $deviceNorm = 'HELMET';
            } elseif ($deviceNorm === 'VEHICLE') {
                $deviceNorm = 'UAV';
            }
            $feedId = trim((string) ($_POST['feed_id'] ?? $_POST['feedId'] ?? ''));
            $unitName = $_POST['unit_name'] ?? $_POST['unitName'] ?? null;
            if (($unitName === null || $unitName === '') && $feedId !== '') {
                $unitName = $feedId;
            }
            $data = [
                'image_path' => 'recon/' . $filename,
                'author_callsign' => $_POST['author'] ?? $_POST['author_callsign'] ?? 'Unknown',
                'unit_name' => $unitName,
                'side' => $_POST['side'] ?? 'WEST',
                'mission_id' => $_POST['mission_id'] ?? $_POST['missionId'] ?? null,
                'caption' => $_POST['caption'] ?? null,
                'fx_profile' => isset($_POST['fx_profile']) ? trim((string) $_POST['fx_profile']) : null,
                'fx_intensity' => isset($_POST['fx_intensity']) && is_numeric($_POST['fx_intensity']) ? round((float) $_POST['fx_intensity'], 2) : null,
                'pos_x' => isset($_POST['pos_x']) && is_numeric($_POST['pos_x']) ? (float) $_POST['pos_x'] : null,
                'pos_y' => isset($_POST['pos_y']) && is_numeric($_POST['pos_y']) ? (float) $_POST['pos_y'] : null,
                'pos_z' => isset($_POST['pos_z']) && is_numeric($_POST['pos_z']) ? (float) $_POST['pos_z'] : null,
                'grid_ref' => $_POST['grid_ref'] ?? $_POST['grid'] ?? null,
                'heading' => isset($_POST['heading']) && is_numeric($_POST['heading']) ? (float) $_POST['heading'] : null,
                'altitude' => isset($_POST['altitude']) && is_numeric($_POST['altitude']) ? (float) $_POST['altitude'] : null,
                'device_type' => $deviceNorm !== '' ? $deviceNorm : 'CTAB',
                'captured_at' => isset($_POST['capturedAt']) ? (int) $_POST['capturedAt'] : time(),
            ];
            try {
                (new \App\Services\Media\ReconPhotoHudService())->applyToFile($tenantId, $path, $data);
            } catch (\Throwable $hudErr) {
                error_log('[atak/recon-images] photo hud ' . $hudErr->getMessage());
            }
            $row = $this->reconImages()->create($tenantId, $data);
            if ($row === []) {
                @unlink($path);
                error_log('[atak/recon-images] create failed for ' . $filename);

                return Response::json([
                    'error' => 'store_failed',
                    'message' => 'La photo a été reçue mais n’a pas pu être indexée. Réessayez dans un instant.',
                ], 503);
            }
            try {
                $row['url'] = user_media_public_url('uploads/recon/' . $filename);
            } catch (\Throwable) {
                $row['url'] = '/uploads/recon/' . $filename;
            }
            $row['device_label'] = $this->reconDeviceLabel((string) ($row['device_type'] ?? 'CTAB'));
            $mapId = (int) ($_POST['mapId'] ?? $_POST['map_id'] ?? self::DEFAULT_MAP_ID);
            try {
                $this->activityLog->record(
                    $tenantId,
                    $mapId > 0 ? $mapId : self::DEFAULT_MAP_ID,
                    AtakActivityLogService::TYPE_INTEL,
                    $row['device_label'] . ' reçue — ' . ($data['author_callsign'] ?? 'Inconnu'),
                    (string) ($data['author_callsign'] ?? '')
                );
            } catch (\Throwable $logErr) {
                error_log('[atak/recon-images] activity ' . $logErr->getMessage());
            }

            return Response::json($row, 201);
        } catch (\Throwable $e) {
            error_log('[atak/recon-images] store ' . $e->getMessage());

            return Response::json([
                'error' => 'store_failed',
                'message' => 'Impossible d’enregistrer la photo pour le moment. Réessayez.',
            ], 503);
        }
    }

    public function reconImagesShow(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $id = (int) ($params['id'] ?? 0);
        $row = $this->reconImages()->get($tenantId, $id);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        $row['url'] = user_media_public_url('uploads/recon/' . basename($row['image_path']));
        return Response::json($row);
    }

    public function reconImagesSseCases(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        if ($resp = $this->requireReconOperatorSession($tenantId)) {
            return $resp;
        }

        $access = new \App\Services\Sse\SseAccessCodeService();
        if (!$access->hasActiveClearance() || $access->tenantId() !== $tenantId) {
            return Response::json([
                'error' => 'sse_clearance_required',
                'message' => 'Ouvrez d’abord le portail SSE pour choisir un dossier de renseignement.',
            ], 403);
        }

        $cases = (new \App\Repositories\SseCaseRepository())->listForTenant($tenantId, $access->caseScope(), []);
        $out = array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'reference_code' => (string) ($row['reference_code'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'classification_label' => (string) ($row['classification_label'] ?? ''),
                'status_label' => (string) ($row['status_label'] ?? ''),
            ];
        }, $cases);

        return Response::json(['cases' => $out, 'count' => count($out)]);
    }

    public function reconImagesOps(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        if ($resp = $this->requireReconOperatorSession($tenantId)) {
            return $resp;
        }

        $id = (int) ($params['id'] ?? 0);
        $row = $this->reconImages()->get($tenantId, $id);
        if ($row === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Photo introuvable.'], 404);
        }

        $body = $this->jsonBody($request);
        $action = strtolower(trim((string) ($body['action'] ?? '')));
        if ($action === '') {
            return Response::json(['error' => 'action_required', 'message' => 'Action photo manquante.'], 422);
        }

        $updated = null;
        $message = 'Action appliquée.';

        if ($action === 'comment') {
            $comment = trim((string) ($body['comment'] ?? ''));
            $updated = $this->reconImages()->updateOps($tenantId, $id, ['operator_comment' => $comment]);
            $message = $comment !== '' ? 'Commentaire enregistré.' : 'Commentaire retiré.';
        } elseif ($action === 'blur') {
            $blur = !empty($body['blurred']);
            $updated = $this->reconImages()->updateOps($tenantId, $id, ['is_blurred' => $blur]);
            $message = $blur ? 'Flou activé.' : 'Flou retiré.';
        } elseif ($action === 'delete') {
            $updated = $this->reconImages()->updateOps($tenantId, $id, ['deleted_at' => date('Y-m-d H:i:s')]);
            $message = 'Photo supprimée du panneau tactique.';
        } elseif ($action === 'sse_transfer') {
            $caseId = (int) ($body['case_id'] ?? 0);
            if ($caseId < 1) {
                return Response::json(['error' => 'case_required', 'message' => 'Choisissez un dossier SSE.'], 422);
            }
            $access = new \App\Services\Sse\SseAccessCodeService();
            if (!$access->hasActiveClearance() || $access->tenantId() !== $tenantId) {
                return Response::json([
                    'error' => 'sse_clearance_required',
                    'message' => 'Ouvrez d’abord le portail SSE pour transférer une photo classifiée.',
                ], 403);
            }
            $scope = $access->caseScope();
            if ($scope !== null && !in_array($caseId, $scope, true)) {
                return Response::json(['error' => 'case_forbidden', 'message' => 'Ce dossier SSE n’est pas accessible dans votre session.'], 403);
            }
            $caseRepo = new \App\Repositories\SseCaseRepository();
            $case = $caseRepo->findById($caseId, $tenantId);
            if ($case === null) {
                return Response::json(['error' => 'case_not_found', 'message' => 'Dossier SSE introuvable.'], 404);
            }
            $srcRel = trim((string) ($row['image_path'] ?? ''));
            $srcAbs = base_path('public/uploads/recon/' . basename($srcRel));
            if (!is_file($srcAbs)) {
                return Response::json(['error' => 'missing_file', 'message' => 'Le fichier source est introuvable sur le serveur.'], 404);
            }
            $destDir = base_path('public/uploads/sse/evidence');
            if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                return Response::json(['error' => 'storage', 'message' => 'Impossible de préparer l’archive SSE.'], 500);
            }
            $ext = pathinfo($srcAbs, PATHINFO_EXTENSION) ?: 'jpg';
            $destName = sprintf('recon_%d_%d.%s', $id, time(), strtolower((string) preg_replace('/[^a-z0-9]/i', '', $ext)));
            $destAbs = $destDir . DIRECTORY_SEPARATOR . $destName;
            if (!@copy($srcAbs, $destAbs)) {
                return Response::json(['error' => 'copy_failed', 'message' => 'Impossible de copier la photo vers l’espace SSE.'], 500);
            }
            $captionParts = [];
            $captionBase = trim((string) ($row['caption'] ?? ''));
            if ($captionBase !== '') {
                $captionParts[] = $captionBase;
            }
            $comment = trim((string) ($row['operator_comment'] ?? ''));
            if ($comment !== '') {
                $captionParts[] = 'Commentaire: ' . $comment;
            }
            $evidenceId = $caseRepo->addEvidence($caseId, $tenantId, [
                'label' => 'Photo terrain Athena',
                'caption' => $captionParts !== [] ? implode("\n", $captionParts) : null,
                'image_path' => 'uploads/sse/evidence/' . $destName,
                'author_label' => (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Opérateur'),
            ]);
            $updated = $this->reconImages()->updateOps($tenantId, $id, [
                'sse_case_id' => $caseId,
                'sse_evidence_id' => $evidenceId,
                'sse_transferred_at' => date('Y-m-d H:i:s'),
            ]);
            $message = 'Photo transférée dans le dossier SSE.';
        } else {
            return Response::json(['error' => 'unknown_action', 'message' => 'Action non prise en charge.'], 422);
        }

        if ($updated === null) {
            return Response::json(['error' => 'update_failed', 'message' => 'Impossible de mettre à jour la photo.'], 500);
        }

        $updated['url'] = user_media_public_url('uploads/recon/' . basename((string) ($updated['image_path'] ?? '')));
        $updated['device_label'] = $this->reconDeviceLabel((string) ($updated['device_type'] ?? 'CTAB'));

        return Response::json(['ok' => true, 'message' => $message, 'photo' => $updated]);
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
        $row = $this->reconImages()->linkToCas($tenantId, $id, $casId);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json($row);
    }

    private function requireReconOperatorSession(int $tenantId): ?Response
    {
        Session::start();
        $userId = (int) (Session::get('user_id') ?? 0);
        $sessionTenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($userId < 1 || $sessionTenantId !== $tenantId) {
            return Response::json([
                'error' => 'member_session_required',
                'message' => 'Connectez-vous avec un compte de la communauté pour gérer les photos reçues.',
            ], 403);
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $dateFields
     * @return list<array<string, mixed>>
     */
    private function applyPlayNightFilter(array $rows, string $night, array $dateFields): array
    {
        $want = strtolower(trim($night));
        $current = AtakPlayNight::currentKey();
        $filterKey = null;
        if ($want === 'current' || $want === '') {
            $filterKey = $current;
        } elseif ($want !== 'all') {
            $filterKey = AtakPlayNight::normalizeKey($want);
        }
        $out = [];
        foreach ($rows as $row) {
            $decorated = AtakPlayNight::decorateRow($row, $dateFields);
            if ($filterKey !== null && (string) ($decorated['play_night'] ?? '') !== $filterKey) {
                continue;
            }
            $out[] = $decorated;
        }

        return $out;
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
        $actor = $this->guardArmaWrite($request, $tenantId, false);
        if ($actor instanceof Response) {
            return $actor;
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        $callSign = $body['call_sign'] ?? $body['callsign'] ?? $body['unit'] ?? 'Unknown';
        $laserCode = $body['laser_code'] ?? $body['laserCode'] ?? '1688';
        $posX = isset($body['pos_x']) ? (float) $body['pos_x'] : (isset($body['pos'][0]) ? (float) $body['pos'][0] : null);
        $posY = isset($body['pos_y']) ? (float) $body['pos_y'] : (isset($body['pos'][1]) ? (float) $body['pos'][1] : null);
        $status = $body['status'] ?? 'ACTIVE';
        $row = $this->laserCodeRepo->upsert($tenantId, $mapId, $callSign, $laserCode, $posX, $posY, $status);
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_LASER,
            'Code laser synchronisé — ' . $callSign,
            (string) $callSign
        );
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
            $crew = $r['crew'] ?? $r['occupants'] ?? null;
            if (is_string($crew) && $crew !== '') {
                $decoded = json_decode($crew, true);
                $crew = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($crew)) {
                $crew = [];
            }
            $out[] = [
                'callsign' => $r['callsign'],
                'model' => $r['model'],
                'aircraft_type' => $r['aircraft_type'],
                'freq' => $r['freq'],
                'laser' => $r['laser'],
                'auth' => $r['auth'],
                'pilot' => $r['pilot'] ?? null,
                'crew' => $crew,
                'occupants' => $crew,
                'crew_count' => count($crew),
                'pos_x' => $r['pos_x'] !== null ? (float) $r['pos_x'] : null,
                'pos_y' => $r['pos_y'] !== null ? (float) $r['pos_y'] : null,
                'alt' => $r['alt'] !== null ? (float) $r['alt'] : null,
                'heading' => $r['heading'] !== null ? (float) $r['heading'] : null,
                'side' => $r['side'],
                'status' => $status,
                'pilot_status' => $r['pilot_status'],
                'aircraft_count' => (int) ($r['aircraft_count'] ?? 1),
                'updated_at' => $r['updated_at'],
                'source' => $r['source'] ?? null,
                'vehicle_id' => $r['vehicle_id'] ?? null,
            ];
        }
        try {
            $units = $this->atak->getUnits($tenantId, $mapId);
            $out = \App\Services\Tactical\AtakAirAssetMergeService::merge($out, is_array($units) ? $units : []);
        } catch (\Throwable) {
        }
        try {
            $out = $this->motionService()->attachToAir($tenantId, $mapId, $out);
        } catch (\Throwable) {
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

    // =============================================================================
    // NOUVELLES FEATURES ATAK - Phase 1
    // =============================================================================

    // --- Rapports tactiques structurés (SPOTREP, SITREP, SALUTE, CONTACT, Iceman) ---

    /**
     * Catalogue des types de rapport (Iceman Reports + Overwatch).
     * GET /api/atak/reports/catalog
     */
    public function tacticalReportsCatalog(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }

        return Response::json(\App\Support\AtakIcemanReportCatalog::forFrontend());
    }

    /**
     * Liste les rapports tactiques pour un contexte
     * GET /api/atak/reports
     */
    public function tacticalReportsIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);

        $repo = new \App\Repositories\AtakTacticalReportRepository();
        
        $filters = [
            'report_type' => $request->get('report_type'),
            'priority' => $request->get('priority'),
            'status' => $request->get('status'),
            'submitter_steam_id' => $request->get('submitter_steam_id'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'limit' => $request->get('limit') ? (int) $request->get('limit') : 100,
            'offset' => $request->get('offset') ? (int) $request->get('offset') : 0,
        ];

        $reports = $repo->listForContext($tenantId, $mapId, array_filter($filters));
        
        return Response::json([
            'reports' => $reports,
            'count' => count($reports)
        ]);
    }

    /**
     * Crée un nouveau rapport tactique
     * POST /api/atak/reports
     */
    public function tacticalReportsStore(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        
        $actor = $this->guardArmaWrite($request, $tenantId, false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);

        $rawType = \App\Support\AtakIcemanReportCatalog::normalizeType(
            (string) ($body['report_type'] ?? '')
        );
        if (($body['report_type'] ?? '') === '' || $rawType === 'OTHER') {
            $alias = strtoupper(trim((string) ($body['type'] ?? '')));
            if (\App\Support\AtakIcemanReportCatalog::isKnown($alias)) {
                $rawType = \App\Support\AtakIcemanReportCatalog::normalizeType($alias);
            }
        }
        $knownTypes = \App\Support\AtakIcemanReportCatalog::knownTypeCodes();
        $summary = trim((string) ($body['summary'] ?? ''));
        $details = trim((string) ($body['details'] ?? ''));
        $hasStructured = is_array($body['structured_data'] ?? null) && $body['structured_data'] !== [];
        if ($rawType === 'OTHER' && $summary === '' && $details === '' && !$hasStructured) {
            return Response::json([
                'error' => 'empty_report',
                'message' => 'Le rapport n’a pas pu être lu. Renvoyez-le depuis le terminal.',
            ], 400);
        }
        if ($rawType === '' || !in_array($rawType, $knownTypes, true)) {
            $rawType = 'OTHER';
        }

        $repo = new \App\Repositories\AtakTacticalReportRepository();

        // Génération automatique du numéro de rapport si absent
        $reportNumber = $body['report_number'] ?? null;
        if (!$reportNumber) {
            $reportNumber = $repo->generateReportNumber($tenantId, $mapId, $rawType);
        }

        $submitterCallsign = trim((string) ($body['submitter_callsign'] ?? $body['callsign'] ?? $body['call_sign'] ?? ''));
        if ($submitterCallsign === '' || strcasecmp($submitterCallsign, 'Unknown') === 0) {
            $submitterCallsign = trim((string) ($actor['callsign'] ?? ''));
        }

        $data = [
            'tenant_id' => $tenantId,
            'context_id' => $mapId,
            'report_type' => $rawType,
            'report_number' => $reportNumber,
            'priority' => $body['priority'] ?? 'ROUTINE',
            'classification' => $body['classification'] ?? 'UNCLASSIFIED',
            'submitter_user_id' => $actor['user_id'] ?? null,
            'submitter_callsign' => $submitterCallsign !== '' ? $submitterCallsign : null,
            'submitter_unit' => $body['submitter_unit'] ?? null,
            'submitter_steam_id' => $body['submitter_steam_id'] ?? $actor['steam_uid'] ?? null,
            'pos_x' => $body['pos_x'] ?? null,
            'pos_y' => $body['pos_y'] ?? null,
            'grid_reference' => $body['grid_reference'] ?? null,
            'location_description' => $body['location_description'] ?? null,
            'dtg' => $body['dtg'] ?? null,
            'event_timestamp' => $body['event_timestamp'] ?? null,
            'structured_data' => $body['structured_data'] ?? [],
            'summary' => $summary !== '' ? $summary : null,
            'details' => $details !== '' ? $details : null,
            'remarks' => $body['remarks'] ?? null,
            'visibility' => $body['visibility'] ?? 'ALL',
            'distributed_to' => $body['distributed_to'] ?? null,
        ];
        if ((!is_array($data['structured_data']) || $data['structured_data'] === []) && ($details !== '' || $summary !== '')) {
            $parsedFields = \App\Support\AtakIcemanReportCatalog::parseFields($rawType, $details !== '' ? $details : $summary);
            if ($parsedFields !== []) {
                $data['structured_data'] = $parsedFields;
            }
        }
        if (!isset($body['priority']) || trim((string) $body['priority']) === '') {
            $data['priority'] = \App\Support\AtakIcemanReportCatalog::priorityFor(
                $rawType,
                is_array($data['structured_data']) ? $data['structured_data'] : []
            );
        }
        if ($summary === '' && is_array($data['structured_data']) && $data['structured_data'] !== []) {
            $built = \App\Support\AtakIcemanReportCatalog::summaryFromFields($rawType, $data['structured_data']);
            if ($built !== '') {
                $data['summary'] = $built;
            }
        }

        $reportId = $repo->create($data);
        $routing = [
            'enabled' => false,
            'rules_applied' => 0,
            'routes_created' => 0,
            'routed_to' => [],
        ];
        $routingState = (new \App\Services\Tactical\AtakBridgeModulesService())->get($tenantId);
        if (($routingState['modules']['report_routing'] ?? true) === true) {
            try {
                $routingResult = (new \App\Repositories\AtakReportRoutingRepository())
                    ->applyRoutingRules($reportId, $tenantId, $mapId);
                $routing = array_merge($routing, $routingResult, ['enabled' => true]);
            } catch (\Throwable $exception) {
                error_log(sprintf(
                    '[atak_report_routing] report=%d tenant=%d context=%d error=%s',
                    $reportId,
                    $tenantId,
                    $mapId,
                    $exception->getMessage()
                ));
                $routing['error'] = 'routing_unavailable';
            }
        }
        
        $activityMeta = $this->buildActivityMeta(
            $tenantId,
            $mapId,
            $body,
            is_array($actor) ? $actor : null,
            (string) ($data['submitter_callsign'] ?? '')
        );
        $activityMeta['report_type'] = (string) ($data['report_type'] ?? 'OTHER');
        if (!empty($reportNumber)) {
            $activityMeta['report_number'] = (string) $reportNumber;
        }
        if (!empty($data['priority'])) {
            $activityMeta['priority'] = (string) $data['priority'];
        }
        if (!empty($data['classification'])) {
            $activityMeta['classification'] = (string) $data['classification'];
        }
        if (!empty($data['summary'])) {
            $activityMeta['summary'] = (string) $data['summary'];
        }
        if (!empty($data['details'])) {
            $activityMeta['details'] = (string) $data['details'];
        }
        if (!empty($data['remarks'])) {
            $activityMeta['remarks'] = (string) $data['remarks'];
        }
        if (!empty($data['grid_reference'])) {
            $activityMeta['grid_reference'] = (string) $data['grid_reference'];
        }
        if (!empty($data['location_description'])) {
            $activityMeta['location_description'] = (string) $data['location_description'];
        }
        if (!empty($data['dtg'])) {
            $activityMeta['dtg'] = (string) $data['dtg'];
        }
        if (!empty($data['event_timestamp'])) {
            $activityMeta['event_timestamp'] = (string) $data['event_timestamp'];
        }
        if (!empty($data['structured_data']) && is_array($data['structured_data'])) {
            foreach ($data['structured_data'] as $fieldKey => $fieldValue) {
                if (is_array($fieldValue)) {
                    continue;
                }
                $safeKey = preg_replace('/[^a-z0-9_]+/i', '_', (string) $fieldKey) ?: 'field';
                $activityMeta['report_' . strtolower($safeKey)] = (string) $fieldValue;
            }
        }
        $typeLabel = $this->reportTypeLabelFr((string) ($data['report_type'] ?? 'OTHER'));
        $labelTail = trim((string) ($data['summary'] ?? $data['details'] ?? $reportNumber ?? ''));
        $activityLabel = $labelTail !== ''
            ? $typeLabel . ' — ' . $labelTail
            : $typeLabel;
        $actorName = trim((string) ($data['submitter_callsign'] ?? ''));
        if ($actorName === '' || strcasecmp($actorName, 'Unknown') === 0) {
            $actorName = trim((string) ($activityMeta['profile_callsign'] ?? $activityMeta['display_name'] ?? $activityMeta['call_sign'] ?? ''));
        }
        if ($actorName === '') {
            $actorName = 'Opérateur';
        }

        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_TACTICAL_REPORT,
            $activityLabel,
            $actorName,
            $activityMeta
        );

        try {
            (new \App\Services\Tactical\AtakReportRoutingService())->notifyForReport(
                $reportId,
                $tenantId,
                $mapId
            );
        } catch (\Throwable $exception) {
            error_log(sprintf(
                '[atak_report_routing] notify report=%d tenant=%d error=%s',
                $reportId,
                $tenantId,
                $exception->getMessage()
            ));
        }

        // Cloisonnement : sans ce filtre, un identifiant deviné suffit à lire le
        // rapport d'une autre communauté.
        $report = $repo->findById($reportId, $tenantId);
        if (is_array($report)) {
            $report['routing'] = $routing;
        }

        return Response::json($report, 201);
    }

    /**
     * Notifications temps réel destinées au terrain.
     * GET /api/atak/notifications?since=YYYY-MM-DD HH:MM:SS
     *
     * `AtakNotificationRepository` existait avec `create()`, `listActive()` et
     * `pollSince()` sans qu'aucune route ne l'expose : les notifications écrites
     * n'étaient lisibles par personne. Sans cette relève, émettre une notification
     * revenait à l'écrire dans un tiroir fermé.
     */
    public function notificationsPoll(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);

        $repo = new \App\Repositories\AtakNotificationRepository();
        $since = trim((string) ($request->query('since') ?? ''));

        // Sans borne, une relève renverrait tout l'historique encore actif à
        // chaque appel : le client rejouerait des alertes déjà vues.
        $notifications = $since !== ''
            ? $repo->pollSince($tenantId, $mapId, $since)
            : $repo->listActive($tenantId, $mapId, ['limit' => 20]);

        return Response::json([
            'notifications' => $notifications,
            'count' => count($notifications),
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Récupère un rapport tactique par ID
     * GET /api/atak/reports/:id
     */
    public function tacticalReportsShow(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        
        $id = (int) ($params['id'] ?? 0);
        $repo = new \App\Repositories\AtakTacticalReportRepository();

        // Cloisonnement par communauté : sans ce filtre, un identifiant deviné
        // suffisait à lire le rapport d'une autre communauté.
        $report = $repo->findById($id, $r);

        if (!$report) {
            return Response::json(['error' => 'Report not found'], 404);
        }

        // Diffusion dirigée : le rapport est scopé ci-dessus, la lecture de son
        // historique de routage l'est donc aussi.
        $report['routing'] = (new \App\Repositories\AtakReportRoutingRepository())->listForReport($id);

        return Response::json($report);
    }

    /**
     * Marque un rapport comme acquitté
     * POST /api/atak/reports/:id/acknowledge
     */
    public function tacticalReportsAcknowledge(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        
        $identity = $this->routingIdentity($request, $r);
        if ($identity instanceof Response) {
            return $identity;
        }

        $id = (int) ($params['id'] ?? 0);
        $userId = $identity['user_id'];

        $repo = new \App\Repositories\AtakTacticalReportRepository();
        // Acquitter est un acte, pas une lecture : cloisonné par communauté et
        // par contexte opérationnel.
        $success = $repo->acknowledge($id, $r, $this->mapId($request, true), $userId);
        
        if (!$success) {
            return Response::json(['error' => 'Report not found'], 404);
        }
        
        return Response::json(['ok' => true]);
    }

    /**
     * Boîte de réception des rapports routés vers l'utilisateur, ses rôles ou ses unités.
     * GET /api/atak/reports/routed
     */
    public function tacticalReportsRouted(Request $request, array $params = []): Response
    {
        $tenant = $this->requireTenant($request);
        if ($tenant instanceof Response) {
            return $tenant;
        }
        $identity = $this->routingIdentity($request, $tenant);
        if ($identity instanceof Response) {
            return $identity;
        }

        $contextId = $this->mapId($request);
        $filters = [
            'unacknowledged_only' => filter_var(
                $request->query('unacknowledged_only', false),
                FILTER_VALIDATE_BOOLEAN
            ),
            'priority' => $request->query('priority'),
            'limit' => max(1, min(200, (int) ($request->query('limit', 100) ?? 100))),
        ];
        $reports = (new \App\Repositories\AtakReportRoutingRepository())->listForRecipients(
            $tenant,
            $contextId,
            $identity['recipients'],
            array_filter($filters, static fn ($value): bool => $value !== null && $value !== false && $value !== '')
        );

        return Response::json([
            'reports' => $reports,
            'count' => count($reports),
        ]);
    }

    /**
     * Acquitte une distribution individuelle si elle vise réellement l'appelant.
     * POST /api/atak/reports/:id/routing/:routingId/acknowledge
     */
    public function tacticalReportRoutingAcknowledge(Request $request, array $params = []): Response
    {
        $tenant = $this->requireTenant($request);
        if ($tenant instanceof Response) {
            return $tenant;
        }
        $identity = $this->routingIdentity($request, $tenant);
        if ($identity instanceof Response) {
            return $identity;
        }

        $success = (new \App\Repositories\AtakReportRoutingRepository())->acknowledgeRoutingForRecipients(
            (int) ($params['routingId'] ?? 0),
            (int) ($params['id'] ?? 0),
            $tenant,
            $this->mapId($request, true),
            $identity['recipients'],
            $identity['user_id']
        );
        if (!$success) {
            return Response::json(['error' => 'Routing not found or not assigned to this user'], 404);
        }

        $this->activityLog->record(
            $tenant,
            $this->mapId($request, true),
            'TACTICAL_REPORT_ROUTING_ACKNOWLEDGED',
            sprintf('Diffusion du rapport #%d acquittée', (int) ($params['id'] ?? 0)),
            (string) ($identity['user_id']),
            [
                'report_id' => (int) ($params['id'] ?? 0),
                'routing_id' => (int) ($params['routingId'] ?? 0),
                'acknowledged_by_user_id' => $identity['user_id'],
            ]
        );

        return Response::json(['ok' => true]);
    }

    /**
     * Résout uniquement des identifiants détenus par l'appelant ; aucun destinataire ne vient du payload.
     *
     * @return array{user_id:int, recipients:list<array{type:string,identifier:string}>}|Response
     */
    private function routingIdentity(Request $request, int $tenantId): array|Response
    {
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($userId < 1) {
            if (!$this->authArma()) {
                return Response::json(['error' => 'Unauthorized'], 401);
            }
            $actor = $this->guardArmaWrite($request, $tenantId, true);
            if ($actor instanceof Response) {
                return $actor;
            }
            $user = $this->userRepository->findBySteamIdForTenant($tenantId, (string) ($actor['steam_uid'] ?? ''));
            $userId = (int) ($user['id'] ?? 0);
        }
        if ($userId < 1) {
            return Response::json(['error' => 'Linked user required'], 403);
        }
        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user || (int) ($user['tenant_id'] ?? 0) !== $tenantId) {
            return Response::json(['error' => 'User does not belong to this tenant'], 403);
        }

        $recipients = [['type' => 'USER', 'identifier' => (string) $userId]];
        $roleIds = $this->userRepository->listOrganizationRoleIdsForUser($userId);
        foreach ((new \App\Repositories\RoleRepository())->allForTenant($tenantId) as $role) {
            if (!in_array((int) ($role['id'] ?? 0), $roleIds, true)) {
                continue;
            }
            foreach (['id', 'slug', 'name'] as $field) {
                $value = trim((string) ($role[$field] ?? ''));
                if ($value !== '') {
                    $recipients[] = ['type' => 'ROLE', 'identifier' => $value];
                }
            }
        }
        foreach ($this->unitRepository->unitIdsForUser($tenantId, $userId) as $unitId) {
            $unit = $this->unitRepository->findById($unitId, $tenantId);
            if (!$unit) {
                continue;
            }
            foreach (['id', 'slug', 'code', 'name'] as $field) {
                $value = trim((string) ($unit[$field] ?? ''));
                if ($value !== '') {
                    $recipients[] = ['type' => 'UNIT', 'identifier' => $value];
                }
            }
        }

        $unique = [];
        foreach ($recipients as $recipient) {
            $unique[$recipient['type'] . ':' . $recipient['identifier']] = $recipient;
        }

        return ['user_id' => $userId, 'recipients' => array_values($unique)];
    }

    // --- Points d'Intérêt (POI) tactiques ---

    /**
     * Liste les POI pour un contexte
     * GET /api/atak/poi
     */
    public function poiIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);

        $repo = new \App\Repositories\AtakPoiRepository();
        
        $filters = [
            'category' => $request->get('category'),
            'affiliation' => $request->get('affiliation'),
            'status' => $request->get('status'),
            'threat_level' => $request->get('threat_level'),
            'is_visible' => $request->get('is_visible') !== null ? (bool) $request->get('is_visible') : null,
            'limit' => $request->get('limit') ? (int) $request->get('limit') : 200,
            'offset' => $request->get('offset') ? (int) $request->get('offset') : 0,
        ];

        $pois = $repo->listForContext($tenantId, $mapId, array_filter($filters, fn($v) => $v !== null));
        
        return Response::json([
            'pois' => $pois,
            'count' => count($pois)
        ]);
    }

    /**
     * Crée un nouveau POI
     * POST /api/atak/poi
     */
    public function poiStore(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        
        $actor = $this->guardArmaWrite($request, $tenantId, false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);

        $repo = new \App\Repositories\AtakPoiRepository();
        
        $data = [
            'tenant_id' => $tenantId,
            'context_id' => $mapId,
            'poi_name' => $body['poi_name'] ?? 'POI',
            'poi_code' => $body['poi_code'] ?? null,
            'category' => $body['category'] ?? 'OTHER',
            'affiliation' => $body['affiliation'] ?? 'UNKNOWN',
            'certainty' => $body['certainty'] ?? 'TO_VERIFY',
            'pos_x' => $body['pos_x'] ?? null,
            'pos_y' => $body['pos_y'] ?? null,
            'pos_z' => $body['pos_z'] ?? null,
            'grid_reference' => $body['grid_reference'] ?? null,
            'description' => $body['description'] ?? null,
            'observed_activity' => $body['observed_activity'] ?? null,
            'threat_level' => $body['threat_level'] ?? 'NONE',
            'status' => $body['status'] ?? 'ACTIVE',
            'source_type' => $body['source_type'] ?? null,
            'source_reliability' => $body['source_reliability'] ?? 'UNKNOWN',
            'reported_by_user_id' => $actor['user_id'] ?? null,
            'reported_by_callsign' => $body['reported_by_callsign'] ?? $actor['callsign'] ?? null,
            'properties' => $body['properties'] ?? [],
            'icon_type' => $body['icon_type'] ?? null,
            'marker_color' => $body['marker_color'] ?? null,
            'visibility_level' => $body['visibility_level'] ?? 'PUBLIC',
            'created_by_user_id' => $actor['user_id'] ?? null,
        ];

        $poiId = $repo->create($data);
        
        $this->activityLog->record(
            $tenantId,
            $mapId,
            'POI_CREATED',
            sprintf('POI créé : %s (%s)', $data['poi_name'], $data['category']),
            $data['reported_by_callsign'] ?? 'Unknown'
        );

        $poi = $repo->findById($poiId);
        
        return Response::json($poi, 201);
    }

    /**
     * Met à jour un POI
     * PUT /api/atak/poi/:id
     */
    public function poiUpdate(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        
        $actor = $this->guardArmaWrite($request, $r, false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);

        $repo = new \App\Repositories\AtakPoiRepository();
        $body['updated_by_user_id'] = $actor['user_id'] ?? null;
        
        $success = $repo->update($id, $body);
        
        if (!$success) {
            return Response::json(['error' => 'POI not found'], 404);
        }
        
        $poi = $repo->findById($id);
        return Response::json($poi);
    }

    // --- Zones tactiques (LZ, DZ, Objectives, Danger Zones) ---

    /**
     * Liste les zones tactiques pour un contexte
     * GET /api/atak/zones
     */
    public function tacticalZonesIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);

        $repo = new \App\Repositories\AtakTacticalZoneRepository();
        
        $filters = [
            'zone_type' => $request->get('zone_type'),
            'status' => $request->get('status'),
            'is_visible' => $request->get('is_visible') !== null ? (bool) $request->get('is_visible') : null,
            'only_active' => $request->get('only_active') !== null,
            'limit' => $request->get('limit') ? (int) $request->get('limit') : 200,
            'offset' => $request->get('offset') ? (int) $request->get('offset') : 0,
        ];

        $zones = $repo->listForContext($tenantId, $mapId, array_filter($filters, fn($v) => $v !== null));
        
        return Response::json([
            'zones' => $zones,
            'count' => count($zones)
        ]);
    }

    /**
     * Crée une nouvelle zone tactique
     * POST /api/atak/zones
     */
    public function tacticalZonesStore(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        
        $actor = $this->guardArmaWrite($request, $tenantId, false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);

        $repo = new \App\Repositories\AtakTacticalZoneRepository();
        
        $data = array_merge($body, [
            'tenant_id' => $tenantId,
            'context_id' => $mapId,
            'created_by_user_id' => $actor['user_id'] ?? null,
        ]);

        $zoneId = $repo->create($data);
        
        $this->activityLog->record(
            $tenantId,
            $mapId,
            'ZONE_CREATED',
            sprintf('Zone créée : %s (%s)', $body['zone_name'] ?? 'Zone', $body['zone_type'] ?? 'OTHER'),
            $actor['callsign'] ?? 'Unknown'
        );

        $zone = $repo->findById($zoneId);
        
        return Response::json($zone, 201);
    }

    /**
     * Vérifie les zones contenant une position et génère des alertes
     * POST /api/atak/zones/check-position
     */
    public function tacticalZonesCheckPosition(Request $request, array $params = []): Response
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
        $posX = $body['pos_x'] ?? null;
        $posY = $body['pos_y'] ?? null;

        if ($posX === null || $posY === null) {
            return Response::json(['error' => 'pos_x and pos_y required'], 400);
        }

        $repo = new \App\Repositories\AtakTacticalZoneRepository();
        $zones = $repo->findZonesContainingPosition($tenantId, $mapId, (float) $posX, (float) $posY);
        
        // Génération des alertes pour les zones avec alert_on_entry
        $alerts = [];
        foreach ($zones as $zone) {
            if ($zone['alert_on_entry']) {
                $alertId = $repo->createAlert((int) $zone['id'], [
                    'alert_type' => 'ENTRY',
                    'unit_callsign' => $body['callsign'] ?? null,
                    'unit_steam_id' => $body['steam_id'] ?? null,
                    'unit_pos_x' => $posX,
                    'unit_pos_y' => $posY,
                ]);
                
                $alerts[] = [
                    'zone_id' => $zone['id'],
                    'zone_name' => $zone['zone_name'],
                    'zone_type' => $zone['zone_type'],
                    'alert_message' => $zone['alert_message'],
                    'alert_sound' => $zone['alert_sound'],
                    'alert_id' => $alertId,
                ];
            }
        }
        
        return Response::json([
            'zones' => $zones,
            'alerts' => $alerts,
            'count' => count($zones)
        ]);
    }

    /**
     * Liste les alertes non acquittées
     * GET /api/atak/zones/alerts
     */
    public function tacticalZonesAlerts(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);

        $repo = new \App\Repositories\AtakTacticalZoneRepository();
        $alerts = $repo->listUnacknowledgedAlerts($tenantId, $mapId);
        
        return Response::json([
            'alerts' => $alerts,
            'count' => count($alerts)
        ]);
    }

    // =============================================================================
    // NOUVELLES FEATURES ATAK - Phase 2
    // =============================================================================

    // --- MEDEVAC 9-Line étendu (liste/création via casRepo plus haut ; détail ci-dessous) ---

    /**
     * Récupère une demande MEDEVAC
     * GET /api/atak/medevac/:id
     */
    public function medevacShow(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        
        $id = (int) ($params['id'] ?? 0);
        $repo = new \App\Repositories\AtakMedevacRepository();
        $medevac = $repo->findById($id);
        
        if (!$medevac) {
            return Response::json(['error' => 'MEDEVAC not found'], 404);
        }
        
        // Récupérer les patients
        $medevac['patients'] = $repo->getPatients($id);
        
        return Response::json($medevac);
    }

    /**
     * Met à jour le statut d'une MEDEVAC
     * PATCH /api/atak/medevac/:id/status
     */
    public function medevacUpdateStatus(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $newStatus = $body['status'] ?? null;
        
        if (!$newStatus) {
            return Response::json(['error' => 'Status required'], 400);
        }

        $repo = new \App\Repositories\AtakMedevacRepository();
        $success = $repo->updateStatus($id, $newStatus, $body['message'] ?? null);
        
        if (!$success) {
            return Response::json(['error' => 'MEDEVAC not found'], 404);
        }
        
        return Response::json(['ok' => true]);
    }

    /**
     * Assigne un asset à une MEDEVAC
     * POST /api/atak/medevac/:id/assign
     */
    public function medevacAssignAsset(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        
        $actor = $this->guardArmaWrite($request, $r, false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        
        $assetCallsign = $body['asset_callsign'] ?? null;
        if (!$assetCallsign) {
            return Response::json(['error' => 'asset_callsign required'], 400);
        }

        $repo = new \App\Repositories\AtakMedevacRepository();
        $success = $repo->assignAsset($id, $assetCallsign, $actor['user_id'] ?? null);
        
        if (!$success) {
            return Response::json(['error' => 'MEDEVAC not found'], 404);
        }
        
        return Response::json(['ok' => true]);
    }

    /**
     * Ajoute un patient à une MEDEVAC
     * POST /api/atak/medevac/:id/patients
     */
    public function medevacAddPatient(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);

        $repo = new \App\Repositories\AtakMedevacRepository();
        $patientId = $repo->addPatient($id, $body);
        
        return Response::json(['patient_id' => $patientId], 201);
    }

    // --- QRF (Quick Reaction Force) ---

    /**
     * Liste les demandes QRF
     * GET /api/atak/qrf
     */
    public function qrfIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);

        $repo = new \App\Repositories\AtakQrfRepository();
        
        $filters = [
            'status' => $request->get('status'),
            'priority' => $request->get('priority'),
            'only_active' => $request->get('only_active') !== null,
            'limit' => $request->get('limit') ? (int) $request->get('limit') : 100,
            'offset' => $request->get('offset') ? (int) $request->get('offset') : 0,
        ];

        $qrfs = $repo->listForContext($tenantId, $mapId, array_filter($filters, fn($v) => $v !== null));
        
        return Response::json([
            'qrfs' => $qrfs,
            'count' => count($qrfs)
        ]);
    }

    /**
     * Crée une demande QRF
     * POST /api/atak/qrf
     */
    public function qrfStore(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        
        $actor = $this->guardArmaWrite($request, $tenantId, false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);

        $repo = new \App\Repositories\AtakQrfRepository();
        
        // Génération numéro QRF
        $qrfNumber = $body['qrf_number'] ?? $repo->generateQrfNumber($tenantId, $mapId);

        $contactPos = $body['contact_pos'] ?? $body['pos'] ?? $body['position'] ?? null;
        $posX = \App\Repositories\AtakDataRepository::coerceFloat(
            $body['contact_pos_x'] ?? $body['pos_x'] ?? $body['x'] ?? (is_array($contactPos) ? ($contactPos[0] ?? null) : null)
        );
        $posY = \App\Repositories\AtakDataRepository::coerceFloat(
            $body['contact_pos_y'] ?? $body['pos_y'] ?? $body['y'] ?? (is_array($contactPos) ? ($contactPos[1] ?? null) : null)
        );
        if ($posX === null || $posY === null) {
            return Response::json([
                'error' => 'contact_pos_required',
                'message' => 'Indiquez la position du contact pour demander le renfort.',
            ], 400);
        }
        $coordsOk = $this->armaGuard->assertPositionCoords($posX, $posY, $tenantId);
        if ($coordsOk instanceof Response) {
            return $coordsOk;
        }

        $data = array_merge($body, [
            'tenant_id' => $tenantId,
            'context_id' => $mapId,
            'qrf_number' => $qrfNumber,
            'contact_pos_x' => $posX,
            'contact_pos_y' => $posY,
            'requesting_user_id' => $actor['user_id'] ?? null,
            'requesting_callsign' => $body['requesting_callsign'] ?? $actor['callsign'] ?? null,
            'requesting_steam_id' => $body['requesting_steam_id'] ?? $actor['steam_uid'] ?? null,
        ]);

        try {
            $qrfId = $repo->create($data);
        } catch (\InvalidArgumentException $e) {
            return Response::json([
                'error' => 'contact_pos_required',
                'message' => 'Indiquez la position du contact pour demander le renfort.',
            ], 400);
        } catch (\Throwable $e) {
            error_log('[atak_qrf] store_failed ' . $e->getMessage());

            return Response::json([
                'error' => 'store_failed',
                'message' => 'Impossible d’enregistrer la demande de renfort.',
            ], 500);
        }
        if ($qrfId < 1) {
            return Response::json([
                'error' => 'store_failed',
                'message' => 'Impossible d’enregistrer la demande de renfort.',
            ], 500);
        }

        $this->activityLog?->record(
            $tenantId,
            $mapId,
            'QRF_REQUEST',
            sprintf(
                'QRF demandé : %s - %s - %s',
                $qrfNumber,
                $data['threat_type'] ?? 'UNKNOWN',
                $data['requesting_unit'] ?? 'Unknown'
            ),
            $data['requesting_callsign'] ?? 'Unknown'
        );

        $qrf = $repo->findById($qrfId);

        return Response::json($qrf ?: ['id' => $qrfId, 'qrf_number' => $qrfNumber], 201);
    }

    /**
     * Assigne une QRF à une demande
     * POST /api/atak/qrf/:id/assign
     */
    public function qrfAssign(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        
        $actor = $this->guardArmaWrite($request, $r, false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        
        $qrfUnit = $body['qrf_unit'] ?? null;
        $qrfCallsign = $body['qrf_callsign'] ?? null;
        
        if (!$qrfUnit || !$qrfCallsign) {
            return Response::json(['error' => 'qrf_unit and qrf_callsign required'], 400);
        }

        $repo = new \App\Repositories\AtakQrfRepository();
        $success = $repo->assignQrf($id, $qrfUnit, $qrfCallsign, $actor['user_id'] ?? null);
        
        if (!$success) {
            return Response::json(['error' => 'QRF request not found'], 404);
        }
        
        return Response::json(['ok' => true]);
    }

    /**
     * Met à jour position QRF
     * POST /api/atak/qrf/:id/position
     */
    public function qrfUpdatePosition(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        
        $posX = $body['pos_x'] ?? null;
        $posY = $body['pos_y'] ?? null;
        
        if ($posX === null || $posY === null) {
            return Response::json(['error' => 'pos_x and pos_y required'], 400);
        }

        $repo = new \App\Repositories\AtakQrfRepository();
        $success = $repo->updateQrfPosition($id, (float) $posX, (float) $posY, $body['eta'] ?? null);
        
        if (!$success) {
            return Response::json(['error' => 'QRF request not found'], 404);
        }
        
        return Response::json(['ok' => true]);
    }

    /**
     * Ajoute une mise à jour SITREP à une QRF
     * POST /api/atak/qrf/:id/sitrep
     */
    public function qrfAddSitrep(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        
        $actor = $this->guardArmaWrite($request, $r, false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);

        $repo = new \App\Repositories\AtakQrfRepository();
        $updateId = $repo->addSitrepUpdate($id, array_merge($body, [
            'updated_by_user_id' => $actor['user_id'] ?? null,
            'updated_by_callsign' => $actor['callsign'] ?? null,
        ]));
        
        return Response::json(['update_id' => $updateId], 201);
    }

    // --- Véhicules et assets lourds ---

    /**
     * Liste les véhicules trackés
     * GET /api/atak/vehicles
     */
    public function vehiclesIndex(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);

        $repo = new \App\Repositories\AtakVehicleTrackingRepository();

        $filters = [
            'vehicle_class' => $request->get('vehicle_class'),
            'side' => $request->get('side'),
            'status' => $request->get('status'),
            'fuel_critical' => $request->get('fuel_critical'),
            'damaged' => $request->get('damaged'),
            'limit' => $request->get('limit') ? (int) $request->get('limit') : 200,
            'offset' => $request->get('offset') ? (int) $request->get('offset') : 0,
        ];

        try {
            $vehicles = $repo->listActive($tenantId, $mapId, array_filter($filters, fn($v) => $v !== null));
        } catch (\Throwable) {
            $vehicles = [];
        }

        return Response::json([
            'vehicles' => $vehicles,
            'count' => count($vehicles)
        ]);
    }

    /**
     * Met à jour ou crée un véhicule (upsert)
     * POST /api/atak/vehicles
     */
    public function vehiclesUpsert(Request $request, array $params = []): Response
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

        $repo = new \App\Repositories\AtakVehicleTrackingRepository();
        
        $data = array_merge($body, [
            'tenant_id' => $tenantId,
            'context_id' => $mapId,
        ]);

        $vehicleId = $repo->upsert($data);
        $vehicle = $repo->findById($vehicleId);
        
        return Response::json($vehicle);
    }

    /**
     * Crée une demande de service véhicule
     * POST /api/atak/vehicles/:id/service
     */
    public function vehiclesServiceRequest(Request $request, array $params = []): Response
    {
        if (!$this->authArma()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        
        $actor = $this->guardArmaWrite($request, $r, false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);

        $repo = new \App\Repositories\AtakVehicleTrackingRepository();
        $serviceId = $repo->createServiceRequest($id, array_merge($body, [
            'requested_by_callsign' => $actor['callsign'] ?? null,
        ]));
        
        return Response::json(['service_request_id' => $serviceId], 201);
    }

    /**
     * Liste les demandes de service en attente
     * GET /api/atak/vehicles/service-requests
     */
    public function vehiclesServiceRequests(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $tenantId = $r;
        $mapId = $this->mapId($request);

        $repo = new \App\Repositories\AtakVehicleTrackingRepository();
        $requests = $repo->listPendingServiceRequests($tenantId, $mapId);
        
        return Response::json([
            'service_requests' => $requests,
            'count' => count($requests)
        ]);
    }
}
