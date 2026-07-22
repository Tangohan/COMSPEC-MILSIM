<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TacticalGameLinkRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserUiPreferencesRepository;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;
use App\Services\Tactical\AtakTokenService;

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
    ) {
        $this->gameLinkRepository ??= new TacticalGameLinkRepository();
        $this->userUiPreferencesRepository ??= new UserUiPreferencesRepository();
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

        $canAccessAdminAtakConfig = function_exists('can') && can('admin.access');
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
        $atakUiPrefs = ['theme' => 'system', 'density' => 'comfortable'];
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
            'canViewPersonnel' => function_exists('can')
                && (can('personnel.profile.view') || can('admin.access') || can('admin.organization')),
            'canLinkPersonnel' => function_exists('can')
                && (can('personnel.profile.update') || can('admin.access') || can('admin.organization')),
            'canRenameUnit' => (bool) $currentUser,
            /** Staff / état-major : peut retirer n’importe quel contact (fantômes inclus). */
            'canDeleteUnitStaff' => (bool) $currentUser && function_exists('can')
                && (can('admin.access') || can('admin.organization')),
            /** Tout compte connecté peut retirer son propre contact (indicatif). */
            'canDeleteOwnUnit' => (bool) $currentUser,
            'canPing' => true,
            // Compte connecté : la spécialité Médecin du profil de session débloque le triage.
            'canTriageMedical' => (bool) $currentUser,
        ];
        if (!$currentUser) {
            $atakCaps['canViewPersonnel'] = false;
            $atakCaps['canLinkPersonnel'] = false;
            $atakCaps['canRenameUnit'] = false;
            $atakCaps['canDeleteUnitStaff'] = false;
            $atakCaps['canDeleteOwnUnit'] = false;
            $atakCaps['canTriageMedical'] = false;
        }

        $atakProfileHints = $this->buildAtakProfileHints($currentUser, $atakCaps, $tenantId);

        return Response::view('atak', [
            'atakToken' => $token,
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
        ]);
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
            'hint' => 'Dans Arma : touche K → Compte Athena (saisir un code), puis entrez ce code.',
        ]);
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

    /**
     * Suggestions de rôle / spécialités ATAK à partir du compte Athena.
     *
     * @param array<string, mixed>|null $currentUser
     * @param array<string, mixed> $atakCaps
     * @return array{suggestedRole: string, suggestedSpecialties: list<string>}
     */
    private function buildAtakProfileHints(?array $currentUser, array $atakCaps, int $tenantId): array
    {
        $suggestedRole = 'operator';
        $suggestedSpecialties = [];
        if (!$currentUser) {
            return [
                'suggestedRole' => $suggestedRole,
                'suggestedSpecialties' => $suggestedSpecialties,
            ];
        }

        $parts = [
            (string) ($currentUser['display_name'] ?? ''),
            (string) ($currentUser['callsign'] ?? ''),
            (string) ($currentUser['arma_callsign'] ?? ''),
            (string) ($currentUser['professional_category_code'] ?? ''),
        ];

        $roleId = (int) ($currentUser['role_id'] ?? 0);
        if ($roleId > 0) {
            try {
                $role = (new RoleRepository())->findById($roleId, $tenantId > 0 ? $tenantId : null);
                if ($role) {
                    $parts[] = (string) ($role['name'] ?? '');
                    $parts[] = (string) ($role['slug'] ?? '');
                    $parts[] = (string) ($role['label_en'] ?? '');
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $gradeId = (int) ($currentUser['grade_id'] ?? 0);
        if ($gradeId > 0) {
            try {
                $grade = (new GradeRepository())->findById($gradeId);
                if ($grade) {
                    $parts[] = (string) ($grade['label_short'] ?? '');
                    $parts[] = (string) ($grade['label_long'] ?? '');
                    $parts[] = (string) ($grade['label_otan'] ?? '');
                    $parts[] = (string) ($grade['category_label'] ?? '');
                } else {
                    $legacy = (new GradeRepository())->findByIdLegacy($gradeId, $tenantId > 0 ? $tenantId : null);
                    if ($legacy) {
                        $parts[] = (string) ($legacy['short_name'] ?? $legacy['label_short'] ?? '');
                        $parts[] = (string) ($legacy['name'] ?? $legacy['label_long'] ?? '');
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        try {
            if ($tenantId > 0) {
                $job = (new PersonnelJobRoleRepository())->getPrimaryJobRoleForUser($tenantId, (int) $currentUser['id']);
                if ($job) {
                    $parts[] = (string) ($job['name'] ?? '');
                    $parts[] = (string) ($job['role_detail'] ?? '');
                    $parts[] = (string) ($job['display'] ?? '');
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        $blob = mb_strtolower(implode(' ', array_filter(array_map('trim', $parts))));
        $blob = str_replace(['é', 'è', 'ê', 'ë', 'à', 'â', 'ù', 'û', 'ô', 'î', 'ï', 'ç'], ['e', 'e', 'e', 'e', 'a', 'a', 'u', 'u', 'o', 'i', 'i', 'c'], $blob);

        $isDeputy = (bool) preg_match('/\b(adjoint|2ic|xo|deputy|second|assistant\s+chef)\b/u', $blob);
        $isCommander = (bool) preg_match('/\b(commandant|commandeur|chef\s+d[e\']?\s*unite|chef\s+de\s+section|squad\s*lead|platoon|leader|cdt|sl|tl|officier|officer)\b/u', $blob);
        if (function_exists('can') && (can('admin.access') || can('admin.organization'))) {
            $isCommander = true;
        }
        if ($isDeputy) {
            $suggestedRole = 'deputy';
        } elseif ($isCommander) {
            $suggestedRole = 'commander';
        }

        $addSpec = static function (string $id) use (&$suggestedSpecialties): void {
            if (!in_array($id, $suggestedSpecialties, true)) {
                $suggestedSpecialties[] = $id;
            }
        };

        if (!empty($atakCaps['canTriageMedical']) || preg_match('/\b(medic|medecin|medical|corpsman|infirmier|secouriste|sante)\b/u', $blob)) {
            $addSpec('medic');
        }
        if (preg_match('/\b(jtac|fac|cas\s*control|controleur\s+aerien|forward\s+air)\b/u', $blob)) {
            $addSpec('jtac');
        }
        if (preg_match('/\b(radio|rto|transmetteur|signal|sigint|communications|comms)\b/u', $blob)) {
            $addSpec('radio');
        }

        return [
            'suggestedRole' => $suggestedRole,
            'suggestedSpecialties' => $suggestedSpecialties,
        ];
    }
}
