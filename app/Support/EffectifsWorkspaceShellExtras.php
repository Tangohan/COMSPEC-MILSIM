<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Container;
use App\Repositories\MemberIntegrationRepository;
use App\Repositories\PersonnelPhaseRepository;
use App\Repositories\PersonnelRoleplayTimelineRepository;
use App\Services\Personnel\PhaseRules\PhaseTransitionService;
use App\Services\Personnel\RoleplayFollowupSettings;

/**
 * Badges partagés du bureau effectifs (roleplay, intégration).
 */
final class EffectifsWorkspaceShellExtras
{
    /**
     * @return array{roleplayDueCount: int, integrationOpenCount: int, phaseGateCount: int, phaseAutoErrorCount: int}
     */
    public static function counts(int $tenantId): array
    {
        $roleplayDue = 0;
        $integrationOpen = 0;
        $phaseGateCount = 0;
        $phaseAutoErrorCount = 0;
        if ($tenantId < 1) {
            return [
                'roleplayDueCount' => 0,
                'integrationOpenCount' => 0,
                'phaseGateCount' => 0,
                'phaseAutoErrorCount' => 0,
            ];
        }
        try {
            $opts = RoleplayFollowupSettings::dueListOptionsForTenant($tenantId);
            $items = (new PersonnelRoleplayTimelineRepository())->listDashboardDueItems(
                $tenantId,
                (int) $opts['horizon'],
                80,
                $opts
            );
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
        try {
            $phaseGateCount = Container::get(PhaseTransitionService::class)->countPendingGates($tenantId);
        } catch (\Throwable) {
            $phaseGateCount = 0;
        }
        try {
            $phaseAutoErrorCount = (new PersonnelPhaseRepository())->countOpenAutoErrors($tenantId);
        } catch (\Throwable) {
            $phaseAutoErrorCount = 0;
        }

        return [
            'roleplayDueCount' => $roleplayDue,
            'integrationOpenCount' => $integrationOpen,
            'phaseGateCount' => $phaseGateCount,
            'phaseAutoErrorCount' => $phaseAutoErrorCount,
        ];
    }
}
