<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\AtakMapRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Services\Tactical\AtakActivityLogService;
use App\Services\Tactical\AtakTenantDataService;
use App\Services\Tactical\AtakBridgeModulesService;
use App\Services\Tactical\AtakExperienceService;
use App\Support\ComspecApiKeyAuth;

class AdminAtakConfigController
{
    public const PURGE_CONFIRM_PHRASE = 'EFFACER';

    public function __construct(
        private TenantAtakConfigRepository $atakConfigRepository,
        private AtakMapRepository $atakMapRepository,
        private ?AtakActivityLogService $activityLog = null,
        private ?AtakTenantDataService $tenantData = null,
    ) {
        $this->activityLog ??= new AtakActivityLogService();
        $this->tenantData ??= new AtakTenantDataService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $config = $this->atakConfigRepository->getByTenantId($tenantId);
        $atakMaps = $this->atakMapRepository->getAll();
        $platformKeyConfigured = ComspecApiKeyAuth::expectedSecret() !== '';
        $accessKeyPrefix = is_array($config) ? trim((string) ($config['access_key_prefix'] ?? '')) : '';
        $accessKeyGeneratedAt = is_array($config) ? ($config['access_key_generated_at'] ?? null) : null;
        $hasTenantAccessKey = is_array($config) && trim((string) ($config['access_key'] ?? '')) !== '';
        $newAccessKey = Session::getFlash('new_atak_access_key');
        $authEvents = $this->activityLog->listRecent($tenantId, AtakActivityLogService::AUTH_MAP_ID, 30);
        $dataSummary = $this->tenantData->summarize($tenantId);
        $maintenanceSchemaReady = $this->atakConfigRepository->isMaintenanceSchemaReady();
        $maintenanceEnabled = $maintenanceSchemaReady
            && is_array($config)
            && (int) ($config['maintenance_enabled'] ?? 0) === 1;
        $maintenanceMessage = ($maintenanceSchemaReady && is_array($config))
            ? trim((string) ($config['maintenance_message'] ?? ''))
            : '';
        $modulesSvc = new AtakBridgeModulesService();
        $bridgeModules = $modulesSvc->catalogWithState($tenantId);
        $bridgeModulesUpdatedAt = $modulesSvc->get($tenantId)['updated_at'] ?? '';
        $experienceSvc = new AtakExperienceService();
        $experiencePack = $experienceSvc->get($tenantId);
        $experienceCatalog = $experienceSvc->catalogWithState($tenantId);
        $experienceSchemaReady = $this->atakConfigRepository->isExperienceSchemaReady();
        $photoHudSvc = new \App\Services\Media\ReconPhotoHudService();
        $photoHud = $photoHudSvc->get($tenantId);
        $photoHudPreview = $photoHudSvc->previewDataUri($tenantId, $photoHud);
        $markerIconsSvc = new \App\Services\Tactical\AtakMarkerIconsService();
        $markerIcons = $markerIconsSvc->get($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.atak-config.index',
            'title' => 'Configuration ATAK / Tacmap',
            'config' => $config,
            'atakMaps' => $atakMaps,
            'tenantId' => $tenantId,
            'platformKeyConfigured' => $platformKeyConfigured,
            'accessKeyPrefix' => $accessKeyPrefix,
            'accessKeyGeneratedAt' => $accessKeyGeneratedAt,
            'hasTenantAccessKey' => $hasTenantAccessKey,
            'newAccessKeyPlain' => is_string($newAccessKey) ? $newAccessKey : null,
            'authEvents' => $authEvents,
            'portalBaseUrl' => rtrim(url(''), '/'),
            'dataSummary' => $dataSummary,
            'maintenanceSchemaReady' => $maintenanceSchemaReady,
            'maintenanceEnabled' => $maintenanceEnabled,
            'maintenanceMessage' => $maintenanceMessage,
            'purgeConfirmPhrase' => self::PURGE_CONFIRM_PHRASE,
            'bridgeModules' => $bridgeModules,
            'bridgeModulesUpdatedAt' => $bridgeModulesUpdatedAt,
            'experienceCatalog' => $experienceCatalog,
            'experienceGuide' => $experiencePack['guide'],
            'experienceGuideCustom' => trim((string) ($experiencePack['settings']['guide_custom'] ?? '')),
            'experienceUpdatedAt' => $experiencePack['updated_at'],
            'experienceSchemaReady' => $experienceSchemaReady,
            'photoHud' => $photoHud,
            'photoHudPreview' => $photoHudPreview,
            'markerIcons' => $markerIcons,
            'markerIconKinds' => \App\Services\Tactical\AtakMarkerIconsService::KINDS,
            'gameExperience' => (new \App\Services\Game\GameOverwatchExperienceService())->get($tenantId),
            'gameExperiencePreview' => $this->gameExperiencePreviewData($tenantId),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');
            return Response::redirect(url('admin/atak-config'));
        }

        $this->atakConfigRepository->createOrUpdate((int) $tenantId, [
            'node_url' => trim((string) $request->input('node_url', '')),
            'jwt_secret' => trim((string) $request->input('jwt_secret', '')),
            'arma_server_host' => trim((string) $request->input('arma_server_host', '')),
            'arma_server_port' => $request->input('arma_server_port') !== '' ? (int) $request->input('arma_server_port') : null,
            'arma_mod_credentials' => trim((string) $request->input('arma_mod_credentials', '')),
            'instructions' => trim((string) $request->input('instructions', '')),
            'default_map_slug' => trim((string) $request->input('default_map_slug', 'altis')) ?: 'altis',
        ]);

        Session::flash('success', 'Configuration ATAK / Tacmap enregistrée.');
        return Response::redirect(url('admin/atak-config'));
    }

    /** Génère ou régénère la clé d’accès Overwatch pour la communauté courante. */
    public function regenerateAccessKey(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('admin/atak-config'));
        }

        $created = $this->atakConfigRepository->generateAccessKey((int) $tenantId);
        if ($created === null) {
            Session::flash('error', 'Impossible de générer la clé d’accès. Réessayez ou contactez le support plateforme.');

            return Response::redirect(url('admin/atak-config'));
        }

        Session::flash('new_atak_access_key', $created['plain_key']);
        Session::flash('success', 'Nouvelle clé d’accès générée. Copiez-la maintenant : elle ne sera plus affichée en entier ensuite.');
        $this->activityLog?->recordAuthAttempt(
            (int) $tenantId,
            true,
            'Clé d’accès régénérée par un administrateur',
            ['reason' => 'key_regenerated', 'method' => 'admin'],
            null
        );

        return Response::redirect(url('admin/atak-config'));
    }

    /** Active ou désactive les modules pont ATAK Enhanced / cTab. */
    public function storeModules(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('admin/atak-config'));
        }

        $svc = new AtakBridgeModulesService();
        $boolMap = [];
        foreach ($svc->catalog() as $row) {
            $id = $row['id'];
            $boolMap[$id] = (string) $request->input('module_' . $id, '0') === '1';
        }
        $svc->put((int) $tenantId, $boolMap);
        Session::flash('success', 'Modules ATAK Enhanced / cTab enregistrés. Les joueurs en liaison récupèrent le réglage sous environ une minute.');

        return Response::redirect(url('admin/atak-config'));
    }

    /** Réglages d’expérience Overwatch (réalisme, troll, personnalisation). */
    public function storeExperience(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('admin/atak-config'));
        }

        if (!$this->atakConfigRepository->isExperienceSchemaReady()) {
            Session::flash(
                'error',
                'Impossible d’enregistrer l’expérience : la base de données n’est pas à jour. Contactez le support plateforme pour appliquer la mise à jour, puis réessayez.'
            );

            return Response::redirect(url('admin/atak-config'));
        }

        $svc = new AtakExperienceService();
        $incoming = [];
        foreach ($svc->catalog() as $row) {
            $id = $row['id'];
            if ($row['type'] === 'bool') {
                $incoming[$id] = (string) $request->input('experience_' . $id, '0') === '1';
            } elseif ($row['type'] === 'tri') {
                $incoming[$id] = trim((string) $request->input('experience_' . $id, 'player'));
            }
        }
        $incoming['guide_custom'] = trim((string) $request->input('experience_guide_custom', ''));
        if (!empty($incoming['realism'])) {
            $incoming['troll'] = false;
        }
        $svc->put((int) $tenantId, $incoming);
        Session::flash('success', 'Expérience Overwatch enregistrée. Les opérateurs en liaison récupèrent les réglages sous environ une minute.');

        return Response::redirect(url('admin/atak-config'));
    }

    public function storeGameExperience(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('admin/atak-config') . '#overwatch-game-experience');
        }
        $tenantId = (int) $tenantId;
        $svc = new \App\Services\Game\GameOverwatchExperienceService();
        $incoming = $svc->get($tenantId);
        $bools = [
            'auth_password', 'auth_otp', 'auth_steam', 'allow_auto_reconnect',
            'sync_profile', 'sync_grade', 'sync_unit', 'sync_callsign', 'sync_avatar',
            'sync_clearances', 'sync_c2', 'bft_enabled', 'chat_enabled', 'intel_enabled',
            'photos_enabled', 'markers_enabled', 'jtac_enabled',
        ];
        foreach ($bools as $key) {
            $incoming[$key] = (string) $request->input('game_' . $key, '0') === '1';
        }
        $incoming['display_name'] = trim((string) $request->input('game_display_name', ''));
        $incoming['welcome_message'] = trim((string) $request->input('game_welcome_message', ''));
        $incoming['min_mod_version'] = trim((string) $request->input('game_min_mod_version', '2.3.0'));
        $incoming['channel'] = trim((string) $request->input('game_channel', 'PROD'));
        $incoming['update_interval'] = (int) $request->input('game_update_interval', 5);
        $uploads = new \App\Services\Community\CommunityMediaUploadService();
        if (!empty($_FILES['game_login_image']['tmp_name'])) {
            $stored = $uploads->storeImage($_FILES['game_login_image'], $tenantId);
            if (($stored['error'] ?? null) !== null) {
                Session::flash('error', (string) $stored['error']);

                return Response::redirect(url('admin/atak-config') . '#overwatch-game-experience');
            }
            if (!empty($stored['path'])) {
                $incoming['login_image_path'] = $stored['path'];
            }
        }
        if (!empty($_FILES['game_logo']['tmp_name'])) {
            $stored = $uploads->storeImage($_FILES['game_logo'], $tenantId);
            if (($stored['error'] ?? null) !== null) {
                Session::flash('error', (string) $stored['error']);

                return Response::redirect(url('admin/atak-config') . '#overwatch-game-experience');
            }
            if (!empty($stored['path'])) {
                $incoming['logo_path'] = $stored['path'];
            }
        }
        $svc->put($tenantId, $incoming);
        try {
            \App\Core\Container::get(\App\Services\ConfigurationUpdate\ConfigurationUpdateService::class)
                ->markCompleted($tenantId, 'OVERWATCH_GAME_AUTH_V1', (int) (Session::get('user_id') ?? 0) ?: null);
        } catch (\Throwable) {
        }
        Session::flash('success', 'L’expérience en jeu a été enregistrée. Elle s’applique à la prochaine connexion Overwatch.');

        return Response::redirect(url('admin/atak-config') . '#overwatch-game-experience');
    }

    /**
     * @return array{name: string, image: string, message: string}
     */
    private function gameExperiencePreviewData(int $tenantId): array
    {
        $svc = new \App\Services\Game\GameOverwatchExperienceService();
        $cfg = $svc->get($tenantId);
        $tenant = (new \App\Repositories\TenantRepository())->findById($tenantId) ?? [];
        $name = trim((string) ($cfg['display_name'] ?: ($tenant['name'] ?? 'ATHENA')));

        return [
            'name' => $name,
            'image' => $svc->loginImageUrl($tenantId, $cfg, (string) ($tenant['logo_url'] ?? '')),
            'message' => (string) ($cfg['welcome_message'] ?? ''),
        ];
    }
    public function storePhotoHud(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('admin/atak-config'));
        }

        $svc = new \App\Services\Media\ReconPhotoHudService();
        $svc->put((int) $tenantId, [
            'enabled' => (string) $request->input('photo_hud_enabled', '0') === '1',
            'position' => trim((string) $request->input('photo_hud_position', 'top')),
            'style' => trim((string) $request->input('photo_hud_style', 'axon')),
            'agency' => trim((string) $request->input('photo_hud_agency', '')),
            'custom_line' => trim((string) $request->input('photo_hud_custom', '')),
            'show_datetime' => (string) $request->input('photo_hud_show_datetime', '0') === '1',
            'show_callsign' => (string) $request->input('photo_hud_show_callsign', '0') === '1',
            'show_device' => (string) $request->input('photo_hud_show_device', '0') === '1',
            'show_grid' => (string) $request->input('photo_hud_show_grid', '0') === '1',
            'show_heading' => (string) $request->input('photo_hud_show_heading', '0') === '1',
            'show_altitude' => (string) $request->input('photo_hud_show_altitude', '0') === '1',
        ]);
        Session::flash('success', 'Bandeau des photos terrain enregistré. Il s’appliquera aux prochaines captures.');

        return Response::redirect(url('admin/atak-config') . '#photo-hud');
    }

    public function storeMarkerIcons(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('admin/atak-config') . '#marker-icons');
        }
        $svc = new \App\Services\Tactical\AtakMarkerIconsService();
        $assign = [];
        foreach (array_keys(\App\Services\Tactical\AtakMarkerIconsService::KINDS) as $kind) {
            $assign[$kind] = trim((string) $request->input('marker_icon_' . $kind, 'nato'));
        }
        $svc->saveAssignments((int) $tenantId, $assign);
        Session::flash('success', 'Apparence des symboles enregistrée. Rechargez la carte pour la voir.');

        return Response::redirect(url('admin/atak-config') . '#marker-icons');
    }

    public function uploadMarkerIcon(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('admin/atak-config') . '#marker-icons');
        }
        $file = $_FILES['marker_icon_file'] ?? [];
        if (!is_array($file)) {
            $file = [];
        }
        $svc = new \App\Services\Tactical\AtakMarkerIconsService();
        $res = $svc->addUpload((int) $tenantId, $file, trim((string) $request->input('marker_icon_label', '')));
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);

        return Response::redirect(url('admin/atak-config') . '#marker-icons');
    }

    public function deleteMarkerIcon(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('admin/atak-config') . '#marker-icons');
        }
        $svc = new \App\Services\Tactical\AtakMarkerIconsService();
        $svc->deleteLibraryItem((int) $tenantId, trim((string) $request->input('icon_id', '')));
        Session::flash('success', 'Icône retirée de la bibliothèque.');

        return Response::redirect(url('admin/atak-config') . '#marker-icons');
    }

    /** Active ou désactive le mode maintenance ATAK / Tacmap. */
    public function setMaintenance(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('admin/atak-config'));
        }

        $enabled = (string) $request->input('maintenance_enabled', '0') === '1';
        $message = trim((string) $request->input('maintenance_message', ''));
        $ok = $this->atakConfigRepository->setMaintenance(
            (int) $tenantId,
            $enabled,
            $message !== '' ? $message : null
        );

        if (!$ok) {
            Session::flash(
                'error',
                'Impossible d’enregistrer le mode maintenance : la mise à jour qui active cette fonction n’a pas encore été appliquée. Demandez au support plateforme de lancer la mise à jour, puis réessayez.'
            );

            return Response::redirect(url('admin/atak-config'));
        }

        Session::flash(
            'success',
            $enabled
                ? 'Mode maintenance activé. La carte tactique et la liaison jeu sont temporairement indisponibles pour les opérateurs. Les administrateurs gardent l’accès.'
                : 'Mode maintenance désactivé. La carte tactique est à nouveau accessible.'
        );

        return Response::redirect(url('admin/atak-config'));
    }

    /** Télécharge un export JSON de tout le journal et des données de mission. */
    public function exportData(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $payload = $this->tenantData->exportAll($tenantId);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            Session::flash('error', 'Impossible de préparer l’export. Réessayez.');

            return Response::redirect(url('admin/atak-config'));
        }

        $filename = 'atak-export-communaute-' . $tenantId . '-' . date('Ymd-His') . '.json';
        $response = new Response();
        $response->setStatusCode(200)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-store')
            ->setBody($json);

        return $response;
    }

    /** Efface définitivement journal + données de mission de la communauté. */
    public function purgeData(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('admin/atak-config'));
        }

        $confirm = trim((string) $request->input('confirm_phrase', ''));
        if (strcasecmp($confirm, self::PURGE_CONFIRM_PHRASE) !== 0) {
            Session::flash(
                'error',
                'Pour confirmer l’effacement, saisissez exactement le mot demandé (en majuscules).'
            );

            return Response::redirect(url('admin/atak-config'));
        }

        $tenantId = (int) $tenantId;
        $result = $this->tenantData->purgeAll($tenantId);
        $tableRows = array_sum($result['tables']);
        $this->activityLog?->recordAuthAttempt(
            $tenantId,
            true,
            'Journal et données de mission effacés par un administrateur',
            [
                'reason' => 'admin_purge',
                'method' => 'admin',
                'activity_files' => $result['activity_files'],
                'table_rows' => $tableRows,
                'photos_removed' => $result['photos_removed'],
            ],
            null
        );

        Session::flash(
            'success',
            sprintf(
                'Effacement terminé : %d fichier(s) de journal, %d enregistrement(s) de mission, %d photo(s) retirée(s). La configuration et les indicatifs liés sont conservés.',
                $result['activity_files'],
                $tableRows,
                $result['photos_removed']
            )
        );

        return Response::redirect(url('admin/atak-config'));
    }
}
