<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\MemberIntegrationRepository;
use App\Repositories\PersonnelRoleplayTimelineRepository;

/**
 * Badges partagés du bureau effectifs (roleplay, intégration).
 */
final class EffectifsWorkspaceShellExtras
{
    /**
     * @return array{roleplayDueCount: int, integrationOpenCount: int}
     */
    public static function counts(int $tenantId): array
    {
        $roleplayDue = 0;
        $integrationOpen = 0;
        if ($tenantId < 1) {
            return ['roleplayDueCount' => 0, 'integrationOpenCount' => 0];
        }
        try {
            $items = (new PersonnelRoleplayTimelineRepository())->listDashboardDueItems($tenantId, 14, 80);
            foreach ($items as $item) {
                if (($item['urgency'] ?? '') === 'overdue') {
                    $roleplayDue++;
                }
            }
        } catch (\Throwable) {
            $roleplayDue = 0;
        }
        try {
            $rows = (new MemberIntegrationRepository())->listDashboard($tenantId, [], 200);
            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? '');
                if ($status !== '' && !MemberIntegrationCatalog::isTerminalStatus($status)) {
                    $integrationOpen++;
                }
            }
        } catch (\Throwable) {
            $integrationOpen = 0;
        }

        return [
            'roleplayDueCount' => $roleplayDue,
            'integrationOpenCount' => $integrationOpen,
        ];
    }
}
