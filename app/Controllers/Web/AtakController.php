<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDeviceAuthRepository;
use App\Repositories\AtakMapRepository;
use App\Repositories\TacticalPhonePairingRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TacticalGameLinkRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserUiPreferencesRepository;
use App\Services\Auth\AuthService;
use App\Services\Effectifs\EffectifsStaffAlertService;
use App\Services\Platform\FeatureGateService;
use App\Services\Tactical\AtakMapAccessGapService;
use App\Services\Tactical\AtakSessionProfileHintService;
use App\Services\Tactical\AtakTokenService;
use App\Services\Game\GameAtakPairingService;
use App\Support\OperatorTacticalIdentity;

class AtakController
{
    public function __construct(
        private AtakTokenService $atakTokenService,
        private TenantAtakConfigRepository $atakConfigRepository,
        private AtakMapRepository $atakMapRepository,
        private AuthService $authService,
        private UserProfileRepository $userProfileRepository,
        private UserRepository $userRepository,
        private FeatureGateService $featureGate,
        private ?TacticalGameLinkRepository $gameLinkRepository = null,
        private ?UserUiPreferencesRepository $userUiPreferencesRepository = null,
        private ?AtakMapAccessGapService $accessGapService = null,
        private ?EffectifsStaffAlertService $staffAlertService = null,
        private ?GameAtakPairingService $pairingService = null,
    ) {
        $this->gameLinkRepository ??= new TacticalGameLinkRepository();
        $this->userUiPreferencesRepository ??= new UserUiPreferencesRepository();
        $this->accessGapService ??= new AtakMapAccessGapService($this->userRepository);
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId && !$this->featureGate->allows($tenantId, 'atak')) {
            return Response::view('layout.main', [
                'title' => 'ATAK / Overwatch',
                'content' => 'platform.upgrade',
                'feature' => 'atak',
                'planName' => 'standard',
            ]);
        }
        $config = $tenantId ? $this->atakConfigRepository->getByTenantId($tenantId) : null;

        $canAccessAdminAtakConfig = function_exists('can') && (
            can('admin.access') || can('admin.organization') || can('admin.system')
        );
        if (
            $tenantId
            && $this->atakConfigRepository->isMaintenanceEnabled($tenantId)
            && !$canAccessAdminAtakConfig
        ) {
            return Response::view('atak-maintenance', [
                'maintenanceMessage' => $this->atakConfigRepository->getMaintenanceMessage($tenantId),
                'canAccessAdminAtakConfig' => false,
            ]);
        }

        $nodeUrl = atak_client_base_url($config);

        $visitorIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $visitorIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }

        $jwtSecret = isset($config['jwt_secret']) && $config['jwt_secret'] !== '' ? $config['jwt_secret'] : null;
        $token = $this->atakTokenService->generate($jwtSecret);

        $atakMap = $tenantId ? $this->atakMapRepository->getDefaultForTenant($tenantId) : $this->atakMapRepository->getBySlug('altis');
        $atakMapsList = $this->atakMapRepository->getAll();
        $atakWorkspaces = [];
        foreach ($atakMapsList as $m) {
            $atakWorkspaces[] = [
                'mapId' => (int) ($m['id'] ?? 0),
                'label' => $m['label'] ?? $m['slug'] ?? ('Carte ' . ($m['id'] ?? '')),
            ];
        }
        if (empty($atakWorkspaces)) {
            $atakWorkspaces[] = ['mapId' => 1, 'label' => 'Principal'];
        }
        $atakDefaultMapId = $atakMap ? (int) ($atakMap['id'] ?? 1) : 1;

        $atakCallsignToUser = [];
        if ($tenantId) {
            $users = $this->userRepository->allForTenant($tenantId);
            foreach ($users as $u) {
                $userId = (int) ($u['id'] ?? 0);
                $profile = $this->userProfileRepository->getByUserId($userId);
                $callsign = trim((string) ($u['callsign'] ?? ''));
                $legacyArma = trim((string) ($profile['arma_callsign'] ?? ''));
                $personnelUrl = url('personnel/' . $userId);
                $effective = $callsign !== '' ? $callsign : $legacyArma;
                if ($effective !== '') {
                    $avatar = trim((string) ($u['avatar_url'] ?? ''));
                    $atakCallsignToUser[strtoupper($effective)] = [
                        'userId' => $userId,
                        'url' => $personnelUrl,
                        'avatarUrl' => $avatar !== '' ? $avatar : null,
                        'displayName' => trim((string) ($u['display_name'] ?? '')),
                    ];
                }
            }
        }

        $atakMapsConfigs = [];
        foreach ($atakMapsList as $m) {
            $c = $m['config'] ?? [];
            $slug = (string) ($m['slug'] ?? 'altis');
            $tp = (string) ($m['tile_pattern'] ?? '');
            $atakMapsConfigs[$slug] = [
                'slug' => $slug,
                'tilePattern' => atak_resolve_tile_pattern($tp, $slug),
                'center' => $c['center'] ?? [15000, 15000],
                'defaultZoom' => (int) ($c['defaultZoom'] ?? 3),
                'minZoom' => (int) ($c['minZoom'] ?? 0),
                'maxZoom' => (int) ($c['maxZoom'] ?? 6),
                'tileSize' => (int) ($c['tileSize'] ?? 212),
                'worldSize' => (int) ($c['worldSize'] ?? 30720),
                'attribution' => $c['attribution'] ?? '&copy; Bohemia Interactive',
                'crs' => $c['crs'] ?? ['factorx' => 0.006839, 'factory' => 0.006836, 'tileWidth' => 212],
                'offsetX' => isset($c['offset_x']) ? (float) $c['offset_x'] : 0,
                'offsetY' => isset($c['offset_y']) ? (float) $c['offset_y'] : 0,
            ];
        }

        $currentUser = $this->authService->user();
        if ($currentUser) {
            $profile = $this->userProfileRepository->getByUserId((int) $currentUser['id']);
            $uCall = trim((string) ($currentUser['callsign'] ?? ''));
            $legacy = trim((string) ($profile['arma_callsign'] ?? ''));
            $currentUser['arma_callsign'] = $uCall !== '' ? $uCall : ($legacy !== '' ? $legacy : null);
        }
        $atakUserForJs = null;
        $atakUiPrefs = ['theme' => 'system', 'density' => 'compact'];
        $atakProfileHints = [
            'suggestedRole' => 'operator',
            'suggestedSpecialties' => [],
        ];
        if ($currentUser) {
            $atakUserForJs = [
                'id' => (int) ($currentUser['id'] ?? 0),
                'tenantId' => (int) ($currentUser['tenant_id'] ?? ($tenantId > 0 ? $tenantId : 0)),
                'displayName' => $currentUser['display_name'] ?? '',
                'callsign' => trim((string) ($currentUser['callsign'] ?? '')) ?: (trim((string) (($currentUser['arma_callsign'] ?? ''))) ?: ''),
                'steamId' => $currentUser['steam_id'] ?? null,
                'armaCallsign' => $currentUser['arma_callsign'] ?? null,
            ];
            if ($tenantId) {
                $atakUiPrefs = $this->userUiPreferencesRepository->getOrDefaults((int) $currentUser['id'], $tenantId);
            }
        }

        $atakModDownloadUrl = null;
        if ($tenantId) {
            $modPath = dirname(__DIR__, 2) . '/../storage/atak-mod/' . $tenantId . '/comspec-overwatch.zip';
            if (is_file($modPath) && is_readable($modPath)) {
                $atakModDownloadUrl = url('atak/mod');
            }
        }

        $atakCaps = [
            'loggedIn' => (bool) $currentUser,
            'phoneSession' => false,
            'canViewPersonnel' => function_exists('can')
                && (can('personnel.profile.view') || can('admin.access') || can('admin.organization')),
            'canLinkPersonnel' => function_exists('can')
                && (can('personnel.profile.update') || can('admin.access') || can('admin.organization')),
            'canRenameUnit' => (bool) $currentUser,
            /** Notes effectifs (fréquence, véhicule, note) — visibles par tous, éditables si connecté. */
            'canEditUnitNotes' => (bool) $currentUser,
            /** Staff / état-major : peut retirer n’importe quel contact (fantômes inclus). */
            'canDeleteUnitStaff' => (bool) $currentUser && function_exists('can')
                && (can('admin.access') || can('admin.organization')),
            /** Tout compte connecté peut retirer son propre contact (indicatif). */
            'canDeleteOwnUnit' => (bool) $currentUser,
            'canPing' => true,
            // Compte connecté : la spécialité Médecin du profil de session débloque le triage.
            'canTriageMedical' => (bool) $currentUser,
            'canManageCertificates' => (bool) $currentUser,
            /** Lecture BFT / médical / messagerie — membre ou session téléphone valide. */
            'canViewBft' => true,
            'canUseMedical' => true,
            'canChat' => true,
        ];
        if (!$currentUser) {
            $atakCaps['canViewPersonnel'] = false;
            $atakCaps['canLinkPersonnel'] = false;
            $atakCaps['canRenameUnit'] = false;
            $atakCaps['canEditUnitNotes'] = false;
            $atakCaps['canDeleteUnitStaff'] = false;
            $atakCaps['canDeleteOwnUnit'] = false;
            $atakCaps['canTriageMedical'] = false;
            $atakCaps['canManageCertificates'] = false;
        }

        $atakProfileHints = (new AtakSessionProfileHintService())->build($currentUser, $tenantId);

        $atakTenantLabel = '';
        $atakCommunityMemberships = [];
        if ($tenantId > 0) {
            try {
                $tenantRow = \App\Core\Container::get(\App\Repositories\TenantRepository::class)->findById($tenantId);
                if (is_array($tenantRow)) {
                    $atakTenantLabel = community_display_name($tenantRow);
                }
            } catch (\Throwable) {
            }
            $email = trim((string) Session::get('email'));
            if ($email !== '') {
                try {
                    $atakCommunityMemberships = $this->userRepository->filterSwitchableTenantsForUser(
                        $this->userRepository->listTenantsForEmail($email)
                    );
                } catch (\Throwable) {
                    $atakCommunityMemberships = [];
                }
            }
        }

        if (is_array($currentUser) && (int) ($currentUser['id'] ?? 0) > 0) {
            $personnelCs = '';
            try {
                $pp = (new \App\Repositories\PersonnelProfileRepository())->getByUserId((int) $currentUser['id']);
                $personnelCs = trim((string) ($pp['callsign'] ?? ''));
            } catch (\Throwable) {
            }
            $resolvedCs = OperatorTacticalIdentity::callsign(
                [
                    $personnelCs,
                    (string) ($currentUser['callsign'] ?? ''),
                    (string) ($currentUser['arma_callsign'] ?? ''),
                ],
                $atakTenantLabel,
                $atakTenantLabel
            );
            $currentUser['callsign'] = $resolvedCs;
            if (is_array($atakUserForJs) && empty($atakUserForJs['phoneSession'])) {
                $atakUserForJs['callsign'] = $resolvedCs;
            }
        }

        $phoneOperatorSession = null;
        $phoneToken = trim((string) Session::get('atak_phone_pairing_token', ''));
        if ($phoneToken !== '' && !$currentUser) {
            $pairingRepo = new TacticalPhonePairingRepository();
            $pairing = $pairingRepo->findValidByToken($phoneToken);
            if (is_array($pairing) && ((int) ($pairing['tenant_id'] ?? 0) === $tenantId || $tenantId < 1)) {
                $label = trim((string) Session::get('atak_phone_operator_label', ''));
                if ($label === '') {
                    $label = 'Opérateur téléphone';
                    Session::set('atak_phone_operator_label', $label);
                }
                $phoneOperatorSession = [
                    'active' => true,
                    'label' => $label,
                    'expires_at' => (string) ($pairing['expires_at'] ?? ''),
                ];
                $atakCaps['phoneSession'] = true;
                $atakCaps['canViewBft'] = true;
                $atakCaps['canUseMedical'] = true;
                $atakCaps['canChat'] = true;
                $atakCaps['canManageCertificates'] = false;
                $atakUserForJs = [
                    'id' => 0,
                    'tenantId' => $tenantId > 0 ? $tenantId : (int) ($pairing['tenant_id'] ?? 0),
                    'displayName' => $label,
                    'callsign' => $label,
                    'phoneSession' => true,
                ];
            }
        }

        return Response::view('atak', [
            'atakToken' => $token,
            'atakTenantId' => $tenantId,
            'nodeAtakUrl' => $nodeUrl,
            'visitorIp' => $visitorIp,
            'atakConfig' => $config ? [
                'arma_server_host' => $config['arma_server_host'] ?? null,
                'arma_server_port' => $config['arma_server_port'] ?? null,
                'arma_mod_credentials' => $config['arma_mod_credentials'] ?? null,
                'instructions' => $config['instructions'] ?? null,
            ] : null,
            'atakMapConfig' => $atakMap,
            'atakMapsList' => $atakMapsList,
            'atakMapsConfigs' => $atakMapsConfigs,
            'atakWorkspaces' => $atakWorkspaces,
            'atakDefaultMapId' => $atakDefaultMapId,
            'atakCallsignToUser' => $atakCallsignToUser,
            'atakCaps' => $atakCaps,
            'atakProfileHints' => $atakProfileHints,
            'currentUser' => $currentUser,
            'atakUserForJs' => $atakUserForJs,
            'atakUiPrefs' => $atakUiPrefs,
            'canAccessAdminAtakConfig' => $canAccessAdminAtakConfig,
            'atakModDownloadUrl' => $atakModDownloadUrl,
            'gameLinkCreateUrl' => url('atak/game-link'),
            'atakMaintenanceActive' => $tenantId > 0 && $this->atakConfigRepository->isMaintenanceEnabled($tenantId),
            'atakMaintenanceMessage' => $tenantId > 0 ? $this->atakConfigRepository->getMaintenanceMessage($tenantId) : '',
            'phoneOperatorSession' => $phoneOperatorSession,
            'atakTenantMarkerIcons' => ($tenantId > 0)
                ? (new \App\Services\Tactical\AtakMarkerIconsService())->publicPayload($tenantId)
                : ['assignments' => [], 'library' => []],
            'atakTenantLabel' => $atakTenantLabel,
            'atakCommunityMemberships' => $atakCommunityMemberships,
            'atakAccessGap' => $this->accessGapPayload($tenantId, $currentUser, $phoneOperatorSession),
            'atakDeviceSecurity' => $this->deviceSecurityPayload($currentUser),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Génère un code court à saisir en jeu pour lier le mod au compte Athena (TTL 15 min).
     */
    public function createGameLink(Request $request, array $params = []): Response
    {
        $block = $this->requireAtakFeature();
        if ($block !== null) {
            return $block;
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['error' => 'unauthorized', 'message' => 'Connectez-vous pour générer un code de liaison.'], 401);
        }
        // Prod : 503 si la migration tactical_game_link n’a pas été exécutée (table absente).
        if (!$this->gameLinkRepository->isReady()) {
            return Response::json([
                'error' => 'unavailable',
                'message' => 'La liaison avec le jeu n’est pas encore activée sur ce serveur. Réessayez plus tard ou contactez un administrateur.',
            ], 503);
        }
        $created = $this->gameLinkRepository->create($tenantId, $userId);
        if ($created === null) {
            return Response::json([
                'error' => 'unavailable',
                'message' => 'Impossible de générer un code pour le moment. Réessayez dans un instant.',
            ], 503);
        }

        return Response::json([
            'ok' => true,
            'code' => $created['code'],
            'expires_at' => $created['expires_at'],
            'api_url' => atak_client_base_url($this->atakConfigRepository->getByTenantId($tenantId)),
            'hint' => 'Dans Arma : téléphone ATAK → Connexion Athena → Code de secours, puis entrez ce code.',
        ]);
    }

    /**
     * Valide le code affiché sur le téléphone ATAK (appairage généré en jeu).
     */
    public function confirmPairCode(Request $request, array $params = []): Response
    {
        $block = $this->requireAtakFeature();
        if ($block !== null) {
            return $block;
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['error' => 'unauthorized', 'message' => 'Connectez-vous pour valider ce terminal.'], 401);
        }
        $raw = (string) ($request->input('user_code', $request->input('code', '')));
        if ($raw === '') {
            $json = json_decode(\App\Support\HttpJsonBody::rawJson(), true);
            $raw = is_array($json) ? (string) ($json['user_code'] ?? $json['code'] ?? '') : '';
        }
        $this->pairingService ??= \App\Core\Container::get(GameAtakPairingService::class);
        $result = $this->pairingService->approveFromWeb($raw, $userId, $tenantId);

        return Response::json($result['payload'], $result['status']);
    }

    /**
     * Demande d’accès carte (grade / rôle / fonction) — même circuit que l’élévation RH.
     */
    public function requestMapAccess(Request $request, array $params = []): Response
    {
        $block = $this->requireAtakFeature();
        if ($block !== null) {
            return $block;
        }
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$user || $tenantId < 1 || $userId < 1) {
            return Response::json([
                'ok' => false,
                'message' => 'Connectez-vous pour demander un accès.',
            ], 401);
        }

        $body = $this->jsonBody($request);
        $token = $request->input('_csrf_token')
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)
            ?? ($body['_csrf_token'] ?? null);
        if (!is_string($token) || !Csrf::validate($token)) {
            return Response::json([
                'ok' => false,
                'message' => 'Votre session a expiré. Rechargez la page puis réessayez.',
            ], 403);
        }

        $payload = $this->accessGapService->webPayload($tenantId, $user);
        if (empty($payload['offer']) || empty($payload['gaps'])) {
            return Response::json([
                'ok' => false,
                'message' => 'Votre accès actuel couvre déjà les vues de la carte pour votre profil.',
            ], 422);
        }
        if (!empty($payload['pending'])) {
            return Response::json([
                'ok' => false,
                'message' => 'Une demande est déjà en cours d’examen par l’encadrement.',
            ], 409);
        }

        $note = AtakMapAccessGapService::formatRequestNote($payload['gaps']);
        $staffAlert = $this->staffAlert();
        $result = $staffAlert->requestElevation(
            $tenantId,
            $userId,
            $user,
            'droits',
            $note
        );

        return Response::json([
            'ok' => !empty($result['ok']),
            'message' => (string) ($result['message'] ?? ''),
            'recipient_names' => $result['recipient_names'] ?? [],
        ], !empty($result['ok']) ? 200 : 422);
    }

    /**
     * @param array<string, mixed>|null $currentUser
     * @param array<string, mixed>|null $phoneOperatorSession
     * @return array<string, mixed>
     */
    private function accessGapPayload(int $tenantId, ?array $currentUser, ?array $phoneOperatorSession): array
    {
        if ($phoneOperatorSession) {
            return [
                'offer' => false,
                'pending' => false,
                'requestUrl' => url('atak/demande-acces'),
                'gaps' => [],
            ];
        }

        return $this->accessGapService->webPayload($tenantId, $currentUser);
    }

    /**
     * @param array<string, mixed>|null $currentUser
     * @return array{needsRecoveryCodes: bool, setupUrl: string}
     */
    private function deviceSecurityPayload(?array $currentUser): array
    {
        $setupUrl = url('account/security/devices') . '#recovery';
        $uid = (int) ($currentUser['id'] ?? 0);
        $tid = (int) ($currentUser['tenant_id'] ?? 0);
        if ($uid < 1 || $tid < 1) {
            return ['needsRecoveryCodes' => false, 'setupUrl' => $setupUrl];
        }
        try {
            $repo = new AtakDeviceAuthRepository();

            return [
                'needsRecoveryCodes' => !$repo->hasActiveRecoveryCodes($uid, $tid),
                'setupUrl' => $setupUrl,
            ];
        } catch (\Throwable) {
            return ['needsRecoveryCodes' => false, 'setupUrl' => $setupUrl];
        }
    }

    private function staffAlert(): EffectifsStaffAlertService
    {
        if ($this->staffAlertService instanceof EffectifsStaffAlertService) {
            return $this->staffAlertService;
        }
        $this->staffAlertService = \App\Core\Container::get(EffectifsStaffAlertService::class);

        return $this->staffAlertService;
    }

    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (str_contains((string) $contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '[]', true);

            return is_array($decoded) ? $decoded : [];
        }

        return array_merge($request->all(), $_POST);
    }

    private function requireAtakFeature(): ?Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId > 0 && !$this->featureGate->allows($tenantId, 'atak')) {
            return Response::view('layout.main', [
                'title' => 'ATAK / Overwatch',
                'content' => 'platform.upgrade',
                'feature' => 'atak',
                'planName' => 'standard',
            ]);
        }

        return null;
    }

    public function downloadMod(Request $request, array $params = []): Response
    {
        $block = $this->requireAtakFeature();
        if ($block !== null) {
            return $block;
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $modPath = dirname(__DIR__, 2) . '/../storage/atak-mod/' . $tenantId . '/comspec-overwatch.zip';
        if (!is_file($modPath) || !is_readable($modPath)) {
            return Response::redirect(url('atak/mod'));
        }
        $name = 'COMSPEC-Overwatch.zip';
        $response = new \App\Core\Response();
        $response->header('Content-Type', 'application/zip');
        $response->header('Content-Disposition', 'attachment; filename="' . str_replace('"', '\\"', $name) . '"');
        $response->header('Content-Length', (string) filesize($modPath));
        $response->header('Cache-Control', 'private, no-cache');
        $response->setBodyStream(static function () use ($modPath): void {
            $h = fopen($modPath, 'rb');
            if ($h) {
                fpassthru($h);
                fclose($h);
            }
        });
        return $response;
    }

    /**
     * Page membre : présentation + téléchargement du pack Overwatch.
     */
    public function modPage(Request $request, array $params = []): Response
    {
        $block = $this->requireAtakFeature();
        if ($block !== null) {
            return $block;
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $modPath = dirname(__DIR__, 2) . '/../storage/atak-mod/' . $tenantId . '/comspec-overwatch.zip';
        $hasMod = is_file($modPath) && is_readable($modPath);
        $sizeLabel = null;
        $updatedAt = null;
        $version = null;
        if ($hasMod) {
            $bytes = (int) filesize($modPath);
            if ($bytes < 1024) {
                $sizeLabel = $bytes . ' o';
            } elseif ($bytes < 1024 * 1024) {
                $sizeLabel = number_format($bytes / 1024, 1, ',', ' ') . ' Ko';
            } else {
                $sizeLabel = number_format($bytes / (1024 * 1024), 1, ',', ' ') . ' Mo';
            }
            $updatedAt = date('d/m/Y H:i', (int) filemtime($modPath));
            if (class_exists(\ZipArchive::class)) {
                $zip = new \ZipArchive();
                if ($zip->open($modPath, \ZipArchive::RDONLY) === true) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $name = $zip->getNameIndex($i);
                        if ($name === false) {
                            continue;
                        }
                        $norm = str_replace('\\', '/', $name);
                        if (!preg_match('#(^|/)mod\.cpp$#i', $norm)) {
                            continue;
                        }
                        $content = $zip->getFromIndex($i);
                        if (is_string($content)
                            && (
                                preg_match('/version\s*=\s*"([^"]+)"/i', $content, $m)
                                || preg_match('/version\s*=\s*\'([^\']+)\'/i', $content, $m)
                                || preg_match('/version\s*=\s*([0-9][0-9.\-]*)/i', $content, $m)
                            )
                        ) {
                            $version = trim($m[1]);
                        }
                        break;
                    }
                    $zip->close();
                }
            }
        }

        return Response::view('layout.main', [
            'content' => 'atak.mod',
            'title' => 'Télécharger le pack Overwatch',
            'hasMod' => $hasMod,
            'modSizeLabel' => $sizeLabel,
            'modUpdatedAt' => $updatedAt,
            'modVersion' => $version,
            'modDownloadUrl' => $hasMod ? url('atak/mod/download') : null,
            'atakSetupUrl' => url('atak/setup'),
            'atakUrl' => url('atak'),
            'docsUrl' => url('documentation') . '#diapositives-briefing-eden',
            'canManageMod' => function_exists('can') && (can('admin.access') || can('admin.organization')),
            'adminModUrl' => url('admin/atak-mod'),
        ]);
    }

    public function setup(Request $request, array $params = []): Response
    {
        $block = $this->requireAtakFeature();
        if ($block !== null) {
            return $block;
        }
        $tenantId = (int) Session::get('tenant_id');
        $config = $tenantId ? $this->atakConfigRepository->getByTenantId($tenantId) : null;

        $nodeUrl = atak_client_base_url($config);

        $atakModDownloadUrl = null;
        if ($tenantId) {
            $modPath = dirname(__DIR__, 2) . '/../storage/atak-mod/' . $tenantId . '/comspec-overwatch.zip';
            if (is_file($modPath) && is_readable($modPath)) {
                $atakModDownloadUrl = url('atak/mod');
            }
        }

        return Response::view('layout.main', [
            'content' => 'atak-setup',
            'title' => 'Assistant Mod Arma — Installation, configuration, vérification',
            'nodeAtakUrl' => $nodeUrl,
            'atakConfig' => $config ? [
                'arma_server_host' => $config['arma_server_host'] ?? null,
                'arma_server_port' => $config['arma_server_port'] ?? null,
                'arma_mod_credentials' => $config['arma_mod_credentials'] ?? null,
                'instructions' => $config['instructions'] ?? null,
            ] : null,
            'atakModDownloadUrl' => $atakModDownloadUrl,
        ]);
    }

    public function tuto(Request $request, array $params = []): Response
    {
        $block = $this->requireAtakFeature();
        if ($block !== null) {
            return $block;
        }

        return Response::view('layout.main', [
            'content' => 'atak-tuto',
            'title' => 'Tutoriel — Mod Arma COMSPEC Overwatch',
        ]);
    }

    /**
     * Page dédiée — journal de liaison (recherche, filtres, historique archivé).
     */
    public function liaison(Request $request, array $params = []): Response
    {
        $block = $this->requireAtakFeature();
        if ($block !== null) {
            return $block;
        }
        $tenantId = (int) Session::get('tenant_id');
        $config = $tenantId ? $this->atakConfigRepository->getByTenantId($tenantId) : null;
        $nodeUrl = atak_client_base_url($config);
        $jwtSecret = isset($config['jwt_secret']) && $config['jwt_secret'] !== '' ? $config['jwt_secret'] : null;
        $token = $this->atakTokenService->generate($jwtSecret);

        $atakMap = $tenantId ? $this->atakMapRepository->getDefaultForTenant($tenantId) : $this->atakMapRepository->getBySlug('altis');
        $atakMapsList = $this->atakMapRepository->getAll();
        $atakWorkspaces = [];
        foreach ($atakMapsList as $m) {
            $atakWorkspaces[] = [
                'mapId' => (int) ($m['id'] ?? 0),
                'label' => $m['label'] ?? $m['slug'] ?? ('Carte ' . ($m['id'] ?? '')),
            ];
        }
        if ($atakWorkspaces === []) {
            $atakWorkspaces[] = ['mapId' => 1, 'label' => 'Principal'];
        }
        $atakDefaultMapId = $atakMap ? (int) ($atakMap['id'] ?? 1) : 1;

        $mapQuery = $request->query('mapId');
        if ($mapQuery !== null && $mapQuery !== '') {
            $mid = (int) $mapQuery;
            if ($mid > 0) {
                $atakDefaultMapId = $mid;
            }
        }

        $demoAllowed = false;
        try {
            $activityLog = new \App\Services\Tactical\AtakActivityLogService();
            $demoAllowed = $activityLog->isDemoSeedAllowed();
        } catch (\Throwable) {
            $demoAllowed = false;
        }

        return Response::view('atak-liaison', [
            'atakToken' => $token,
            'nodeAtakUrl' => $nodeUrl,
            'atakWorkspaces' => $atakWorkspaces,
            'atakDefaultMapId' => $atakDefaultMapId,
            'demoSeedAllowed' => $demoAllowed,
        ]);
    }

}
