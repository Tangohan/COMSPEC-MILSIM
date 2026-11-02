<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\UserRepository;

/**
 * Actions groupées du tableau « Qui tient quel emploi » : attribuer un emploi à plusieurs membres, le leur retirer,
 * ou remplacer un titulaire par un autre.
 *
 * Chaque membre passe par le même enregistrement que sa fiche (replaceUserPivotJobRoles), et son changement est
 * consigné dans son historique de structure (PersonnelStructureChangeNotificationService). Les autres emplois du
 * membre, leur précision et l’emploi principal sont conservés ; l’affectation ORBAT n’est jamais modifiée.
 */
final class JobAssignmentBulkService
{
    public const ACTIONS = ['attribuer', 'retirer', 'remplacer'];
    public const MAX_MEMBERS_PER_ACTION = 200;

    public function __construct(
        private PersonnelJobRoleRepository $jobRoles,
        private UserRepository $users,
        private ?PersonnelStructureChangeNotificationService $history = null,
    ) {}

    /**
     * @param list<int> $userIds
     * @return array{ok: bool, changed: int, unchanged: int, skipped: list<string>, message: string}
     */
    public function apply(int $tenantId, int $actorId, string $action, int $roleId, array $userIds, int $replacedUserId = 0, int $maxRoles = 5): array
    {
        $fail = static fn (string $message): array => ['ok' => false, 'changed' => 0, 'unchanged' => 0, 'skipped' => [], 'message' => $message];
        if (!in_array($action, self::ACTIONS, true)) {
            return $fail('Action inconnue.');
        }
        if (!$this->jobRoles->pivotTableExists()) {
            return $fail('Les attributions sont indisponibles tant que la mise à jour du site n’est pas appliquée.');
        }
        $role = $roleId > 0 ? $this->jobRoles->findRoleById($roleId, $tenantId) : null;
        if (!$role) {
            return $fail('Emploi introuvable dans cette communauté.');
        }
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return $fail('Sélectionnez au moins un membre.');
        }
        if (count($userIds) > self::MAX_MEMBERS_PER_ACTION) {
            return $fail('Trop de membres sélectionnés en une fois (200 au maximum).');
        }
        if ($action === 'remplacer') {
            if ($replacedUserId < 1 || count($userIds) !== 1) {
                return $fail('Choisissez un seul nouveau titulaire.');
            }
            if ($userIds[0] === $replacedUserId) {
                return $fail('Le nouveau titulaire est déjà celui qui tient l’emploi.');
            }
        }
        $users = [];
        foreach (array_merge($userIds, $action === 'remplacer' ? [$replacedUserId] : []) as $uid) {
            $user = $this->users->findById($uid, $tenantId);
            if (!$user) {
                return $fail('Un des membres choisis n’appartient pas à cette communauté.');
            }
            $users[$uid] = $user;
        }
        $current = $this->jobRoles->listPivotAssignmentsForUsers($tenantId, array_keys($users));

        $result = ['ok' => true, 'changed' => 0, 'unchanged' => 0, 'skipped' => [], 'message' => ''];
        $wasPrimary = false;
        if ($action === 'remplacer') {
            $slots = self::slots($current[$replacedUserId] ?? []);
            $kept = array_values(array_filter($slots, static fn (array $s): bool => $s['personnel_job_role_id'] !== $roleId));
            if (count($kept) === count($slots)) {
                return $fail('Ce membre ne tient plus cet emploi : rechargez la page.');
            }
            foreach ($slots as $s) {
                if ($s['personnel_job_role_id'] === $roleId && $s['is_primary']) {
                    $wasPrimary = true;
                }
            }
            // Le nouveau titulaire est vérifié avant de retirer l’emploi à l’ancien.
            $incoming = self::slots($current[$userIds[0]] ?? []);
            if (!self::holds($incoming, $roleId) && count($incoming) >= $maxRoles) {
                return $fail('Le nouveau titulaire a déjà le nombre maximal d’emplois. Retirez-en un avant de le désigner.');
            }
            $this->commit($tenantId, $actorId, $replacedUserId, $kept);
        }
        foreach ($userIds as $uid) {
            $slots = self::slots($current[$uid] ?? []);
            $name = trim((string) ($users[$uid]['display_name'] ?? '')) ?: ('#' . $uid);
            if ($action === 'retirer') {
                $kept = array_values(array_filter($slots, static fn (array $s): bool => $s['personnel_job_role_id'] !== $roleId));
                if (count($kept) === count($slots)) {
                    $result['unchanged']++;
                    continue;
                }
                $this->commit($tenantId, $actorId, $uid, $kept);
                $result['changed']++;
                continue;
            }
            if (self::holds($slots, $roleId)) {
                $result['unchanged']++;
                continue;
            }
            if (count($slots) >= $maxRoles) {
                $result['skipped'][] = $name;
                continue;
            }
            // Premier emploi du membre, ou emploi principal de l’ancien titulaire : il devient principal.
            $primary = $slots === [] || ($wasPrimary && !self::hasPrimary($slots));
            $slots[] = ['personnel_job_role_id' => $roleId, 'role_detail' => '', 'is_primary' => $primary];
            $this->commit($tenantId, $actorId, $uid, $slots);
            $result['changed']++;
        }
        $result['message'] = self::message($action, (string) $role['name'], $result);

        return $result;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function slots(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $rid = (int) ($row['personnel_job_role_id'] ?? 0);
            if ($rid > 0) {
                $out[] = ['personnel_job_role_id' => $rid, 'role_detail' => trim((string) ($row['role_detail'] ?? '')), 'is_primary' => !empty($row['is_primary'])];
            }
        }

        return $out;
    }

    private static function holds(array $slots, int $roleId): bool
    {
        foreach ($slots as $s) {
            if ($s['personnel_job_role_id'] === $roleId) {
                return true;
            }
        }

        return false;
    }

    private static function hasPrimary(array $slots): bool
    {
        foreach ($slots as $s) {
            if ($s['is_primary']) {
                return true;
            }
        }

        return false;
    }

    private function commit(int $tenantId, int $actorId, int $userId, array $slots): void
    {
        $before = null;
        try {
            $before = $this->history?->snapshot($tenantId, $userId);
        } catch (\Throwable $e) {
            error_log('Job assignment board: snapshot failed: ' . $e->getMessage());
        }
        $this->jobRoles->replaceUserPivotJobRoles($tenantId, $userId, $slots);
        if ($before === null || $this->history === null) {
            return;
        }
        try {
            $this->history->notifyFromSnapshots($tenantId, $userId, $actorId > 0 ? $actorId : null, $before, $this->history->snapshot($tenantId, $userId));
        } catch (\Throwable $e) {
            error_log('Job assignment board: history failed: ' . $e->getMessage());
        }
    }

    /** @param array{changed: int, unchanged: int, skipped: list<string>} $r */
    private static function message(string $action, string $role, array $r): string
    {
        $parts = [];
        if ($action === 'remplacer') {
            $parts[] = sprintf('Titulaire remplacé pour « %s ».', $role);
        } elseif ($r['changed'] > 0) {
            $parts[] = $action === 'retirer'
                ? sprintf('« %s » retiré à %d membre(s).', $role, $r['changed'])
                : sprintf('« %s » attribué à %d membre(s).', $role, $r['changed']);
        }
        if ($r['unchanged'] > 0 && $action !== 'remplacer') {
            $parts[] = $action === 'retirer'
                ? sprintf('%d membre(s) ne tenaient pas cet emploi.', $r['unchanged'])
                : sprintf('%d membre(s) le tenaient déjà.', $r['unchanged']);
        }
        if ($r['skipped'] !== []) {
            $parts[] = sprintf('Non attribué (nombre maximal d’emplois atteint) : %s.', implode(', ', $r['skipped']));
        }

        return $parts !== [] ? implode(' ', $parts) : 'Aucun changement.';
    }
}
