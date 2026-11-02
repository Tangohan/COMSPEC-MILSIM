<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Gate;
use App\Support\EffectifsLmsAccess;
use Throwable;

/**
 * Données d’affichage des prérequis d’emploi : fiche emploi (critères, titulaires, dérogations, éditeur), fiche
 * membre (emplois tenus, progression, dérogations) et fenêtre d’attribution (prérequis manquants par emploi).
 * Toute erreur de lecture laisse la page s’afficher sans le bloc.
 */
final class JobPrerequisiteViewData
{
    /** Droit de déroger : les personnes qui attribuent les emplois, avec un motif obligatoire. */
    public static function canWaive(Gate $gate): bool
    {
        return EffectifsLmsAccess::canManageAssignments($gate);
    }

    public static function blocking(array $tenantSettings): bool
    {
        return PersonnelJobRoleAssignmentsSettings::resolve($tenantSettings)['prerequisites_blocking'];
    }

    /**
     * @param array<string, mixed>|null $selected emploi ouvert
     * @param array<int, list<array<string, mixed>>> $holdersByRole
     * @return array<string, mixed>
     */
    public static function forJobSheet(int $tenantId, ?array $selected, array $holdersByRole, bool $canManage, array $tenantSettings): array
    {
        $data = ['jobPrereqReady' => false, 'jobPrereqItems' => [], 'jobPrereqOptions' => [], 'jobPrereqHolders' => [], 'jobWaivers' => [], 'jobPrereqBlocking' => self::blocking($tenantSettings)];
        if ($selected === null) {
            return $data;
        }
        try {
            $service = new JobPrerequisiteService();
            if (!$service->schemaReady()) {
                return $data;
            }
            $roleId = (int) $selected['id'];
            $items = $service->requirements($tenantId, $roleId);
            $options = $service->options($tenantId);
            $data['jobPrereqReady'] = true;
            $data['jobPrereqOptions'] = $canManage ? $options : [];
            $data['jobPrereqItems'] = array_map(static fn (array $c): array => ['criterion' => $c, 'parts' => JobPrerequisiteService::describe($c, $options)], $items);
            $data['jobWaivers'] = $service->waiversForRole($tenantId, $roleId);
            $waived = [];
            foreach ($data['jobWaivers'] as $w) {
                if (empty($w['revoked_at'])) {
                    $waived[(int) $w['user_id']] = true;
                }
            }
            if ($items !== []) {
                foreach ($holdersByRole[$roleId] ?? [] as $holder) {
                    $uid = (int) ($holder['user_id'] ?? 0);
                    if ($uid > 0) {
                        $eval = $service->evaluate($tenantId, $uid, $items);
                        $data['jobPrereqHolders'][$uid] = ['met' => $eval['met'], 'total' => $eval['total'], 'progress' => $eval['progress'], 'complete' => $eval['complete'], 'waived' => !$eval['complete'] && isset($waived[$uid])];
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Job prerequisites sheet: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * @param list<array<string, mixed>> $memberJobRoles emplois tenus (personnel_job_role_id, role_name)
     * @return array<string, mixed>
     */
    public static function forMember(int $tenantId, int $userId, array $memberJobRoles, array $tenantSettings): array
    {
        $out = ['ready' => false, 'roles' => [], 'picker' => [], 'waivers' => [], 'blocking' => self::blocking($tenantSettings), 'canWaive' => self::canWaive(Gate::getInstance())];
        try {
            $service = new JobPrerequisiteService();
            if ($tenantId < 1 || $userId < 1 || !$service->schemaReady()) {
                return $out;
            }
            $out['ready'] = true;
            $all = $service->requirementsByRole($tenantId);
            if ($all === []) {
                $out['waivers'] = $service->activeWaiversForUser($tenantId, $userId);

                return $out;
            }
            $options = $service->options($tenantId);
            $waivers = $service->activeWaiversForUser($tenantId, $userId);
            $out['waivers'] = $waivers;
            $held = [];
            foreach ($memberJobRoles as $jr) {
                $held[(int) ($jr['personnel_job_role_id'] ?? 0)] = (string) ($jr['role_name'] ?? '');
            }
            foreach ($all as $roleId => $items) {
                $eval = $service->evaluate($tenantId, $userId, $items);
                $rows = [];
                foreach ($eval['items'] as $row) {
                    $rows[] = $row + ['parts' => JobPrerequisiteService::describe($row['criterion'], $options)];
                }
                if (isset($held[$roleId])) {
                    $out['roles'][$roleId] = ['name' => $held[$roleId], 'eval' => $eval, 'rows' => $rows, 'waiver' => $waivers[$roleId] ?? null];
                }
                $missing = array_values(array_filter($rows, static fn (array $r): bool => $r['met'] === false));
                if ($missing !== [] && !isset($waivers[$roleId])) {
                    $out['picker'][$roleId] = $missing;
                }
            }
        } catch (Throwable $e) {
            error_log('Job prerequisites member: ' . $e->getMessage());
        }

        return $out;
    }
}
