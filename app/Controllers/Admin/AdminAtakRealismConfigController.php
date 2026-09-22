<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakRealismConfigRepository;
use App\Services\ConfigSchemaService;
use App\Support\ModuleFeatureAccess;

/**
 * Interface d'administration de la configuration centralisée réalisme ATAK.
 * Gère tous les paramètres de réalisme (relais, certificats, dommages terminal, etc.)
 * via une seule source de vérité JSON.
 */
final class AdminAtakRealismConfigController
{
    public function __construct(
        private ?AtakRealismConfigRepository $configRepo = null,
    ) {
        $this->configRepo ??= new AtakRealismConfigRepository();
    }

    /**
     * Affichage de l'écran de configuration avec 11 onglets générés depuis schéma.
     */
    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        
        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        // Récupérer la config active ou créer un template par défaut
        $activeConfig = $this->configRepo->getActiveConfig($tenantId);
        
        // Charger schéma JSON
        $schema = ConfigSchemaService::getSchema();
        
        if ($activeConfig === null) {
            // Pas encore de config : créer une config par défaut depuis schéma
            $defaultConfig = ConfigSchemaService::buildDefaultConfig();
            
            // Insérer dans DB
            $this->configRepo->upsertConfig(
                $tenantId,
                'Configuration par défaut (auto-générée)',
                $defaultConfig
            );
            
            // Recharger
            $activeConfig = $this->configRepo->getActiveConfig($tenantId);
        }

        $config = json_decode($activeConfig['config_json'], true);
        $history = $this->configRepo->listConfigHistory($tenantId, 10);

        return Response::view('layout.main', [
            'content' => 'admin.atak_realism.config',
            'title' => 'Configuration réalisme ATAK',
            'pageTitle' => 'Configuration réalisme ATAK',
            'schema' => $schema,
            'config' => $config,
            'configMeta' => [
                'id' => $activeConfig['id'],
                'version' => $activeConfig['config_version'],
                'name' => $activeConfig['config_name'],
                'updated_at' => $activeConfig['updated_at'],
            ],
            'history' => $history,
            'csrfToken' => Csrf::token(),
            'success' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    /**
     * Enregistrement de la configuration (API POST).
     * Validation stricte selon schéma JSON.
     */
    public function save(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        
        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak');
        if ($forbidden instanceof Response) {
            return Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }

        // Vérifier CSRF
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'Token CSRF invalide.'], 400);
        }

        // Récupérer config JSON envoyée
        $configJson = $request->input('config');
        
        if (!is_array($configJson)) {
            return Response::json(['ok' => false, 'error' => 'Config JSON invalide.'], 400);
        }

        // Validation stricte selon schéma
        $validation = ConfigSchemaService::validateConfig($configJson);
        
        if (!$validation['valid']) {
            return Response::json([
                'ok' => false,
                'error' => 'Configuration invalide.',
                'errors' => $validation['errors']
            ], 400);
        }

        // Enregistrer (avec historisation automatique via Repository)
        $configName = $request->input('name', 'Configuration modifiée');
        
        try {
            $this->configRepo->upsertConfig(
                $tenantId,
                $configName,
                $configJson,
                $request->user['id'] ?? null
            );
            
            return Response::json([
                'ok' => true,
                'message' => 'Configuration enregistrée avec succès.'
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'ok' => false,
                'error' => 'Erreur enregistrement : ' . $e->getMessage()
            ], 500);
        }
    }

        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak');
        if ($forbidden instanceof Response) {
            return Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }

        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée.'], 419);
        }

        // Récupérer les données JSON du body
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        
        if (!is_array($body) || !isset($body['config'])) {
            return Response::json(['ok' => false, 'error' => 'Format de configuration invalide.'], 422);
        }

        $configJson = $body['config'];
        $configName = trim((string) ($body['config_name'] ?? 'Configuration modifiée'));
        $userId = (int) Session::get('user_id');

        try {
            $newConfig = $this->configRepo->upsertConfig(
                $tenantId,
                $configJson,
                $userId,
                $configName,
                '1.0.0'
            );

            return Response::json([
                'ok' => true,
                'message' => 'Configuration enregistrée avec succès.',
                'config' => $newConfig,
            ]);
        } catch (\InvalidArgumentException $e) {
            return Response::json([
                'ok' => false,
                'error' => 'Validation échouée : ' . $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            error_log('Failed to save realism config: ' . $e->getMessage());
            return Response::json([
                'ok' => false,
                'error' => 'Erreur lors de l'enregistrement de la configuration.',
            ], 500);
        }
    }

    /**
     * Historique des versions de configuration.
     */
    public function history(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }

        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak');
        if ($forbidden instanceof Response) {
            return Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }

        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        $history = $this->configRepo->listConfigHistory($tenantId, $limit);

        return Response::json([
            'ok' => true,
            'history' => $history,
        ]);
    }

    /**
     * Récupération d'une version spécifique de config (pour consultation ou restauration).
     */
    public function getVersion(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }

        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak');
        if ($forbidden instanceof Response) {
            return Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }

        $configId = (int) ($params['id'] ?? 0);
        if ($configId < 1) {
            return Response::json(['ok' => false, 'error' => 'ID de configuration invalide.'], 422);
        }

        $config = $this->configRepo->getConfigById($tenantId, $configId);
        if ($config === null) {
            return Response::json(['ok' => false, 'error' => 'Configuration introuvable.'], 404);
        }

        return Response::json([
            'ok' => true,
            'config' => json_decode($config['config_json'], true),
            'meta' => [
                'id' => $config['id'],
                'version' => $config['config_version'],
                'name' => $config['config_name'],
                'created_at' => $config['created_at'],
                'updated_at' => $config['updated_at'],
                'created_by' => $config['created_by'],
                'updated_by' => $config['updated_by'],
            ],
        ]);
    }

    private function tenantId(): int
    {
        return (int) (Session::get('tenant_id') ?? 0);
    }
}
