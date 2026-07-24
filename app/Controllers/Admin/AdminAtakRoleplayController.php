<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;

/**
 * Administration de la configuration roleplay ATAK par communauté.
 */
class AdminAtakRoleplayController
{
    public function __construct(
        private TenantAtakConfigRepository $atakConfigRepo,
        private TenantRepository $tenantRepo
    ) {
    }

    /**
     * Affiche le formulaire de configuration roleplay.
     */
    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('/'))->flash('error', 'Communauté non identifiée');
        }

        $tenant = $this->tenantRepo->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('/'))->flash('error', 'Communauté introuvable');
        }

        $config = $this->atakConfigRepo->getRoleplayConfig($tenantId);

        return Response::view('admin/atak/roleplay', [
            'pageTitle' => 'Configuration Roleplay ATAK',
            'tenant' => $tenant,
            'config' => $config,
        ]);
    }

    /**
     * Enregistre la configuration roleplay.
     */
    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('/'))->flash('error', 'Communauté non identifiée');
        }

        $body = $request->postData();

        $config = [
            // Réseau
            'network_enabled' => isset($body['network_enabled']),
            'network_mode' => in_array($body['network_mode'] ?? 'normal', ['normal', 'hostile', 'degraded', 'equipment'], true)
                ? $body['network_mode']
                : 'normal',
            'latency_min_ms' => max(0, (int) ($body['latency_min_ms'] ?? 0)),
            'latency_max_ms' => max(0, (int) ($body['latency_max_ms'] ?? 0)),
            'packet_loss_percent' => max(0.0, min(100.0, (float) ($body['packet_loss_percent'] ?? 0))),
            'disconnect_enabled' => isset($body['disconnect_enabled']),
            'disconnect_min_sec' => max(1, (int) ($body['disconnect_min_sec'] ?? 5)),
            'disconnect_max_sec' => max(1, (int) ($body['disconnect_max_sec'] ?? 30)),
            'disconnect_interval_sec' => max(60, (int) ($body['disconnect_interval_sec'] ?? 600)),

            // Capteurs
            'sensor_enabled' => isset($body['sensor_enabled']),
            'sensor_failure_percent' => max(0.0, min(100.0, (float) ($body['sensor_failure_percent'] ?? 0))),
            'sensor_error_percent' => max(0.0, min(100.0, (float) ($body['sensor_error_percent'] ?? 0))),
            'sensor_missing_percent' => max(0.0, min(100.0, (float) ($body['sensor_missing_percent'] ?? 0))),

            // Zones (pour l'instant JSON brut)
            'zones_enabled' => isset($body['zones_enabled']),
            'zones_config' => !empty($body['zones_config']) ? trim($body['zones_config']) : null,
        ];

        // Valider latency_max >= latency_min
        if ($config['latency_max_ms'] < $config['latency_min_ms']) {
            $config['latency_max_ms'] = $config['latency_min_ms'];
        }

        // Valider disconnect_max >= disconnect_min
        if ($config['disconnect_max_sec'] < $config['disconnect_min_sec']) {
            $config['disconnect_max_sec'] = $config['disconnect_min_sec'];
        }

        $this->atakConfigRepo->updateRoleplayConfig($tenantId, $config);

        return Response::redirect(url('admin/atak/roleplay'))
            ->flash('success', 'Configuration roleplay enregistrée');
    }

    /**
     * Réinitialise la configuration roleplay aux valeurs par défaut.
     */
    public function reset(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('/'))->flash('error', 'Communauté non identifiée');
        }

        $defaultConfig = [
            'network_enabled' => false,
            'network_mode' => 'normal',
            'latency_min_ms' => 0,
            'latency_max_ms' => 0,
            'packet_loss_percent' => 0.0,
            'disconnect_enabled' => false,
            'disconnect_min_sec' => 5,
            'disconnect_max_sec' => 30,
            'disconnect_interval_sec' => 600,
            'sensor_enabled' => false,
            'sensor_failure_percent' => 0.0,
            'sensor_error_percent' => 0.0,
            'sensor_missing_percent' => 0.0,
            'zones_enabled' => false,
            'zones_config' => null,
        ];

        $this->atakConfigRepo->updateRoleplayConfig($tenantId, $defaultConfig);

        return Response::redirect(url('admin/atak/roleplay'))
            ->flash('success', 'Configuration roleplay réinitialisée');
    }
}
