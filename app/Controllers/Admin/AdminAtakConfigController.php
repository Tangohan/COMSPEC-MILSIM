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
                'Impossible d’enregistrer le mode maintenance : la base de données n’est pas à jour. Contactez le support plateforme pour appliquer la mise à jour, puis réessayez.'
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
