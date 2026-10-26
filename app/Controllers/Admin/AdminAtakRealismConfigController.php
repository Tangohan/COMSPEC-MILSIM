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

        $schema = ConfigSchemaService::getSchema();
        $activeConfig = null;
        try {
            $activeConfig = $this->configRepo->getActiveConfig($tenantId);
            if ($activeConfig === null) {
                // Première visite : configuration par défaut du schéma.
                // (Les arguments étaient passés dans le désordre : TypeError, page en erreur.)
                $this->configRepo->upsertConfig(
                    $tenantId,
                    ConfigSchemaService::buildDefaultConfig(),
                    $this->userId() ?: null,
                    'Configuration par défaut'
                );
                $activeConfig = $this->configRepo->getActiveConfig($tenantId);
            }
        } catch (\Throwable $e) {
            error_log('[atak realism] ' . $e->getMessage());
        }

        $stored = is_array($activeConfig) ? (json_decode((string) $activeConfig['config_json'], true) ?: []) : [];
        $history = [];
        try {
            $history = $this->configRepo->listConfigHistory($tenantId, 15);
        } catch (\Throwable) {
            $history = [];
        }

        return Response::view('layout.main', [
            'content' => 'admin.atak_realism.config',
            'title' => 'Configuration réalisme ATAK',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Ressources',
            'boPageKicker' => 'RESSOURCES · RÉALISME',
            'boPageTitle' => 'Configuration réalisme',
            'boPageSubtitle' => 'Relais radio, météo, certificats, dégâts des terminaux, symbologie : les règles de réalisme appliquées à la communauté.',
            'boPageQuick' => [
                ['label' => 'Vérifier la configuration', 'href' => url('back-office/ressources/atak-realism/verify')],
                ['label' => 'Roleplay', 'href' => url('back-office/atak/roleplay')],
                ['label' => 'Contrôle serveur', 'href' => url('back-office/atak/controle-serveur')],
            ],
            'backOfficePageCss' => ['back-office-atak-realism.css'],
            'schema' => $schema,
            'config' => ConfigSchemaService::mergeWithDefaults($stored),
            'configReady' => $activeConfig !== null,
            'configMeta' => [
                'id' => (int) ($activeConfig['id'] ?? 0),
                'version' => (string) ($activeConfig['config_version'] ?? '1.0.0'),
                'name' => (string) ($activeConfig['config_name'] ?? ''),
                'updated_at' => $activeConfig['updated_at'] ?? null,
                'updated_by' => $activeConfig['updated_by'] ?? null,
            ],
            'history' => $history,
            'csrfToken' => Csrf::token(),
        ]);
    }

    /**
     * Enregistrement de la configuration (API POST).
     * Validation stricte selon schéma JSON.
     */
    public function save(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $isForm = $request->input('cfg') !== null;
        $back = url('back-office/ressources/atak-realism/config');
        if ($tenantId < 1) {
            return $isForm ? Response::redirect(url('login')) : Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }

        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak');
        if ($forbidden instanceof Response) {
            return $isForm ? $forbidden : Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }

        if (!Csrf::verify($request)) {
            if ($isForm) {
                Session::flash('error', 'Session expirée. Réessayez.');

                return Response::redirect($back);
            }

            return Response::json(['ok' => false, 'error' => 'Token CSRF invalide.'], 400);
        }

        $current = [];
        try {
            $row = $this->configRepo->getActiveConfig($tenantId);
            $current = is_array($row) ? (json_decode((string) $row['config_json'], true) ?: []) : [];
        } catch (\Throwable) {
            $current = [];
        }
        $base = ConfigSchemaService::mergeWithDefaults($current);

        if ($isForm) {
            // Formulaire de l'écran : valeurs converties au type du schéma (interrupteurs, curseurs, listes…).
            $posted = $request->input('cfg');
            $configJson = ConfigSchemaService::coerceFromForm(is_array($posted) ? $posted : [], $base);
        } else {
            $configJson = $request->input('config');
            if (!is_array($configJson)) {
                return Response::json(['ok' => false, 'error' => 'Config JSON invalide.'], 400);
            }
            $configJson = ConfigSchemaService::mergeWithDefaults($configJson);
        }

        $validation = ConfigSchemaService::validateConfig($configJson);
        if (!$validation['valid']) {
            if ($isForm) {
                Session::flash('error', 'Configuration refusée : ' . implode(' · ', array_slice($validation['errors'], 0, 5)));

                return Response::redirect($back);
            }

            return Response::json(['ok' => false, 'error' => 'Configuration invalide.', 'errors' => $validation['errors']], 400);
        }

        $configName = mb_substr(trim((string) $request->input('name', '')), 0, 120);
        if ($configName === '') {
            $configName = 'Configuration modifiée le ' . date('d/m/Y à H:i');
        }

        try {
            // Ordre des arguments corrigé (tenant, config, auteur, nom) : l'ancien appel levait une TypeError.
            $this->configRepo->upsertConfig($tenantId, $configJson, $this->userId() ?: null, $configName);
        } catch (\Throwable $e) {
            if ($isForm) {
                Session::flash('error', 'Enregistrement impossible : ' . $e->getMessage());

                return Response::redirect($back);
            }

            return Response::json(['ok' => false, 'error' => 'Erreur enregistrement : ' . $e->getMessage()], 500);
        }

        if ($isForm) {
            Session::flash('success', 'Configuration réalisme enregistrée. Le pack la relit sous quelques minutes (cache du mod).');
            $tab = preg_replace('/[^a-z_]/', '', (string) $request->input('_tab', ''));

            return Response::redirect($back . ($tab !== '' ? '#' . $tab : ''));
        }

        return Response::json(['ok' => true, 'message' => 'Configuration enregistrée avec succès.']);
    }

    /** Remet en service une version de l'historique (copiée comme nouvelle version active). */
    public function restore(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $back = url('back-office/ressources/atak-realism/config');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back . '#historique');
        }
        $row = $this->configRepo->getConfigById($tenantId, (int) ($params['id'] ?? 0));
        if ($row === null) {
            Session::flash('error', 'Version introuvable.');

            return Response::redirect($back . '#historique');
        }
        $config = ConfigSchemaService::mergeWithDefaults(json_decode((string) $row['config_json'], true) ?: []);
        try {
            $this->configRepo->upsertConfig(
                $tenantId,
                $config,
                $this->userId() ?: null,
                mb_substr('Restauration — ' . (string) ($row['config_name'] ?? 'version ' . $row['id']), 0, 120)
            );
            Session::flash('success', 'Version restaurée : elle est de nouveau active.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Restauration impossible : ' . $e->getMessage());
        }

        return Response::redirect($back . '#historique');
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

    private function userId(): int
    {
        return (int) (Session::get('user_id') ?? 0);
    }
}
