<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDataRepository;
use App\Repositories\AtakMedicalTriageRepository;
use App\Repositories\AtakOperatorIdRepository;
use App\Repositories\AtakOrderRepository;
use App\Repositories\AtakOrderTemplateRepository;
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
use App\Repositories\TenantAtakConfigRepository;
use App\Services\Qr\QrPngGenerator;
use App\Services\Tactical\AtakActivityLogService;
use App\Support\AtakArmaWriteGuard;
use App\Support\AtakGameSession;
use App\Support\ChatMentionParser;
use App\Support\MedicalAlertParser;
use App\Support\TacticalAlertParser;
use App\Support\SteamId;
use App\Services\Tactical\MissionDisplaySettingsService;

class AtakApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** @var array<string, mixed>|null Cache php://input (une seule lecture par requête). */
    private ?array $jsonBodyCache = null;

    private AtakArmaWriteGuard $armaGuard;

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
        private ?TacticalBriefingSlideCommentRepository $briefingSlideCommentRepository = null,
        private ?TacticalPhonePairingRepository $phonePairingRepository = null,
        private ?AtakActivityLogService $activityLog = null,
        private ?TacticalGameLinkRepository $gameLinkRepository = null,
        private ?TenantAtakConfigRepository $tenantAtakConfigRepository = null,
        private ?\App\Repositories\UnitRepository $unitRepository = null,
        private ?AtakOrderRepository $orderRepository = null,
        private ?AtakOrderTemplateRepository $orderTemplateRepository = null,
        private ?FireTeamRepository $fireTeamRepository = null,
        private ?AtakOperatorIdRepository $operatorIdRepository = null,
        private ?AtakMedicalTriageRepository $medicalTriageRepository = null,
        ?AtakArmaWriteGuard $armaGuard = null,
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
        $this->fireTeamRepository ??= new FireTeamRepository();
        $this->operatorIdRepository ??= new AtakOperatorIdRepository();
        $this->medicalTriageRepository ??= new AtakMedicalTriageRepository();
        $this->armaGuard = $armaGuard ?? new AtakArmaWriteGuard($this->userRepository, $this->activityLog);
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

        return Response::json(['slides' => $out]);
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
        foreach ($viewers as $v) {
            $out[] = [
                'label' => $v['label'],
                'source' => $v['source'],
            ];
        }

        return Response::json([
            'viewers' => $out,
            'count' => count($out),
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
     * Connexion téléphone (inspiré de cTab) : génère un token (QR) + un code court lisible,
     * consommés par l'extension Arma (fonction native GetPhoneConnectInfo) pour affichage
     * en jeu, puis par un navigateur mobile sans compte sur /atak/connect/{token}.
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

        // URL absolues (APP_URL + éventuel /public) : l’extension Arma télécharge le PNG ensuite.
        $connectUrl = url('atak/connect/' . $token);
        $qrImageUrl = url('api/atak/phone-pairing/' . $token . '/qr.png');

        // Data-URI inline : le dashboard ATAK affiche le QR sans second GET
        // (évite img cassée si l’endpoint PNG est bloqué, en 503, ou mal servi).
        $qrDataUri = $this->phonePairingQrDataUri($connectUrl);

        return Response::json([
            'token' => $token,
            'code' => $pairing['code'],
            'connect_url' => $connectUrl,
            'qr_image_url' => $qrImageUrl,
            'qr_image_data_uri' => $qrDataUri,
            'expires_at' => $pairing['expires_at'],
        ]);
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
        $connectUrl = url('atak/connect/' . $token);
        $generator = new QrPngGenerator();
        // pngOnly : Arma RscPicture n’affiche pas de SVG — forcer un PNG binaire (Endroid, GD ou zlib).
        $png = $generator->png($connectUrl, 400, 12, true);
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
            $png = $generator->png($connectUrl, 400, 12, true);
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

        if ($this->tenantAtakConfigRepository->isMaintenanceEnabled($id) && !$this->canBypassAtakMaintenance()) {
            $message = $this->tenantAtakConfigRepository->getMaintenanceMessage($id);
            if ($message === '') {
                $message = 'La carte tactique est temporairement en maintenance. Réessayez plus tard.';
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

        return $meta;
    }

    private function authArma(): bool
    {
        return ComspecApiKeyAuth::armaInlineAuthOk();
    }

    /**
     * Garde écriture Arma : clé déjà vérifiée + Steam lié (si fourni) + session + anti-spoof.
     *
     * @return array{steam_uid: ?string, session_ok: bool}|Response
     */
    private function guardArmaWrite(Request $request, int $tenantId, bool $requireSteam = false): array|Response
    {
        return $this->armaGuard->assertActor($request, $tenantId, $this->jsonBody($request), $requireSteam);
    }

    public function ping(Request $request, array $params = []): Response
    {
        return Response::json([
            'ok' => true,
            'service' => 'atak',
            // Horodatage serveur (ms) pour mesurer la latence côté navigateur.
            'server_ms' => (int) round(microtime(true) * 1000),
        ]);
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
            $mapId = $this->mapId($request);
            $this->activityLog->recordClientInit(
                $tenantId,
                $mapId,
                $this->activityLog->clientKeyFromRequest()
            );
        }
        return Response::json(['ip' => $ip ?: '—']);
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
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_MEDEVAC,
            'Demande MEDEVAC envoyée — ' . $author,
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
        $row = $this->atak->addChatMessage($tenantId, $mapId, $callSign, $msg);
        $parsed = TacticalAlertParser::enrichChatRow(is_array($row) ? $row : []);
        $summary = is_array($parsed) ? (string) ($parsed['summary'] ?? 'SALUTE') : 'SALUTE';
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_TACTICAL_ALERT,
            'SALUTE — ' . $summary,
            $callSign,
            ['kind' => 'salute']
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
        $units = $this->atak->getUnits($r, $this->mapId($request));
        $rows = [];
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
            }
            $ammo = ($ammoRaw !== null && $ammoRaw !== '' && strtolower((string) $ammoRaw) !== 'n/a')
                ? (string) $ammoRaw
                : null;
            if ($fuel === null && $ammo === null) {
                continue;
            }
            $cs = trim((string) ($u['call_sign'] ?? ''));
            $rows[] = [
                'call_sign' => $cs,
                'team' => $this->perstatTeamKey($cs),
                'role' => (string) ($u['role'] ?? ''),
                'fuel' => $fuel,
                'ammo' => $ammo,
                'status' => $status,
                'grid_ref' => (string) ($u['grid_ref'] ?? ''),
                'updated_at' => (string) ($u['updated_at'] ?? ''),
                'fuel_level' => $fuel === null ? 'unknown' : ($fuel <= 15 ? 'critical' : ($fuel <= 35 ? 'low' : 'ok')),
            ];
        }
        usort($rows, static function (array $a, array $b): int {
            $fa = $a['fuel'] ?? 999;
            $fb = $b['fuel'] ?? 999;

            return $fa <=> $fb;
        });

        return Response::json([
            'rows' => $rows,
            'count' => count($rows),
            'generated_at' => gmdate('c'),
        ]);
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

        $payload = ['ok' => true];
        if ($sessionToken !== '') {
            $payload['session_token'] = $sessionToken;
            $payload['expires_in'] = $expiresIn;
            $payload['steam_uid'] = $steam;
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

        return Response::json(['ok' => true]);
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
        $unitsCount = count(array_filter(
            $units,
            static fn ($u) => is_array($u) && (string) ($u['status'] ?? '') === 'linked'
        ));
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
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_MARKER,
            'Marqueur placé',
            null
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
                $this->activityLog->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_MARKER,
                    'Marqueur retiré — ' . $armaName,
                    (string) $armaName
                );
            }

            return Response::json(['ok' => true, 'deleted' => $ok]);
        }
        $markerData = $this->normalizeArmaMarkerData($body['markerData'] ?? '{}', (string) $armaName);
        $row = $this->atak->upsertMarkerByArmaName($tenantId, $mapId, $layerId, $armaName, $markerData);
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_MARKER,
            'Marqueur placé — ' . $armaName,
            (string) $armaName
        );
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

        if (!isset($decoded['source'])) {
            $decoded['source'] = 'arma';
        }
        if (empty($decoded['text']) && empty($decoded['label']) && $armaName !== '') {
            // Ne pas exposer les ids techniques comspec_tabletmk_* comme libellé.
            if (!str_starts_with($armaName, 'comspec_')) {
                $decoded['text'] = $armaName;
            }
        }
        if (isset($decoded['pos']) && is_array($decoded['pos'])) {
            $decoded['pos'] = array_map(static function ($v) {
                if (is_string($v)) {
                    $v = str_replace(',', '.', $v);
                }

                return is_numeric($v) ? (float) $v : $v;
            }, $decoded['pos']);
        }

        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '{}';
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
        $opIds = $this->operatorIdRepository ?? new AtakOperatorIdRepository();
        if ($opIds->tablesReady() && $opIds->unitsMilitaryIdColumnReady()) {
            foreach ($rows as &$row) {
                $mid = trim((string) ($row['military_id'] ?? ''));
                $cs = trim((string) ($row['call_sign'] ?? ''));
                if ($mid === '' && $cs !== '') {
                    $mid = $opIds->syncUnitMilitaryId($tenantId, (int) ($row['id'] ?? 0), $cs, null);
                    $row['military_id'] = $mid;
                }
            }
            unset($row);
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

        $rows = $this->enrichUnitsWithFireTeams($tenantId, $rows);

        return Response::json($rows);
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
            $applyProfileCallsign = !empty($body['apply_profile_callsign']) || !empty($body['applyProfileCallsign']);
            if ($applyProfileCallsign && $profileCall !== '') {
                $body['call_sign'] = $profileCall;
                $unitCall = $profileCall;
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

        $prevCall = trim((string) ($before['call_sign'] ?? ''));
        $row = $this->atak->updateUnit($tenantId, $id, $patch);
        if ($row === null) {
            return Response::json(['error' => 'Not found', 'message' => 'Contact introuvable.'], 404);
        }

        $newCall = trim((string) ($row['call_sign'] ?? ''));
        if ($prevCall !== '' && $newCall !== '' && strcasecmp($prevCall, $newCall) !== 0) {
            $actor = $sessionUser
                ? ($sessionUser['callsign'] !== '' ? $sessionUser['callsign'] : $sessionUser['displayName'])
                : 'Opérateur';
            $mapId = (int) ($row['map_id'] ?? $this->mapId($request, true));
            $this->activityLog?->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_CALLSIGN_CHANGE,
                'Indicatif mis à jour — ' . $prevCall . ' → ' . $newCall,
                $actor !== '' ? $actor : null,
                ['from' => $prevCall, 'to' => $newCall, 'unit_id' => $id]
            );
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
        $userCall = trim((string) ($sessionUser['callsign'] ?? ''));
        if ($unitCall === '' || $userCall === '') {
            return false;
        }

        return strcasecmp($unitCall, $userCall) === 0;
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
        $actor = $this->guardArmaWrite($request, $tenantId, false);
        if ($actor instanceof Response) {
            return $actor;
        }
        $body = $this->jsonBody($request);
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID);
        $callSign = trim((string) ($body['call_sign'] ?? $body['callsign'] ?? ''));
        $steamNorm = $actor['steam_uid'] ?? null;
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
        $extra = $body['extra'] ?? [];
        if (!is_array($extra)) {
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
        // Groupe Arma (groupId) — top-level ou déjà dans extra
        $groupName = trim((string) ($body['group_name'] ?? $body['groupName'] ?? $body['group'] ?? $extra['group_name'] ?? $extra['groupName'] ?? $extra['group'] ?? ''));
        if ($groupName !== '') {
            $extra['group_name'] = $groupName;
            $extra['group'] = $groupName;
        }
        $groupId = trim((string) ($body['group_id'] ?? $body['groupId'] ?? $extra['group_id'] ?? $extra['groupId'] ?? ''));
        if ($groupId !== '') {
            $extra['group_id'] = $groupId;
            if ($groupName === '') {
                $extra['group_name'] = $groupId;
                $extra['group'] = $groupId;
            }
        }
        if ($steamNorm !== null && $steamNorm !== '') {
            $extra['steam_uid'] = $steamNorm;
        }
        $upsert = $this->atak->upsertUnitPosition($tenantId, $mapId, $callSign, $posX, $posY, $heading, $role, json_encode($extra));
        $healthNow = strtolower(trim((string) ($extra['health'] ?? $body['health'] ?? '')));
        if (MedicalAlertParser::isRecoveredHealth($healthNow)) {
            try {
                $this->autoResolveOpenMedicalAlertsForCallSign($tenantId, $mapId, (string) $callSign);
            } catch (\Throwable) {
            }
        }
        try {
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
        $mapId = $this->mapId($request);
        $limit = (int) ($request->query('limit') ?: 100);
        $rows = $this->atak->getChatMessages($tenantId, $mapId, min($limit, 500));
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
        $mapId = $this->mapId($request);
        $limit = (int) ($request->query('limit') ?: 40);
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
            $this->atak->upsertUnitPosition(
                $tenantId,
                $mapId,
                $resolvedCallSign,
                $px,
                $py,
                null,
                $role,
                is_string($extraJson) ? $extraJson : '{}'
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

        // Dédup alertes auto (KO / arrêt cardiaque) : ne pas réinsérer la même alerte en boucle.
        $medicalDup = false;
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
            $row = $this->atak->addChatMessage($tenantId, $mapId, $author, $bodyText);
        }

        // Réglages d’affichage camps (CBA / params mission → Tacmap)
        $factionSettings = TacticalAlertParser::parseFactionSettings(is_string($bodyText) ? $bodyText : null);
        if (is_array($factionSettings)) {
            $settingsSvc = new MissionDisplaySettingsService();
            $saved = $settingsSvc->put($tenantId, $mapId, $factionSettings);
            if (is_array($row)) {
                $row['mission_settings'] = $saved;
            }
            $this->activityLog?->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_TACTICAL_ALERT,
                'Réglages d’affichage carte mis à jour',
                (string) $author,
                $saved
            );
        }

        // Alertes tactiques TIC / CLEAR / FRAGO / SALUTE / Eagle Down
        $tactical = TacticalAlertParser::enrichChatRow(is_array($row) ? $row : []);
        if ($tactical !== null) {
            if (is_array($row)) {
                $row['tactical'] = $tactical;
            }
            $tacSummary = trim((string) ($tactical['summary'] ?? ''));
            if ($tacSummary === '') {
                $tacSummary = (string) (($tactical['kind_label'] ?? 'Alerte') . ' — ' . $author);
            }
            $this->activityLog?->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_TACTICAL_ALERT,
                $tacSummary,
                (string) (($tactical['call_sign'] ?? '') !== '' ? $tactical['call_sign'] : $author),
                array_merge($chatActivityMeta, ['kind' => $tactical['kind'] ?? ''])
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
            // Déjà journalisé métier (ordre / alerte tactique / réglages camps) : pas de doublon « Message envoyé ».
            !isset($row['order'])
            && $tactical === null
            && !is_array($factionSettings)
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
            $activityLabel = 'Message envoyé — ' . $author;
            $activityMeta = $chatActivityMeta;
            if (is_array($mentionSummary) && $mentionSummary['mentions'] !== []) {
                $labels = [];
                foreach ($mentionSummary['mentions'] as $m) {
                    $cs = trim((string) ($m['call_sign'] ?? $m['token'] ?? ''));
                    if ($cs !== '') {
                        $labels[] = '@' . $cs;
                    }
                }
                if ($labels !== []) {
                    $activityLabel = 'Mention — ' . $author . ' → ' . implode(', ', $labels);
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
        $forGame = (int) ($request->query('for_game') ?? 0) === 1
            || ($user === null && ComspecApiKeyAuth::matchedTenantId() !== null);
        // Vue émetteur (web connecté) : voit aussi le transit radio. Jeu / clé API : destinataire.
        $issuerView = $user !== null && !$forGame;
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
        $recipientAliases = $forGame
            ? $this->resolveGameRecipientAliases($r, $mapId, $steam, $callsignQ)
            : [];

        $orders = [];
        $maxUpdated = '';
        foreach ($rows as $row) {
            $serialized = $this->serializeOrder($row);
            $aliases = $this->orderMatchAliases($r, $mapId, $row);
            $serialized['match_aliases'] = $aliases;

            if ($forGame && $recipientAliases !== [] && !$this->orderMatchesRecipientAliases($row, $aliases, $recipientAliases)) {
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
            ],
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
        $templates = $this->orderTemplateRepository && $this->orderTemplateRepository->tablesReady()
            ? $this->orderTemplateRepository->listForTenant($r)
            : [];

        return Response::json([
            'ok' => true,
            'templates' => $templates,
            'persisted' => $this->orderTemplateRepository?->tablesReady() ?? false,
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
            ],
            'users' => $users,
            'groups' => $groups,
            'fire_teams' => $fireTeams,
            'channels' => $channels,
            'solos' => $solos,
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
        if (array_key_exists('radio_sim', $body)) {
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

        $externalId = trim((string) ($params['id'] ?? ''));
        if ($externalId === '') {
            return Response::json(['error' => 'not_found', 'message' => 'Ordre introuvable.'], 404);
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

        $existing = $this->orderRepository->findByExternalId($r, $mapId, $externalId);
        if (!$existing) {
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

        $row = $this->orderRepository->updateStatus($r, $mapId, $externalId, $normalized, $by, $note);
        if (!$row) {
            return Response::json(['error' => 'not_found', 'message' => 'Ordre introuvable.'], 404);
        }

        $this->activityLog?->record(
            $r,
            $mapId,
            AtakActivityLogService::TYPE_ORDER,
            'Statut d’ordre mis à jour — ' . $this->orderStatusLabelFr((string) ($row['status'] ?? '')),
            $by !== '' ? $by : (string) ($row['issuer'] ?? '')
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

            return is_array($decoded) ? $decoded : [];
        }

        return [];
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

        return [
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
            'payload' => (string) ($row['payload'] ?? ''),
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
    }

    private function orderTypeLabelFr(string $type, string $customLabel = ''): string
    {
        $customLabel = trim($customLabel);
        $upper = strtoupper($type);
        return match ($upper) {
            'HOLD' => 'Tenir la position',
            'RECON' => 'Reconnaissance',
            'CAS' => 'Appui aérien',
            'QRF' => 'Force de réaction',
            'CUSTOM' => $customLabel !== '' ? $customLabel : 'Ordre personnalisé',
            'MOVE' => 'Se déplacer',
            default => $customLabel !== ''
                ? $customLabel
                : ((str_starts_with($upper, 'CUSTOM') || str_starts_with($upper, 'TPL_'))
                    ? 'Ordre personnalisé'
                    : 'Se déplacer'),
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
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_FLIGHT,
            'Manifeste de vol déclaré — ' . $callsign,
            (string) $callsign
        );
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
        $rows = $this->casRepo->listCas($tenantId, $mapId, $assignedTo, $status, CasNineLineRepository::KIND_CAS);
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
        $actor = $this->guardArmaWrite($request, $tenantId, false);
        if ($actor instanceof Response) {
            return $actor;
        }
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
