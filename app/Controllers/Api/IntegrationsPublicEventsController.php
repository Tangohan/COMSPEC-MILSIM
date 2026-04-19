<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Support\Api\ApiResponder;
use App\Repositories\CommunityEventRepository;
use App\Services\Platform\FeatureGateService;

final class IntegrationsPublicEventsController
{
    public function __construct(
        private CommunityEventRepository $events,
        private FeatureGateService $featureGate,
    ) {}

    public function upcoming(Request $request, array $params = []): Response
    {
        $tenantId = (int) $request->attribute('integration_tenant_id', 0);
        if ($tenantId < 1) {
            return ApiResponder::error('invalid_context', 'Contexte invalide.', 400);
        }
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return ApiResponder::success(['events' => [], 'notice' => 'module_disabled']);
        }
        $rows = $this->events->upcomingForTenant($tenantId, 100);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'title' => (string) ($r['title'] ?? ''),
                'starts_at' => (string) ($r['starts_at'] ?? ''),
                'ends_at' => $r['ends_at'] ?? null,
                'location' => $r['location'] ?? null,
                'type' => (string) ($r['event_type'] ?? ''),
            ];
        }

        return ApiResponder::success(['events' => $out]);
    }
}
