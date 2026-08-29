<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Repositories\TacticalPhonePairingRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;

/**
 * Shell ATAK mobile dédié téléphone (QR détaché) — hors iframe desktop.
 */
final class AtakMobileController
{
    /** @var list<string> */
    public const MODULES = [
        'c2', 'sitac', 'chat', 'bft', 'status',
        'pings', 'intel', 'jtac', 'air', 'sigint', 'orders', 'explosives',
    ];

    public function __construct(
        private AuthService $authService,
        private AtakMapRepository $atakMapRepository,
        private TenantAtakConfigRepository $atakConfigRepository,
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private FeatureGateService $featureGate,
        private ?TacticalPhonePairingRepository $phonePairingRepository = null,
    ) {
        $this->phonePairingRepository ??= new TacticalPhonePairingRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        return $this->render($request, 'c2');
    }

    public function module(Request $request, array $params = []): Response
    {
        $module = strtolower(trim((string) ($params['module'] ?? 'c2')));
        if (!in_array($module, self::MODULES, true)) {
            $module = 'c2';
        }

        return $this->render($request, $module);
    }

    private function render(Request $request, string $module): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('connect'));
        }
        if (!$this->featureGate->allows($tenantId, 'atak')) {
            return Response::view('layout.main', [
                'title' => 'ATAK / Overwatch',
                'content' => 'platform.upgrade',
                'feature' => 'atak',
                'planName' => 'standard',
            ]);
        }

        $currentUser = $this->authService->user();
        $phoneSession = $this->resolvePhoneSession($currentUser !== null);
        $atakMap = $this->atakMapRepository->getDefaultForTenant($tenantId)
            ?: $this->atakMapRepository->getBySlug('altis');
        $mapConfig = $this->buildMapConfig($atakMap);
        $tenantName = '';
        try {
            $row = $this->tenantRepository->findById($tenantId);
            if (is_array($row)) {
                $tenantName = function_exists('community_display_name')
                    ? community_display_name($row)
                    : (string) ($row['name'] ?? '');
            }
        } catch (\Throwable) {
        }

        $labels = [
            'c2' => 'Centre C2',
            'sitac' => 'SITAC',
            'chat' => 'Tchat',
            'bft' => 'BFT',
            'status' => 'État C2',
            'pings' => 'Pings',
            'intel' => 'Intel',
            'jtac' => 'JTAC',
            'air' => 'Air Assets',
            'sigint' => 'SIGINT',
            'orders' => 'Ordres',
            'explosives' => 'Explosifs',
        ];

        return Response::view('atak.mobile', [
            'title' => ($labels[$module] ?? 'ATAK') . ' — COMSPEC Mobile',
            'mobileModule' => $module,
            'mobileModuleLabel' => $labels[$module] ?? strtoupper($module),
            'tenantId' => $tenantId,
            'tenantName' => $tenantName !== '' ? $tenantName : ('Communauté #' . $tenantId),
            'mapConfig' => $mapConfig,
            'mapId' => (int) ($atakMap['id'] ?? 1),
            'phoneSession' => $phoneSession,
            'user' => $currentUser,
            'apiBase' => rtrim((string) url(''), '/'),
            'assetVer' => function_exists('platform_app_version') ? platform_app_version() : '1',
        ]);
    }

    /**
     * @return array{active: bool, label: string, expires_at: ?string}|null
     */
    private function resolvePhoneSession(bool $hasMember): ?array
    {
        if ($hasMember) {
            return null;
        }
        $token = trim((string) Session::get('atak_phone_pairing_token'));
        if ($token === '' || !$this->phonePairingRepository->isReady()) {
            return ['active' => true, 'label' => (string) (Session::get('atak_phone_operator_label') ?: 'Opérateur téléphone'), 'expires_at' => null];
        }
        $row = $this->phonePairingRepository->findValidByToken($token);
        if ($row === null) {
            return ['active' => false, 'label' => 'Session expirée', 'expires_at' => null];
        }

        return [
            'active' => true,
            'label' => (string) (Session::get('atak_phone_operator_label') ?: 'Opérateur téléphone'),
            'expires_at' => $row['expires_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed>|null $map
     * @return array<string, mixed>
     */
    private function buildMapConfig(?array $map): array
    {
        $c = is_array($map['config'] ?? null) ? $map['config'] : [];
        $slug = (string) ($map['slug'] ?? 'altis');
        $tp = (string) ($map['tile_pattern'] ?? '');

        return [
            'slug' => $slug,
            'title' => (string) ($map['label'] ?? $slug),
            'tilePattern' => function_exists('atak_resolve_tile_pattern')
                ? atak_resolve_tile_pattern($tp, $slug)
                : $tp,
            'center' => $c['center'] ?? [15000, 15000],
            'defaultZoom' => (int) ($c['defaultZoom'] ?? 3),
            'minZoom' => (int) ($c['minZoom'] ?? 0),
            'maxZoom' => (int) ($c['maxZoom'] ?? 6),
            'tileSize' => (int) ($c['tileSize'] ?? 212),
            'worldSize' => (int) ($c['worldSize'] ?? 30720),
            'attribution' => $c['attribution'] ?? '&copy; Bohemia Interactive',
            'crs' => $c['crs'] ?? ['factorx' => 0.006839, 'factory' => 0.006836, 'tileWidth' => 212],
        ];
    }
}
