<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
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
    /** @var array<string, string> */
    public const ZONE_EFFECT_LABELS = [
        'degraded' => 'Couverture dégradée',
        'interference' => 'Interférences',
        'high_loss' => 'Forte perte de signal',
        'jammer' => 'Brouillage actif',
        'no_coverage' => 'Sans couverture',
    ];

    public function __construct(
        private ?TenantAtakConfigRepository $atakConfigRepo = null,
        private ?TenantRepository $tenantRepo = null,
    ) {
        $this->atakConfigRepo ??= new TenantAtakConfigRepository();
        $this->tenantRepo ??= new TenantRepository();
    }

    /**
     * Affiche le formulaire de configuration roleplay.
     */
    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            Session::flash('error', 'Communauté non identifiée');
            return Response::redirect(url('/'));
        }

        $tenant = $this->tenantRepo->findById($tenantId);
        if (!$tenant) {
            Session::flash('error', 'Communauté introuvable');
            return Response::redirect(url('/'));
        }

        $config = $this->atakConfigRepo->getRoleplayConfig($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.atak.roleplay',
            'title' => 'Mode Roleplay ATAK',
            'pageTitle' => 'Mode Roleplay ATAK',
            'tenant' => $tenant,
            'config' => $config,
            'zoneRows' => $this->decodeZoneRows($config['zones_config'] ?? null),
            'zoneEffectOptions' => self::ZONE_EFFECT_LABELS,
            'csrfToken' => Csrf::token(),
        ]);
    }

    /**
     * Ancien lien catalogue (#intel-scramble en chemin) → ancre de la page.
     */
    public function intelScrambleRedirect(Request $request, array $params = []): Response
    {
        return Response::redirect(url('admin/atak/roleplay') . '#intel-scramble');
    }

    /**
     * Enregistre la configuration roleplay.
     */
    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            Session::flash('error', 'Communauté non identifiée');
            return Response::redirect(url('/'));
        }

        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('admin/atak/roleplay'));
        }

        $body = $request->all();
        $zonesConfig = $this->encodeZonesFromPost($body);

        $config = [
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

            'sensor_enabled' => isset($body['sensor_enabled']),
            'sensor_failure_percent' => max(0.0, min(100.0, (float) ($body['sensor_failure_percent'] ?? 0))),
            'sensor_error_percent' => max(0.0, min(100.0, (float) ($body['sensor_error_percent'] ?? 0))),
            'sensor_missing_percent' => max(0.0, min(100.0, (float) ($body['sensor_missing_percent'] ?? 0))),

            'zones_enabled' => isset($body['zones_enabled']),
            'zones_config' => $zonesConfig,

            'intel_scramble_enabled' => isset($body['intel_scramble_enabled']),
            'intel_scramble_reviewed' => true,
        ];

        if ($config['latency_max_ms'] < $config['latency_min_ms']) {
            $config['latency_max_ms'] = $config['latency_min_ms'];
        }
        if ($config['disconnect_max_sec'] < $config['disconnect_min_sec']) {
            $config['disconnect_max_sec'] = $config['disconnect_min_sec'];
        }

        try {
            (new \App\Repositories\AtakRealismRepository())->ensureDefaultCryptoDomain($tenantId);
        } catch (\Throwable) {
        }

        $this->atakConfigRepo->updateRoleplayConfig($tenantId, $config);

        Session::flash('success', 'Configuration roleplay enregistrée');
        return Response::redirect(url('admin/atak/roleplay'));
    }

    /**
     * Réinitialise la configuration roleplay aux valeurs par défaut.
     */
    public function reset(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            Session::flash('error', 'Communauté non identifiée');
            return Response::redirect(url('/'));
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
            'intel_scramble_enabled' => false,
            'intel_scramble_reviewed' => true,
        ];

        $this->atakConfigRepo->updateRoleplayConfig($tenantId, $defaultConfig);

        Session::flash('success', 'Configuration roleplay réinitialisée');
        return Response::redirect(url('admin/atak/roleplay'));
    }

    /**
     * @return list<array{center_x: float, center_y: float, radius: float, effect: string}>
     */
    private function decodeZoneRows(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $rows = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $center = $item['center'] ?? null;
            $x = is_array($center) ? (float) ($center[0] ?? 0) : (float) ($item['center_x'] ?? $item['x'] ?? 0);
            $y = is_array($center) ? (float) ($center[1] ?? 0) : (float) ($item['center_y'] ?? $item['y'] ?? 0);
            $radius = (float) ($item['radius'] ?? 500);
            $effect = (string) ($item['effect'] ?? $item['type'] ?? 'degraded');
            if (!array_key_exists($effect, self::ZONE_EFFECT_LABELS)) {
                $effect = 'degraded';
            }
            $rows[] = [
                'center_x' => $x,
                'center_y' => $y,
                'radius' => max(5.0, $radius),
                'effect' => $effect,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function encodeZonesFromPost(array $body): ?string
    {
        $xs = $body['zone_center_x'] ?? [];
        $ys = $body['zone_center_y'] ?? [];
        $radii = $body['zone_radius'] ?? [];
        $effects = $body['zone_effect'] ?? [];
        if (!is_array($xs)) {
            return null;
        }
        $zones = [];
        $count = count($xs);
        for ($i = 0; $i < $count; $i++) {
            $x = (float) ($xs[$i] ?? 0);
            $y = (float) ($ys[$i] ?? 0);
            $radius = max(5.0, (float) ($radii[$i] ?? 500));
            $effect = (string) ($effects[$i] ?? 'degraded');
            if (!array_key_exists($effect, self::ZONE_EFFECT_LABELS)) {
                $effect = 'degraded';
            }
            // Ignorer les lignes vides (0,0, rayon défaut sans intention)
            if ($x == 0.0 && $y == 0.0 && $radius <= 5.0) {
                continue;
            }
            $zones[] = [
                'center' => [$x, $y],
                'radius' => $radius,
                'effect' => $effect,
            ];
        }

        if ($zones === []) {
            return null;
        }

        return json_encode($zones, JSON_UNESCAPED_UNICODE);
    }
}
